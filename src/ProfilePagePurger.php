<?php

namespace MediaWiki\Extension\IntegratedProfiles;

use MediaWiki\Cache\HTMLCacheUpdater;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserIdentity;

/**
 * Purges local User:X / User_talk:X HTML so anonymous CDN copies drop after a visibility change, and the URLs of deleted profile media.
 */
class ProfilePagePurger {

	public const SERVICE_NAME = 'IntegratedProfiles.ProfilePagePurger';

	public function __construct(
		private readonly HTMLCacheUpdater $html_cache_updater,
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
	 * Sends a local CDN/file-cache purge for the subject's user and talk pages (also reaches Cloudflare and others through MultiPurge's cdn-url-purges relayer).
	 */
	public function purge_subject_pages( UserIdentity $user ): void {
		$this->purge_subject_pages_batch( [ $user ] );
	}

	/**
	 * Same as purge_subject_pages, but for many subjects in a single CDN purge (one MultiPurge job instead of one job for every user).
	 *
	 * @param iterable<UserIdentity> $users
	 */
	public function purge_subject_pages_batch( iterable $users ): void {
		$titles = [];
		foreach ( $users as $user ) {
			$name = $user->getName();
			if ( $name === '' ) {
				continue;
			}

			foreach ( [ NS_USER, NS_USER_TALK ] as $namespace ) {
				$title = $this->title_factory->makeTitleSafe( $namespace, $name );
				if ( $title ) {
					$titles[] = $title;
				}
			}
		}

		if ( $titles === [] ) {
			return;
		}

		$this->html_cache_updater->purgeTitleUrls( $titles );
	}

	/**
	 * Purges media URLs (deleted originals and thumbnails) from the CDN, through the same path as page purges.
	 *
	 * @param list<string> $urls
	 */
	public function purge_urls( array $urls ): void {
		$urls = array_values( array_unique( array_filter( $urls, static fn ( $url ): bool => is_string( $url ) && $url !== '' ) ) );
		if ( $urls === [] ) {
			return;
		}

		$this->html_cache_updater->purgeUrls( $urls );
	}

}
