<?php

namespace MediaWiki\Extension\IntegratedProfiles;

use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\UserIdentity;

/**
 * Resolve local vs CentralAuth (or LocalIdLookup) IDs. Local IDs are used if CentralAuth is missing.
 */
class ProfileSubjectIds {

	public const SERVICE_NAME = 'IntegratedProfiles.ProfileSubjectIds';

	public function __construct(
		private readonly CentralIdLookup $central_id_lookup,
	) {
	}

	public function central_id_for( UserIdentity $user ): int {
		$local_id = $user->getId();
		if ( $local_id <= 0 ) {
			return 0;
		}

		$central_id = $this->central_id_lookup->centralIdFromLocalUser( $user, CentralIdLookup::AUDIENCE_RAW );
		if ( $central_id > 0 ) {
			return $central_id;
		}

		$name = $user->getName();
		if ( $name !== '' ) {
			$central_id = $this->central_id_lookup->centralIdFromName(
				$name,
				CentralIdLookup::AUDIENCE_RAW
			);
			
			if ( $central_id > 0 ) {
				return $central_id;
			}
		}

		return $local_id;
	}

	public function local_id_for( UserIdentity $user ): int {
		return $user->getId();
	}

	/**
	 * @return array{central_id: int, local_id: int}
	 */
	public function ids_for( UserIdentity $user ): array {
		return [ 'central_id' => $this->central_id_for( $user ), 'local_id' => $this->local_id_for( $user ) ];
	}

}
