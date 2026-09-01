<?php

namespace MediaWiki\Extension\IntegratedProfiles;

use ExtensionRegistry;
use MediaWiki\Context\IContextSource;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\User\User;

/**
 * Handles the profile UI (see ProfileRenderer.php for the actual HTML, rendering, etc.)
 */
class ProfileHandler {

	public const SERVICE_NAME = 'IntegratedProfiles.ProfileHandler';

	public function __construct(
		private readonly ProfileService $profile_service,
		private readonly ProfileRenderer $profile_renderer,
		private readonly HookRunner $hook_runner,
		private readonly ProfileTabs $profile_tabs,
	) {
	}

	/**
	 * Renders the masthead itself, then AfterAvatar decorations, AfterMasthead appends, and tab chrome onto the OutputPage.
	 *
	 * @param IContextSource $context Request context
	 * @param User $subject_user Profile owner
	 * @param string $about_page_url Canonical about-tab URL
	 * @param string|null $forced_active Force active tab id (e.g. contributions on Special:Contributions)
	 * @return array{active: string, payload: array<string, mixed>}
	 */
	public function render_to_output( IContextSource $context, User $subject_user, string $about_page_url, ?string $forced_active = null ): array {
		$out = $context->getOutput();
		$viewer = $context->getUser();

		$payload = $this->profile_service->get_payload( $subject_user, $viewer );
		$can_edit = $this->profile_service->can_edit( $viewer, $subject_user );

		$out->addBodyClasses( [ 'integratedprofiles-profile' ] );
		$out->addModuleStyles( [ 'ext.IntegratedProfiles.styles' ] );
		$out->addModules( [ 'ext.IntegratedProfiles.pageSidebar' ] );

		$use_floating_ui = ExtensionRegistry::getInstance()->isLoaded( 'FloatingUI' );
		if ( $use_floating_ui ) {
			$out->addModuleStyles( 'ext.floatingUI.init.styles' );
			$out->addModules( [ 'ext.floatingUI', 'ext.IntegratedProfiles.mastheadTips' ] );
		}

		$joined = '';
		$registration = $payload['registration'] ?? null;
		if ( is_string( $registration ) && $registration !== '' ) {
			$joined_date = $context->getLanguage()->userDate( $registration, $viewer );
			$joined = $context->msg( 'integratedprofiles-joined', $joined_date )->text();
		}

		$messages = [
			'aka' => $payload['real_name'] !== '' ? $context->msg( 'integratedprofiles-aka', $payload['real_name'] )->text() : '',
			'edit_count' => $context->msg( 'integratedprofiles-edit-count', $payload['edit_count'] )->text(),

			'joined' => $joined,
			'avatar_alt' => $context->msg( 'integratedprofiles-avatar-alt' )->text(),
			'avatar_edit' => $context->msg( 'integratedprofiles-avatar-edit' )->text(),

			'edit' => $context->msg( 'integratedprofiles-edit' )->text(),
			'you' => $context->msg( 'integratedprofiles-you' )->text(),

			'featured_label' => $context->msg( 'integratedprofiles-featured-label' )->text(),
			'wiki_profiles_label' => $context->msg( 'integratedprofiles-wiki-profiles-label' )->text(),

			'wiki_profile_labels' => [
				'mediawiki' => $context->msg( 'integratedprofiles-field-mediawiki' )->text(),
				'miraheze' => $context->msg( 'integratedprofiles-field-miraheze' )->text(),
				'fandom' => $context->msg( 'integratedprofiles-field-fandom' )->text(),
			],

			'connection_verified' => $context->msg( 'integratedprofiles-connection-verified' )->text(),
			'connection_labels' => [
				'discord' => $context->msg( 'integratedprofiles-connection-discord' )->text(),
				'roblox' => $context->msg( 'integratedprofiles-connection-roblox' )->text(),
			],

			'private_notice' => $context->msg( 'integratedprofiles-private-notice' )->text(),
		];

		$is_owner = $viewer->isRegistered() && $viewer->getId() === $subject_user->getId();
		$connections_enabled = ExtensionRegistry::getInstance()->isLoaded( 'NewAuth' ) && (bool)$context->getConfig()->get( 'IntegratedProfilesEnableNewAuthPanel' );
		$show_manage_connections = $is_owner && $connections_enabled;

		$contributions_url = SpecialPage::getTitleFor( 'Contributions', $subject_user->getName() )->getLocalURL();
		$preferences_url = $this->resolve_connections_preferences_url( $context );

		$render_payload = $payload;
		$render_payload['groups'] = $this->build_group_items( is_array( $payload['groups'] ?? null ) ? $payload['groups'] : [], $context, $subject_user );

		$decoration = '';
		$this->hook_runner->onIntegratedProfilesAfterAvatar( $payload, $decoration );

		$html = $this->profile_renderer->render_masthead( $render_payload, $can_edit, $messages, $contributions_url, $use_floating_ui, $is_owner, $decoration );

		$after = '';
		$this->hook_runner->onIntegratedProfilesAfterMasthead( $after, $payload );
		if ( $after !== '' ) {
			$html .= $after;
		}

		$out->addHTML( $html );

		if ( $can_edit ) {
			$out->addModules( [ 'ext.IntegratedProfiles.profile' ] );
			$out->addJsConfigVars( [
				'wgIntegratedProfiles' => [
					'user_name' => $payload['user_name'],
					'user_id' => $payload['user_id'],
					'central_id' => $payload['central_id'] ?? 0,

					'can_edit' => true,
					'can_upload_animated_avatar' => $this->profile_service->can_use_animated_avatar( $viewer ),

					'fields' => $payload['fields'],
					'links' => $payload['links'],
					'wiki_profiles' => $payload['wiki_profiles'] ?? [],

					'avatar_url' => $payload['avatar_url'],
					'has_custom_avatar' => $payload['has_custom_avatar'],
					'banner_url' => $payload['banner_url'] ?? '',
					'has_custom_banner' => $payload['has_custom_banner'] ?? false,
					'banner_presets' => ProfileFields::BANNER_GRADIENT_PRESETS,

					'ui' => $payload['ui'],

					'show_manage_connections' => $show_manage_connections,
					'show_connection_privacy' => $connections_enabled,

					'preferences_url' => $preferences_url,
					'connections' => $show_manage_connections ? $this->profile_service->get_connection_links( $subject_user ) : [],
					'connection_providers' => NewAuthBridge::PROFILE_PROVIDERS,

					'limits' => [
						'about' => (int)$context->getConfig()->get( 'IntegratedProfilesAboutMaxLength' ),
						'link' => (int)$context->getConfig()->get( 'IntegratedProfilesLinkMaxLength' ),
						'avatar_max_bytes' => (int)$context->getConfig()->get(
							'IntegratedProfilesAvatarMaxBytes'
						),
						'banner_max_bytes' => (int)$context->getConfig()->get(
							'IntegratedProfilesBannerMaxBytes'
						),
					],
				],
			] );
		}

		$iptab = (string)$context->getRequest()->getVal( ProfileTabs::QUERY_PARAM, '' );
		$tab_state = $this->profile_tabs->build( $iptab, $about_page_url,
			[
				'about' => $context->msg( 'integratedprofiles-tab-about' )->text(),
				'contributions' => $context->msg( 'integratedprofiles-tab-contributions' )->text()
			],
			$payload, $contributions_url, $forced_active
		);

		$out->addHTML(
			$this->profile_renderer->render_tabs( $tab_state['tabs'], $context->msg( 'integratedprofiles-tabs-label' )->text() )
		);

		return [ 'active' => $tab_state['active'], 'payload' => $payload ];
	}

