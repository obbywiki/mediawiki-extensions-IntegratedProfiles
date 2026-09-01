<?php

namespace MediaWiki\Extension\IntegratedProfiles\Api;

use MediaWiki\Api\ApiQuery;
use MediaWiki\Api\ApiQueryBase;
use MediaWiki\Extension\IntegratedProfiles\ProfileService;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Handles profile query requests.
 */
class ApiQueryIntegratedProfile extends ApiQueryBase {

	public function __construct(
		ApiQuery $query,
		string $moduleName,
		private readonly ProfileService $profile_service,
	) {
		parent::__construct( $query, $moduleName, 'ip' );
	}

	public function execute(): void {
		$params = $this->extractRequestParams();
		$username = (string)$params['user'];

		$subject = $this->profile_service->resolve_user_by_name( $username );
		if ( !$subject ) {
			$this->dieWithError( [ 'integratedprofiles-error-user-not-found', $username ], 'usernotfound' );
		}

		$payload = $this->profile_service->get_payload( $subject, $this->getUser() );
		$this->getResult()->addValue( 'query', $this->getModuleName(), $payload );
	}

	/** @inheritDoc */
	public function getAllowedParams(): array {
		return [ 'user' => [ ParamValidator::PARAM_TYPE => 'user', ParamValidator::PARAM_REQUIRED => true ] ];
	}

	/** @inheritDoc */
	protected function getExamplesMessages(): array {
		return [ 'action=query&list=integratedprofile&ipuser=Example' => 'apihelp-query+integratedprofile-example-1' ];
	}

}
