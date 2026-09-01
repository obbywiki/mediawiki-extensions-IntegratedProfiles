import type { App } from 'vue';

export type ProfileFieldsMap = {
	'ip-about': string;
	'ip-featured-article': string;
	'ip-website': string;
	'ip-twitter': string;
	'ip-github': string;
	'ip-mediawiki': string;
	'ip-miraheze': string;
	'ip-fandom': string;
	'ip-banner': string;
	'ip-hide-connections': string;
	'ip-visibility': string;
};

export type ProfileLink = {
	label: string;
	url: string;
	kind: string;
};

export type ProfileConnection = {
	provider: string;
	remote_user?: string;
	remote_username?: string;
	metadata?: Record<string, unknown>;
};

export type FeaturedArticle = {
	title: string;
	display_title: string;
	url: string;
};

export type WikiProfile = {
	kind: string;
	username: string;
	url: string;
};

export type IntegratedProfilesConfig = {
	user_name: string;
	user_id: number;
	can_edit: boolean;
	can_upload_animated_avatar?: boolean;
	fields: ProfileFieldsMap;
	links: Record<string, ProfileLink>;
	wiki_profiles?: WikiProfile[];
	avatar_url: string;
	has_custom_avatar: boolean;
	banner_url?: string;
	has_custom_banner?: boolean;
	banner_presets?: string[];
	ui: {
		color: string;
		avatar_border_radius: string;
	};
	show_manage_connections?: boolean;
	show_connection_privacy?: boolean;
	preferences_url?: string;
	connections?: ProfileConnection[];
	connection_providers?: string[];
	limits: {
		about: number;
		link: number;
		avatar_max_bytes?: number;
		banner_max_bytes?: number;
	};
};

export type ProfilePayload = {
	user_id: number;
	user_name: string;
	real_name: string;
	edit_count: number;
	registration: string | null;
	groups: string[];
	fields: ProfileFieldsMap;
	links: Record<string, ProfileLink>;
	wiki_profiles?: WikiProfile[];
	featured_article?: FeaturedArticle | null;
	avatar_url: string;
	has_custom_avatar: boolean;
	banner_url?: string;
	has_custom_banner?: boolean;
	connections: ProfileConnection[];
	ui: {
		color: string;
		avatar_border_radius: string;
	};
};

export interface IntegratedProfilesApi {
	mount_editor: ( mount_root: HTMLElement ) => App | null;
	mount_avatar_modal: (
		mount_root: HTMLElement,
		return_focus?: HTMLElement | null
	) => App | null;
	open_avatar_modal: ( return_focus?: HTMLElement | null ) => App | null;
	mount_banner_modal: (
		mount_root: HTMLElement,
		return_focus?: HTMLElement | null
	) => App | null;
	open_banner_modal: ( return_focus?: HTMLElement | null ) => App | null;
}

declare global {
	interface MediaWikiConfigMap {
		wgIntegratedProfiles?: IntegratedProfilesConfig;
	}

	namespace mw {
		var IntegratedProfiles: IntegratedProfilesApi | undefined;
	}
}

export {};
