<?php

namespace MediaWiki\Extension\IntegratedProfiles;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\IntegratedProfiles\Hook\IntegratedProfilesAfterAvatarHook;
use MediaWiki\Extension\IntegratedProfiles\Hook\IntegratedProfilesVerifyImageUploadHook;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\User\UserIdentity;

/**
 * IntegratedProfiles hooks dispatcher.
 * TODO: add these to src/Hook/*
 */
class HookRunner implements IntegratedProfilesAfterAvatarHook, IntegratedProfilesVerifyImageUploadHook {

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
	 * Core seeds: About (weight 10), Talk (weight 15), Contributions (weight 20).
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
	 * @param string $tab_id Active tab id (not about/talk/contributions)
	 * @param string &$html Mutable panel HTML
	 * @param array<string, mixed> $profile Profile payload
	 * @param IContextSource $context Request context
	 */
	public function onIntegratedProfilesRenderTab( string $tab_id, string &$html, array $profile, IContextSource $context ): void {
		$this->hook_container->run( 'IntegratedProfilesRenderTab', [ $tab_id, &$html, $profile, $context ] );
	}

	/**
	 * Lets other extensions reject an avatar or banner before it is stored. Intended for moderation purposes.
	 * Works like core's UploadVerifyFile, set $error (or return false) to reject the upload.
	 *
	 * @param string $kind 'avatar' or 'banner'
	 * @param string $path Local path to the sanitized image that would be stored
	 * @param string $mime Detected MIME type
	 * @param int $central_id Central user ID the image is stored under
	 * @param UserIdentity|null $user Profile owner, when known
	 * @param string|array|\Wikimedia\Message\MessageSpecifier|null &$error Rejection message
	 * @return bool False when a handler aborted
	 */
	public function onIntegratedProfilesVerifyImageUpload( string $kind, string $path, string $mime, int $central_id, ?UserIdentity $user, &$error ) {
		return $this->hook_container->run( 'IntegratedProfilesVerifyImageUpload', [ $kind, $path, $mime, $central_id, $user, &$error ] );
	}

}
