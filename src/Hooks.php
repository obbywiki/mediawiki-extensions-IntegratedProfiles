<?php

namespace MediaWiki\Extension\IntegratedProfiles;

use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Output\Hook\LanguageLinksHook;
use MediaWiki\Output\Hook\OutputPageBodyAttributesHook;
use MediaWiki\Page\Hook\ArticleFromTitleHook;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Specials\Hook\SpecialContributionsBeforeMainOutputHook;
use MediaWiki\Title\Title;
use MediaWiki\User\Options\Hook\SaveUserOptionsHook;
use MediaWiki\User\UserIdentity;

/**
 * Hooks.
 */
class Hooks implements
	ArticleFromTitleHook,
	GetPreferencesHook,
	BeforePageDisplayHook,
	OutputPageBodyAttributesHook,
	SaveUserOptionsHook,
	LanguageLinksHook,
	SpecialContributionsBeforeMainOutputHook
{

	/**
	 * Cached avatar URL for the Citizen header pulled in BeforePageDisplay so OutputPageBodyAttributes can use it without a second AvatarService lookup.
	 */
	private ?string $header_avatar_url = null;

	public function __construct(
		private readonly ProfileService $profile_service,
		private readonly ProfileHandler $profile_chrome,
		private readonly HookRunner $hook_runner,
		private readonly AvatarService $avatar_service,
		private readonly ProfileLanguageLinks $profile_language_links,
	) {
	}

	/** @inheritDoc */
	public function onArticleFromTitle( $title, &$article, $context ): void {
		$subject = $this->profile_service->resolve_subject_user( $title );
		if ( !$subject ) {
			return;
		}

		$article = new ProfilePage(
			$title,
			$subject,
			$this->profile_chrome,
			$this->hook_runner
		);
	}

	/**
	 * Renders the masthead/tabs on Special:Contributions (for registered users).
	 *
	 * @inheritDoc
	 */
	public function onSpecialContributionsBeforeMainOutput( $id, $user, $sp ) {
		if ( $sp->getName() !== 'Contributions' ) {
			return;
		}
		if ( !$user->isRegistered() || !$user->isNamed() ) {
			return;
		}

		$subject = $this->profile_service->resolve_user_by_name( $user->getName() );
		if ( !$subject || !$subject->isNamed() ) {
			return;
		}

		$about_url = Title::makeTitle( NS_USER, $subject->getName() )->getLocalURL();
		$this->profile_chrome->render_to_output(
			$sp->getContext(),
			$subject,
			$about_url,
			ProfileTabs::ID_CONTRIBUTIONS
		);

		$sp->getOutput()->clearSubtitle();
	}

	/**
	 * Special:Preferences only exposes privacy controls, plus a pointer back to the profile editor. Profile content (banner, tagline, links, etc.) is edited on the user page.
	 * @inheritDoc
	 */
	public function onGetPreferences( $user, &$preferences ): void {
		foreach ( ProfileFields::KEYS as $key ) {
			if (
				$key === ProfileFields::KEY_VISIBILITY ||
				$key === ProfileFields::KEY_HIDE_CONNECTIONS
			) {
				continue;
			}
			$preferences[$key] = [
				'type' => 'api',
			];
		}

		$preferences['integratedprofiles-prefs-edit'] = [
			'type' => 'info',
			'section' => 'personal/integratedprofiles',
			'label-message' => 'integratedprofiles-prefs-edit-label',
			'help-message' => 'integratedprofiles-prefs-edit-help',
		];

		$preferences[ ProfileFields::KEY_VISIBILITY ] = [
			'type' => 'select',
			'section' => 'personal/integratedprofiles',
			'label-message' => 'integratedprofiles-field-visibility',
			'help-message' => 'integratedprofiles-field-visibility-help',
			'options-messages' => [
				'integratedprofiles-visibility-public' => ProfileFields::VISIBILITY_PUBLIC,
				'integratedprofiles-visibility-users' => ProfileFields::VISIBILITY_USERS,
				'integratedprofiles-visibility-private' => ProfileFields::VISIBILITY_PRIVATE,
			],
		];

		$preferences[ ProfileFields::KEY_HIDE_CONNECTIONS ] = [
			'type' => 'toggle',
			'section' => 'personal/integratedprofiles',
			'label-message' => 'integratedprofiles-field-hide-connections',
			'help-message' => 'integratedprofiles-field-hide-connections-help',
		];
	}

	/**
	 * Cleans up unused custom banner files when a save leaves an orphaned banner (a user uploads a banner but uses a preset instead).
	 *
	 * @param UserIdentity $user
	 * @param array<string,mixed> &$modifiedOptions
	 * @param array<string,mixed> $originalOptions
	 */
	public function onSaveUserOptions( $user, &$modifiedOptions, $originalOptions ): void {
		if ( !array_key_exists( ProfileFields::KEY_BANNER, $modifiedOptions ) ) {
			return;
		}

		$previous = ProfileFields::normalize_banner( isset( $originalOptions[ ProfileFields::KEY_BANNER ] ) ? (string)$originalOptions[ ProfileFields::KEY_BANNER ] : '' );
		$next = ProfileFields::normalize_banner( (string)$modifiedOptions[ ProfileFields::KEY_BANNER ] );
		$this->profile_service->maybe_delete_unused_banner( $user, $previous, $next );
	}

	/**
	 * Cleans up avatar/banner files when a local account is deleted.
	 *
	 * @param UserIdentity $user
	 */
	public function onDeleteAccount( $user ): void {
		if ( !$user instanceof UserIdentity || $user->getId() <= 0 ) {
			return;
		}

		$this->profile_service->delete_media_for_user( $user );
	}

	/** @inheritDoc */
	public function onBeforePageDisplay( $out, $skin ): void {
		$this->prepare_header_avatar( $out, $skin );

		$title = $out->getTitle();
		if ( !$title ) {
			return;
		}

		if ( $this->profile_service->is_profile_title( $title ) ) {
			if ( !$this->profile_service->resolve_subject_user( $title ) ) {
				return;
			}

			$out->addModuleStyles( [ 'ext.IntegratedProfiles.styles' ] );
			$out->addBodyClasses( [ 'integratedprofiles-profile' ] );
			return;
		}

		if ( $title->isSpecial( 'Contributions' ) ) {
			$target = $out->getRequest()->getText( 'target' );
			if ( $target === '' ) {
				$parts = explode( '/', $title->getText(), 2 );
				$target = $parts[1] ?? '';
			}

			if ( $target === '' ) {
				return;
			}

			$subject = $this->profile_service->resolve_user_by_name( $target );
			if ( !$subject || !$subject->isNamed() ) {
				return;
			}

			$out->addModuleStyles( [ 'ext.IntegratedProfiles.styles' ] );
			$out->addBodyClasses( [ 'integratedprofiles-profile' ] );
		}
	}

	/** @inheritDoc */
	public function onOutputPageBodyAttributes( $out, $sk, &$bodyAttrs ): void {
		if ( $this->header_avatar_url === null ) {
			return;
		}
		HeaderAvatar::apply_body_attrs( $bodyAttrs, $this->header_avatar_url );
	}

	/**
	 * Handles $wgIntegratedProfilesLanguageInterwikis.
	 *
	 * @param Title $title
	 * @param string[] &$links
	 * @param array<string,string> &$linkFlags
	 */
	public function onLanguageLinks( $title, &$links, &$linkFlags ): void {
		if ( !$this->profile_service->is_profile_title( $title ) ) {
			return;
		}

		$this->profile_language_links->append_for_profile_title(
			ProfileLanguageLinks::canonical_user_title(
				$title->getText(),
				$title->getNamespace() === NS_USER_TALK
			),
			$links
		);
	}

	/**
	 * Prepare ONE AvatarService lookup per request for the Citizen header avatar.
	 *
	 * @param \MediaWiki\Output\OutputPage $out
	 * @param \MediaWiki\Skin\Skin $skin
	 */
	private function prepare_header_avatar( $out, $skin ): void {
		$user = $out->getUser();
		$central_id = $user->isRegistered() ? $this->profile_service->get_subject_ids()->central_id_for( $user ) : 0;
		$local_id = $user->isRegistered() ? $user->getId() : 0;

		$url = HeaderAvatar::resolve_url(
			$skin->getSkinName(),
			$user->isRegistered(),
			$central_id,
			$this->avatar_service,
			$local_id
		);
		if ( $url === null ) {
			return;
		}

		$this->header_avatar_url = $url;
		$out->addModuleStyles( [ HeaderAvatar::MODULE_STYLES ] );
		$out->addBodyClasses( [ HeaderAvatar::BODY_CLASS ] );
	}

}
