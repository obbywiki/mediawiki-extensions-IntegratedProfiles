<?php

namespace MediaWiki\Extension\IntegratedProfiles;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\Registration\UserRegistrationLookup;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;

/**
 * Assembles profile payloads and persists ip-* preference fields.
 */
class ProfileService {

	public const SERVICE_NAME = 'IntegratedProfiles.ProfileService';

	public const RIGHT_ANIMATED_AVATAR = 'integratedprofiles-animated-avatar';

	public const CONSTRUCTOR_OPTIONS = [
		'IntegratedProfilesColor',
		'IntegratedProfilesAvatarBorderRadius',
		'IntegratedProfilesAboutMaxLength',
		'IntegratedProfilesLinkMaxLength',
		'IntegratedProfilesEnableAnimatedAvatars',
	];

	private readonly ProfileFields $fields;

	public function __construct(
		private readonly ServiceOptions $options,
		private readonly ProfilePermissions $permissions,
		private readonly ProfileSubjectIds $subject_ids,
		private readonly AvatarService $avatar_service,
		private readonly BannerService $banner_service,
		private readonly NewAuthBridge $newauth_bridge,
		private readonly UserFactory $user_factory,
		private readonly UserOptionsLookup $user_options_lookup,
		private readonly UserOptionsManager $user_options_manager,
		private readonly UserGroupManager $user_group_manager,
		private readonly UserRegistrationLookup $user_registration_lookup,
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->fields = new ProfileFields(
			(int)$options->get( 'IntegratedProfilesAboutMaxLength' ),
			(int)$options->get( 'IntegratedProfilesLinkMaxLength' )
		);
	}

	public function get_fields_helper(): ProfileFields {
		return $this->fields;
	}

	public function get_permissions(): ProfilePermissions {
		return $this->permissions;
	}

	public function get_subject_ids(): ProfileSubjectIds {
		return $this->subject_ids;
	}

	/**
	 * Whether this title is a root User: / User_talk: page (not a subpage).
	 */
	public function is_profile_title( Title $title ): bool {
		$ns = $title->getNamespace();
		if ( $ns !== NS_USER && $ns !== NS_USER_TALK ) {
			return false;
		}
		return !$title->isSubpage();
	}

	/**
	 * Resolve a registered, usable subject user from a profile title, or null.
	 */
	public function resolve_subject_user( Title $title ): ?User {
		if ( !$this->is_profile_title( $title ) ) {
			return null;
		}

		$root = $title->getRootText();
		$user = $this->user_factory->newFromName( $root );
		if ( !$user || !$user->isRegistered() || !$user->isNamed() ) {
			return null;
		}

		return $user;
	}

	/**
	 * Resolve a registered subject by username for APIs.
	 */
	public function resolve_user_by_name( string $username ): ?User {
		$user = $this->user_factory->newFromName( $username );
		if ( !$user || !$user->isRegistered() ) {
			return null;
		}
		return $user;
	}

	public function can_edit( UserIdentity $actor, User $subject ): bool {
		$is_blocked = method_exists( $actor, 'getBlock' ) && (bool)$actor->getBlock();
		$has_manage = method_exists( $actor, 'isAllowed' )
			&& (bool)$actor->isAllowed( 'integratedprofiles-manage' );

		return $this->permissions->can_edit(
			$actor,
			$subject->getId(),
			$is_blocked,
			$has_manage
		);
	}

	/**
	 * Whether the actor may upload an animated avatar (GIF / animated WebP / APNG).
	 *
	 * Requires `$wgIntegratedProfilesEnableAnimatedAvatars` and the
	 * `integratedprofiles-animated-avatar` right.
	 */
	public function can_use_animated_avatar( UserIdentity $actor ): bool {
		if ( !(bool)$this->options->get( 'IntegratedProfilesEnableAnimatedAvatars' ) ) {
			return false;
		}

		return method_exists( $actor, 'isAllowed' )
			&& (bool)$actor->isAllowed( self::RIGHT_ANIMATED_AVATAR );
	}

	/**
	 * Whether the actor may see masthead details for the subject under their
	 * configured visibility mode.
	 */
	public function can_view_details( UserIdentity $actor, User $subject ): bool {
		$has_manage = method_exists( $actor, 'isAllowed' )
			&& (bool)$actor->isAllowed( 'integratedprofiles-manage' );
		$visibility = ProfileFields::normalize_visibility(
			(string)$this->user_options_lookup->getOption(
				$subject,
				ProfileFields::KEY_VISIBILITY,
				ProfileFields::VISIBILITY_PUBLIC
			)
		);

		return $this->permissions->can_view_details(
			$actor,
			$subject->getId(),
			$has_manage,
			$visibility
		);
	}

	/**
	 * Subject's masthead visibility mode (`public` / `users` / `private`).
	 */
	public function get_visibility( User $subject ): string {
		return ProfileFields::normalize_visibility(
			(string)$this->user_options_lookup->getOption(
				$subject,
				ProfileFields::KEY_VISIBILITY,
				ProfileFields::VISIBILITY_PUBLIC
			)
		);
	}

