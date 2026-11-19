<?php

namespace MediaWiki\Extension\IntegratedProfiles\Hook;

/**
 * @stable to implement
 */
interface IntegratedProfilesAfterMastheadHook {

	/**
	 * Allows other extensions to append HTML after the profile masthead.
	 * Runs after the masthead HTML is rendered and before it is added to the output.
	 * Use the profile tab hooks for content that needs its own panel.
	 *
	 * @param string &$html Mutable HTML string appended after the masthead
	 * @param array<string, mixed> $profile Profile payload
	 * @see IntegratedProfilesGetTabsHook
	 * @see IntegratedProfilesRenderTabHook
	 */
	public function onIntegratedProfilesAfterMasthead( string &$html, array $profile ): void;

}
