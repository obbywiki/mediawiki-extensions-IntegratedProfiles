<?php

namespace MediaWiki\Extension\IntegratedProfiles;

use MediaWiki\User\UserIdentity;

/**
 * Edit permission checks for profile fields and avatar mutations.
 */
class ProfilePermissions {

	/**
	 * Returns whether the actor may edit the subject's profile fields / avatar or not.
	 */
	public function can_edit( UserIdentity $actor, int $owner_id, bool $is_blocked, bool $has_manage_right ): bool {
		if ( !$actor->isRegistered() || $is_blocked ) {
			return false;
		}

		if ( $has_manage_right ) {
			return true;
		}

		return $actor->getId() === $owner_id;
	}

	/**
	 * Returns whether the viewer may know this subject exists or not.
	 *
	 * Hidden (hideuser / oversight) accounts must look missing unless the viewer has the hideuser right (this matches core), or is the subject. This is independent of ip-visibility and integratedprofiles-manage.
	 */
	public function can_see_subject( bool $subject_is_hidden, bool $viewer_has_hideuser, bool $is_owner = false ): bool {
		if ( $is_owner ) {
			return true;
		}

		return !$subject_is_hidden || $viewer_has_hideuser;
	}

	/**
	 * Returns whether the actor may see masthead details for the given visibility mode or not, refer to the docs for what each setting means.
	 */
	public function can_view_details( UserIdentity $actor, int $owner_id, bool $has_manage_right, string $visibility = ProfileFields::VISIBILITY_PUBLIC ): bool {
		if ( $has_manage_right && $actor->isRegistered() ) {
			return true;
		}

		if ( $actor->isRegistered() && $actor->getId() === $owner_id ) {
			return true;
		}

		$visibility = ProfileFields::normalize_visibility( $visibility );

		return match ( $visibility ) {
			ProfileFields::VISIBILITY_PUBLIC => true,
			ProfileFields::VISIBILITY_USERS => $actor->isRegistered(),
			ProfileFields::VISIBILITY_PRIVATE => false,
			default => true,
		};
	}

}
