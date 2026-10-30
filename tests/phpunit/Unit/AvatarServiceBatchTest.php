<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\IntegratedProfiles\Tests\Unit;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\IntegratedProfiles\AvatarService;
use MediaWiki\Extension\IntegratedProfiles\AvatarStorage;
use MediaWiki\Extension\IntegratedProfiles\ImageUploadSanitizer;
use MediaWiki\Extension\IntegratedProfiles\ImageUploadVerifier;
use MediaWiki\Extension\IntegratedProfiles\ProfileSubjectIds;
use MediaWiki\Extension\IntegratedProfiles\ThumbnailStorage;
use MediaWiki\User\UserIdentity;
use MediaWikiUnitTestCase;
use Wikimedia\ObjectCache\BagOStuff;
use Wikimedia\ObjectCache\HashBagOStuff;

/**
 * @group IntegratedProfiles
 * @covers \MediaWiki\Extension\IntegratedProfiles\AvatarService
 */
class AvatarServiceBatchTest extends MediaWikiUnitTestCase {

	private const VERSION = '0123456789';

	public function test_batch_uses_get_multi_cache_hits(): void {
		$cache = $this->new_cache();
		$cache->set( $this->cache_key( $cache, '1' ), '1:png:100::0' );
		$cache->set( $this->cache_key( $cache, '2' ), '' );

		$storage = $this->createMock( AvatarStorage::class );
		$storage->expects( $this->never() )->method( 'find_extension_with_mtime' );
		$storage->method( 'public_url' )->willReturnCallback(
			static fn ( int $owner_id, string $ext, ?string $mtime ): string => "/images/ipavatars/avatar_{$owner_id}.{$ext}?r={$mtime}"
		);

		$service = $this->make_service( $storage, $cache );

		$info = $service->get_avatar_info_for_users( [
			$this->identity( 1, 'User1' ),
			$this->identity( 2, 'User2' ),
		] );

		$this->assertSame( [
			'User1' => [
				'avatar_url' => '/images/ipavatars/avatar_1.png?r=100',
				'has_custom_avatar' => true,
				'avatar_thumbs' => [],
			],
			'User2' => [
				'avatar_url' => '/extensions/IntegratedProfiles/resources/avatars/default.svg',
				'has_custom_avatar' => false,
				'avatar_thumbs' => [],
			],
		], $info );
	}

	public function test_storage_miss_with_thumbnails_caches_ready_flag_and_returns_urls(): void {
		$cache = $this->new_cache();
		$thumbs = $this->createMock( ThumbnailStorage::class );
		$thumbs->expects( $this->once() )->method( 'set_exists' )->with( 8, self::VERSION )->willReturn( true );
		$thumbs->method( 'public_urls' )->with( 8, self::VERSION )->willReturn( [ 32 => '/t/32.webp', 512 => '/t/512.webp' ] );

		$storage = $this->createMock( AvatarStorage::class );
		$storage->method( 'thumbs' )->willReturn( $thumbs );
		$storage->method( 'find_extension_with_mtime' )->willReturn( [ 'ext' => 'webp', 'mtime' => '7', 'version' => self::VERSION ] );
		$storage->method( 'public_url' )->willReturn( '/images/ipavatars/avatar_8.webp?r=7' );

		$info = $this->make_service( $storage, $cache )->get_avatar_info( 8 );

		$this->assertSame( [ 32 => '/t/32.webp', 512 => '/t/512.webp' ], $info['avatar_thumbs'] );
		$this->assertSame( '/images/ipavatars/avatar_8.webp?r=7', $info['avatar_url'] );
		$this->assertSame( '8:webp:7:' . self::VERSION . ':1', $cache->get( $this->cache_key( $cache, '8' ) ) );
	}

	public function test_cache_hit_with_ready_flag_skips_every_stat(): void {
		$cache = $this->new_cache();
		$cache->set( $this->cache_key( $cache, '8' ), '8:webp:7:' . self::VERSION . ':1' );

		$thumbs = $this->createMock( ThumbnailStorage::class );
		$thumbs->expects( $this->never() )->method( 'set_exists' );
		$thumbs->expects( $this->never() )->method( 'exists' );
		$thumbs->method( 'public_urls' )->willReturn( [ 128 => '/t/128.webp' ] );

		$storage = $this->createMock( AvatarStorage::class );
		$storage->expects( $this->never() )->method( 'find_extension_with_mtime' );
		$storage->method( 'thumbs' )->willReturn( $thumbs );
		$storage->method( 'public_url' )->willReturn( '/a.webp' );

		$this->assertSame( [ 128 => '/t/128.webp' ], $this->make_service( $storage, $cache )->get_avatar_info( 8 )['avatar_thumbs'] );
	}

	public function test_ready_flag_without_version_has_no_thumbnails(): void {
		$cache = $this->new_cache();
		$cache->set( $this->cache_key( $cache, '8' ), '8:webp:7::1' );

		$storage = $this->createMock( AvatarStorage::class );
		$storage->expects( $this->never() )->method( 'thumbs' );
		$storage->method( 'public_url' )->willReturn( '/a.webp' );

		$this->assertSame( [], $this->make_service( $storage, $cache )->get_avatar_info( 8 )['avatar_thumbs'] );
	}

