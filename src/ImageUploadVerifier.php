<?php

namespace MediaWiki\Extension\IntegratedProfiles;

use MediaWiki\Status\Status;
use MediaWiki\User\UserIdentity;
use Wikimedia\Message\MessageSpecifier;

/**
 * Runs the IntegratedProfilesVerifyImageUpload hook so that other extensions can run their own checks and deny uploads (intended for external moderation purposes).
 */
class ImageUploadVerifier {

	public const ERROR = 'integratedprofiles-error-image-rejected';

	private const MIME_FOR_EXT = [ 'jpg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp' ];

	public function __construct(
		private readonly HookRunner $hook_runner,
	) {
	}

	/**
	 * @param string $kind 'avatar' or 'banner'
	 * @param string $path Sanitized file about to be stored
	 * @param string $ext Validated extension (jpg, png, gif, webp)
	 * @param int $central_id Storage owner ID
	 * @param UserIdentity|null $user Profile owner, when known
	 */
	public function verify( string $kind, string $path, string $ext, int $central_id, ?UserIdentity $user ): Status {
		$error = null;
		$ok = $this->hook_runner->onIntegratedProfilesVerifyImageUpload( $kind, $path, self::MIME_FOR_EXT[$ext] ?? 'application/octet-stream', $central_id, $user, $error );

		if ( $ok !== false && ( $error === null || $error === '' || $error === [] ) ) {
			return Status::newGood();
		}

		// modelled after core's UploadVerifyFile (e.g., key, [ key, ...params ] or MessageSpecifier)
		$params = is_array( $error ) ? array_values( $error ) : [ $error ];
		$key = $params[0] ?? null;
		if ( ( is_string( $key ) && $key !== '' ) || $key instanceof MessageSpecifier ) {
			return Status::newFatal( ...$params );
		}

		return Status::newFatal( self::ERROR );
	}

}
