<?php

namespace MediaWiki\Extension\IntegratedProfiles\Api;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Extension\IntegratedProfiles\ProfileService;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Handles banner upload requests.
 */
class ApiIntegratedProfileUploadBanner extends ApiBase {

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

		$subject = $this->profile_service->resolve_user_by_name( $username );
		if ( !$subject ) {
			$this->dieWithError( [ 'integratedprofiles-error-user-not-found', $username ], 'usernotfound' );
		}

		if ( !$this->profile_service->can_edit( $actor, $subject ) ) {
			$this->dieWithError( 'integratedprofiles-error-permission', 'permissiondenied' );
		}

		$upload = $this->getRequest()->getUpload( 'file' );
		if ( !$upload->exists() || $upload->getError() !== UPLOAD_ERR_OK ) {
			$this->dieWithError( 'integratedprofiles-error-banner-size', 'bannersize' );
		}

		$status = $this->profile_service->upload_banner(
			$subject,
			(string)$upload->getTempName(),
			(int)$upload->getSize()
		);

		if ( !$status->isOK() ) {
			$this->dieStatus( $status );
		}

		$this->getResult()->addValue( null, $this->getModuleName(), [ 'result' => 'success', 'profile' => $status->getValue() ] );
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
			'username' => [ ParamValidator::PARAM_TYPE => 'user' ],
			'file' => [ ParamValidator::PARAM_TYPE => 'upload', ParamValidator::PARAM_REQUIRED => true ],
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages(): array {
		return [ 'action=integratedprofileuploadbanner&file=banner.png&token=TOKEN' => 'apihelp-integratedprofileuploadbanner-example-1' ];
	}

}
