<?php

namespace MediaWiki\Extension\IntegratedProfiles;

/**
 * Resolves and formats the Citizen header personal-menu avatar.
 */
class HeaderAvatar {

	public const BODY_CLASS = 'ip-has-header-avatar';

	public const CSS_VAR = '--ip-header-avatar';

	public const MODULE_STYLES = 'ext.IntegratedProfiles.headerAvatar';

	/**
	 * @param string $skin_name Current skin name (only `citizen` is supported)
	 * @param bool $is_registered Whether the viewer is a registered user
	 * @param int $central_id CentralIdLookup storage id
	 * @param AvatarService $avatar_service Avatar lookup
	 * @return string|null Public avatar URL when the header should show a photo
	 */
	public static function resolve_url( string $skin_name, bool $is_registered, int $central_id, AvatarService $avatar_service ): ?string {
		if ( $skin_name !== 'citizen' || !$is_registered || $central_id <= 0 ) {
			return null;
		}

		$info = $avatar_service->get_avatar_info( $central_id );
		if ( !$info['has_custom_avatar'] ) {
			return null;
		}

		$url = (string)$info['avatar_url'];
		return $url !== '' ? $url : null;
	}

	public static function css_url_value( string $url ): string {
		$escaped = addcslashes( $url, "\\\"\n\r\f" );
		return 'url("' . $escaped . '")';
	}

	/**
	 * @param array<string,string> &$body_attrs
	 */
	public static function apply_body_attrs( array &$body_attrs, string $avatar_url ): void {
		$fragment = self::CSS_VAR . ':' . self::css_url_value( $avatar_url ) . ';';

		$existing = isset( $body_attrs['style'] ) ? (string)$body_attrs['style'] : '';
		if ( $existing !== '' ) {
			$body_attrs['style'] = rtrim( $existing, "; \t\n\r\0\x0B" ) . ';' . $fragment;
		} else {
			$body_attrs['style'] = $fragment;
		}
	}

}
