# Changelog

## [0.8.0](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/compare/v0.7.0...v0.8.0) (2026-10-30)


### ⚠ BREAKING CHANGES

* rights & permissions revamp/overhaul (see docs for migration) ([#35](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/issues/35))

### Features

* allow for installs on 1.44 ([766d9c4](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/766d9c47b685da4d56b7381c017659be3fe9f12f))
* avatar/banner upload thumbnailing ([#40](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/issues/40)) ([8c0a047](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/8c0a04778af2de40f0ec921552233ce859634e02))
* **avatars:** make default avatar square ([759bf2e](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/759bf2e9b8558adb31b93035d39b2295bed1a5c7))
* **avatars:** optimize default.svg ([34e64e1](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/34e64e1780358dfb56e7ac3f882e2edcb2e7519f))
* configurable cache type ([#36](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/issues/36)) ([27ce26b](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/27ce26b8bba644733ff03a260bb5502441e84cdd))
* configurable upload base URL ([a3f1fdf](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/a3f1fdfff500d11ce7f4971b9547e0e95a97f537))
* improve edit button styling for non-citizen skins ([c244113](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/c2441137bad577b254c7b1900526cf748da34ac9))
* improve group chips ui + configurable group display order/priority ([3af900a](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/3af900a7311dd12fc173c7febca7642bf35d77d4))
* log edits and uploads to profiles when changes by other users ([89ea962](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/89ea9624ef1d304bb3aeb5faf8891907a62d1379))
* purge local cache when a user's profile visibility changes ([7ade369](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/7ade36978c2737a1d5b5713ab6870e131646d7a5))
* rights & permissions revamp/overhaul (see docs for migration) ([#35](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/issues/35)) ([042abc5](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/042abc5cb4a7ba68ad69852a03374e840ae2a5ef))
* strengthen image animation checks ([a3e1362](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/a3e1362e70d67a38d6c155517b1067608c1d9e85))
* strip metadata from uploaded avatars and banners on the server ([#39](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/issues/39)) ([80136f2](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/80136f275a1f964d81665c35db8e89fe61a40a07))
* verify image upload hook for moderation integrations ([d2ab31d](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/d2ab31d4ef77f0f8338034ab96c5c0442151dd70))


### Bug Fixes

* check for read rights before revealing featured articles ([3037703](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/3037703fa7eaf55642defe560aa0f476a799a4bf))
* class mismatches with HTMLCacheUpdater ([84d99cd](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/84d99cd8018ae0390ea789b382e893cdb92170a3))
* don't fallback masthead border radius (--ip-radius) ([940ade6](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/940ade6d9e6186f6a267c537c6407f8c7b142a99))
* **editor:** make avatar preview respect --ip-avatar-radius ([2c377fb](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/2c377fbbac4694f4e379abe62d1e48c64a1adb62))
* hide suppressed users in all surfaces ([68b1ef5](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/68b1ef54d0b0ec89fc977a358b2c56a22563a69f))
* implement rate limit defaults for uploads ([fa31175](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/fa31175c8407137c300f7386bf5ce5a97499dd00))
* prevent local UserMerge DeleteAccount events from deleting global files on wiki farms ([83dd19e](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/83dd19e43adcc92c9aedda61fa07cefaf8543f84))
* respect realname in wgHiddenPrefs ([7122563](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/71225638a263962f3909d94776bd080d91fae814))
* skip hidden user checks when viewer is the subject ([c28f2ba](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/c28f2ba8170c024f17dcd5a103be708510e6ab85))


### Performance Improvements

* check if user has connections hidden before fetching ([12c354c](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/12c354c4bcf3bfe4f80b9064655eb3577d112bd0))
* skip storage backend initialization for cached avatar/banner URLs when wgIntegratedProfilesUploadBaseUrl is configured + skip unnecessary full-file reads on JPEG uploads ([3aa3bbc](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/3aa3bbc56a5fbb370ffb62d8cd5bd3153340778f))


### Miscellaneous Chores

* **dependabot:** add 1-day cooldown to npm ([cde7091](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/cde7091e89b4012bd3fb9252e7513c9721b973af))
* **dependabot:** auto-assign to wlft ([8e72e53](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/8e72e53ecf0e86fa49d84f773b6fc8ecc0162df3))
* **dependabot:** set npm interval to daily ([b2d9cd4](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/b2d9cd41381ac4af0a04c4061e5d010ddc6870a4))
* **deps-dev:** bump eslint-plugin-vue ([#38](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/issues/38)) ([6820052](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/682005236164ff6b8a67295408b86f17d7833f34))
* **deps-dev:** bump the minor-updates group across 1 directory with 7 updates ([#34](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/issues/34)) ([cbd215e](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/cbd215eefd768248d2eac80e54604fa4cbdf6511))
* **deps-dev:** bump the patch-updates group across 1 directory with 8 updates ([#29](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/issues/29)) ([de7f349](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/de7f349ad24cf570feb5c177333c328f75a9ee00))
* **deps-dev:** bump the patch-updates group with 2 updates ([#37](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/issues/37)) ([65f3996](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/65f3996dc3dce602a496bd8ff111119aad6f4fc8))
* **deps:** bump actions/checkout from 6 to 7 ([#31](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/issues/31)) ([4eafe87](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/4eafe870a77602b373590aead739d3cd283c41aa))
* **deps:** bump actions/setup-node from 6 to 7 ([#33](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/issues/33)) ([4441963](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/444196339ce618fe17acb06207060d941796d95e))
* **deps:** bump pnpm/action-setup from 4 to 6 ([#32](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/issues/32)) ([ce95494](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/ce95494494c68c8a6ab918e18386ed8e32ceddcd))
* exclude dev-only resources from archives ([2f58924](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/2f58924d07e07d39c12608cd3efdb0553602736e))
* ignore .bak ([c8ccbff](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/c8ccbff73265a0d08aeddb05c94c72bc92817594))
* ignore core's pins and update override rules to pnpm 11+ ([11e32fd](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/11e32fd3a3b396e7ebe785d73bc8b626fb3ada38))
* include vendor/** and composer.lock in worktrees ([2b9d9c4](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/2b9d9c434b60e1a7f25f52e2709dde117ac55476))
* remove stale package-lock.json ([412f06e](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/412f06e151b1c84be6f787bc10f35a7f073b9cf5))
* setup better local testing with mwenv ([0f5eeeb](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/0f5eeeb64fc7d74c1842016d4be97ec7d541727e))
* update FUNDING ([24db8fd](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/24db8fd60a4e1c70103cd8028478410d53c83897))
* vendor scripts/* ([666e56f](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/666e56f97221a9a3bf18c42b8d873c45b70649e1))

## [0.7.0](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/compare/v0.6.0...v0.7.0) (2026-10-08)


### ⚠ BREAKING CHANGES

* rename get_card_payload to get_preview_payload and make it independent of user cards ([#26](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/issues/26))

### Features

* cropping and sizing controls for avatar and banner uploads ([#23](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/issues/23)) ([745ae2e](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/745ae2ef4ab53f62bd932ae1c552c33b0b511e8c))
* rename get_card_payload to get_preview_payload and make it independent of user cards ([#26](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/issues/26)) ([77f5300](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/77f53004b5fbb4be7edc692f58b2132a64b37f3b))


### Bug Fixes

* banner previews instead of saving on click ([#20](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/issues/20)) ([907ddae](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/907ddae49a52583bc526d9dd6849dce18fa5dcd6))
* citizen languages indicator styling error ([987591b](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/987591b0bf9971f83f315cc5223307bc06588bb6))
* **cropper:** block confirm/enter while a replacement image is being inspected ([a18f9e4](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/a18f9e4ae01ef7bbbdd7bfe75a489e46180a6bf0))
* **cropper:** invalidate pending file inspections on close/cancel ([21c2aa5](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/21c2aa54eeeb24231c85fd715a0b7f7644d03eb2))
* **cropper:** preserve crop state on window resize ([1adbd7e](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/1adbd7e434f4372dd4b1df8019231812a7b65b1f))
* **cropper:** prevent multiple touches from interfering with the dragger ([b25900b](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/b25900bf66918c4c536b9c195553b0d6dbb78a98))
* discord username regex is incorrectly strict ([#19](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/issues/19)) ([4053f2f](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/4053f2fa8487a7b37adc45fe503286cb8d5e6a0c))
* implement image density validation server-side ([aa8dae7](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/aa8dae748e0398027575a8b8f99955f7c7caa539))
* match citizen's actual mobile width breakpoint ([0867e71](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/0867e71f6bdf2133c94c978e57e0d3bb39da81ab))
* minor citizen styling issue ([6cf79e3](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/6cf79e3081540d8dc128f20b2d1fff71aa2ee33a))
* php lint error ([4837f31](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/4837f3119c969fb863ce5d7d795312b7d92407c5))
* prevent keyboard focus from leaving the modals ([b143632](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/b14363211dd23e65f498ff668cdccf618bbff023))
* prevent toolbar style overrides from creeping into non-view actions on citizen where the masthead isn't present ([7a88052](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/7a8805210e148fbc341a76c5074239cde11759ac))
* regression with how .citizen-page-aside was modified in 3.24+ ([77b692f](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/77b692f0c2ed2913d0fc112de2052625a13c19dd))
* sticky actions bar being covered by citizen's mobile toolbar ([ec12216](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/ec122166a6d28a217c39dcac9da9895bc6ea8620))
* stop falling back to local ids when a central id/provider is available ([8ccc51e](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/8ccc51edd4b2806376ff939c3df83422640b4a0f))


### Performance Improvements

* **cropper:** reject images over 16 megapixels or 16,384 pixels per side + release canvas buffers after encoding + reuse loaded previews for the  crop export ([c77edeb](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/c77edebbd800bbd67eb60b70c5086b2059c94485))


### Miscellaneous Chores

* add .node-version ([5e614ee](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/5e614ee8413438a5ca4fde77b55663ecc20d7a33))
* add .nvmrc ([1a4ff8c](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/1a4ff8c1b95505c70bed87ac10c495fe5c4f21de))
* add FloatingUI 1.0.5 as an extension suggestion ([4a05709](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/4a05709ea62cb2fce86169b649921f8c0dd8fa55))
* configure markdownlint + fix issues ([e058fea](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/e058feaee274b8d477e3d7a9faae5350a4de4db5))
* **deps-dev:** bump jsdom from 29.1.1 to 30.1.0 ([#4](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/issues/4)) ([5088b95](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/5088b9508b3d979a7357971ddf353c12fb5f4108))
* disable md,i18n lints ([b461795](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/b46179511d4de7775990d92d2c0f2f47a4b94884))
* enable i18n lint ([8cd40f3](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/8cd40f3ed5c7dbba0accfa50d8a926b728a22be0))
* enable md lint ([33835dc](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/33835dcd2b69b66d3871067cfa2822d3be4e8e5e))
* **eslint:** remove vue deps from config ([575bade](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/575bade142cce2cc5348e923c9fedc6427ffde38))
* fix md lint ([1f8ea12](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/1f8ea12fabebaccb75e9fdbf1f2413089b092006))
* fix style lints and re-enable ([f09caaa](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/f09caaa31db4377ee0fcc70558c1d389710bbe7e))
* import .markdownlint.json ([87bfbb6](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/87bfbb607da4934ec6ab65a01ebd6d579c706227))
* re-enable php lint ci ([3026d70](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/3026d70e4ae933ba6c02c6e033a44392fcdf4f5d))
* re-enable php lint ci ([dd3eb98](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/dd3eb98fd6a9d9ba47edac8c4c5d750d57d967d4))
* regen lockfiles ([2c98293](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/2c98293838cdb4a386b9bc21ef20aa7014d32ba5))
* rename lint:css script to lint:styles ([b8afdf3](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/b8afdf3268560b5da9985a115ad20d08d0bedf2d))
* update pnpm ([f8de29f](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/f8de29fc3d01dae30ef6cd01751eb6d14ffff578))
* use lint:styles in the package lint script ([cacabe5](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/cacabe5a57fbcf16a136bb2ca1ffe2e98747e9f1))

## [0.6.0](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/compare/v0.5.0...v0.6.0) (2026-09-12)


### Features

* better support for larger about/tagline prose ([bcbc270](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/bcbc270a0dce3b706d386addbc474afd3e585466))
* bump sidebar offset to match padding of the about page prose ([9ec3624](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/9ec3624073fa1b1ac0df68fa451aec98d11b09aa))
* make the editor action strip sticky ([dad988a](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/dad988a01f75d673c8460ec0308c0a740f499f52))
* revamp appearance section ([426cde2](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/426cde2905006bf33065c7679cab7dacaf3474c9))
* stateful editor actions ([fb78795](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/fb78795ba97a6145022312609ca67e628776ed3d))
* wiki-configurable custom banner presets ([#16](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/issues/16)) ([bdaebdf](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/bdaebdf1e69eec93169ff8d034f6d572eefd650d))


### Bug Fixes

* minor codex tweak ([ceaa672](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/ceaa67252b1a188be505ba74678f1ff1cb0e2bfa))


### Performance Improvements

* check profile visibility before retrieving profile data ([24c443b](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/24c443bf99959cfa9e1de78cd86be0d5bc363df8))
* fix request duplication on user/user_talk pages by caching local username requests ([052e466](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/052e4664a6505f45cd52f043569c2ca6332ff037))
* **header:** avoid triggering an id lookup when it isn't applicable anyway ([d3a1c02](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/d3a1c0272a199ed065bdb7ad0b5097e01bc5721d))
* lazy load the editor app + split masthead/editor css ([7a2e9b6](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/7a2e9b634321fbaf56eef872fcb4749137af051d))
* optimize central id lookups ([4621fc2](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/4621fc27a92f3fb3f64823410f8dd9dff1cdbece))


### Miscellaneous Chores

* add version to composer.json ([62b38f4](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/62b38f4ff6031b293d6a4403063d66c6f19f154e))
* regen package-lock.json ([1b3c428](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/1b3c42843cfc4284e6570140a38624ccb80a78fd))
* state deprecated in config descriptions ([4c33bf6](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/4c33bf6e6f6dc7da5fcb2be62dbc6745f63b5e8a))

## [0.5.0](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/compare/v0.4.0...v0.5.0) (2026-09-11)


### Features

* **cards:** add shadow to user-card__panel ([bfb0987](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/bfb09873bead7b20cc7da054562d2ba59e858957))
* **cards:** move extras above meta ([f23aa6e](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/f23aa6ef51f960e841cf76e1ec2b57f8533b4632))
* **cards:** remove box-shadow from avatar ([97a091e](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/97a091ea981e84c5e6bba08ff5d27872234155ce))
* default talk tab ([00d93a2](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/00d93a2457335e0846b1e30e652c103bb34e500e))
* improve user card previews layout ([0ca107a](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/0ca107af61b18e8382c6be60904396ce0dfaf2db))
* override old featured article globals ([ec27f4d](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/ec27f4d27db06c3bb9eb7744f4bde4185070e3f6))
* profile location option ([e3783f9](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/e3783f96010054e62697c2e023de1b2f03b15f8f))
* reorganize extra data into ip-facts + move website link into ip-facts ([77f6fb2](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/77f6fb24a7f0be0707e262ec63dab36501d6d5f9))
* support optional pronouns display ([a057e1b](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/a057e1b9bcd26db03b6eb510c8a30e70f5314639))
* tighten masthead margin ([dba559f](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/dba559ff67e947b0f8345e37f34ee9875a19e56c))


### Bug Fixes

* minor styling improvements ([b6f2d58](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/b6f2d58ad49dcfb969da36098a8ffe6f81de83af))

## [0.4.0](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/compare/v0.3.0...v0.4.0) (2026-09-10)


### Features

* improve banner upload button ([7e0f437](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/7e0f4376a30183aa4af1054054a0b78467120a9a))
* improve skin compatibility ([1b34a8a](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/1b34a8a365463d39354c26e114a9466ef9e61371))
* support mw 1.45 ([d3a6bd5](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/d3a6bd5ab6926bb7d0ad068ff4e66c4fa6095ae2))
* user card previews ([#12](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/issues/12)) ([e55b60b](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/e55b60b315d36a7224a393dbd064fa5a2ffec59e))


### Bug Fixes

* alias missing classes/interfaces for &lt;1.46 ([7be5b35](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/7be5b3532be62462275b36b671ee29b1f92813d4))
* invalid merge_strategy for wgIntegratedProfilesEnabledSocialLinks ([312b192](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/312b192a22e2b1507abaccc74a35ffbea5eb4bab))


### Miscellaneous Chores

* **deps:** bump googleapis/release-please-action from 4 to 5 ([a641ee4](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/a641ee448b58bf299f3ed0b49213f485805b417c))

## [0.3.0](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/compare/v0.2.0...v0.3.0) (2026-09-02)


### Features

* configurable social links ([d7ec603](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/d7ec603395cc483e79c1236452f7749188234bfa))
* make FEATURED_ARTICLE a non-global option ([f87d107](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/f87d107a7f720dee40a444a1ce4d9db5d8fd8062))
* temporarily disable wiki profile links ([e559848](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/e55984846facdce7366def80a66a0dc245bccc10))


### Miscellaneous Chores

* remove prerelease flag from release-please ([6fc28d9](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/6fc28d90df175c7c0156ec879f3cbd39957ee81b))

## [0.2.0](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/compare/v0.1.0...v0.2.0) (2026-09-02)


### Features

* improve social link styling on vector22 ([cbdc109](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/cbdc10911265d1e13c17e8e23a4aff9c52828fe4))


### Bug Fixes

* missing string in you label ([5f38334](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/5f38334129aa42f2211b8d5f4a9cc00c8550d2be))


### Miscellaneous Chores

* **deps-dev:** bump @vue/test-utils from 2.4.11 to 2.5.0 ([34eef80](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/34eef800377d396f2fc109c3b33f9d7c60cd0fac))
* setup dependabot ([f3f0419](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/f3f041953fb70051b3b8a85b28b742a19bfe8529))
* setup release please ([7894cd9](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/7894cd9a3dbb2e6a06a7588544be07a23a157a39))
* switch release-please to pre-release ([defc858](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/defc858082822c22b4c4082c8ec87596db4c3397))
* update release-please targets ([bc25678](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles/commit/bc25678ca29c484166620554c52c79cf14c6fd43))
