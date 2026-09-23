<?php

namespace MediaWiki\Config;

/**
 * Minimal ServiceOptions for standalone PHPUnit (when MediaWiki core is not loaded).
 */
class ServiceOptions {

	/** @param array<int, string> $keys */
	public function __construct(
		array $keys,
		private readonly mixed $options,
	) {
	}

	/** @param array<int, string> $keys */
	public function assertRequiredOptions( array $keys ): void {
	}

	public function get( string $key ): mixed {
		if ( is_array( $this->options ) ) {
			return $this->options[$key] ?? null;
		}
		if ( is_object( $this->options ) && method_exists( $this->options, 'get' ) ) {
			return $this->options->get( $key );
		}
		return null;
	}

}
