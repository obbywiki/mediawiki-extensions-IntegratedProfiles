<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\IntegratedProfiles\Tests\Integration;

use MediaWiki\Api\ApiUsageException;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\IntegratedProfiles\ProfileHandler;
use MediaWiki\Extension\IntegratedProfiles\ProfileService;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\Title\Title;
use MediaWiki\User\User;

/**
 * @group IntegratedProfiles
 * @group Database
 * @covers \MediaWiki\Extension\IntegratedProfiles\ProfileService
 * @covers \MediaWiki\Extension\IntegratedProfiles\ProfilePermissions
 * @covers \MediaWiki\Extension\IntegratedProfiles\ProfileHandler
 * @covers \MediaWiki\Extension\IntegratedProfiles\Api\ApiQueryIntegratedProfile
 * @covers \MediaWiki\Extension\IntegratedProfiles\Api\ApiQueryIntegratedProfilePreview
 * @covers \MediaWiki\Extension\IntegratedProfiles\Api\ApiSetIntegratedProfile
 * @covers \MediaWiki\Extension\IntegratedProfiles\Api\ApiClearIntegratedProfile
 */
class ProfilePrivacyTest extends ApiTestCase {

	private User $subject;
	private ProfileService $profile;

	protected function setUp(): void {
		parent::setUp();
		$this->setGroupPermissions( [
			'profile-manager' => [ 'integratedprofiles-manage' => true ],
			'profile-viewer' => [ 'integratedprofiles-view-private' => true ],
			'profile-remover' => [ 'integratedprofiles-remove' => true ],
			'profile-suppressor' => [ 'hideuser' => true ],
		] );
		$this->subject = $this->getMutableTestUser()->getUser();
		$options = $this->getServiceContainer()->getUserOptionsManager();
		$options->setOption( $this->subject, 'ip-visibility', 'private' );
		$options->setOption( $this->subject, 'ip-about', 'Private biography' );
		$options->saveOptions( $this->subject );
		$this->profile = $this->getServiceContainer()->get( ProfileService::SERVICE_NAME );
	}

	/** @dataProvider provide_roles */
	public function test_viewing_and_management_are_independent( array $groups, bool $view, bool $edit, bool $remove ): void {
		$viewer = $this->getMutableTestUser( $groups )->getUser();
		$this->assertSame( $view, $this->profile->can_view_details( $viewer, $this->subject ) );
		$this->assertSame( $edit, $this->profile->can_edit( $viewer, $this->subject ) );
		$this->assertSame( $edit, $this->profile->can_upload_avatar( $viewer, $this->subject ) );
		$this->assertSame( $edit, $this->profile->can_upload_banner( $viewer, $this->subject ) );
		$this->assertSame( $remove, $this->profile->can_remove( $viewer, $this->subject ) );

		[ $full ] = $this->doApiRequest( [
			'action' => 'query', 'list' => 'integratedprofile', 'ipuser' => $this->subject->getName(),
		], null, false, $viewer );
		$this->assertSame( $view ? 'Private biography' : '', $full['query']['integratedprofile']['fields']['ip-about'] );
		[ $preview ] = $this->doApiRequest( [
			'action' => 'query', 'list' => 'integratedprofilepreview', 'ippuser' => $this->subject->getName(),
		], null, false, $viewer );
		$this->assertSame( !$view, $preview['query']['integratedprofilepreview'][0]['is_private'] );
		$this->assertSame( $view ? 'Private biography' : '', $preview['query']['integratedprofilepreview'][0]['about'] );

		$context = new RequestContext();
		$context->setUser( $viewer );
		$context->setTitle( Title::makeTitle( NS_USER, $this->subject->getName() ) );
		$this->getServiceContainer()->get( ProfileHandler::SERVICE_NAME )
			->render_to_output( $context, $this->subject, '/wiki/User:Example' );
		$config = $context->getOutput()->getJsConfigVars();
		$this->assertSame( $view && ( $edit || $remove ), isset( $config['wgIntegratedProfiles'] ) );
	}

	public static function provide_roles(): array {
		return [
			'ordinary user' => [ [], false, false, false ],
			'manager alone' => [ [ 'profile-manager' ], false, false, true ],
			'viewing alone' => [ [ 'profile-viewer' ], true, false, false ],
			'manager with viewing' => [ [ 'profile-manager', 'profile-viewer' ], true, true, true ],
			'remover alone' => [ [ 'profile-remover' ], false, false, true ],
			'remover with viewing' => [ [ 'profile-remover', 'profile-viewer' ], true, false, true ],
		];
	}

	public function test_manager_cannot_change_private_profile_to_public_without_viewing_access(): void {
		$manager = $this->getMutableTestUser( [ 'profile-manager' ] )->getUser();
		try {
			$this->doApiRequestWithToken( [
				'action' => 'setintegratedprofile', 'username' => $this->subject->getName(),
				'fields' => '{"ip-visibility":"public"}',
			], null, $manager );
			$this->fail( 'A manager without viewing access must not expose a private profile.' );
		} catch ( ApiUsageException ) {
			$this->assertSame( 'private', $this->profile->get_visibility( $this->subject ) );
		}
	}

	public function test_owners_retain_private_access_and_managers_can_edit_visible_profiles(): void {
		$this->assertTrue( $this->profile->can_view_details( $this->subject, $this->subject ) );
		$this->assertTrue( $this->profile->can_edit( $this->subject, $this->subject ) );
		$manager = $this->getMutableTestUser( [ 'profile-manager' ] )->getUser();
		$options = $this->getServiceContainer()->getUserOptionsManager();
		foreach ( [ 'public', 'users' ] as $visibility ) {
			$options->setOption( $this->subject, 'ip-visibility', $visibility );
			$this->assertTrue( $this->profile->can_view_details( $manager, $this->subject ) );
			$this->assertTrue( $this->profile->can_edit( $manager, $this->subject ) );
		}
	}

	public function test_clear_only_removal_response_does_not_reveal_other_private_fields(): void {
		$remover = $this->getMutableTestUser( [ 'profile-remover' ] )->getUser();
		[ $result ] = $this->doApiRequestWithToken( [
			'action' => 'clearintegratedprofile', 'username' => $this->subject->getName(), 'fields' => 'ip-location',
		], null, $remover );
		$this->assertTrue( $result['clearintegratedprofile']['profile']['is_private'] );
		$this->assertSame( '', $result['clearintegratedprofile']['profile']['fields']['ip-about'] );
		$this->assertSame( 'Private biography', $this->profile->get_payload( $this->subject, $this->subject )['fields']['ip-about'] );
	}

	public function test_private_viewing_does_not_reveal_suppressed_accounts(): void {
		$viewer = $this->getMutableTestUser( [ 'profile-viewer', 'profile-manager' ] )->getUser();
		$hidden = $this->createMock( User::class );
		$hidden->method( 'getId' )->willReturn( $this->subject->getId() );
		$hidden->method( 'isHidden' )->willReturn( true );
		$this->assertFalse( $this->profile->can_see_subject( $viewer, $hidden ) );
		$this->assertTrue( $this->profile->can_see_subject( $this->subject, $hidden ) );
		$suppressor = $this->getMutableTestUser( [ 'profile-suppressor' ] )->getUser();
		$this->assertTrue( $this->profile->can_see_subject( $suppressor, $hidden ) );
	}
}
