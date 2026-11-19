<?php

namespace MediaWiki\Extension\IntegratedProfiles\Hook;

/**
 * @stable to implement
 */
interface IntegratedProfilesGetTabsHook {

	/**
	 * Allows other extensions to add or remove profile body tabs before the list is sorted and the active tab is resolved.
	 *
	 * Each tab entry has an ID, a label, and a weight. The default weight is 100.
	 * The initial tabs are About (weight 10), Talk (weight 15), and Contributions (weight 20).
	 * Use IntegratedProfilesRenderTab to supply the panel HTML.
	 *
	 * @param list<array{id?: string, label?: string, weight?: int}> &$tabs Mutable list of tab entries
	 * @param array<string, mixed> $profile Profile payload
	 */
	public function onIntegratedProfilesGetTabs( array &$tabs, array $profile ): void;

}
