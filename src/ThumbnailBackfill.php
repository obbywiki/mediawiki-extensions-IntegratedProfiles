<?php

namespace MediaWiki\Extension\IntegratedProfiles;

use Closure;
use MediaWiki\User\UserIdentity;

/**
 * Backfills thumbnails from stored originals that don't already have any current set, removes versions superseded for longer than the retention period, and removes thumbnails left without an original. Runs synchronously, see maintenance/GenerateThumbnails.php.
 *
 * Can potentially purge a lot of cached user pages (in batches). Only purges in the wiki that it runs in (TODO).
 */
class ThumbnailBackfill {

	public const GENERATED = 'generated';
	public const CURRENT = 'current';
	public const PENDING = 'pending';
	public const UNSUPPORTED = 'unsupported';
	public const FAILED = 'failed';
	public const MISSING = 'missing';
	public const ORPHANED = 'orphaned';

	public const DEFAULT_PURGE_BATCH = 50;

	/** @var list<UserIdentity> */
	private array $purge_queue = [];

	/**
	 * @param ThumbnailGenerator $generator
	 * @param ProfileSubjectIds $subject_ids Maps storage owners back to local accounts for purging
	 * @param ProfilePagePurger|null $purger null skips purging
	 * @param int $purge_batch Users per purge
	 */
	public function __construct(
		private readonly ThumbnailGenerator $generator,
		private readonly ProfileSubjectIds $subject_ids,
		private readonly ?ProfilePagePurger $purger = null,
		private readonly int $purge_batch = self::DEFAULT_PURGE_BATCH,
	) {
	}

	/**
	 * @param AvatarStorage|BannerStorage $storage
	 * @param AvatarService|BannerService $service Matching service, for its cache
	 * @param list<int>|null $owner_ids null for every stored owner
	 * @param array{start?: int, force?: bool, dry_run?: bool, sleep_ms?: int} $options start: skip owners below this ID (resume), force: regenerate current sets, dry_run: only report, sleep_ms: pause after each generated set
	 * @param (Closure(int, string): void)|null $report Called with each owner and its status
	 * @return array<string, int> status => count
	 */
	public function run( AvatarStorage|BannerStorage $storage, AvatarService|BannerService $service, ?array $owner_ids, array $options = [], ?Closure $report = null ): array {
		$counts = array_fill_keys( [ self::GENERATED, self::CURRENT, self::PENDING, self::UNSUPPORTED, self::FAILED, self::MISSING, self::ORPHANED ], 0 );
		$start = (int)( $options['start'] ?? 0 );
		$force = (bool)( $options['force'] ?? false );
		$dry_run = (bool)( $options['dry_run'] ?? false );
		$sleep_ms = max( 0, (int)( $options['sleep_ms'] ?? 0 ) );

		try {
			// a full run also visits thumbnail directories left without an original
			if ( $owner_ids === null ) {
				$owner_ids = array_values( array_unique( array_merge( $storage->owner_ids(), $storage->thumbs()->owner_ids() ) ) );
				sort( $owner_ids );
			}

			foreach ( $owner_ids as $owner_id ) {
				if ( $owner_id <= 0 || $owner_id < $start ) {
					continue;
				}

				$status = $this->backfill_owner( $storage, $service, $owner_id, $force, $dry_run );
				$counts[$status]++;
				if ( $report !== null ) {
					$report( $owner_id, $status );
				}

				if ( $status === self::GENERATED && $sleep_ms > 0 ) {
					usleep( $sleep_ms * 1000 );
				}
			}
		} finally {
			$this->flush_purges();
		}

		return $counts;
	}

	/**
	 * @return string One of the status constants
	 */
	public function backfill_owner( AvatarStorage|BannerStorage $storage, AvatarService|BannerService $service, int $owner_id, bool $force = false, bool $dry_run = false ): string {
		$found = $storage->find_extension_with_mtime( $owner_id );
		if ( $found === null ) {
			if ( !$storage->thumbs()->has_owner( $owner_id ) ) {
				return self::MISSING;
			}

			// e.g. a set stored while its original was being deleted
			if ( !$dry_run ) {
				return $storage->thumbs()->delete_owner( $owner_id )->isOK() ? self::ORPHANED : self::FAILED;
			}

			return self::ORPHANED;
		}

		$version = $found['version'] ?? '';
		if ( !ThumbnailStorage::is_valid_version( $version ) ) {
			return self::FAILED;
		}

		if ( !$force && $storage->thumbs()->set_exists( $owner_id, $version ) ) {
			if ( !$dry_run ) {
				// uploads keep the replaced version around for cached pages, this is what eventually removes it
				$storage->thumbs()->delete_superseded( $owner_id, $version, $found['mtime'] );
			}

			return self::CURRENT;
		}

		if ( $dry_run ) {
			return self::PENDING;
		}

		$generated = $this->generator->generate_for( $storage, $owner_id, function () use ( $service, $owner_id ): void {
			$service->forget_cached( $owner_id );
			$this->queue_purge( $owner_id );
		} );

		if ( $generated ) {
			return self::GENERATED;
		}

		return $this->generator->last_was_unsupported() ? self::UNSUPPORTED : self::FAILED;
	}

	private function queue_purge( int $owner_id ): void {
		if ( $this->purger === null ) {
			return;
		}

		$user = $this->subject_ids->local_user_for( $owner_id );
		if ( $user === null ) {
			return;
		}

		$this->purge_queue[] = $user;
		if ( count( $this->purge_queue ) >= max( 1, $this->purge_batch ) ) {
			$this->flush_purges();
		}
	}

	private function flush_purges(): void {
		if ( $this->purger === null || $this->purge_queue === [] ) {
			return;
		}

		$this->purger->purge_subject_pages_batch( $this->purge_queue );
		$this->purge_queue = [];
	}

}
