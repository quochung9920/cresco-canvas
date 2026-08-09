<?php
/**
 * Minimal frontend shell for Page Settings: Canvas.
 *
 * @package CrescoCanvas
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'cresco-canvas-page-template' ); ?>>
<?php wp_body_open(); ?>
<main id="cresco-canvas-page" class="cresco-canvas-page-shell">
	<?php
	while ( have_posts() ) {
		the_post();
		the_content();
	}
	?>
</main>
<?php wp_footer(); ?>
</body>
</html>
