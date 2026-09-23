<?php

/**
 * Minimal bootstrap for extension unit tests that do not boot MediaWiki.
 */
if ( file_exists( dirname( __DIR__, 2 ) . '/vendor/autoload.php' ) ) {
	require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';
}

if ( !interface_exists( \MediaWiki\User\UserIdentity::class ) ) {
	require_once __DIR__ . '/UserIdentityStub.php';
}

if ( !class_exists( \MediaWiki\User\CentralId\CentralIdLookup::class, false ) ) {
	require_once __DIR__ . '/CentralIdLookupStub.php';
}

if ( !class_exists( \MediaWiki\Config\ServiceOptions::class, false ) ) {
	require_once __DIR__ . '/ServiceOptionsStub.php';
}

if ( !class_exists( \Wikimedia\ObjectCache\BagOStuff::class, false ) ) {
	require_once __DIR__ . '/BagOStuffStub.php';
}

spl_autoload_register( static function ( string $class ): void {
	$prefix = 'MediaWiki\\Extension\\IntegratedProfiles\\';
	if ( !str_starts_with( $class, $prefix ) ) {
		return;
	}
	$relative = substr( $class, strlen( $prefix ) );
	$path = dirname( __DIR__, 2 ) . '/src/' . str_replace( '\\', '/', $relative ) . '.php';
	if ( is_readable( $path ) ) {
		require_once $path;
	}
} );
