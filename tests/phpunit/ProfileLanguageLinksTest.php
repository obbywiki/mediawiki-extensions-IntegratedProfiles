<?php

namespace MediaWiki\Extension\IntegratedProfiles\Tests;

use MediaWiki\Extension\IntegratedProfiles\ProfileLanguageLinks;
use PHPUnit\Framework\TestCase;

class ProfileLanguageLinksTest extends TestCase {

	public function test_appends_configured_prefixes(): void {
		$helper = new ProfileLanguageLinks( [ 'en', 'ko', 'ja' ], 'en' );
		$links = [];
		$helper->append_for_profile_title( 'User:User1', $links );
		$this->assertSame( [ 'ko:User:User1', 'ja:User:User1' ], $links );
	}

	public function test_skips_content_language_and_local_interwikis(): void {
		$helper = new ProfileLanguageLinks(
			[ 'en', 'ko', 'zh' ],
			'ko',
			[ 'ko', 'obby-ko' ]
		);
		$links = [];
		$helper->append_for_profile_title( 'User:User2', $links );
		$this->assertSame( [ 'en:User:User2', 'zh:User:User2' ], $links );
	}

	public function test_does_not_duplicate_existing_links(): void {
		$helper = new ProfileLanguageLinks( [ 'ko', 'ja' ], 'en' );
		$links = [ 'ko:User:User3' ];
		$helper->append_for_profile_title( 'User:User3', $links );
		$this->assertSame( [ 'ko:User:User3', 'ja:User:User3' ], $links );
	}

	public function test_normalizes_messy_config_entries(): void {
		$helper = new ProfileLanguageLinks(
			[ ' KO:', 'ja', '', 'JA', 'bad:prefix', 'en' ],
			'en'
		);
		$links = [];
		$helper->append_for_profile_title( 'User_talk:User4', $links );
		$this->assertSame( [ 'ko:User_talk:User4', 'ja:User_talk:User4' ], $links );
	}

	public function test_canonical_user_title_uses_english_namespace(): void {
		$this->assertSame( 'User:User1', ProfileLanguageLinks::canonical_user_title( 'User1', false ) );
		$this->assertSame( 'User talk:User1', ProfileLanguageLinks::canonical_user_title( 'User1', true ) );
	}

	public function test_empty_config_is_noop(): void {
		$helper = new ProfileLanguageLinks( [], 'en' );
		$links = [ 'fr:User:User5' ];
		$helper->append_for_profile_title( 'User:User5', $links );
		$this->assertSame( [ 'fr:User:User5' ], $links );
	}

}
