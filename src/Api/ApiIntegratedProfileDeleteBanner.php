<?php

namespace MediaWiki\Extension\IntegratedProfiles\Api;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Extension\IntegratedProfiles\ProfileChangeLogger;
use MediaWiki\Extension\IntegratedProfiles\ProfileService;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Handles banner deletion requests.
 */
class ApiIntegratedProfileDeleteBanner extends ApiBase {
	use ApiUploadRateLimitTrait;

	public function __construct(
		ApiMain $main,
		string $moduleName,
		private readonly ProfileService $profile_service,
	) {
		parent::__construct( $main, $moduleName );
	}

	public function execute(): void {
		$params = $this->extractRequestParams();
		$actor = $this->getUser();

		if ( !$actor->isRegistered() ) {
			$this->dieWithError( 'apierror-mustbeloggedin', 'notloggedin' );
		}

		$username = $params['username'] !== null && $params['username'] !== '' ? (string)$params['username'] : $actor->getName();

		$subject = $this->profile_service->resolve_visible_user_by_name( $username, $actor );
		if ( !$subject ) {
			$this->dieWithError( [ 'integratedprofiles-error-user-not-found', $username ], 'usernotfound' );
		}

		if ( !$this->profile_service->can_edit( $actor, $subject ) ) {
			$this->dieWithError( 'integratedprofiles-error-permission', 'permissiondenied' );
		}

		$this->die_if_upload_rate_limited();

		$status = $this->profile_service->delete_banner( $subject, $actor );
		if ( !$status->isOK() ) {
			$this->dieStatus( $status );
		}

		ProfileChangeLogger::log_if_other( $actor, $subject, ProfileChangeLogger::ACTION_DELETE_BANNER );

		$this->getResult()->addValue( null, $this->getModuleName(), [
			'result' => 'success',
			'profile' => $status->getValue(),
		] );
	}

	/** @inheritDoc */
	public function mustBePosted(): bool {
		return true;
	}

	/** @inheritDoc */
	public function isWriteMode(): bool {
		return true;
	}

	/** @inheritDoc */
	public function needsToken(): string {
		return 'csrf';
	}

	/** @inheritDoc */
	protected function getAllowedParams(): array {
		return [
			'username' => [
				ParamValidator::PARAM_TYPE => 'user',
			],
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages(): array {
		return [ 'action=integratedprofiledeletebanner&token=TOKEN' => 'apihelp-integratedprofiledeletebanner-example-1' ];
	}

}
