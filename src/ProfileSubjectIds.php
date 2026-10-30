<?php

namespace MediaWiki\Extension\IntegratedProfiles;

use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\CentralId\LocalIdLookup;
use MediaWiki\User\UserIdentity;

/**
 * Resolve local vs CentralAuth (or LocalIdLookup) IDs. Local IDs are used if CentralAuth (or another CentralIdLookup provider) is missing.
 */
class ProfileSubjectIds {

	public const SERVICE_NAME = 'IntegratedProfiles.ProfileSubjectIds';

	/** @var array<int, int> Request-local map of local user id => resolved central id */
	private array $central_ids_by_local = [];

	public function __construct(
		private readonly CentralIdLookup $central_id_lookup,
	) {
	}

	public function central_id_for( UserIdentity $user ): int {
		$local_id = $user->getId();
		if ( $local_id <= 0 ) {
			return 0;
		}

		if ( isset( $this->central_ids_by_local[$local_id] ) ) {
			return $this->central_ids_by_local[$local_id];
		}

		$central_id = $this->central_id_lookup->centralIdFromLocalUser( $user, CentralIdLookup::AUDIENCE_RAW );
		if ( $central_id <= 0 ) {
			$name = $user->getName();
			if ( $name !== '' ) {
				$central_id = $this->central_id_lookup->centralIdFromName(
					$name,
					CentralIdLookup::AUDIENCE_RAW
				);
			}
		}

		$this->central_ids_by_local[$local_id] = $central_id;
		return $central_id;
	}

	/**
	 * The local account for a storage owner, or null instead when it has none on this wiki (e.g. a CentralAuth user who never visited).
	 */
	public function local_user_for( int $central_id ): ?UserIdentity {
		if ( $central_id <= 0 ) {
			return null;
		}

		return $this->central_id_lookup->localUserFromCentralId( $central_id, CentralIdLookup::AUDIENCE_RAW );
	}

	public function local_id_for( UserIdentity $user ): int {
		return $user->getId();
	}

	public function uses_local_ids(): bool {
		return $this->central_id_lookup instanceof LocalIdLookup;
	}

	/**
	 * @return array{central_id: int, local_id: int}
	 */
	public function ids_for( UserIdentity $user ): array {
		return [ 'central_id' => $this->central_id_for( $user ), 'local_id' => $this->local_id_for( $user ) ];
	}

}
