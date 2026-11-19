<?php

namespace MediaWiki\Extension\IntegratedProfiles;

/**
 * FileBackend storage for profile avatars (see ProfileImageStorage).
 */
class AvatarStorage extends ProfileImageStorage {

	public const SERVICE_NAME = 'IntegratedProfiles.AvatarStorage';
	public const CONTAINER = 'ipavatars';
	public const THUMB_CONTAINER = 'ipavatars-thumbs';
	protected const FILE_PREFIX = 'avatar_';
	protected const THUMB_SIZES = ThumbnailSizes::AVATAR;
	protected const SQUARE_THUMBS = true;

}
