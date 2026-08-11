<?php
/**
 * Prevent legacy Session v1 write routes from overwriting builder-owned docs.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebsiteBuilderSessionIsolation {
	public function register() {
		add_filter( 'rest_pre_dispatch', array( $this, 'block_legacy_write' ), 8, 3 );
	}

	public function block_legacy_write( $result, $server, $request ) {
		unset( $server );
		if ( null !== $result || ! $request instanceof WP_REST_Request ) return $result;
		if ( ! in_array( strtoupper( (string) $request->get_method() ), array( 'POST', 'PUT', 'PATCH' ), true ) ) return $result;
		$route = (string) $request->get_route();
		if ( ! preg_match( '#^/cresco-canvas/v1/session/(\d+)$#', $route, $match ) ) return $result;
		$post_id = absint( $match[1] ?? 0 );
		if ( ! $post_id || WebsiteBuilder::BUILDER_VERSION !== (string) get_post_meta( $post_id, WebsiteBuilder::BUILDER_META, true ) ) return $result;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return $result;

		return new WP_Error(
			'cresco_legacy_session_write_blocked',
			__( 'This document is owned by Cresco Studio. Save it through the Website Builder Session endpoint.', 'cresco-canvas' ),
			array( 'status' => 409 )
		);
	}
}