	/**
	 * Builds and displays badges for explicit user groups.
	 *
	 * @param list<mixed> $groups
	 * @return list<array{label: string, url: string}>
	 */
	private function build_group_items( array $groups, IContextSource $context, User $subject_user ): array {
		$lang = $context->getLanguage();
		$items = [];

		foreach ( $groups as $group ) {
			if ( !is_string( $group ) || $group === '' || $group === '*' ) {
				continue;
			}

			$member_name = $lang->getGroupMemberName( $group, $subject_user );
			$label = $lang->ucfirst( $member_name !== '' ? $member_name : $group );

			$url = '';
			$page_msg = $context->msg( 'grouppage-' . $group )->inContentLanguage();

			if ( $page_msg->exists() ) {
				$group_title = Title::newFromText( $page_msg->text() );
				if ( $group_title ) {
					$url = $group_title->getLocalURL();
				}
			}

			if ( $url === '' ) {
				$group_title = Title::makeTitleSafe( NS_PROJECT, $label );
				$url = $group_title ? $group_title->getLocalURL() : '';
			}

			$items[] = [ 'label' => $label, 'url' => $url ];
		}

		return $items;
	}

	/**
	 * Returns the preferences URL for managing NewAuth connections.
	 */
	private function resolve_connections_preferences_url( IContextSource $context ): string {
		if ( ExtensionRegistry::getInstance()->isLoaded( 'NewAuth' ) && class_exists( \MediaWiki\Extension\NewAuth\NewAuthUrl::class ) ) {
			return \MediaWiki\Extension\NewAuth\NewAuthUrl::get_preferences_connected_accounts_url( $context->getConfig(), $context->getRequest() );
		}

		return SpecialPage::getTitleFor( 'Preferences' )->getLocalURL() . '#mw-prefsection-personal-connectedaccounts';
	}

}
