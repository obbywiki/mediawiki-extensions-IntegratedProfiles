<?php

namespace MediaWiki\Extension\IntegratedProfiles\Hook;

use MediaWiki\User\UserIdentity;
use Wikimedia\Message\MessageSpecifier;

interface IntegratedProfilesVerifyImageUploadHook {

	/**
	 * Lets other extensions (like image moderation extensions) reject an avatar or banner upload before it is stored.
	 *
	 * Fired after validation and sanitization, so $path holds the exact bytes that would end up being stored.
	 * Works like core's UploadVerifyFile, set $error (or return false) to reject the upload.
	 *
	 * @param string $kind 'avatar' or 'banner'
	 * @param string $path Local path to the sanitized image (deleted after the upload finishes)
	 * @param string $mime Detected MIME type (image/png, image/jpeg, image/gif or image/webp)
	 * @param int $central_id Central user ID the image is stored under
	 * @param UserIdentity|null $user Profile owner, when known
	 * @param string|array|MessageSpecifier|null &$error Message key, [ key, ...params ] or MessageSpecifier to reject with
	 * @return bool|void False to reject (and stop further handlers)
	 */
	public function onIntegratedProfilesVerifyImageUpload( string $kind, string $path, string $mime, int $central_id, ?UserIdentity $user, &$error );

}
