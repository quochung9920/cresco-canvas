<?php
/**
 * Canonical server-side Website Builder editor configuration.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

use CrescoCanvas\Theme\ThemeBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebsiteBuilderEditorConfig {
	private function __construct() {}

	public static function for_context( WebsiteBuilderRuntimeContext $context ) {
		$post_id  = $context->post_id();
		$is_theme = $context->is_theme_editor();
		$post     = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) return array();

		$session_path       = $is_theme ? '/cresco-canvas/v1/website-builder/theme-session/' . $post_id : '/cresco-canvas/v1/website-builder/session/' . $post_id;
		$context_path       = $is_theme ? '/cresco-canvas/v1/website-builder/theme-context/' . $post_id : '/cresco-canvas/v1/website-builder/context/' . $post_id;
		$page_settings_path = $is_theme ? '/cresco-canvas/v1/website-builder/theme-page-settings/' . $post_id : '/cresco-canvas/v1/page-settings/' . $post_id;
		$history_path       = $is_theme ? '/cresco-canvas/v1/website-builder/theme-history/' . $post_id : '/cresco-canvas/v1/history/' . $post_id;

		return array(
			'postId'               => $post_id,
			'postTitle'            => (string) $post->post_title,
			'documentType'         => $context->document_type(),
			'sessionPath'          => $session_path,
			'validatePath'         => '/cresco-canvas/v1/website-builder/session/validate',
			'contextPath'          => $context_path,
			'optionsPath'          => '/cresco-canvas/v1/website-builder/options',
			'componentsPath'       => '/cresco-canvas/v1/website-builder/components',
			'pageSettingsPath'     => $page_settings_path,
			'settingsPath'         => '/cresco-canvas/v1/settings',
			'historyPath'          => $history_path,
		
'themeTemplatesPath'   => '/cresco-canvas/v1/theme-templates',
		
'themeOptionsPath'     => '/cresco-canvas/v1/theme-builder/options',
			'previewUrl'           => self::preview_url( $context ),
			'adminPagesUrl'        => $is_theme ? admin_url( 'edit.php?post_type=' . ThemeBuilder::POST_TYPE ) : admin_url( 'edit.php?post_type=page' ),
			'widgetCatalog'        => WidgetCatalog::all(),
			'previewWidths'        => array( 'wide' => 1920, 'desktop' => 1440, 'laptop' => 1366, 'tablet' => 768, 'mobile' => 390 ),
			'canManageGlobal'      => current_user_can( 'edit_theme_options' ),
			'canManageComponents'  => current_user_can( 'edit_pages' ),
			'builderVersion'       => WebsiteBuilder::BUILDER_VERSION,
			'pluginVersion'        => CRESCO_CANVAS_VERSION,
			'runtimeIsolationMode' => $context->isolation_mode(),
		);
	}

	public static function for_post_id( $post_id ) {
		$context = WebsiteBuilderRuntimeContext::for_document( $post_id );
		return $context ? self::for_context( $context ) : array();
	}

	public static function bootstrap_paths( WebsiteBuilderRuntimeContext $context ) {
		$config = self::for_context( $context );
		return array(
			'session'        => (string) ( $config['sessionPath'] ?? '' ),
			'context'        => (string) ( $config['contextPath'] ?? '' ),
			'options'        => (string) ( $config['optionsPath'] ?? '' ),
			'components'     => (string) ( $config['componentsPath'] ?? '' ),
			'pageSettings'   => (string) ( $config['pageSettingsPath'] ?? '' ),
		
'themeTemplates' => (string) ( $config['themeTemplatesPath'] ?? '' ),
			'globalSettings' => (string) ( $config['settingsPath'] ?? '' ),
		);
	}

	private static function preview_url( WebsiteBuilderRuntimeContext $context ) {
		$post_id = $context->post_id();
		if ( ! $context->is_theme_editor() ) return get_preview_post_link( $post_id );
		$url = add_query_arg( 'cresco_theme_preview', $post_id, home_url( '/' ) );
		return wp_nonce_url( $url, 'cresco_theme_preview_' . $post_id );
	}
}
