<?php
/**
 * Minimal document renderer for resolved Cresco Theme Builder templates.
 *
 * @package CrescoCanvas
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$template = isset( $GLOBALS['cresco_canvas_resolved_theme_template'] ) && $GLOBALS['cresco_canvas_resolved_theme_template'] instanceof WP_Post
	? $GLOBALS['cresco_canvas_resolved_theme_template']
	: null;

if ( ! $template ) {
	return;
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'cresco-theme-builder-document' ); ?>>
<?php wp_body_open(); ?>
<main class="cresco-theme-builder-main" id="primary">
	<?php echo do_blocks( $template->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Native block rendering escapes at block level. ?>
</main>
<?php wp_footer(); ?>
</body>
</html>
