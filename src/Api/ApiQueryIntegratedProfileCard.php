<?php

namespace MediaWiki\Extension\IntegratedProfiles\Api;

use MediaWiki\Api\ApiQuery;
use MediaWiki\Api\ApiQueryBase;
use MediaWiki\Api\ApiResult;
use MediaWiki\Extension\IntegratedProfiles\ProfileService;
use MediaWiki\User\User;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Handles hover-card query requests for one or more users.
 */
class ApiQueryIntegratedProfileCard extends ApiQueryBase {

	public const MAX_USERS = 50;

	public function __construct(
		ApiQuery $query,
		string $moduleName,
		private readonly ProfileService $profile_service,
	) {
		parent::__construct( $query, $moduleName, 'ipc' );
	}

	public function execute(): void {
		$params = $this->extractRequestParams();
		/** @var list<string> $usernames */
		$usernames = array_values( array_unique( (array)$params['user'] ) );

		/** @var list<User> $subjects */
		$subjects = [];
		$seen_names = [];
		foreach ( $usernames as $username ) {
			$username = (string)$username;
			$subject = $this->profile_service->resolve_user_by_name( $username );

			if ( !$subject ) {
				$this->addWarning( [ 'integratedprofiles-error-user-not-found', $username ] );
				continue;
			}

			$name = $subject->getName();
			if ( isset( $seen_names[$name] ) ) {
				continue;
			}

			$seen_names[$name] = true;
			$subjects[] = $subject;
		}

		$list = $this->profile_service->get_card_payloads_for_users(
			$subjects,
			$this->getUser()
		);

		ApiResult::setIndexedTagName( $list, 'card' );
		$this->getResult()->addValue( 'query', $this->getModuleName(), $list );
	}

	/** @inheritDoc */
	public function getAllowedParams(): array {
		return [
			'user' => [
				ParamValidator::PARAM_TYPE => 'user',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_ISMULTI_LIMIT1 => self::MAX_USERS,
				ParamValidator::PARAM_ISMULTI_LIMIT2 => self::MAX_USERS
			],
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages(): array {
		return [ 'action=query&list=integratedprofilecard&ipcuser=User1|User2' => 'apihelp-query+integratedprofilecard-example-1', ];
	}

}
