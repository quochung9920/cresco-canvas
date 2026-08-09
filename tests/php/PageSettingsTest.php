<?php
/**
 * Page Settings tests.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\Page\PageSettings;
use PHPUnit\Framework\TestCase;

final class PageSettingsTest extends TestCase {
	public function test_defaults_make_new_cresco_pages_full_width_without_theme_title(): void {
		$settings = PageSettings::defaults();
		self::assertSame( 2, $settings['version'] );
		self::assertSame( 'full-width', $settings['layout'] );
		self::assertSame( 'hide', $settings['pageTitle'] );
		self::assertSame( 'inherit', $settings['header'] );
		self::assertSame( 'inherit', $settings['footer'] );
		self::assertSame( 'viewport', $settings['contentRoot'] );
		self::assertSame( 'px', $settings['bodyStyle']['margin']['unit'] );
		self::assertTrue( $settings['bodyStyle']['margin']['linked'] );
		self::assertSame( 'classic', $settings['bodyStyle']['background']['type'] );
		self::assertSame( '', $settings['customCSS'] );
		self::assertFalse( $settings['scrollSnap']['enabled'] );
	}

	public function test_invalid_values_fall_back_to_safe_defaults(): void {
		$settings = PageSettings::sanitize(
			array(
				'layout'      => 'javascript:bad',
				'pageTitle'   => 'maybe',
				'header'      => 'remove-everything',
				'footer'      => 'remove-everything',
				'contentRoot' => '100vw',
				'bodyStyle'   => array(
					'margin'     => array( 'unit' => 'javascript' ),
					'background' => array( 'type' => 'script', 'color' => 'expression(alert(1))' ),
				),
				'scrollSnap'  => array( 'axis' => 'diagonal', 'strictness' => 'violent' ),
			)
		);
		$defaults = PageSettings::defaults();
		self::assertSame( $defaults['layout'], $settings['layout'] );
		self::assertSame( $defaults['pageTitle'], $settings['pageTitle'] );
		self::assertSame( $defaults['header'], $settings['header'] );
		self::assertSame( $defaults['footer'], $settings['footer'] );
		self::assertSame( $defaults['contentRoot'], $settings['contentRoot'] );
		self::assertSame( 'px', $settings['bodyStyle']['margin']['unit'] );
		self::assertSame( 'classic', $settings['bodyStyle']['background']['type'] );
		self::assertSame( '', $settings['bodyStyle']['background']['color'] );
		self::assertSame( 'y', $settings['scrollSnap']['axis'] );
		self::assertSame( 'proximity', $settings['scrollSnap']['strictness'] );
	}

	public function test_body_style_sanitizes_responsive_spacing_background_and_scroll_snap(): void {
		$settings = PageSettings::sanitize(
			array(
				'bodyStyle' => array(
					'margin' => array(
						'unit'    => '%',
						'linked'  => false,
						'desktop' => array( 'top' => '-12.5', 'right' => '4', 'bottom' => '8', 'left' => '4' ),
						'tablet'  => array( 'top' => '6.25' ),
					),
					'padding' => array(
						'unit'    => 'rem',
						'desktop' => array( 'top' => '-2', 'right' => '2', 'bottom' => '2', 'left' => '2' ),
					),
					'background' => array(
						'type'     => 'gradient',
						'gradient' => array( 'color1' => '#112233', 'color2' => '#abcdef', 'angle' => 999 ),
					),
				),
				'scrollSnap' => array(
					'enabled'    => true,
					'axis'       => 'both',
					'strictness' => 'mandatory',
					'align'      => 'center',
					'stop'       => 'always',
					'offset'     => 900,
				),
			)
		);
		self::assertSame( '%', $settings['bodyStyle']['margin']['unit'] );
		self::assertFalse( $settings['bodyStyle']['margin']['linked'] );
		self::assertSame( '-12.5', $settings['bodyStyle']['margin']['desktop']['top'] );
		self::assertSame( '6.25', $settings['bodyStyle']['margin']['tablet']['top'] );
		self::assertSame( '0', $settings['bodyStyle']['padding']['desktop']['top'] );
		self::assertSame( 'gradient', $settings['bodyStyle']['background']['type'] );
		self::assertSame( '#112233', $settings['bodyStyle']['background']['gradient']['color1'] );
		self::assertSame( 360, $settings['bodyStyle']['background']['gradient']['angle'] );
		self::assertTrue( $settings['scrollSnap']['enabled'] );
		self::assertSame( 'both', $settings['scrollSnap']['axis'] );
		self::assertSame( 'mandatory', $settings['scrollSnap']['strictness'] );
		self::assertSame( 'always', $settings['scrollSnap']['stop'] );
		self::assertSame( 500, $settings['scrollSnap']['offset'] );
	}

	public function test_page_custom_css_accepts_selector_alias_and_blocks_global_escape(): void {
		$valid = PageSettings::sanitize_page_custom_css( "selector { color: #123456; }\nselector .hero { opacity: .9; }" );
		self::assertIsString( $valid );
		self::assertStringContainsString( 'selector .hero', $valid );

		$invalid = PageSettings::sanitize_page_custom_css( 'body { display: none; }' );
		self::assertInstanceOf( WP_Error::class, $invalid );
	}

	public function test_layout_modes_apply_shell_guarantees_without_changing_container_semantics(): void {
		$full = PageSettings::effective(
			array(
				'layout'      => 'full-width',
				'pageTitle'   => 'show',
				'header'      => 'show',
				'footer'      => 'show',
				'contentRoot' => 'theme',
			)
		);
		self::assertSame( 'viewport', $full['contentRoot'] );
		self::assertSame( 'show', $full['pageTitle'] );

		$canvas = PageSettings::effective(
			array(
				'layout'      => 'canvas',
				'pageTitle'   => 'show',
				'header'      => 'show',
				'footer'      => 'show',
				'contentRoot' => 'theme',
			)
		);
		self::assertSame( 'viewport', $canvas['contentRoot'] );
		self::assertSame( 'hide', $canvas['pageTitle'] );
		self::assertSame( 'hide', $canvas['header'] );
		self::assertSame( 'hide', $canvas['footer'] );
	}

	public function test_template_part_area_prefers_native_area_and_semantic_tag(): void {
		self::assertSame( 'header', PageSettings::template_part_area( array( 'attrs' => array( 'area' => 'header', 'slug' => 'anything' ) ) ) );
		self::assertSame( 'footer', PageSettings::template_part_area( array( 'attrs' => array( 'tagName' => 'footer', 'slug' => 'anything' ) ) ) );
	}

	public function test_template_part_area_uses_slug_as_compatibility_fallback(): void {
		self::assertSame( 'header', PageSettings::template_part_area( array( 'attrs' => array( 'slug' => 'site-header-centered' ) ) ) );
		self::assertSame( 'footer', PageSettings::template_part_area( array( 'attrs' => array( 'slug' => 'footer-columns' ) ) ) );
		self::assertSame( '', PageSettings::template_part_area( array( 'attrs' => array( 'slug' => 'sidebar' ) ) ) );
	}

	public function test_ai_context_filter_ignores_array_responses_without_fatal_errors(): void {
		$service  = new PageSettings();
		$response = array( 'ok' => true );

		$wordpress_request = new class() extends WP_REST_Request {
			public function get_route() {
				return '/wp/v2/settings';
			}
		};
		self::assertSame( $response, $service->inject_ai_context( $response, null, $wordpress_request ) );

		$ai_request = new class() extends WP_REST_Request {
			public function get_route() {
				return '/cresco-canvas/v1/ai-context/42';
			}
		};
		self::assertSame( $response, $service->inject_ai_context( $response, null, $ai_request ) );
	}
}
