<?php

namespace MediaWiki\Extension\IntegratedProfiles\Maintenance;

use MediaWiki\Extension\IntegratedProfiles\AvatarService;
use MediaWiki\Extension\IntegratedProfiles\AvatarStorage;
use MediaWiki\Extension\IntegratedProfiles\BannerService;
use MediaWiki\Extension\IntegratedProfiles\BannerStorage;
use MediaWiki\Extension\IntegratedProfiles\ProfilePagePurger;
use MediaWiki\Extension\IntegratedProfiles\ProfileSubjectIds;
use MediaWiki\Extension\IntegratedProfiles\ThumbnailBackfill;
use MediaWiki\Extension\IntegratedProfiles\ThumbnailGenerator;
use MediaWiki\Maintenance\Maintenance;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Generates thumbnails for stored avatars and banners that don't have a current set (e.g. uploads from before thumbnailing existed, or after a purge of the -thumbs containers).
 *
 * Usage: php maintenance/run.php IntegratedProfiles:GenerateThumbnails [--type=avatar|banner|all] [--owner=ID] [--start=ID] [--force] [--dry-run] [--no-purge] [--purge-batch=N] [--sleep=MS]
 *
 * This script is safe to run repeatedly (it is effecient). On a farm that's sharing one upload directory, running it on a single wiki is enough.
 */
class GenerateThumbnails extends Maintenance {

	private const KINDS = [
		'avatar' => [ AvatarStorage::SERVICE_NAME, AvatarService::SERVICE_NAME ],
		'banner' => [ BannerStorage::SERVICE_NAME, BannerService::SERVICE_NAME ],
	];

	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'IntegratedProfiles' );
		$this->addDescription( 'Generate missing avatar and banner thumbnails (e.g. for uploads from before thumbnailing existed).' );
		$this->addOption( 'type', 'avatar, banner, or all (default)', false, true );
		$this->addOption( 'owner', 'Only this storage owner (the central user ID, or the local one without CentralAuth)', false, true );
		$this->addOption( 'start', 'Skip owners below this ID, to resume an interrupted run', false, true );
		$this->addOption( 'force', 'Regenerate even when a current set exists' );
		$this->addOption( 'dry-run', 'Only report what would be generated' );
		$this->addOption( 'no-purge', 'Do not purge CDN copies of the affected profile pages (they expire after $wgCdnMaxAge)' );
		$this->addOption( 'purge-batch', 'Users per CDN purge (default ' . ThumbnailBackfill::DEFAULT_PURGE_BATCH . ')', false, true );
		$this->addOption( 'sleep', 'Milliseconds to pause after each generated set, to go easy on a live server (default 0)', false, true );
	}

	public function execute(): void {
		$type = (string)$this->getOption( 'type', 'all' );
		if ( $type !== 'all' && !isset( self::KINDS[$type] ) ) {
			$this->fatalError( '--type must be avatar, banner, or all.' );
		}

		$owner = $this->positive_option( 'owner' );
		$start = $this->positive_option( 'start' ) ?? 0;
		$dry_run = $this->hasOption( 'dry-run' );
		$services = $this->getServiceContainer();

		/** @var ThumbnailGenerator $generator */
		$generator = $services->get( ThumbnailGenerator::SERVICE_NAME );
		$available = $generator->available();
		if ( $available === [] ) {
			$this->fatalError( 'No thumbnail library is available. Check $wgIntegratedProfilesThumbnailers and the README requirements.' );
		}
		$this->output( 'Using: ' . implode( ', ', $available ) . ( $dry_run ? ' (dry run)' : '' ) . "\n" );

		$backfill = new ThumbnailBackfill(
			$generator,
			$services->get( ProfileSubjectIds::SERVICE_NAME ),
			$dry_run || $this->hasOption( 'no-purge' ) ? null : $services->get( ProfilePagePurger::SERVICE_NAME ),
			$this->positive_option( 'purge-batch' ) ?? ThumbnailBackfill::DEFAULT_PURGE_BATCH
		);

		$failed = 0;
		foreach ( self::KINDS as $kind => [ $storage_name, $service_name ] ) {
			if ( $type !== 'all' && $type !== $kind ) {
				continue;
			}

			/** @var AvatarStorage|BannerStorage $storage */
			$storage = $services->get( $storage_name );
			if ( !$storage->thumbs()->is_configured() ) {
				$this->error( "Skipping {$kind}s: the backend has no container for their thumbnails. Add it to the containerPaths of \$wgIntegratedProfilesBackend." );
				$failed++;
				continue;
			}

			$this->output( "\n" . ucfirst( $kind ) . "s:\n" );
			$counts = $backfill->run(
				$storage,
				$services->get( $service_name ),
				$owner !== null ? [ $owner ] : null,
				[
					'start' => $start,
					'force' => $this->hasOption( 'force' ),
					'dry_run' => $dry_run,
					'sleep_ms' => $this->positive_option( 'sleep' ) ?? 0,
				],
				function ( int $owner_id, string $status ): void {
					// current sets produce no output because they are common after reruns
					if ( $status !== ThumbnailBackfill::CURRENT ) {
						$this->output( "  $owner_id: $status\n" );
					}
				}
			);

			$this->output( sprintf(
				"  %d %s, %d already current, %d unsupported, %d failed%s\n",
				$dry_run ? $counts[ThumbnailBackfill::PENDING] : $counts[ThumbnailBackfill::GENERATED],
				$dry_run ? 'to generate' : 'generated',
				$counts[ThumbnailBackfill::CURRENT],
				$counts[ThumbnailBackfill::UNSUPPORTED],
				$counts[ThumbnailBackfill::FAILED],
				$counts[ThumbnailBackfill::MISSING] > 0 ? ', ' . $counts[ThumbnailBackfill::MISSING] . ' without an original' : ''
			) );
			$failed += $counts[ThumbnailBackfill::FAILED];
		}

		if ( $failed > 0 ) {
			$this->fatalError( "\nSome thumbnails could not be generated. Details are in the IntegratedProfiles log channel." );
		}
	}

	private function positive_option( string $name ): ?int {
		if ( !$this->hasOption( $name ) ) {
			return null;
		}

		$value = (string)$this->getOption( $name );
		if ( !ctype_digit( $value ) || (int)$value <= 0 ) {
			$this->fatalError( "--$name must be a positive integer." );
		}

		return (int)$value;
	}

}

// @codeCoverageIgnoreStart
$maintClass = GenerateThumbnails::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
