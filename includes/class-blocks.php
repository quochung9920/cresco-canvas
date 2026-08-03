<?php

namespace CrescoCanvas;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Blocks {
	public function register(): void {
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	public function register_blocks(): void {
		register_block_type( CRESCO_CANVAS_PATH . 'blocks/container' );
	}
}
