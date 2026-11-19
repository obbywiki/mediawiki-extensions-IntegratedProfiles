<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\IntegratedProfiles\Tests\Unit;

use MediaWiki\Extension\IntegratedProfiles\ProfileImageResolver;
use MediaWiki\Extension\IntegratedProfiles\ProfileImageStorage;
use MediaWiki\Extension\IntegratedProfiles\ProfileSubjectIds;
use MediaWiki\Extension\IntegratedProfiles\ThumbnailStorage;
use MediaWikiUnitTestCase;
use Wikimedia\ObjectCache\HashBagOStuff;

/**
 * @group IntegratedProfiles
 * @covers \MediaWiki\Extension\IntegratedProfiles\ProfileImageResolver
 */
class ProfileImageResolverTest extends MediaWikiUnitTestCase {

	public static function provide_namespaces(): array {
		return [ 'avatar' => [ 'avatar' ], 'banner' => [ 'banner' ] ];
	}

	/** @dataProvider provide_namespaces */
	public function test_cached_results_skip_storage_and_keep_legacy_format( string $namespace ): void {
		$cache = new HashBagOStuff();
		$storage = $this->createMock( ProfileImageStorage::class );
		$storage->expects( $this->never() )->method( 'lookup_original' );
		$resolver = new ProfileImageResolver(
			$storage, $cache, $this->createMock( ProfileSubjectIds::class ), $namespace
		);
		$resolver->remember( 7, 'png', '42', '0123456789', true );
		$key = $cache->makeGlobalKey( 'integratedprofiles', $namespace, '4', '7' );
		$this->assertSame( '7:png:42:0123456789:1', $cache->get( $key ) );
		$this->assertSame( [
			'owner_id' => 7, 'ext' => 'png', 'mtime' => '42', 'version' => '0123456789', 'thumbs' => true,
		], $resolver->resolve( 7 ) );
		$resolver->remember_missing( 7 );
		$this->assertSame( '', $cache->get( $key ) );
		$this->assertNull( $resolver->resolve( 7 ) );
		$resolver->forget( 7 );
		$this->assertFalse( $cache->get( $key ) );
		$this->assertNull( $resolver->resolve( 0 ) );
	}

	public function test_invalidation_is_isolated_by_media_namespace(): void {
		$cache = new HashBagOStuff();
		$storage = $this->createMock( ProfileImageStorage::class );
		$storage->expects( $this->never() )->method( 'lookup_original' );
		$ids = $this->createMock( ProfileSubjectIds::class );
		$avatar = new ProfileImageResolver( $storage, $cache, $ids, 'avatar' );
		$banner = new ProfileImageResolver( $storage, $cache, $ids, 'banner' );
		$avatar->remember( 7, 'png', '1', '', false );
		$banner->remember( 7, 'webp', '2', '', false );
		$avatar->forget( 7 );
		$this->assertFalse( $cache->get( $cache->makeGlobalKey( 'integratedprofiles', 'avatar', '4', '7' ) ) );
		$this->assertSame( 'webp', $banner->resolve( 7 )['ext'] );
	}

	/** @dataProvider provide_namespaces */
	public function test_backend_failure_is_retried_instead_of_negatively_cached( string $namespace ): void {
		$cache = new HashBagOStuff();
		$storage = $this->createMock( ProfileImageStorage::class );
		$storage->expects( $this->exactly( 2 ) )->method( 'lookup_original' )->with( 7 )
			->willReturnOnConsecutiveCalls( null, false );
		$resolver = new ProfileImageResolver(
			$storage, $cache, $this->createMock( ProfileSubjectIds::class ), $namespace
		);
		$key = $cache->makeGlobalKey( 'integratedprofiles', $namespace, '4', '7' );
		$this->assertNull( $resolver->resolve( 7 ) );
		$this->assertFalse( $cache->get( $key ) );
		$this->assertNull( $resolver->resolve( 7 ) );
		$this->assertSame( '', $cache->get( $key ) );
		$this->assertNull( $resolver->resolve( 7 ) );
	}

	/** @dataProvider provide_namespaces */
	public function test_invalid_cached_owner_falls_back_to_current_storage( string $namespace ): void {
		$cache = new HashBagOStuff();
		$key = $cache->makeGlobalKey( 'integratedprofiles', $namespace, '4', '7' );
		$cache->set( $key, '8:png:42:0123456789:1' );
		$thumbs = $this->createMock( ThumbnailStorage::class );
		$thumbs->expects( $this->once() )->method( 'set_exists' )->with( 7, '0123456789' )->willReturn( true );
		$storage = $this->createMock( ProfileImageStorage::class );
		$storage->expects( $this->once() )->method( 'lookup_original' )->with( 7 )
			->willReturn( [ 'ext' => 'webp', 'mtime' => '99', 'version' => '0123456789' ] );
		$storage->method( 'thumbs' )->willReturn( $thumbs );
		$resolver = new ProfileImageResolver(
			$storage, $cache, $this->createMock( ProfileSubjectIds::class ), $namespace
		);
		$this->assertSame( [
			'owner_id' => 7, 'ext' => 'webp', 'mtime' => '99', 'version' => '0123456789', 'thumbs' => true,
		], $resolver->resolve( 7 ) );
		$this->assertSame( '7:webp:99:0123456789:1', $cache->get( $key ) );
	}

}
