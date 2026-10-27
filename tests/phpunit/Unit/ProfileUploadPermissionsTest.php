<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\IntegratedProfiles\Tests\Unit;

use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Extension\IntegratedProfiles\ProfilePermissions;
use MediaWiki\Extension\IntegratedProfiles\ProfileService;
use MediaWiki\User\User;
use MediaWikiUnitTestCase;
use ReflectionClass;

/**
 * @group IntegratedProfiles
 * @covers \MediaWiki\Extension\IntegratedProfiles\ProfileService
 * @covers \MediaWiki\Extension\IntegratedProfiles\ProfilePermissions
 */
class ProfileUploadPermissionsTest extends MediaWikiUnitTestCase {

	/** @dataProvider provide_upload_permissions */
	public function test_upload_permissions(
		array $rights, int $actor_id, bool $blocked, bool $avatar, bool $banner
	): void {
		$reflection = new ReflectionClass( ProfileService::class );
		$service = $reflection->newInstanceWithoutConstructor();
		$reflection->getProperty( 'permissions' )->setValue( $service, new ProfilePermissions() );
		$actor = $this->actor( $rights, $actor_id, $blocked );
		$subject = $this->actor( [], 7 );

		$this->assertSame( $avatar, $service->can_upload_avatar( $actor, $subject ) );
		$this->assertSame( $banner, $service->can_upload_banner( $actor, $subject ) );
		if ( $actor_id === 7 && !$blocked ) {
			$this->assertTrue( $service->can_edit( $actor, $subject ) );
			$this->assertTrue( $service->can_remove( $actor, $subject ) );
		}
	}

	public static function provide_upload_permissions(): array {
		$avatar = 'integratedprofiles-upload-avatar';
		$banner = 'integratedprofiles-upload-banner';
		$manage = 'integratedprofiles-manage';
		return [
			'owner without upload rights' => [ [], 7, false, false, false ],
			'owner avatar only' => [ [ $avatar ], 7, false, true, false ],
			'owner banner only' => [ [ $banner ], 7, false, false, true ],
			'owner both' => [ [ $avatar, $banner ], 7, false, true, true ],
			'uploads do not grant management' => [ [ $avatar, $banner ], 8, false, false, false ],
			'manager without uploads' => [ [ $manage ], 8, false, false, false ],
			'manager avatar only' => [ [ $manage, $avatar ], 8, false, true, false ],
			'manager banner only' => [ [ $manage, $banner ], 8, false, false, true ],
			'manager both' => [ [ $manage, $avatar, $banner ], 8, false, true, true ],
			'removal does not grant replacement' => [ [ 'integratedprofiles-remove', $avatar, $banner ], 8, false, false, false ],
			'animation does not grant uploads' => [ [ 'integratedprofiles-animated-avatar' ], 7, false, false, false ],
			'blocked owner' => [ [ $avatar, $banner ], 7, true, false, false ],
			'blocked manager' => [ [ $manage, $avatar, $banner ], 8, true, false, false ],
			'anonymous' => [ [ $manage, $avatar, $banner ], 0, false, false, false ],
		];
	}

	private function actor( array $rights, int $id, bool $blocked = false ): User {
		$actor = $this->createMock( User::class );
		$actor->method( 'getId' )->willReturn( $id );
		$actor->method( 'isRegistered' )->willReturn( $id > 0 );
		$actor->method( 'getBlock' )->willReturn( $blocked ? $this->createMock( DatabaseBlock::class ) : null );
		$actor->method( 'isAllowed' )->willReturnCallback( static fn ( $right ) => in_array( $right, $rights, true ) );
		return $actor;
	}
}
