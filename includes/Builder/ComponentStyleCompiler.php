<?php
/**
 * Compile styles for component-backed nested slots and loop templates.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ComponentStyleCompiler {
	/** Return canonical CSS for every component referenced by Architecture v2 slots. */
	public static function compile( $architecture ) {
		$ids = array();
		foreach ( (array) ( $architecture['nodes'] ?? array() ) as $config ) {
			if ( ! is_array( $config ) ) continue;
			$slots = (array) ( $config['slots'] ?? array() );
			if ( ! empty( $slots['templateComponentId'] ) ) $ids[] = absint( $slots['templateComponentId'] );
			foreach ( (array) ( $slots['items'] ?? array() ) as $id ) $ids[] = absint( $id );
		}

		$css = '';
		foreach ( array_values( array_unique( array_filter( $ids ) ) ) as $component_id ) {
			if ( WebsiteBuilder::COMPONENT_TYPE !== get_post_type( $component_id ) ) continue;
			$raw = (string) get_post_meta( $component_id, WebsiteBuilder::COMPONENT_META, true );
			$node = '' !== $raw ? json_decode( $raw, true ) : null;
			if ( ! is_array( $node ) ) continue;
			$session = WebsiteBuilder::sanitize_session(
				array(
					'schema'     => 'cresco-session/v1',
					'version'    => 1,
					'documentId' => 'component-' . $component_id,
					'nodes'      => array( $node ),
				)
			);
			if ( is_wp_error( $session ) ) continue;
			$css .= WebsiteBuilderCssCompiler::compile( $session );
		}
		return $css;
	}

	private function __construct() {}
}
