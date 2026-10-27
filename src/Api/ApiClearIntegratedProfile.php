<?php

namespace MediaWiki\Extension\IntegratedProfiles\Api;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Extension\IntegratedProfiles\ProfileChangeLogger;
use MediaWiki\Extension\IntegratedProfiles\ProfileService;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Removes profile content without accepting replacement values or preferences.
 */
class ApiClearIntegratedProfile extends ApiBase {

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
		if ( !$this->profile_service->can_remove( $actor, $subject ) ) {
			$this->dieWithError( 'integratedprofiles-error-permission', 'permissiondenied' );
		}
		$status = $this->profile_service->clear_fields( $actor, $subject, $params['fields'] );
		if ( !$status->isOK() ) {
			$this->dieStatus( $status );
		}
		ProfileChangeLogger::log_if_other( $actor, $subject, ProfileChangeLogger::ACTION_CLEAR );
		$this->getResult()->addValue( null, $this->getModuleName(), [
			'result' => 'success', 'profile' => $status->getValue(),
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
			'username' => [ ParamValidator::PARAM_TYPE => 'user' ],
			'fields' => [
				// Validate the complete list in clear_fields so a mixed request fails atomically.
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages(): array {
		return [ 'action=clearintegratedprofile&username=Example&fields=ip-about&token=TOKEN'
			=> 'apihelp-clearintegratedprofile-example-1' ];
	}
}
