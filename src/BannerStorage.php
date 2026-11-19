<?php

namespace MediaWiki\Extension\IntegratedProfiles;

/**
 * FileBackend storage for profile banners (see ProfileImageStorage).
 */
class BannerStorage extends ProfileImageStorage {

	public const SERVICE_NAME = 'IntegratedProfiles.BannerStorage';
	public const CONTAINER = 'ipbanners';
	public const THUMB_CONTAINER = 'ipbanners-thumbs';
	protected const FILE_PREFIX = 'banner_';
	protected const THUMB_SIZES = ThumbnailSizes::BANNER;
	protected const SQUARE_THUMBS = false;

}
