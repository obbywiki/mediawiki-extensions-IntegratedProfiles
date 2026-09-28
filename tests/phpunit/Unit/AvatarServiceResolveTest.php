<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\IntegratedProfiles\Tests\Unit;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\IntegratedProfiles\AvatarService;
use MediaWiki\Extension\IntegratedProfiles\AvatarStorage;
use MediaWiki\Extension\IntegratedProfiles\ProfileSubjectIds;
use MediaWiki\User\UserIdentity;
use MediaWikiUnitTestCase;
use Wikimedia\ObjectCache\HashBagOStuff;

/**
 * @group IntegratedProfiles
 * @covers \MediaWiki\Extension\IntegratedProfiles\AvatarService
 */
class AvatarServiceResolveTest extends MediaWikiUnitTestCase {

	public function test_does_not_use_local_id_file_when_central_missing(): void {
		$storage = $this->createMock( AvatarStorage::class );
		$storage->expects( $this->once() )
			->method( 'find_extension_with_mtime' )
			->with( 1000 )
			->willReturn( null );
		$storage->expects( $this->never() )->method( 'public_url' );

		$info = $this->make_service( $storage, [ 'central_id' => 1000, 'local_id' => 42 ] )
			->get_avatar_info_for_user( $this->identity( 42, 'Alice' ) );

		$this->assertFalse( $info['has_custom_avatar'] );
		$this->assertSame(
			'/extensions/IntegratedProfiles/resources/avatars/default.svg',
			$info['avatar_url']
		);
	}

	public function test_uses_central_id_file_when_local_id_differs(): void {
		$storage = $this->createMock( AvatarStorage::class );
		$storage->method( 'find_extension_with_mtime' )->willReturnCallback(
			static function ( int $owner_id ): ?array {
				if ( $owner_id === 42 ) {
					return [ 'ext' => 'png', 'mtime' => '9' ];
				}

				return null;
			}
		);
		$storage->method( 'public_url' )->willReturnCallback(
			static fn ( int $owner_id, string $ext, ?string $mtime ): string =>
				"/images/ipavatars/avatar_{$owner_id}.{$ext}?r={$mtime}"
		);

		$info = $this->make_service( $storage, [ 'central_id' => 42, 'local_id' => 99 ] )
			->get_avatar_info_for_user( $this->identity( 99, 'Bob' ) );

		$this->assertTrue( $info['has_custom_avatar'] );
		$this->assertSame( '/images/ipavatars/avatar_42.png?r=9', $info['avatar_url'] );
	}

	public function test_ignores_cached_owner_that_does_not_match_central_id(): void {
		$cache = new HashBagOStuff();
		$cache->set(
			$cache->makeGlobalKey(
				'integratedprofiles',
				'avatar',
				AvatarService::CACHE_KEY_VERSION,
				'1000'
			),
			'42:png:1'
		);

		$storage = $this->createMock( AvatarStorage::class );
		$storage->expects( $this->once() )
			->method( 'find_extension_with_mtime' )
			->with( 1000 )
			->willReturn( null );
		$storage->expects( $this->never() )->method( 'public_url' );

		$info = $this->make_service( $storage, [ 'central_id' => 1000, 'local_id' => 42 ], $cache )
			->get_avatar_info_for_user( $this->identity( 42, 'Alice' ) );

		$this->assertFalse( $info['has_custom_avatar'] );
	}

	/**
	 * @param array{central_id: int, local_id: int} $ids
	 */
	private function make_service(
		AvatarStorage $storage,
		array $ids,
		?HashBagOStuff $cache = null
	): AvatarService {
		$subject_ids = $this->createMock( ProfileSubjectIds::class );
		$subject_ids->method( 'ids_for' )->willReturn( $ids );

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
			$cache ?? new HashBagOStuff(),
			$subject_ids
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
