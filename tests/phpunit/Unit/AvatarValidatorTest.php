<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\IntegratedProfiles\Tests\Unit;

use MediaWiki\Extension\IntegratedProfiles\AvatarValidator;
use MediaWikiUnitTestCase;

/**
 * @group IntegratedProfiles
 * @covers \MediaWiki\Extension\IntegratedProfiles\AvatarValidator
 * @covers \MediaWiki\Extension\IntegratedProfiles\ImageAnimation
 */
class AvatarValidatorTest extends MediaWikiUnitTestCase {

	public function test_legacy_animation_helper(): void {
		$path = tempnam( sys_get_temp_dir(), 'ip-animation-' );
		try {
			file_put_contents( $path, 'GIF89a' );
			$this->assertTrue( ( new AvatarValidator( 1024 ) )->is_animated( $path, 'gif' ) );
		} finally {
			unlink( $path );
		}
	}

}