	public function test_pre_thumbnail_cache_format_is_treated_as_a_miss(): void {
		$cache = $this->new_cache();
		$cache->set( $this->cache_key( $cache, '8' ), '8:webp:7:' . self::VERSION );

		$storage = $this->createMock( AvatarStorage::class );
		$storage->expects( $this->once() )->method( 'find_extension_with_mtime' )->willReturn( null );

		$this->assertFalse( $this->make_service( $storage, $cache )->get_avatar_info( 8 )['has_custom_avatar'] );
	}

	public function test_batch_dedupes_and_skips_anons(): void {
		$cache = $this->new_cache();
		$cache->set( $this->cache_key( $cache, '5' ), '5:webp:9::0' );

		$storage = $this->createMock( AvatarStorage::class );
		$storage->expects( $this->never() )->method( 'find_extension_with_mtime' );
		$storage->method( 'public_url' )->willReturn( '/a.webp?r=9' );

		$service = $this->make_service( $storage, $cache );

		$info = $service->get_avatar_info_for_users( [
			$this->identity( 0, 'User0' ),
			$this->identity( 5, 'User3' ),
			$this->identity( 5, 'User3' ),
		] );

		$this->assertSame( [ 'User3' ], array_keys( $info ) );
		$this->assertTrue( $info['User3']['has_custom_avatar'] );
	}

	public function test_batch_storage_miss_then_cache_write(): void {
		$cache = $this->new_cache();
		$storage = $this->createMock( AvatarStorage::class );
		$storage->method( 'find_extension_with_mtime' )->willReturn( [
			'ext' => 'jpg',
			'mtime' => '42',
		] );
		$storage->method( 'public_url' )->willReturn( '/images/ipavatars/avatar_8.jpg?r=42' );

		$service = $this->make_service( $storage, $cache );
		$info = $service->get_avatar_info_for_users( [ $this->identity( 8, 'User4' ) ] );

		$this->assertTrue( $info['User4']['has_custom_avatar'] );
		$this->assertSame(
			'8:jpg:42::0',
			$cache->get( $this->cache_key( $cache, '8' ) )
		);
	}

	public function test_batch_storage_miss_caches_thumbnail_version(): void {
		$cache = $this->new_cache();
		$storage = $this->createMock( AvatarStorage::class );
		$storage->method( 'find_extension_with_mtime' )->willReturn( [
			'ext' => 'webp',
			'mtime' => '20261029120000',
			'version' => '0123456789',
		] );
		$storage->method( 'public_url' )->willReturn( '/images/ipavatars/avatar_8.webp?r=20261029120000' );

		$this->make_service( $storage, $cache )->get_avatar_info_for_users( [ $this->identity( 8, 'User4' ) ] );

		$this->assertSame(
			'8:webp:20261029120000:0123456789:0',
			$cache->get( $this->cache_key( $cache, '8' ) )
		);
	}

	public function test_batch_ignores_cache_with_invalid_version(): void {
		$cache = $this->new_cache();
		$cache->set( $this->cache_key( $cache, '3' ), '3:png:100:../evil:1' );

		$storage = $this->createMock( AvatarStorage::class );
		$storage->expects( $this->once() )->method( 'find_extension_with_mtime' )->with( 3 )->willReturn( null );

		$info = $this->make_service( $storage, $cache )->get_avatar_info_for_users( [ $this->identity( 3, 'User3' ) ] );

		$this->assertFalse( $info['User3']['has_custom_avatar'] );
	}

	public function test_get_avatar_urls_for_users_maps_urls(): void {
		$service = $this->getMockBuilder( AvatarService::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_avatar_info_for_users' ] )
			->getMock();
		$service->method( 'get_avatar_info_for_users' )->willReturn( [
			'User1' => [
				'avatar_url' => '/a.png',
				'has_custom_avatar' => true,
			],
			'User2' => [
				'avatar_url' => '/default.svg',
				'has_custom_avatar' => false,
			],
		] );

		$this->assertSame(
			[ 'User1' => '/a.png', 'User2' => '/default.svg' ],
			$service->get_avatar_urls_for_users( [] )
		);
	}

	private function make_service( AvatarStorage $storage, BagOStuff $cache ): AvatarService {
		$subject_ids = $this->createMock( ProfileSubjectIds::class );
		$subject_ids->method( 'ids_for' )->willReturnCallback(
			static function ( UserIdentity $user ): array {
				$id = $user->getId();

				return [ 'central_id' => $id, 'local_id' => $id ];
			}
		);

		return new AvatarService(
			new ServiceOptions(
				AvatarService::CONSTRUCTOR_OPTIONS,
				[
					'IntegratedProfilesAvatarMaxBytes' => 2097152,
					'IntegratedProfilesEnableAnimatedAvatars' => true,
					'ExtensionAssetsPath' => '/extensions',
				]
			),
			$storage,
			$cache,
			$subject_ids,
			$this->createNoOpMock( ImageUploadSanitizer::class ),
			$this->createNoOpMock( ImageUploadVerifier::class )
		);
	}

	private function new_cache(): BagOStuff {
		return new HashBagOStuff();
	}

	private function cache_key( BagOStuff $cache, string $central_id ): string {
		return $cache->makeGlobalKey(
			'integratedprofiles',
			'avatar',
			AvatarService::CACHE_KEY_VERSION,
			$central_id
		);
	}

	private function identity( int $local_id, string $name ): UserIdentity {
		$user = $this->createMock( UserIdentity::class );
		$user->method( 'getId' )->willReturn( $local_id );
		$user->method( 'getName' )->willReturn( $name );
		$user->method( 'isRegistered' )->willReturn( $local_id > 0 );

		return $user;
	}

}
