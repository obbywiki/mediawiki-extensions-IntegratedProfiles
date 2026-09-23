<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\IntegratedProfiles\Tests\Unit;

use MediaWiki\Extension\IntegratedProfiles\AvatarService;
use MediaWiki\User\UserIdentity;
use MediaWikiUnitTestCase;

/**
 * @group IntegratedProfiles
 * @covers \MediaWiki\Extension\IntegratedProfiles\AvatarService
 */
class AvatarServiceUrlTest extends MediaWikiUnitTestCase {

	public function test_get_avatar_url_for_user_returns_info_url(): void {
		$service = $this->getMockBuilder( AvatarService::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_avatar_info_for_user' ] )
			->getMock();
		$service->method( 'get_avatar_info_for_user' )->willReturn( [
			'avatar_url' => '/images/ipavatars/avatar_7.png?r=1',
			'has_custom_avatar' => true,
		] );

		$this->assertSame(
			'/images/ipavatars/avatar_7.png?r=1',
			$service->get_avatar_url_for_user( $this->identity( 7 ) )
		);
	}

	public function test_get_avatar_url_for_user_returns_default_url(): void {
		$service = $this->getMockBuilder( AvatarService::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_avatar_info_for_user' ] )
			->getMock();
		$service->method( 'get_avatar_info_for_user' )->willReturn( [
			'avatar_url' => '/extensions/IntegratedProfiles/resources/avatars/default.svg',
			'has_custom_avatar' => false,
		] );

		$this->assertSame(
			'/extensions/IntegratedProfiles/resources/avatars/default.svg',
			$service->get_avatar_url_for_user( $this->identity( 3 ) )
		);
	}

	private function identity( int $local_id ): UserIdentity {
		$user = $this->createMock( UserIdentity::class );
		$user->method( 'getId' )->willReturn( $local_id );
		$user->method( 'getName' )->willReturn( 'User1' );
		$user->method( 'isRegistered' )->willReturn( $local_id > 0 );

		return $user;
	}

}
