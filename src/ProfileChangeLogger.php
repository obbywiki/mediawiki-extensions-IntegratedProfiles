<?php

namespace MediaWiki\Extension\IntegratedProfiles;

use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;

/**
 * Writes a Special:Log notice when someone edits another user's profile.
 */
class ProfileChangeLogger {

	public const TYPE = 'integratedprofiles';

	public const ACTION_EDIT = 'edit';
	public const ACTION_AVATAR = 'avatar';
	public const ACTION_DELETE_AVATAR = 'deleteavatar';
	public const ACTION_BANNER = 'banner';
	public const ACTION_DELETE_BANNER = 'deletebanner';

	public static function log_if_other( UserIdentity $actor, UserIdentity $subject, string $action ): void {
		if ( !self::should_log( $actor, $subject ) ) {
			return;
		}

		$title = Title::makeTitleSafe( NS_USER, $subject->getName() );
		if ( !$title ) {
			return;
		}

		$entry = new ManualLogEntry( self::TYPE, $action );
		$entry->setPerformer( $actor );
		$entry->setTarget( $title );
		$log_id = $entry->insert();
		$entry->publish( $log_id );
	}

	public static function should_log( UserIdentity $actor, UserIdentity $subject ): bool {
		return $actor->isRegistered() && $actor->getId() > 0 && $actor->getId() !== $subject->getId(); // TODO: maybe check hideuser?
	}

}
