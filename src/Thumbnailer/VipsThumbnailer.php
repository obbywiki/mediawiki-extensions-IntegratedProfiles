<?php

namespace MediaWiki\Extension\IntegratedProfiles\Thumbnailer;

use Closure;
use MediaWiki\Shell\Shell;

/**
 * libvips via the vipsthumbnail CLI (the same approach as Extension:Thumbro). Fastest and lowest memory, since it shrinks on load and streams, and it keeps animated GIF/WebP intact.
 *
 * Thank you alistair3149! Parts of this implementation were inspired by Extension:Thumbro.
 */
class VipsThumbnailer implements Thumbnailer {

	/** @var list<string> vipsthumbnail cannot read APNG frames, so animated PNGs are left to other libraries */
	private const ANIMATED_EXTENSIONS = [ 'gif', 'webp' ];

	private const MAX_OUTPUT_KB = 102400; // passed to Shell limits

	private string $error = '';

	/**
	 * @param string $command Path to vipsthumbnail
	 * @param Closure|null $runner Takes the command (list<string>) and returns [exit code, output]. Defaults to MediaWiki's Shell.
	 */
	public function __construct(
		private readonly string $command,
		private readonly ?Closure $runner = null,
	) {
	}

	public function name(): string {
		return 'vips';
	}

	public function is_available(): bool {
		if ( $this->runner !== null ) {
			return $this->command !== '';
		}

		return $this->command !== '' && !Shell::isDisabled() && is_executable( $this->command );
	}

	public function supports( string $ext, bool $animated ): bool {
		return in_array( $ext, self::EXTENSIONS, true ) && ( !$animated || in_array( $ext, self::ANIMATED_EXTENSIONS, true ) );
	}

	public function render( string $src_path, string $ext, bool $animated, array $targets ): bool {
		$this->error = '';

		foreach ( $targets as $target ) {
			[ $code, $output ] = $this->run(
				$this->command_for( $src_path, $animated, $target['path'], $target['max_width'], $target['max_height'] )
			);

			if ( $code !== 0 ) {
				$this->error = 'vipsthumbnail exited with ' . $code . ': ' . trim( $output );
				return false;
			}
		}

		return true;
	}

	public function last_error(): string {
		return $this->error;
	}

	/**
	 * vipsthumbnail <src>[n=-1] --size WxH> -o <dst>[Q=..,strip]
	 *
	 * vipsthumbnail applies EXIF orientation itself.
	 *
	 * @internal exposed for tests
	 * @return list<string>
	 */
	public function command_for( string $src_path, bool $animated, string $dst_path, int $max_width, int $max_height ): array {
		return [
			$this->command,
			$src_path . ( $animated ? '[n=-1]' : '' ),
			'--size',
			$max_width . 'x' . $max_height . '>',
			'-o',
			$dst_path . '[Q=' . self::QUALITY . ',strip]',
		];
	}

	/**
	 * @param list<string> $cmd
	 * @return array{0: int, 1: string}
	 */
	private function run( array $cmd ): array {
		if ( $this->runner !== null ) {
			return ( $this->runner )( $cmd );
		}

		$result = Shell::command( $cmd )
			// only libvips' fuzzed loaders (vips >= 8.13, ignored by older versions)
			->environment( [ 'VIPS_BLOCK_UNTRUSTED' => '1' ] )
			->limits( [ 'filesize' => self::MAX_OUTPUT_KB ] )
			->includeStderr()
			->execute();

		return [ $result->getExitCode(), $result->getStdout() ];
	}

}