	/**
	 * Full public profile payload.
	 *
	 * When the subject's visibility restricts `$viewer`, returns a scrubbed
	 * payload (avatar + username only extras cleared). Pass `null` (or omit)
	 * for mutation/API responses that must stay unscrubbed.
	 *
	 * @return array<string, mixed>
	 */
	public function get_payload( User $subject, ?UserIdentity $viewer = null ): array {
		$field_values = $this->read_fields( $subject );
		$groups = array_values( array_filter(
			$this->user_group_manager->getUserGroups( $subject ),
			static fn ( string $group ): bool => $group !== '*'
		) );
		$avatar = $this->avatar_service->get_avatar_info_for_user( $subject );
		$banner = $this->banner_service->get_banner_info_for_user( $subject );
		$field_values[ ProfileFields::KEY_BANNER ] = $this->resolve_banner_mode(
			(string)( $field_values[ ProfileFields::KEY_BANNER ] ?? '' ),
			$banner['has_custom_banner']
		);
		$registration = $this->user_registration_lookup->getFirstRegistration( $subject );

		$connections = $this->newauth_bridge->get_links_for_user( $subject );
		if ( ProfileFields::is_flag_on(
			(string)( $field_values[ ProfileFields::KEY_HIDE_CONNECTIONS ] ?? '0' )
		) ) {
			$connections = [];
		}

		$payload = [
			'user_id' => $subject->getId(),
			'central_id' => $this->subject_ids->central_id_for( $subject ),
			'user_name' => $subject->getName(),
			'real_name' => $subject->getRealName(),
			'edit_count' => (int)$subject->getEditCount(),
			'registration' => is_string( $registration ) && $registration !== ''
				? $registration
				: null,
			'groups' => $groups,
			'fields' => $field_values,
			'links' => $this->fields->build_public_links( $field_values ),
			'wiki_profiles' => $this->fields->build_wiki_profiles( $field_values ),
			'featured_article' => $this->resolve_featured_article(
				(string)( $field_values[ ProfileFields::KEY_FEATURED_ARTICLE ] ?? '' )
			),
			'avatar_url' => $avatar['avatar_url'],
			'has_custom_avatar' => $avatar['has_custom_avatar'],
			'banner_url' => $banner['banner_url'],
			'has_custom_banner' => $banner['has_custom_banner'],
			'connections' => $connections,
			'ui' => [
				'color' => (string)$this->options->get( 'IntegratedProfilesColor' ),
				'avatar_border_radius' => (string)$this->options->get(
					'IntegratedProfilesAvatarBorderRadius'
				),
			],
		];

		// Mutation callers omit $viewer and must receive the full payload.
		if ( $viewer !== null && !$this->can_view_details( $viewer, $subject ) ) {
			return ProfileFields::scrub_private_payload( $payload );
		}

		return $payload;
	}

	/**
	 * Resolve a stored page title into a public featured-article descriptor.
	 *
	 * @return array{title: string, display_title: string, url: string}|null
	 */
	public function resolve_featured_article( string $title_text ): ?array {
		$title_text = trim( $title_text );
		if ( $title_text === '' ) {
			return null;
		}

		$title = Title::newFromText( $title_text );
		if ( !$title || !$title->canExist() || $title->isSpecialPage() ) {
			return null;
		}
		if ( !$title->exists() ) {
			return null;
		}

		return [
			'title' => $title->getPrefixedText(),
			'display_title' => $title->getPrefixedText(),
			'url' => $title->getLocalURL(),
		];
	}

	public function get_avatar_service(): AvatarService {
		return $this->avatar_service;
	}

	public function get_banner_service(): BannerService {
		return $this->banner_service;
	}

	/**
	 * NewAuth connection rows for editor display (ignores hide preference).
	 *
	 * @return list<array{
	 *   provider: string,
	 *   remote_user: string,
	 *   remote_username: string,
	 *   metadata: array<string, mixed>
	 * }>
	 */
	public function get_connection_links( User $subject ): array {
		return $this->newauth_bridge->get_links_for_user( $subject );
	}

