<?php

namespace MediaWiki\Extension\IntegratedProfiles\Api;

/**
 * Shared pingLimiter check for avatar/banner upload and delete API modules.
 */
trait ApiUploadRateLimitTrait {

	private function die_if_upload_rate_limited(): void {
		if ( $this->getUser()->pingLimiter( 'integratedprofiles-upload' ) ) {
			$this->dieWithError( 'apierror-ratelimited', 'ratelimited' );
		}
	}

}
