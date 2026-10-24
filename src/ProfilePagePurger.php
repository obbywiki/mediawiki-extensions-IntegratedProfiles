<?php

namespace MediaWiki\Extension\IntegratedProfiles;

use MediaWiki\Cache\HtmlCacheUpdater;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserIdentity;

/**
 * Purges local User:X / User_talk:X HTML so anonymous CDN copies drop after a visibility change.
 */
class ProfilePagePurger {

	public const SERVICE_NAME = 'IntegratedProfiles.ProfilePagePurger';

	public function __construct(
		private readonly HtmlCacheUpdater $html_cache_updater,
		private readonly TitleFactory $title_factory,
	) {
	}

	/**
	 * @param UserIdentity $user Profile subject
	 * @param array<string, mixed> $modified_options Options being written
	 * @param array<string, mixed> $original_options Options before this save
	 */
	public function maybe_purge_on_visibility_change( UserIdentity $user, array $modified_options, array $original_options ): void {
		if ( !array_key_exists( ProfileFields::KEY_VISIBILITY, $modified_options ) ) {
			return;
		}

		$previous = ProfileFields::normalize_visibility(
			isset( $original_options[ ProfileFields::KEY_VISIBILITY ] ) ? (string)$original_options[ ProfileFields::KEY_VISIBILITY ] : ''
		);
		$next = ProfileFields::normalize_visibility(
			(string)( $modified_options[ ProfileFields::KEY_VISIBILITY ] ?? '' )
		);

		if ( $previous === $next ) {
			return;
		}

		$this->purge_subject_pages( $user );
	}

	/**
	 * Sends a local CDN/file-cache purge for the subject's user and talk pages.
	 */
	private function purge_subject_pages( UserIdentity $user ): void {
		$name = $user->getName();
		if ( $name === '' ) {
			return;
		}

		$titles = [];
		$user_title = $this->title_factory->makeTitleSafe( NS_USER, $name );
		if ( $user_title ) {
			$titles[] = $user_title;
		}

		$talk_title = $this->title_factory->makeTitleSafe( NS_USER_TALK, $name );
		if ( $talk_title ) {
			$titles[] = $talk_title;
		}

		if ( $titles === [] ) {
			return;
		}

		$this->html_cache_updater->purgeTitleUrls( $titles, HtmlCacheUpdater::PURGE_INTENT_TXROUND_REFLECTED );
	}

}
