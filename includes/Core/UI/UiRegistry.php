<?php
/**
 * Stable editor extension points. Feature modules register into these slots
 * instead of manipulating the Builder shell directly.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Core\UI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class UiRegistry {
	/** Return the public shell contract consumed by the editor runtime. */
	public static function manifest() {
		$manifest = array(
			'schema' => 'cresco-ui-registry/v1',
			'zones'  => array( 'topbar', 'activity-rail', 'context-panel', 'canvas', 'inspector', 'statusbar' ),
			'activities' => array(
				array( 'id' => 'add', 'label' => 'Add', 'legacyLabels' => array( 'Add' ) ),
				array( 'id' => 'navigator', 'label' => 'Navigator', 'legacyLabels' => array( 'Structure', 'Navigator' ) ),
				array( 'id' => 'components', 'label' => 'Components', 'legacyLabels' => array( 'Components' ) ),
				array( 'id' => 'data', 'label' => 'Data', 'legacyLabels' => array( 'Dynamic', 'Data' ) ),
				array( 'id' => 'site', 'label' => 'Site', 'legacyLabels' => array( 'Theme', 'Site' ) ),
				array( 'id' => 'ai', 'label' => 'AI', 'legacyLabels' => array( 'AI' ) ),
				array( 'id' => 'settings', 'label' => 'Settings', 'legacyLabels' => array( 'Settings' ) ),
			),
			'inspectorTabs' => array(
				array( 'id' => 'content', 'label' => 'Content' ),
				array( 'id' => 'layout', 'label' => 'Layout' ),
				array( 'id' => 'style', 'label' => 'Style' ),
				array( 'id' => 'advanced', 'label' => 'Advanced' ),
			),
			'commandGroups' => array( 'insert', 'edit', 'ai', 'export', 'view', 'site', 'diagnostics' ),
			'extensionPoints' => array(
				'activity.register',
				'panel.register',
				'inspector.registerSection',
				'contextMenu.register',
				'command.register',
				'diagnostics.register',
			),
		);
		return apply_filters( 'cresco_canvas_ui_registry', $manifest );
	}
}
