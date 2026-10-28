<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\IntegratedProfiles\Tests\Unit;

use MediaWiki\Extension\IntegratedProfiles\HookRunner;
use MediaWiki\Extension\IntegratedProfiles\ImageUploadVerifier;
use MediaWiki\Message\Message;
use MediaWikiUnitTestCase;

/**
 * @group IntegratedProfiles
 * @covers \MediaWiki\Extension\IntegratedProfiles\ImageUploadVerifier
 */
class ImageUploadVerifierTest extends MediaWikiUnitTestCase {

	public function test_passes_with_no_handlers(): void {
		$this->assertStatusGood( $this->make( null )->verify( 'banner', '/tmp/x.webp', 'webp', 7, null ) );
	}

	public function test_passes_mime_for_ext(): void {
		$seen = null;
		$verifier = $this->make( static function ( $kind, $path, $mime ) use ( &$seen ) {
			$seen = [ $kind, $mime ];
		} );

		$this->assertStatusGood( $verifier->verify( 'banner', '/tmp/x.jpg', 'jpg', 7, null ) );
		$this->assertSame( [ 'banner', 'image/jpeg' ], $seen );
	}

	public static function provide_rejections(): iterable {
		yield 'string key' => [ 'some-error', false, 'some-error' ];
		yield 'key with params' => [ [ 'some-error', 'p1' ], null, 'some-error' ];
		yield 'false without error' => [ null, false, ImageUploadVerifier::ERROR ];
		yield 'false with empty array' => [ [], false, ImageUploadVerifier::ERROR ];
		yield 'unusable error' => [ 42, null, ImageUploadVerifier::ERROR ];
	}

	/**
	 * @dataProvider provide_rejections
	 */
	public function test_rejections( mixed $set_error, ?bool $return, string $expected ): void {
		$verifier = $this->make( static function ( $kind, $path, $mime, $central_id, $user, &$error ) use ( $set_error, $return ) {
			$error = $set_error;
			return $return;
		} );

		$this->assertStatusError( $expected, $verifier->verify( 'avatar', '/tmp/x.png', 'png', 7, null ) );
	}

	public function test_message_specifier_error(): void {
		$verifier = $this->make( static function ( $kind, $path, $mime, $central_id, $user, &$error ) {
			$error = new Message( 'some-error', [ 'p1' ] );
		} );

		$this->assertStatusError( 'some-error', $verifier->verify( 'avatar', '/tmp/x.png', 'png', 7, null ) );
	}

	private function make( ?callable $handler ): ImageUploadVerifier {
		$hooks = $handler ? [ 'IntegratedProfilesVerifyImageUpload' => $handler ] : [];

		return new ImageUploadVerifier( new HookRunner( $this->createHookContainer( $hooks ) ) );
	}

}
