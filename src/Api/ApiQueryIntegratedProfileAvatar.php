<?php

namespace MediaWiki\Extension\IntegratedProfiles\Api;

use MediaWiki\Api\ApiQuery;
use MediaWiki\Api\ApiQueryBase;
use MediaWiki\Api\ApiResult;
use MediaWiki\Extension\IntegratedProfiles\AvatarService;
use MediaWiki\Extension\IntegratedProfiles\ProfileService;
use MediaWiki\User\User;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Handles avatar query requests for one or more users' avatar URLs.
 */
class ApiQueryIntegratedProfileAvatar extends ApiQueryBase {

	public const MAX_USERS = 50;

	public function __construct(
		ApiQuery $query,
		string $moduleName,
		private readonly ProfileService $profile_service,
		private readonly AvatarService $avatar_service,
	) {
		parent::__construct( $query, $moduleName, 'ipa' );
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

		$info_by_name = $this->avatar_service->get_avatar_info_for_users( $subjects );

		$list = [];
		foreach ( $subjects as $subject ) {
			$name = $subject->getName();
			if ( !isset( $info_by_name[$name] ) ) {
				continue;
			}

			$info = $info_by_name[$name];
			$list[] = [
				'user' => $name,
				'avatar_url' => $info['avatar_url'],
				'has_custom_avatar' => $info['has_custom_avatar']
			];
		}

		ApiResult::setIndexedTagName( $list, 'avatar' );
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
				ParamValidator::PARAM_ISMULTI_LIMIT2 => self::MAX_USERS,
			],
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages(): array {
		return [ 'action=query&list=integratedprofileavatar&ipauser=Alice|Bob' => 'apihelp-query+integratedprofileavatar-example-1' ];
	}

}
