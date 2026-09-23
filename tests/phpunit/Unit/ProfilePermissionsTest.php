<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\IntegratedProfiles\Tests\Unit;

use MediaWiki\Extension\IntegratedProfiles\ProfileFields;
use MediaWiki\Extension\IntegratedProfiles\ProfilePermissions;
use MediaWiki\User\UserIdentity;
use MediaWikiUnitTestCase;

/**
 * @group IntegratedProfiles
 * @covers \MediaWiki\Extension\IntegratedProfiles\ProfilePermissions
 */
class ProfilePermissionsTest extends MediaWikiUnitTestCase {

	private ProfilePermissions $permissions;

	protected function setUp(): void {
		parent::setUp();
		$this->permissions = new ProfilePermissions();
	}

	public function test_owner_can_edit(): void {
		$owner = $this->user( 7, true );
		$this->assertTrue(
			$this->permissions->can_edit( $owner, 7, false, false )
		);
	}

	public function test_other_user_cannot_edit(): void {
		$other = $this->user( 8, true );
		$this->assertFalse(
			$this->permissions->can_edit( $other, 7, false, false )
		);
	}

	public function test_manager_can_edit_other_profile(): void {
		$manager = $this->user( 8, true );
		$this->assertTrue(
			$this->permissions->can_edit( $manager, 7, false, true )
		);
	}

	public function test_blocked_owner_cannot_edit(): void {
		$owner = $this->user( 7, true );
		$this->assertFalse(
			$this->permissions->can_edit( $owner, 7, true, false )
		);
	}

	public function test_blocked_manager_cannot_edit(): void {
		$manager = $this->user( 8, true );
		$this->assertFalse(
			$this->permissions->can_edit( $manager, 7, true, true )
		);
	}

	public function test_anonymous_cannot_edit(): void {
		$anon = $this->user( 0, false );
		$this->assertFalse(
			$this->permissions->can_edit( $anon, 7, false, false )
		);
	}

	public function test_public_visibility_allows_everyone(): void {
		$anon = $this->user( 0, false );
		$other = $this->user( 8, true );
		$this->assertTrue(
			$this->permissions->can_view_details(
				$anon,
				7,
				false,
				ProfileFields::VISIBILITY_PUBLIC
			)
		);
		$this->assertTrue(
			$this->permissions->can_view_details(
				$other,
				7,
				false,
				ProfileFields::VISIBILITY_PUBLIC
			)
		);
	}

	public function test_users_visibility_requires_login(): void {
		$anon = $this->user( 0, false );
		$other = $this->user( 8, true );
		$this->assertFalse(
			$this->permissions->can_view_details(
				$anon,
				7,
				false,
				ProfileFields::VISIBILITY_USERS
			)
		);
		$this->assertTrue(
			$this->permissions->can_view_details(
				$other,
				7,
				false,
				ProfileFields::VISIBILITY_USERS
			)
		);
	}

	public function test_private_visibility_owner_and_manager_only(): void {
		$owner = $this->user( 7, true );
		$other = $this->user( 8, true );
		$manager = $this->user( 9, true );
		$anon = $this->user( 0, false );

		$this->assertTrue(
			$this->permissions->can_view_details(
				$owner,
				7,
				false,
				ProfileFields::VISIBILITY_PRIVATE
			)
		);
		$this->assertFalse(
			$this->permissions->can_view_details(
				$other,
				7,
				false,
				ProfileFields::VISIBILITY_PRIVATE
			)
		);
		$this->assertTrue(
			$this->permissions->can_view_details(
				$manager,
				7,
				true,
				ProfileFields::VISIBILITY_PRIVATE
			)
		);
		$this->assertFalse(
			$this->permissions->can_view_details(
				$anon,
				7,
				false,
				ProfileFields::VISIBILITY_PRIVATE
			)
		);
	}

	private function user( int $id, bool $registered ): UserIdentity {
		$user = $this->createMock( UserIdentity::class );
		$user->method( 'getId' )->willReturn( $id );
		$user->method( 'getName' )->willReturn( 'User' . $id );
		$user->method( 'isRegistered' )->willReturn( $registered );

		return $user;
	}

}
