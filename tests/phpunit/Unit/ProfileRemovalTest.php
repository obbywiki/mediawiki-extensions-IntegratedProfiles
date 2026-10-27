<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\IntegratedProfiles\Tests\Unit;

use MediaWiki\Extension\IntegratedProfiles\ProfileFields;
use MediaWiki\Extension\IntegratedProfiles\ProfilePermissions;
use MediaWiki\Extension\IntegratedProfiles\ProfileService;
use MediaWiki\Status\Status;
use MediaWiki\User\User;
use MediaWikiUnitTestCase;
use ReflectionClass;

/**
 * @group IntegratedProfiles
 * @covers \MediaWiki\Extension\IntegratedProfiles\ProfileService
 * @covers \MediaWiki\Extension\IntegratedProfiles\ProfileFields
 * @covers \MediaWiki\Extension\IntegratedProfiles\ProfilePermissions
 */
class ProfileRemovalTest extends MediaWikiUnitTestCase {

	public function test_removal_right_does_not_grant_editing_or_private_access(): void {
		$service = $this->service();
		$actor = $this->actor( [ 'integratedprofiles-remove' ] );
		$subject = $this->actor( [], 7 );
		$this->assertTrue( $service->can_remove( $actor, $subject ) );
		$this->assertFalse( $service->can_edit( $actor, $subject ) );
		$this->assertFalse( ( new ProfilePermissions() )->can_view_details( $actor, 7, false, 'private' ) );
	}

	public function test_owners_and_managers_retain_removal(): void {
		$service = $this->service();
		$owner = $this->actor( [], 7 );
		$this->assertTrue( $service->can_remove( $owner, $owner ) );
		$this->assertTrue( $service->can_remove( $this->actor( [ 'integratedprofiles-manage' ] ), $owner ) );
		$this->assertFalse( $service->can_remove( $this->actor( [] ), $owner ) );
	}

	public function test_blocked_and_anonymous_actors_cannot_remove(): void {
		$permissions = new ProfilePermissions();
		$this->assertFalse( $permissions->can_remove( $this->actor( [] ), 7, true, true ) );
		$this->assertFalse( $permissions->can_remove( $this->actor( [], 0 ), 7, false, true ) );
	}

	public function test_content_clear_accepts_names_and_generates_only_empty_values(): void {
		$this->assertSame( [ 'ip-about' => '', 'ip-website' => '' ],
			ProfileFields::removal_fields( [ 'ip-about', 'ip-website' ] ) );
	}

	public function test_preferences_unknown_keys_and_replacement_values_are_rejected(): void {
		foreach ( [ 'ip-visibility', 'ip-hide-connections', 'ip-show-pronouns', 'ip-banner', 'ip-banner-wiki', 'unknown', [ 'text' ] ] as $key ) {
			$this->assertNull( ProfileFields::removal_fields( [ 'ip-about', $key ] ) );
		}
		$this->assertNull( ProfileFields::removal_fields( [ 'ip-about' => 'Replacement text' ] ) );
	}

	public function test_disabled_social_fields_are_not_offered_for_removal(): void {
		$fields = new ProfileFields( 300, 255, [ 'website' ] );
		$this->assertContains( 'ip-about', $fields->removable_keys() );
		$this->assertContains( 'ip-website', $fields->removable_keys() );
		$this->assertNotContains( 'ip-twitter', $fields->removable_keys() );
	}

	public function test_clear_service_rechecks_permission_before_writing(): void {
		$service = $this->getMockBuilder( ProfileService::class )->disableOriginalConstructor()
			->onlyMethods( [ 'can_remove', 'save_fields' ] )->getMock();
		$service->method( 'can_remove' )->willReturn( false );
		$service->expects( $this->never() )->method( 'save_fields' );
		$this->assertFalse( $service->clear_fields( $this->actor( [] ), $this->actor( [], 7 ), [ 'ip-about' ] )->isOK() );
	}

	public function test_invalid_mixed_clear_is_rejected_before_any_write(): void {
		$service = $this->getMockBuilder( ProfileService::class )->disableOriginalConstructor()
			->onlyMethods( [ 'can_remove', 'save_fields' ] )->getMock();
		$service->method( 'can_remove' )->willReturn( true );
		$service->expects( $this->never() )->method( 'save_fields' );
		$this->assertFalse( $service->clear_fields( $this->actor( [] ), $this->actor( [], 7 ), [ 'ip-about', 'ip-visibility' ] )->isOK() );
	}

	public function test_clear_service_saves_only_empty_content_fields(): void {
		$actor = $this->actor( [ 'integratedprofiles-remove' ] );
		$subject = $this->actor( [], 7 );
		$service = $this->getMockBuilder( ProfileService::class )->disableOriginalConstructor()
			->onlyMethods( [ 'can_remove', 'save_fields' ] )->getMock();
		$service->method( 'can_remove' )->willReturn( true );
		$service->expects( $this->once() )->method( 'save_fields' )
			->with( $subject, [ 'ip-about' => '' ], $actor )->willReturn( Status::newGood() );
		$this->assertTrue( $service->clear_fields( $actor, $subject, [ 'ip-about' ] )->isOK() );
	}

	private function service(): ProfileService {
		$reflection = new ReflectionClass( ProfileService::class );
		$service = $reflection->newInstanceWithoutConstructor();
		$reflection->getProperty( 'permissions' )->setValue( $service, new ProfilePermissions() );
		return $service;
	}

	private function actor( array $rights, int $id = 8 ): User {
		$actor = $this->createMock( User::class );
		$actor->method( 'getId' )->willReturn( $id );
		$actor->method( 'isRegistered' )->willReturn( $id > 0 );
		$actor->method( 'getBlock' )->willReturn( null );
		$actor->method( 'isAllowed' )->willReturnCallback( static fn ( $right ) => in_array( $right, $rights, true ) );
		return $actor;
	}
}
