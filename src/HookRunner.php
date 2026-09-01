<?php

namespace MediaWiki\Extension\IntegratedProfiles;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\IntegratedProfiles\Hook\IntegratedProfilesAfterAvatarHook;
use MediaWiki\HookContainer\HookContainer;

/**
 * IntegratedProfiles hooks dispatcher.
 * TODO: add these to src/Hook/*
 */
class HookRunner implements IntegratedProfilesAfterAvatarHook {

	public function __construct(
		private readonly HookContainer $hook_container,
	) {
	}

	/**
	 * Allows other extensions to append HTML after the profile masthead.
	 *
	 * @param string &$html Mutable HTML string appended after the masthead
	 * @param array<string, mixed> $profile Profile payload
	 */
	public function onIntegratedProfilesAfterMasthead( string &$html, array $profile ): void {
		$this->hook_container->run( 'IntegratedProfilesAfterMasthead', [ &$html, $profile ] );
	}

	/**
	 * Allows other extensions to append HTML inside the profile avatar.
	 *
	 * Fired after `<img class="ip-avatar__image">` and before the edit button.
	 * Argument order is `$profile` then `&$html`, inverse of IntegratedProfilesAfterMasthead.
	 * (which is confusing so this may be subject to change in the future)
	 *
	 * @param array<string, mixed> $profile Profile payload
	 * @param string &$html Mutable HTML string appended inside `.ip-avatar`
	 */
	public function onIntegratedProfilesAfterAvatar( array $profile, string &$html ): void {
		$this->hook_container->run( 'IntegratedProfilesAfterAvatar', [ $profile, &$html ]
		);
	}

	/**
	 * Allows other extensions (aka. companions) to register additional profile body tabs.
	 *
	 * Each tab entry: `[ 'id' => string, 'label' => string, 'weight' => int ]`.
	 * Core seeds: About (weight 10), Contributions (weight 20).
	 * Please don't register 'Talk' as it may be used in the future.
	 *
	 * @param list<array{id?: string, label?: string, weight?: int}> &$tabs
	 * @param array<string, mixed> $profile Profile payload
	 */
	public function onIntegratedProfilesGetTabs( array &$tabs, array $profile ): void {
		$this->hook_container->run( 'IntegratedProfilesGetTabs', [ &$tabs, $profile ] );
	}

	/**
	 * Fills HTML for a tab when it is the active tab (requires onIntegratedProfilesGetTabs to be called first).
	 *
	 * @param string $tab_id Active tab id (not about/contributions)
	 * @param string &$html Mutable panel HTML
	 * @param array<string, mixed> $profile Profile payload
	 * @param IContextSource $context Request context
	 */
	public function onIntegratedProfilesRenderTab( string $tab_id, string &$html, array $profile, IContextSource $context ): void {
		$this->hook_container->run( 'IntegratedProfilesRenderTab', [ $tab_id, &$html, $profile, $context ] );
	}

}
