<?php

namespace MediaWiki\Extension\IntegratedProfiles;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\IntegratedProfiles\Hook\IntegratedProfilesAfterAvatarHook;
use MediaWiki\Extension\IntegratedProfiles\Hook\IntegratedProfilesAfterMastheadHook;
use MediaWiki\Extension\IntegratedProfiles\Hook\IntegratedProfilesGetTabsHook;
use MediaWiki\Extension\IntegratedProfiles\Hook\IntegratedProfilesRenderTabHook;
use MediaWiki\Extension\IntegratedProfiles\Hook\IntegratedProfilesVerifyImageUploadHook;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\User\UserIdentity;

/**
 * IntegratedProfiles hooks dispatcher.
 *
 * @internal
 */
class HookRunner implements
	IntegratedProfilesAfterMastheadHook,
	IntegratedProfilesAfterAvatarHook,
	IntegratedProfilesGetTabsHook,
	IntegratedProfilesRenderTabHook,
	IntegratedProfilesVerifyImageUploadHook
{

	public function __construct(
		private readonly HookContainer $hook_container,
	) {
	}

	/** @inheritDoc */
	public function onIntegratedProfilesAfterMasthead( string &$html, array $profile ): void {
		$this->hook_container->run( 'IntegratedProfilesAfterMasthead', [ &$html, $profile ] );
	}

	/** @inheritDoc */
	public function onIntegratedProfilesAfterAvatar( string &$html, array $profile ): void {
		$this->hook_container->run( 'IntegratedProfilesAfterAvatar', [ &$html, $profile ] );
	}

	/** @inheritDoc */
	public function onIntegratedProfilesGetTabs( array &$tabs, array $profile ): void {
		$this->hook_container->run( 'IntegratedProfilesGetTabs', [ &$tabs, $profile ] );
	}

	/** @inheritDoc */
	public function onIntegratedProfilesRenderTab( string $tab_id, string &$html, array $profile, IContextSource $context ): void {
		$this->hook_container->run( 'IntegratedProfilesRenderTab', [ $tab_id, &$html, $profile, $context ] );
	}

	/**
	 * @inheritDoc
	 * @return bool False when a handler aborted
	 */
	public function onIntegratedProfilesVerifyImageUpload( string $kind, string $path, string $mime, int $central_id, ?UserIdentity $user, &$error ) {
		return $this->hook_container->run( 'IntegratedProfilesVerifyImageUpload', [ $kind, $path, $mime, $central_id, $user, &$error ] );
	}

}
