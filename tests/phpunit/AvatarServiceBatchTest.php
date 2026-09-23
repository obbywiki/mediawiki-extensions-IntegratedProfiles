<?php

namespace MediaWiki\Extension\IntegratedProfiles\Tests;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\IntegratedProfiles\AvatarService;
use MediaWiki\Extension\IntegratedProfiles\AvatarStorage;
use MediaWiki\Extension\IntegratedProfiles\ProfileSubjectIds;
use MediaWiki\User\UserIdentity;
use PHPUnit\Framework\TestCase;
use Wikimedia\ObjectCache\BagOStuff;

class AvatarServiceBatchTest extends TestCase {

	public function test_batch_uses_get_multi_cache_hits(): void {
		$cache = $this->new_cache();
		$cache->set( $cache->makeGlobalKey( 'integratedprofiles', 'avatar', '1' ), '1:png:100' );
		$cache->set( $cache->makeGlobalKey( 'integratedprofiles', 'avatar', '2' ), '' );

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
			],
			'User2' => [
				'avatar_url' => '/extensions/IntegratedProfiles/resources/avatars/default.svg',
				'has_custom_avatar' => false,
			],
		], $info );
	}

	public function test_batch_dedupes_and_skips_anons(): void {
		$cache = $this->new_cache();
		$cache->set( $cache->makeGlobalKey( 'integratedprofiles', 'avatar', '5' ), '5:webp:9' );

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
			'8:jpg:42',
			$cache->get( $cache->makeGlobalKey( 'integratedprofiles', 'avatar', '8' ) )
		);
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
			$subject_ids
		);
	}

	private function new_cache(): BagOStuff {
		if ( class_exists( \Wikimedia\ObjectCache\HashBagOStuff::class ) ) {
			return new \Wikimedia\ObjectCache\HashBagOStuff( [] );
		}

		return new BagOStuff();
	}

	private function identity( int $local_id, string $name ): UserIdentity {
		$user = $this->createMock( UserIdentity::class );
		$user->method( 'getId' )->willReturn( $local_id );
		$user->method( 'getName' )->willReturn( $name );
		$user->method( 'isRegistered' )->willReturn( $local_id > 0 );

		return $user;
	}

}
