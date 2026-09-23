<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\IntegratedProfiles\Tests\Unit;

use MediaWiki\Extension\IntegratedProfiles\ProfileTabs;
use MediaWikiUnitTestCase;

/**
 * @group IntegratedProfiles
 * @covers \MediaWiki\Extension\IntegratedProfiles\ProfileTabs
 */
class ProfileTabsTest extends MediaWikiUnitTestCase {

	/** @return list<array{id: string, label: string, weight: int}> */
	private function core_tabs(): array {
		return [
			[
				'id' => ProfileTabs::ID_ABOUT,
				'label' => 'About',
				'weight' => 10,
			],
			[
				'id' => ProfileTabs::ID_TALK,
				'label' => 'Talk',
				'weight' => 15,
			],
			[
				'id' => ProfileTabs::ID_CONTRIBUTIONS,
				'label' => 'Contributions',
				'weight' => 20,
			],
		];
	}

	public function test_default_active_is_about(): void {
		$result = ProfileTabs::resolve(
			$this->core_tabs(),
			'',
			'/wiki/User:User1',
			'/wiki/Special:Contributions/User1',
			'/wiki/User_talk:User1'
		);

		$this->assertSame( ProfileTabs::ID_ABOUT, $result['active'] );
		$this->assertCount( 3, $result['tabs'] );
		$this->assertTrue( $result['tabs'][0]['active'] );
		$this->assertFalse( $result['tabs'][1]['active'] );
		$this->assertFalse( $result['tabs'][2]['active'] );
		$this->assertSame( '/wiki/User:User1', $result['tabs'][0]['url'] );
		$this->assertSame( '/wiki/User_talk:User1', $result['tabs'][1]['url'] );
		$this->assertSame(
			'/wiki/Special:Contributions/User1',
			$result['tabs'][2]['url']
		);
	}

	public function test_iptab_contributions_falls_back_when_special_url_set(): void {
		$result = ProfileTabs::resolve(
			$this->core_tabs(),
			'contributions',
			'/wiki/User:User1',
			'/wiki/Special:Contributions/User1'
		);

		$this->assertSame( ProfileTabs::ID_ABOUT, $result['active'] );
		$this->assertTrue( $result['tabs'][0]['active'] );
		$this->assertFalse( $result['tabs'][1]['active'] );
	}

	public function test_iptab_talk_falls_back_when_talk_url_set(): void {
		$result = ProfileTabs::resolve(
			$this->core_tabs(),
			'talk',
			'/wiki/User:User1',
			'/wiki/Special:Contributions/User1',
			'/wiki/User_talk:User1'
		);

		$this->assertSame( ProfileTabs::ID_ABOUT, $result['active'] );
		$this->assertSame( '/wiki/User_talk:User1', $result['tabs'][1]['url'] );
		$this->assertFalse( $result['tabs'][1]['active'] );
	}

	public function test_iptab_talk_without_dedicated_url(): void {
		$result = ProfileTabs::resolve(
			$this->core_tabs(),
			'talk',
			'/wiki/User:User1'
		);

		$this->assertSame( ProfileTabs::ID_TALK, $result['active'] );
		$this->assertTrue( $result['tabs'][1]['active'] );
		$this->assertSame(
			'/wiki/User:User1?iptab=talk',
			$result['tabs'][1]['url']
		);
	}

	public function test_forced_active_selects_contributions(): void {
		$result = ProfileTabs::resolve(
			$this->core_tabs(),
			'',
			'/wiki/User:User1',
			'/wiki/Special:Contributions/User1',
			'/wiki/User_talk:User1',
			ProfileTabs::ID_CONTRIBUTIONS
		);

		$this->assertSame( ProfileTabs::ID_CONTRIBUTIONS, $result['active'] );
		$this->assertTrue( $result['tabs'][2]['active'] );
		$this->assertFalse( $result['tabs'][0]['active'] );
	}

	public function test_legacy_iptab_contributions_without_special_url(): void {
		$result = ProfileTabs::resolve(
			$this->core_tabs(),
			'contributions',
			'/wiki/User:User1'
		);

		$this->assertSame( ProfileTabs::ID_CONTRIBUTIONS, $result['active'] );
		$this->assertSame(
			'/wiki/User:User1?iptab=contributions',
			$result['tabs'][2]['url']
		);
	}

	public function test_unknown_iptab_falls_back_to_about(): void {
		$result = ProfileTabs::resolve(
			$this->core_tabs(),
			'nope',
			'/wiki/User:User1',
			'/wiki/Special:Contributions/User1'
		);

		$this->assertSame( ProfileTabs::ID_ABOUT, $result['active'] );
	}

	public function test_invalid_iptab_falls_back_to_about(): void {
		$result = ProfileTabs::resolve(
			$this->core_tabs(),
			'../evil',
			'/wiki/User:User1',
			'/wiki/Special:Contributions/User1'
		);

		$this->assertSame( ProfileTabs::ID_ABOUT, $result['active'] );
	}

	public function test_companion_tab_appended_and_selectable(): void {
		$tabs = $this->core_tabs();
		$tabs[] = [
			'id' => 'collections',
			'label' => 'Collections',
			'weight' => 30,
		];

		$result = ProfileTabs::resolve(
			$tabs,
			'collections',
			'/wiki/User:User1',
			'/wiki/Special:Contributions/User1'
		);

		$this->assertSame( 'collections', $result['active'] );
		$this->assertCount( 4, $result['tabs'] );
		$this->assertSame( 'collections', $result['tabs'][3]['id'] );
		$this->assertSame(
			'/wiki/User:User1?iptab=collections',
			$result['tabs'][3]['url']
		);
		$this->assertTrue( $result['tabs'][3]['active'] );
	}

	public function test_weight_sort_orders_tabs(): void {
		$tabs = [
			[ 'id' => 'zeta', 'label' => 'Z', 'weight' => 50 ],
			[ 'id' => 'about', 'label' => 'About', 'weight' => 10 ],
			[ 'id' => 'alpha', 'label' => 'A', 'weight' => 50 ],
			[ 'id' => 'contributions', 'label' => 'Contributions', 'weight' => 20 ],
		];

		$normalized = ProfileTabs::normalize_and_sort( $tabs );

		$this->assertSame(
			[ 'about', 'contributions', 'alpha', 'zeta' ],
			array_column( $normalized, 'id' )
		);
	}

	public function test_tab_url_about_omits_iptab(): void {
		$this->assertSame(
			'/wiki/User:User1',
			ProfileTabs::tab_url( '/wiki/User:User1', 'about' )
		);
		$this->assertSame(
			'/wiki/User:User1',
			ProfileTabs::tab_url( '/wiki/User:User1?iptab=collections', 'about' )
		);
	}

	public function test_tab_url_non_about_includes_iptab(): void {
		$this->assertSame(
			'/wiki/User:User1?iptab=collections',
			ProfileTabs::tab_url( '/wiki/User:User1', 'collections' )
		);
	}

	public function test_duplicate_ids_last_wins(): void {
		$tabs = [
			[ 'id' => 'about', 'label' => 'About', 'weight' => 10 ],
			[ 'id' => 'about', 'label' => 'About renamed', 'weight' => 15 ],
		];

		$normalized = ProfileTabs::normalize_and_sort( $tabs );

		$this->assertCount( 1, $normalized );
		$this->assertSame( 'About renamed', $normalized[0]['label'] );
		$this->assertSame( 15, $normalized[0]['weight'] );
	}

}
