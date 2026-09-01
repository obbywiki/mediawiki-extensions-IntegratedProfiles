<?php

namespace MediaWiki\Extension\IntegratedProfiles\Api;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Extension\IntegratedProfiles\AvatarService;
use MediaWiki\Extension\IntegratedProfiles\ProfileService;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Handles avatar upload requests.
 */
class ApiIntegratedProfileUploadAvatar extends ApiBase {

	public function __construct(
		ApiMain $main,
		string $moduleName,
		private readonly ProfileService $profile_service,
		private readonly AvatarService $avatar_service,
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
			$this->dieWithError( 'integratedprofiles-error-avatar-size', 'avatarsize' );
		}

		$allow_animated = $this->profile_service->can_use_animated_avatar( $actor );
		$status = $this->avatar_service->upload_for_user(
			$subject,
			(string)$upload->getTempName(),
			(int)$upload->getSize(),
			$allow_animated
		);
		if ( !$status->isOK() ) {
			if ( $status->hasMessage( 'integratedprofiles-error-avatar-animated' ) ) {
				$this->dieWithError( 'integratedprofiles-error-avatar-animated', 'avataranimated' );
			}

			$this->dieStatus( $status );
		}

		$payload = $this->profile_service->get_payload( $subject );
		$this->getResult()->addValue( null, $this->getModuleName(), [ 'result' => 'success', 'profile' => $payload ] );
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
			'file' => [ ParamValidator::PARAM_TYPE => 'upload', ParamValidator::PARAM_REQUIRED => true ]
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages(): array {
		return [ 'action=integratedprofileuploadavatar&file=avatar.png&token=TOKEN' => 'apihelp-integratedprofileuploadavatar-example-1' ];
	}

}
