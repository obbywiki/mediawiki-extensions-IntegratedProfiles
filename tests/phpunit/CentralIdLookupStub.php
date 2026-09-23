<?php

namespace MediaWiki\User\CentralId;

/**
 * Minimal CentralIdLookup mock for standalone PHPUnit (when MediaWiki core is not loaded).
 * Loaded only from tests/phpunit/bootstrap.php when the real class is not available.
 */
abstract class CentralIdLookup {

	public const AUDIENCE_PUBLIC = 1;

	public const AUDIENCE_RAW = 2;

	/**
	 * @param \MediaWiki\User\UserIdentity $user
	 * @param int|mixed $audience
	 * @param int $flags
	 */
	abstract public function centralIdFromLocalUser( $user, $audience = self::AUDIENCE_PUBLIC, $flags = 0 ): int;

	/**
	 * @param string $name
	 * @param int|mixed $audience
	 * @param int $flags
	 */
	public function centralIdFromName( $name, $audience = self::AUDIENCE_PUBLIC, $flags = 0 ): int {
		return 0;
	}

}
