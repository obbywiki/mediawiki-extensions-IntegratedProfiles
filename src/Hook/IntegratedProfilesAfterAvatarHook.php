<?php

namespace MediaWiki\Extension\IntegratedProfiles\Hook;

interface IntegratedProfilesAfterAvatarHook {

	/**
	 * @param array<string, mixed> $profile Profile payload (same as AfterMasthead)
	 * @param string &$html Mutable HTML string appended inside of `.ip-avatar`
	 */
	public function onIntegratedProfilesAfterAvatar( array $profile, string &$html ): void;

}
