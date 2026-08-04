<?php
/**
 * Cresco Canvas admin screen.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Admin;

use CrescoCanvas\Styles\GlobalStyles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {
	/**
	 * Editor preference service.
	 *
	 * @var EditorPreferences
	 */
	private $preferences;

	/**
	 * Global style service.
	 *
	 * @var GlobalStyles
	 */
	private $styles;

	/**
	 * Registered submenu hook suffix.
	 *
	 * @var string
	 */
	private $hook_suffix = '';

	/**
	 * Constructor.
	 *
	 * @param EditorPreferences $preferences Editor preference service.
	 * @param GlobalStyles      $styles      Global style service.
	 */
	public function __construct( EditorPreferences $preferences, GlobalStyles $styles ) {
		$this->preferences = $preferences;
		$this->styles      = $styles;
	}

	/**
	 * Register the admin screen and its isolated assets.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add Canvas beneath Pages.
	 */
	public function add_menu() {
		$this->hook_suffix = (string) add_submenu_page(
			'edit.php?post_type=page',
			__( 'Cresco Canvas', 'cresco-canvas' ),
			__( 'Cresco Canvas', 'cresco-canvas' ),
			'edit_pages',
			'cresco-canvas',
			array( $this, 'render' )
		);
	}

	/**
	 * Enqueue compiled editor assets on the Canvas screen only.
	 *
	 * @param string $hook Current admin hook suffix.
	 */
	public function enqueue_assets( $hook ) {
		if ( $hook !== $this->hook_suffix ) {
			return;
		}

		$post_id = $this->requested_post_id();

		if ( $post_id && ( ! $this->is_editable_page( $post_id ) || $this->preferences->is_safe_mode( $post_id ) ) ) {
			return;
		}

		$asset = $this->editor_asset();

		if ( null === $asset ) {
			return;
		}

		if ( function_exists( 'wp_enqueue_registered_block_scripts_and_styles' ) ) {
			wp_enqueue_registered_block_scripts_and_styles();
		}

		wp_enqueue_style( 'wp-edit-blocks' );
		wp_enqueue_style( 'wp-block-library' );
		wp_enqueue_script( 'wp-block-library' );
		wp_enqueue_script( 'wp-format-library' );
		wp_enqueue_script( 'cresco-canvas-container-editor' );
		wp_enqueue_style(
			'cresco-canvas-admin',
			CRESCO_CANVAS_URL . 'build/editor.css',
			array( 'wp-components', 'wp-edit-blocks' ),
			(string) $asset['version']
		);
		wp_add_inline_style( 'cresco-canvas-admin', GlobalStyles::css( '.cresco-canvas-scope' ) );

		wp_enqueue_script(
			'cresco-canvas-editor',
			CRESCO_CANVAS_URL . 'build/editor.js',
			(array) $asset['dependencies'],
			(string) $asset['version'],
			true
		);

		wp_add_inline_script(
			'cresco-canvas-editor',
			'window.crescoCanvasSettings = ' . wp_json_encode( $this->bootstrap_settings( $post_id ) ) . ';',
			'before'
		);
		wp_set_script_translations( 'cresco-canvas-editor', 'cresco-canvas' );
	}

	/**
	 * Render Canvas, Safe Mode, or a missing-build recovery screen.
	 */
	public function render() {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You are not allowed to access Cresco Canvas.', 'cresco-canvas' ) );
		}

		$post_id = $this->requested_post_id();

		if ( $post_id && ! $this->is_editable_page( $post_id ) ) {
			wp_die( esc_html__( 'The requested Page could not be found or you cannot edit it.', 'cresco-canvas' ) );
		}

		if ( $post_id && $this->preferences->is_safe_mode( $post_id ) ) {
			$this->render_safe_mode( $post_id );
			return;
		}

		if ( null === $this->editor_asset() ) {
			$this->render_missing_build( $post_id );
			return;
		}
		?>
		<div class="wrap cresco-canvas-admin-wrap cresco-canvas-scope">
			<div id="cresco-canvas-app">
				<p><?php esc_html_e( 'Loading Cresco Canvas…', 'cresco-canvas' ); ?></p>
				<?php if ( $post_id ) : ?>
					<p>
						<a href="<?php echo esc_url( $this->preferences->get_native_editor_url( $post_id ) ); ?>">
							<?php esc_html_e( 'Open the WordPress Editor', 'cresco-canvas' ); ?>
						</a>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Return a validated asset manifest or null when the build is unavailable.
	 *
	 * @return array<string, mixed>|null
	 */
	private function editor_asset() {
		$script_path = CRESCO_CANVAS_PATH . 'build/editor.js';
		$style_path  = CRESCO_CANVAS_PATH . 'build/editor.css';
		$asset_path  = CRESCO_CANVAS_PATH . 'build/editor.asset.php';

		if ( ! is_readable( $script_path ) || ! is_readable( $style_path ) || ! is_readable( $asset_path ) ) {
			return null;
		}

		$asset = require $asset_path;

		if ( ! is_array( $asset ) || ! isset( $asset['dependencies'], $asset['version'] ) ) {
			return null;
		}

		return $asset;
	}

	/**
	 * Build the minimal editor bootstrap payload.
	 *
	 * @param int $post_id Optional Page ID.
	 * @return array<string, mixed>
	 */
	private function bootstrap_settings( $post_id ) {
		return array(
			'adminUrl'          => admin_url(),
			'brand'             => 'Cresco Canvas',
			'canManageSettings' => current_user_can( 'edit_theme_options' ),
			'nativeEditUrl'     => $post_id ? $this->preferences->get_native_editor_url( $post_id ) : '',
			'nonce'             => wp_create_nonce( 'wp_rest' ),
			'pagesUrl'          => admin_url( 'edit.php?post_type=page' ),
			'postId'            => $post_id,
			'restPath'          => '/cresco-canvas/v1/',
			'safeModeUrl'       => $post_id ? $this->preferences->get_canvas_url( $post_id, true ) : '',
			'version'           => CRESCO_CANVAS_VERSION,
		);
	}

	/**
	 * Render a no-JavaScript recovery mode.
	 *
	 * @param int $post_id Page ID.
	 */
	private function render_safe_mode( $post_id ) {
		?>
		<div class="wrap cresco-canvas-recovery">
			<h1><?php esc_html_e( 'Cresco Canvas Safe Mode', 'cresco-canvas' ); ?></h1>
			<p><?php esc_html_e( 'Editor scripts and generated Canvas admin styles are disabled for this request. Your Page content has not been changed.', 'cresco-canvas' ); ?></p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $this->preferences->get_native_editor_url( $post_id ) ); ?>">
					<?php esc_html_e( 'Open WordPress Editor', 'cresco-canvas' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( $this->preferences->get_canvas_url( $post_id ) ); ?>">
					<?php esc_html_e( 'Retry Cresco Canvas', 'cresco-canvas' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Render an actionable development-build failure without a white screen.
	 *
	 * @param int $post_id Optional Page ID.
	 */
	private function render_missing_build( $post_id ) {
		?>
		<div class="wrap cresco-canvas-recovery">
			<h1><?php esc_html_e( 'Cresco Canvas assets are unavailable', 'cresco-canvas' ); ?></h1>
			<p><?php esc_html_e( 'The compiled editor files are missing or incomplete. Install a release ZIP, or run the production build in a development checkout.', 'cresco-canvas' ); ?></p>
			<?php if ( $post_id ) : ?>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( $this->preferences->get_native_editor_url( $post_id ) ); ?>">
						<?php esc_html_e( 'Open WordPress Editor', 'cresco-canvas' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Get the requested Page ID.
	 *
	 * @return int
	 */
	private function requested_post_id() {
		return isset( $_GET['post_id'] ) ? absint( wp_unslash( $_GET['post_id'] ) ) : 0;
	}

	/**
	 * Validate Page type and edit permission.
	 *
	 * @param int $post_id Page ID.
	 * @return bool
	 */
	private function is_editable_page( $post_id ) {
		$post = get_post( $post_id );
		return $post && 'page' === $post->post_type && current_user_can( 'edit_post', $post_id );
	}
}
