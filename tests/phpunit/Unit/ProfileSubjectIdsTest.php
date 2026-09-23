<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\IntegratedProfiles\Tests\Unit;

use MediaWiki\Extension\IntegratedProfiles\ProfileSubjectIds;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\UserIdentity;
use MediaWikiUnitTestCase;

/**
 * @group IntegratedProfiles
 * @covers \MediaWiki\Extension\IntegratedProfiles\ProfileSubjectIds
 */
class ProfileSubjectIdsTest extends MediaWikiUnitTestCase {

	public function test_central_id_prefers_lookup_value(): void {
		$ids = new ProfileSubjectIds( $this->lookup_returning( 9001 ) );
		$this->assertSame( 9001, $ids->central_id_for( $this->identity( 42 ) ) );
		$this->assertSame( 42, $ids->local_id_for( $this->identity( 42 ) ) );
		$this->assertSame(
			[ 'central_id' => 9001, 'local_id' => 42 ],
			$ids->ids_for( $this->identity( 42 ) )
		);
	}

	public function test_central_id_prefers_local_user_over_name(): void {
		$ids = new ProfileSubjectIds( $this->lookup_returning( 7, 9001 ) );
		$this->assertSame( 7, $ids->central_id_for( $this->identity( 42 ) ) );
	}

	public function test_central_id_falls_back_to_name_when_local_unattached(): void {
		$ids = new ProfileSubjectIds( $this->lookup_returning( 0, 9001 ) );
		$this->assertSame( 9001, $ids->central_id_for( $this->identity( 42 ) ) );
		$this->assertSame(
			[ 'central_id' => 9001, 'local_id' => 42 ],
			$ids->ids_for( $this->identity( 42 ) )
		);
	}

	public function test_central_id_falls_back_to_local_when_lookup_zero(): void {
		$ids = new ProfileSubjectIds( $this->lookup_returning( 0 ) );
		$this->assertSame( 42, $ids->central_id_for( $this->identity( 42 ) ) );
	}

	public function test_central_id_zero_for_anon(): void {
		$ids = new ProfileSubjectIds( $this->lookup_returning( 99 ) );
		$this->assertSame( 0, $ids->central_id_for( $this->identity( 0 ) ) );
	}

	private function identity( int $local_id ): UserIdentity {
		$user = $this->createMock( UserIdentity::class );
		$user->method( 'getId' )->willReturn( $local_id );
		$user->method( 'getName' )->willReturn( 'User1' );
		$user->method( 'isRegistered' )->willReturn( $local_id > 0 );

		return $user;
	}

	private function lookup_returning( int $from_local, int $from_name = 0 ): CentralIdLookup {
		$lookup = $this->createMock( CentralIdLookup::class );
		$lookup->method( 'centralIdFromLocalUser' )->willReturn( $from_local );
		$lookup->method( 'centralIdFromName' )->willReturn( $from_name );

		return $lookup;
	}

}