	/**
	 * Persist sanitized fields for the subject. Caller must check can_edit.
	 *
	 * @param User $subject Profile owner
	 * @param array $input Raw field map from the client
	 * @return Status
	 */
	public function save_fields( User $subject, array $input ): Status {
		$result = $this->fields->sanitize_fields( $input );
		if ( $result['invalid'] !== [] ) {
			return Status::newFatal(
				'integratedprofiles-error-invalid-fields',
				implode( ', ', $result['invalid'] )
			);
		}

		if ( $result['fields'] === [] ) {
			return Status::newGood( $this->get_payload( $subject ) );
		}

		$previous_banner = ProfileFields::normalize_banner(
			(string)$this->user_options_lookup->getOption( $subject, ProfileFields::KEY_BANNER )
		);

		foreach ( $result['fields'] as $key => $value ) {
			$this->set_profile_option( $subject, $key, $value );
		}
		$this->user_options_manager->saveOptions( $subject );

		if ( array_key_exists( ProfileFields::KEY_BANNER, $result['fields'] ) ) {
			$new_banner = ProfileFields::normalize_banner(
				(string)$result['fields'][ ProfileFields::KEY_BANNER ]
			);
			$this->maybe_delete_unused_banner( $subject, $previous_banner, $new_banner );
		}

		return Status::newGood( $this->get_payload( $subject ) );
	}

	/**
	 * Delete custom banner files when leaving the custom preset.
	 */
	public function maybe_delete_unused_banner(
		UserIdentity $subject,
		string $previous_banner,
		string $new_banner
	): void {
		$previous = ProfileFields::normalize_banner( $previous_banner );
		$next = ProfileFields::normalize_banner( $new_banner );
		if (
			$previous === ProfileFields::BANNER_CUSTOM
			&& $next !== ProfileFields::BANNER_CUSTOM
		) {
			$this->banner_service->delete_for_user( $subject );
		}
	}

	/**
	 * Remove avatar and banner files for a deleted account.
	 */
	public function delete_media_for_user( UserIdentity $user ): Status {
		$status = $this->avatar_service->delete_for_user( $user );
		$status->merge( $this->banner_service->delete_for_user( $user ) );
		return $status;
	}

	/**
	 * Store a custom banner and set ip-banner to custom. Caller must check can_edit.
	 */
	public function upload_banner( User $subject, string $tmp_path, int $size ): Status {
		$status = $this->banner_service->upload_for_user( $subject, $tmp_path, $size );
		if ( !$status->isOK() ) {
			return $status;
		}

		$this->set_profile_option(
			$subject,
			ProfileFields::KEY_BANNER,
			ProfileFields::BANNER_CUSTOM
		);
		$this->user_options_manager->saveOptions( $subject );

		return Status::newGood( $this->get_payload( $subject ) );
	}

	/**
	 * Delete a custom banner and reset ip-banner to accent. Caller must check can_edit.
	 */
	public function delete_banner( User $subject ): Status {
		$status = $this->banner_service->delete_for_user( $subject );
		if ( !$status->isOK() ) {
			return $status;
		}

		$this->set_profile_option(
			$subject,
			ProfileFields::KEY_BANNER,
			ProfileFields::BANNER_ACCENT
		);
		$this->user_options_manager->saveOptions( $subject );

		return Status::newGood( $this->get_payload( $subject ) );
	}

	public function default_avatar_url(): string {
		return $this->avatar_service->default_avatar_url();
	}

	/**
	 * Persist an ip-* field globally when GlobalPreferences is loaded.
	 *
	 * setOption() defaults to GLOBAL_IGNORE, which writes only local
	 * user_properties. AutoPrefs update existing global_preferences rows but
	 * do not create them, so Edit profile never synced across CentralAuth wikis.
	 * GLOBAL_CREATE writes my_wiki.global_preferences keyed by central id, and
	 * falls back to a local save when no global store exists.
	 */
	private function set_profile_option( UserIdentity $user, string $key, string $value ): void {
		$this->user_options_manager->setOption(
			$user,
			$key,
			$value,
			UserOptionsManager::GLOBAL_CREATE
		);
	}

	/**
	 * Effective banner mode for display: custom without a file falls back to accent.
	 */
	private function resolve_banner_mode( string $stored, bool $has_custom_banner ): string {
		$mode = ProfileFields::normalize_banner( $stored );
		if ( $mode === ProfileFields::BANNER_CUSTOM && !$has_custom_banner ) {
			return ProfileFields::BANNER_ACCENT;
		}
		return $mode;
	}

	/**
	 * @return array<string, string>
	 */
	private function read_fields( User $subject ): array {
		$fields = $this->fields->empty_fields();
		foreach ( ProfileFields::KEYS as $key ) {
			$value = $this->user_options_lookup->getOption( $subject, $key );
			if ( $key === ProfileFields::KEY_BANNER ) {
				$fields[$key] = ProfileFields::normalize_banner(
					is_string( $value ) ? $value : ''
				);
				continue;
			}
			if ( $key === ProfileFields::KEY_VISIBILITY ) {
				$fields[$key] = ProfileFields::normalize_visibility(
					is_string( $value ) ? $value : ''
				);
				continue;
			}
			if ( $key === ProfileFields::KEY_HIDE_CONNECTIONS ) {
				$fields[$key] = ProfileFields::is_flag_on(
					is_string( $value ) || is_numeric( $value ) ? (string)$value : ''
				) ? '1' : '0';
				continue;
			}
			$fields[$key] = is_string( $value ) ? $value : '';
		}
		return $fields;
	}

}
