<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\IntegratedProfiles\Tests\Unit;

use MediaWiki\Extension\IntegratedProfiles\NewAuthBridge;
use MediaWiki\User\UserIdentity;
use MediaWikiUnitTestCase;
use RuntimeException;

/**
 * @group IntegratedProfiles
 * @covers \MediaWiki\Extension\IntegratedProfiles\NewAuthBridge
 */
class NewAuthBridgeTest extends MediaWikiUnitTestCase {

	private function identity(): UserIdentity {
		$user = $this->createMock( UserIdentity::class );
		$user->method( 'getId' )->willReturn( 7 );
		$user->method( 'getName' )->willReturn( 'User1' );
		$user->method( 'isRegistered' )->willReturn( true );

		return $user;
	}

	public function test_filter_keeps_discord_and_roblox_drops_google(): void {
		$filtered = NewAuthBridge::filter_for_profile( [
			[
				'provider' => 'Discord',
				'remote_user' => '111',
				'remote_username' => 'user1',
				'metadata' => [ 'linked_at' => '1' ],
			],
			[
				'provider' => 'google',
				'remote_user' => 'g-1',
				'remote_username' => 'user1@gmail.com',
				'metadata' => [],
			],
			[
				'provider' => 'roblox',
				'remote_user' => '222',
				'remote_username' => 'User2',
				'metadata' => [],
			],
			[
				'provider' => 'unknown',
				'remote_user' => 'x',
				'remote_username' => 'x',
				'metadata' => [],
			],
			'not-an-array',
		] );

		$this->assertCount( 2, $filtered );
		$this->assertSame( 'discord', $filtered[0]['provider'] );
		$this->assertSame( 'user1', $filtered[0]['remote_username'] );
		$this->assertSame( 'roblox', $filtered[1]['provider'] );
		$this->assertSame( 'User2', $filtered[1]['remote_username'] );
	}

	public function test_filter_skips_empty_identity(): void {
		$filtered = NewAuthBridge::filter_for_profile( [
			[
				'provider' => 'discord',
				'remote_user' => '',
				'remote_username' => '',
				'metadata' => [],
			],
		] );
		$this->assertSame( [], $filtered );
	}

	public function test_flag_off_returns_empty(): void {
		$bridge = new NewAuthBridge(
			false,
			null,
			null,
			static fn (): bool => true,
			static fn (): array => [
				[
					'provider' => 'discord',
					'remote_user' => '1',
					'remote_username' => 'user1',
					'metadata' => [],
				],
			]
		);

		$this->assertSame( [], $bridge->get_links_for_user( $this->identity() ) );
	}

	public function test_newauth_unloaded_returns_empty(): void {
		$bridge = new NewAuthBridge(
			true,
			null,
			null,
			static fn (): bool => false,
			static fn (): array => [
				[
					'provider' => 'discord',
					'remote_user' => '1',
					'remote_username' => 'user1',
					'metadata' => [],
				],
			]
		);

		$this->assertSame( [], $bridge->get_links_for_user( $this->identity() ) );
	}

	public function test_fetch_success_filters_google(): void {
		$bridge = new NewAuthBridge(
			true,
			null,
			null,
			static fn (): bool => true,
			static fn (): array => [
				[
					'provider' => 'google',
					'remote_user' => 'g',
					'remote_username' => 'g@x.com',
					'metadata' => [],
				],
				[
					'provider' => 'discord',
					'remote_user' => '1',
					'remote_username' => 'user1',
					'metadata' => [],
				],
			]
		);

		$links = $bridge->get_links_for_user( $this->identity() );
		$this->assertCount( 1, $links );
		$this->assertSame( 'discord', $links[0]['provider'] );
	}

	public function test_fetch_exception_returns_empty(): void {
		$bridge = new NewAuthBridge(
			true,
			null,
			null,
			static fn (): bool => true,
			static function (): array {
				throw new RuntimeException( 'newauth boom' );
			}
		);

		$this->assertSame( [], $bridge->get_links_for_user( $this->identity() ) );
	}

	public function test_fetch_is_memoized_per_user(): void {
		$calls = 0;
		$bridge = new NewAuthBridge(
			true,
			null,
			null,
			static fn (): bool => true,
			static function () use ( &$calls ): array {
				$calls++;
				return [
					[
						'provider' => 'discord',
						'remote_user' => '1',
						'remote_username' => 'user1',
						'metadata' => [],
					],
				];
			}
		);

		$user = $this->identity();
		$this->assertCount( 1, $bridge->get_links_for_user( $user ) );
		$this->assertCount( 1, $bridge->get_links_for_user( $user ) );
		$this->assertSame( 1, $calls );
	}

}
