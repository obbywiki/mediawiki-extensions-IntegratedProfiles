<?php

namespace MediaWiki\Extension\IntegratedProfiles\Thumbnailer;

/**
 * One image library that can turn a stored original into WebP thumbnails.
 *
 * Implementations must never throw for missing libraries or bad input. They report through is_available(), supports() and render()'s return value, so the generator can fall through to the next library.
 *
 * @see \MediaWiki\Extension\IntegratedProfiles\ThumbnailGenerator
 */
interface Thumbnailer {

	public const QUALITY = 80;

	/** @var list<string> */
	public const EXTENSIONS = [ 'jpg', 'png', 'gif', 'webp' ];

	/**
	 * Short name used in IntegratedProfilesThumbnailers and logs.
	 */
	public function name(): string;

	/**
	 * Whether the library is installed and can write WebP. Must be cheap and must not throw.
	 */
	public function is_available(): bool;

	/**
	 * Whether this library can thumbnail the given source. Animated sources should only be accepted when the output stays animated.
	 */
	public function supports( string $ext, bool $animated ): bool;

	/**
	 * Writes one WebP per target, each fitting inside its box with the source aspect kept and never upscaled.
	 *
	 * @param string $src_path Local path of the original
	 * @param string $ext Original extension (jpg, png, gif, webp)
	 * @param bool $animated Whether the original is animated
	 * @param list<array{path: string, max_width: int, max_height: int}> $targets
	 * @return bool true when every target was written
	 */
	public function render( string $src_path, string $ext, bool $animated, array $targets ): bool;

	/**
	 * Human readable reason for the last failed render(), for logs. '' when there is none.
	 */
	public function last_error(): string;

}
