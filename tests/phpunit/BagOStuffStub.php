<?php

namespace Wikimedia\ObjectCache;

/**
 * Minimal BagOStuff for standalone PHPUnit (when MediaWiki core is not loaded).
 */
class BagOStuff {

	/** @var array<string, mixed> */
	private array $data = [];

	public function get( $key, $flags = 0 ) {
		return $this->data[$key] ?? false;
	}

	/**
	 * @param list<string> $keys
	 * @return array<string, mixed>
	 */
	public function getMulti( array $keys, $flags = 0 ): array {
		$out = [];
		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $this->data ) ) {
				$out[$key] = $this->data[$key];
			}
		}

		return $out;
	}

	public function set( $key, $value, $exptime = 0, $flags = 0 ): bool {
		$this->data[$key] = $value;

		return true;
	}

	public function makeGlobalKey( $keygroup, ...$components ): string {
		return 'global:' . $keygroup . ( $components !== [] ? ':' . implode( ':', $components ) : '' );
	}

}
