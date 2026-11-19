<?php

namespace MediaWiki\Extension\IntegratedProfiles\Hook;

use MediaWiki\Context\IContextSource;

/**
 * @stable to implement
 */
interface IntegratedProfilesRenderTabHook {

	/**
	 * Allows other extensions to provide panel HTML when a tab registered through IntegratedProfilesGetTabs is active. Handlers should check the tab ID before writing HTML.
	 *
	 * @param string $tab_id Active tab ID
	 * @param string &$html Mutable panel HTML
	 * @param array<string, mixed> $profile Profile payload
	 * @param IContextSource $context Request context
	 */
	public function onIntegratedProfilesRenderTab( string $tab_id, string &$html, array $profile, IContextSource $context ): void;

}
