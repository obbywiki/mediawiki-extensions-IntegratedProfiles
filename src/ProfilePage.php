<?php

namespace MediaWiki\Extension\IntegratedProfiles;

use Article;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\User\User;

/**
 * Renders the IntegratedProfiles cutout above tabs.
 */
class ProfilePage extends Article {

	public function __construct(
		Title $title,
		private readonly User $subject_user,
		private readonly ProfileHandler $profile_chrome,
		private readonly HookRunner $hook_runner,
	) {
		parent::__construct( $title );
	}

	public function view() {
		$context = $this->getContext();
		$out = $context->getOutput();
		$is_talk = $this->getTitle()->getNamespace() === NS_USER_TALK;
		$user_name = $this->subject_user->getName();
		$about_page_url = Title::makeTitle( NS_USER, $user_name )->getLocalURL();

		$iptab = ProfileTabs::sanitize_tab_id(
			(string)$context->getRequest()->getVal( ProfileTabs::QUERY_PARAM, '' )
		);
		if ( $iptab === ProfileTabs::ID_CONTRIBUTIONS ) {
			$out->redirect(
				SpecialPage::getTitleFor( 'Contributions', $user_name )->getLocalURL()
			);

			return;
		}

		if ( $iptab === ProfileTabs::ID_TALK && !$is_talk ) {
			$out->redirect( Title::makeTitle( NS_USER_TALK, $user_name )->getLocalURL() );

			return;
		}

		if ( $iptab === ProfileTabs::ID_ABOUT && $is_talk ) {
			$out->redirect( $about_page_url );

			return;
		}

		$chrome = $this->profile_chrome->render_to_output(
			$context,
			$this->subject_user,
			$about_page_url,
			$is_talk ? ProfileTabs::ID_TALK : null
		);

		$active = $chrome['active'];
		$payload = $chrome['payload'];

		if ( $active !== ProfileTabs::ID_ABOUT && $active !== ProfileTabs::ID_TALK ) {
			$panel = '';
			$this->hook_runner->onIntegratedProfilesRenderTab( $active, $panel, $payload, $context );

			if ( $panel !== '' ) {
				$out->addHTML( '<div class="ip-tab-panel ip-tab-panel--' . htmlspecialchars( $active, ENT_QUOTES, 'UTF-8' ) . '">' . $panel . '</div>' );

				return;
			}
		}

		parent::view();
	}

	public function showMissingArticle() {
		// avoid overwriting GlobalUserPage
		if ( $this->try_show_global_user_page() ) {
			return;
		}

		parent::showMissingArticle();
	}

	private function try_show_global_user_page(): bool {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'GlobalUserPage' ) ) {
			return false;
		}

		$services = MediaWikiServices::getInstance();
		$manager = $services->getService( 'GlobalUserPage.GlobalUserPageManager' );

		if ( !$manager->shouldDisplayGlobalPage( $this->getTitle() ) ) {
			return false;
		}

		$global_page = new \MediaWiki\GlobalUserPage\GlobalUserPage(
			$this->getTitle(),
			$services->getConfigFactory()->makeConfig( 'globaluserpage' ),
			$services->getMainWANObjectCache(),
			$manager,
			$services->getHttpRequestFactory(),
			$services->getUrlUtils(),
			$services->getNamespaceInfo()
		);

		$global_page->setContext( $this->getContext() );
		$global_page->showMissingArticle();

		return true;
	}

}
