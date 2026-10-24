<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\IntegratedProfiles\Tests\Unit;

use MediaWiki\Cache\HtmlCacheUpdater;
use MediaWiki\Extension\IntegratedProfiles\ProfileFields;
use MediaWiki\Extension\IntegratedProfiles\ProfilePagePurger;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserIdentity;
use MediaWikiUnitTestCase;

/**
 * @group IntegratedProfiles
 * @covers \MediaWiki\Extension\IntegratedProfiles\ProfilePagePurger
 */
class ProfilePagePurgerTest extends MediaWikiUnitTestCase {

	public function test_skips_when_visibility_not_modified(): void {
		$html_cache = $this->createMock( HtmlCacheUpdater::class );
		$html_cache->expects( $this->never() )->method( 'purgeTitleUrls' );

		$this->purger( $html_cache )->maybe_purge_on_visibility_change( $this->identity(), [ ProfileFields::KEY_ABOUT => 'Hello' ], [] );
	}

	public function test_skips_when_normalized_visibility_is_unchanged(): void {
		$html_cache = $this->createMock( HtmlCacheUpdater::class );
		$html_cache->expects( $this->never() )->method( 'purgeTitleUrls' );

		$purger = $this->purger( $html_cache );
		$purger->maybe_purge_on_visibility_change( $this->identity(), [ ProfileFields::KEY_VISIBILITY => ProfileFields::VISIBILITY_PUBLIC ], [] );
		$purger->maybe_purge_on_visibility_change( $this->identity(), [ ProfileFields::KEY_VISIBILITY => 'PUBLIC' ], [ ProfileFields::KEY_VISIBILITY => ProfileFields::VISIBILITY_PUBLIC ] );
	}

	public function test_purges_user_and_talk_when_visibility_changes(): void {
		$user_title = $this->createMock( Title::class );
		$talk_title = $this->createMock( Title::class );

		$html_cache = $this->createMock( HtmlCacheUpdater::class );
		$html_cache->expects( $this->once() )
			->method( 'purgeTitleUrls' )
			->with( [ $user_title, $talk_title ], HtmlCacheUpdater::PURGE_INTENT_TXROUND_REFLECTED );

		$this->purger( $html_cache, $user_title, $talk_title )->maybe_purge_on_visibility_change( $this->identity(), [ ProfileFields::KEY_VISIBILITY => ProfileFields::VISIBILITY_PRIVATE ], [ ProfileFields::KEY_VISIBILITY => ProfileFields::VISIBILITY_PUBLIC ] );
	}

	public function test_purges_when_visibility_is_cleared_to_default(): void {
		$user_title = $this->createMock( Title::class );
		$talk_title = $this->createMock( Title::class );

		$html_cache = $this->createMock( HtmlCacheUpdater::class );
		$html_cache->expects( $this->once() )->method( 'purgeTitleUrls' );

		$this->purger( $html_cache, $user_title, $talk_title )->maybe_purge_on_visibility_change( $this->identity(), [ ProfileFields::KEY_VISIBILITY => null ], [ ProfileFields::KEY_VISIBILITY => ProfileFields::VISIBILITY_PRIVATE ] );
	}

	public function test_skips_empty_username(): void {
		$html_cache = $this->createMock( HtmlCacheUpdater::class );
		$html_cache->expects( $this->never() )->method( 'purgeTitleUrls' );

		$title_factory = $this->createMock( TitleFactory::class );
		$title_factory->expects( $this->never() )->method( 'makeTitleSafe' );

		$purger = new ProfilePagePurger( $html_cache, $title_factory );
		$purger->maybe_purge_on_visibility_change( $this->identity( '' ), [ ProfileFields::KEY_VISIBILITY => ProfileFields::VISIBILITY_PRIVATE ], [] );
	}

	private function purger( HtmlCacheUpdater $html_cache, ?Title $user_title = null, ?Title $talk_title = null ): ProfilePagePurger {
		$title_factory = $this->createMock( TitleFactory::class );
		$title_factory->method( 'makeTitleSafe' )->willReturnCallback(
			static function ( int $ns ) use ( $user_title, $talk_title ): ?Title {
				return match ( $ns ) {
					NS_USER => $user_title,
					NS_USER_TALK => $talk_title,
					default => null,
				};
			}
		);

		return new ProfilePagePurger( $html_cache, $title_factory );
	}

	private function identity( string $name = 'User1' ): UserIdentity {
		$user = $this->createMock( UserIdentity::class );
		$user->method( 'getId' )->willReturn( 7 );
		$user->method( 'getName' )->willReturn( $name );
		$user->method( 'isRegistered' )->willReturn( true );

		return $user;
	}

}
