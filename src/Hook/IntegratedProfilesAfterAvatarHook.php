<?php

namespace MediaWiki\Extension\IntegratedProfiles\Hook;

/**
 * @stable to implement
 */
interface IntegratedProfilesAfterAvatarHook {

	/**
	 * Allows other extensions to append HTML inside the profile avatar.
	 * Runs before the masthead is rendered. The HTML appears after the avatar image and before the edit button.
	 *
	 * @param array<string, mixed> $profile Profile payload (same as AfterMasthead)
	 * @param string &$html Mutable HTML string appended inside of `.ip-avatar`
	 */
	public function onIntegratedProfilesAfterAvatar( array $profile, string &$html ): void;

}
