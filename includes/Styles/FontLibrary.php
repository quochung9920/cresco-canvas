<?php
/**
 * Searchable Google/System font catalog and selected-font delivery.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Styles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps font discovery metadata separate from Global Design persistence while
 * loading the font selected by the canonical `fontFamily` setting.
 */
final class FontLibrary {
	const REST_NAMESPACE = 'cresco-canvas/v1';
	const REST_ROUTE     = '/font-library';
	const METADATA_URL   = 'https://fonts.google.com/metadata/fonts';
	const CACHE_KEY      = 'cresco_google_font_catalog_v2';
	const CACHE_OPTION   = 'cresco_google_font_catalog_cache_v2';
	const CACHE_TTL      = 604800; // 7 days.
	const STYLE_HANDLE   = 'cresco-canvas-global-google-font';

	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_font' ), 8 );
	}

	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_catalog' ),
				'permission_callback' => static function () {
					return current_user_can( 'edit_theme_options' );
				},
			)
		);
	}

	public function rest_catalog() {
		return rest_ensure_response( self::catalog_payload( true ) );
	}

	/**
	 * Load the selected Google Font on Cresco frontend pages. The catalog cache
	 * is never refreshed from a public request; Studio is responsible for the
	 * periodic metadata refresh.
	 */
	public function enqueue_frontend_font() {
		$styles = new GlobalStyles();
		if ( ! $styles->is_canvas_page() ) return;

		$settings = GlobalStyles::get_settings();
		$family   = self::first_family( $settings['fontFamily'] ?? '' );
		if ( '' === $family || self::is_system_family( $family ) ) return;

		$font = self::find_google_font( $family, false );
		if ( ! $font ) return;

		$url = self::google_css_url( $font );
		if ( '' === $url ) return;
		wp_enqueue_style( self::STYLE_HANDLE, $url, array(), null );
	}

	/** @return array<string,mixed> */
	public static function catalog_payload( $allow_remote = true ) {
		$record = self::catalog_record( (bool) $allow_remote );
		$system = self::system_fonts();
		$google = $record['fonts'];
		return array(
			'schema'       => 'cresco-font-library/v1',
			'source'       => $record['source'],
			'refreshedAt'  => $record['refreshedAt'],
			'systemCount'  => count( $system ),
			'googleCount'  => count( $google ),
			'totalCount'   => count( $system ) + count( $google ),
			'fonts'        => array_values( array_merge( $system, $google ) ),
		);
	}

	/** @return array<string,mixed> */
	private static function catalog_record( $allow_remote ) {
		$cached = get_transient( self::CACHE_KEY );
		if ( self::valid_record( $cached ) ) return $cached;

		$stored = get_option( self::CACHE_OPTION, array() );
		if ( $allow_remote ) {
			$fresh = self::fetch_google_catalog();
			if ( count( $fresh ) >= 100 ) {
				$record = array(
					'source'      => 'google-metadata',
					'refreshedAt' => gmdate( 'c' ),
					'fonts'       => $fresh,
				);
				set_transient( self::CACHE_KEY, $record, self::CACHE_TTL );
				update_option( self::CACHE_OPTION, $record, false );
				return $record;
			}
		}

		if ( self::valid_record( $stored ) ) {
			$stored['source'] = 'persistent-cache';
			set_transient( self::CACHE_KEY, $stored, self::CACHE_TTL );
			return $stored;
		}

		return array(
			'source'      => 'built-in-fallback',
			'refreshedAt' => null,
			'fonts'       => self::fallback_google_fonts(),
		);
	}

	private static function valid_record( $record ) {
		return is_array( $record ) && isset( $record['fonts'] ) && is_array( $record['fonts'] ) && count( $record['fonts'] ) >= 100;
	}

	/** @return array<int,array<string,mixed>> */
	private static function fetch_google_catalog() {
		$response = wp_safe_remote_get(
			self::METADATA_URL,
			array(
				'timeout'     => 10,
				'redirection' => 2,
				'headers'     => array( 'Accept' => 'application/json' ),
				'user-agent'  => 'Cresco Canvas/' . ( defined( 'CRESCO_CANVAS_VERSION' ) ? CRESCO_CANVAS_VERSION : '1.0' ),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) return array();

		$body = ltrim( (string) wp_remote_retrieve_body( $response ) );
		if ( 0 === strpos( $body, ")]}'" ) ) $body = substr( $body, 4 );
		$data = json_decode( $body, true );
		$list = is_array( $data ) && isset( $data['familyMetadataList'] ) && is_array( $data['familyMetadataList'] )
			? $data['familyMetadataList']
			: array();

		$out = array();
		foreach ( $list as $item ) {
			if ( ! is_array( $item ) ) continue;
			$family = sanitize_text_field( (string) ( $item['family'] ?? '' ) );
			if ( '' === $family || strlen( $family ) > 120 ) continue;
			$category = self::normalize_category( $item['category'] ?? '' );
			if ( 'symbols' === $category ) continue;
			$weight_range = self::extract_weight_range( $item );
			$out[] = array(
				'family'     => $family,
				'stack'      => self::stack_for( $family, $category ),
				'category'   => $category,
				'source'     => 'google',
				'weights'    => self::extract_weights( $item, $weight_range ),
				'variable'   => null !== $weight_range,
				'weightMin'  => $weight_range ? $weight_range[0] : null,
				'weightMax'  => $weight_range ? $weight_range[1] : null,
				'popularity' => absint( $item['popularity'] ?? $item['defaultSort'] ?? 999999 ),
			);
		}

		usort(
			$out,
			static function ( $a, $b ) {
				return strcasecmp( $a['family'], $b['family'] );
			}
		);
		return $out;
	}

	private static function normalize_category( $category ) {
		$value = strtolower( trim( str_replace( '_', ' ', (string) $category ) ) );
		if ( false !== strpos( $value, 'mono' ) ) return 'monospace';
		if ( false !== strpos( $value, 'hand' ) ) return 'handwriting';
		if ( false !== strpos( $value, 'display' ) ) return 'display';
		if ( false !== strpos( $value, 'symbol' ) || false !== strpos( $value, 'icon' ) ) return 'symbols';
		if ( 'serif' === $value || ( false !== strpos( $value, 'serif' ) && false === strpos( $value, 'sans' ) ) ) return 'serif';
		return 'sans-serif';
	}

	private static function extract_weight_range( $item ) {
		foreach ( (array) ( $item['axes'] ?? array() ) as $axis ) {
			if ( ! is_array( $axis ) || 'wght' !== strtolower( (string) ( $axis['tag'] ?? '' ) ) ) continue;
			$min = (int) round( (float) ( $axis['min'] ?? $axis['minValue'] ?? 100 ) );
			$max = (int) round( (float) ( $axis['max'] ?? $axis['maxValue'] ?? 900 ) );
			$min = max( 1, min( 1000, $min ) );
			$max = max( $min, min( 1000, $max ) );
			return array( $min, $max );
		}
		return null;
	}

	private static function extract_weights( $item, $weight_range ) {
		$weights = array();
		foreach ( (array) ( $item['fonts'] ?? array() ) as $key => $font ) {
			if ( is_numeric( $key ) ) $weights[] = (int) $key;
			if ( is_array( $font ) && isset( $font['weight'] ) ) $weights[] = (int) $font['weight'];
		}
		if ( $weight_range ) {
			foreach ( array( 100, 200, 300, 400, 500, 600, 700, 800, 900 ) as $weight ) {
				if ( $weight >= $weight_range[0] && $weight <= $weight_range[1] ) $weights[] = $weight;
			}
		}
		$weights = array_values( array_unique( array_filter( array_map( 'absint', $weights ) ) ) );
		sort( $weights, SORT_NUMERIC );
		return $weights ?: array( 400 );
	}

	/** @return array<int,array<string,mixed>> */
	public static function system_fonts() {
		$rows = array(
			array( 'System UI', 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif', 'system' ),
			array( 'Arial', 'Arial, Helvetica, sans-serif', 'sans-serif' ),
			array( 'Helvetica', 'Helvetica, Arial, sans-serif', 'sans-serif' ),
			array( 'Verdana', 'Verdana, Geneva, sans-serif', 'sans-serif' ),
			array( 'Tahoma', 'Tahoma, Geneva, sans-serif', 'sans-serif' ),
			array( 'Trebuchet MS', '"Trebuchet MS", Arial, sans-serif', 'sans-serif' ),
			array( 'Georgia', 'Georgia, "Times New Roman", serif', 'serif' ),
			array( 'Times New Roman', '"Times New Roman", Times, serif', 'serif' ),
			array( 'Palatino', 'Palatino, "Palatino Linotype", serif', 'serif' ),
			array( 'Garamond', 'Garamond, "Times New Roman", serif', 'serif' ),
			array( 'Baskerville', 'Baskerville, Georgia, serif', 'serif' ),
			array( 'Courier New', '"Courier New", Courier, monospace', 'monospace' ),
			array( 'Consolas', 'Consolas, "Courier New", monospace', 'monospace' ),
			array( 'Monaco', 'Monaco, Consolas, monospace', 'monospace' ),
		);
		return array_map(
			static function ( $row ) {
				return array(
					'family'     => $row[0],
					'stack'      => $row[1],
					'category'   => $row[2],
					'source'     => 'system',
					'weights'    => array(),
					'variable'   => false,
					'weightMin'  => null,
					'weightMax'  => null,
					'popularity' => 0,
				);
			},
			$rows
		);
	}

	/**
	 * Offline safety net. Studio normally refreshes the complete Google Fonts
	 * metadata catalog; this list only keeps the picker useful when remote HTTP
	 * is unavailable.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function fallback_google_fonts() {
		$groups = array(
			'sans-serif' => array( 'ABeeZee','Abel','Alegreya Sans','Almarai','Archivo','Archivo Narrow','Arimo','Assistant','Barlow','Barlow Condensed','Barlow Semi Condensed','Cabin','Cairo','Cantarell','Catamaran','DM Sans','Dosis','Exo 2','Figtree','Fira Sans','Heebo','Hind','IBM Plex Sans','Instrument Sans','Inter','Josefin Sans','Karla','Lato','League Spartan','Lexend','Libre Franklin','Manrope','Montserrat','Mulish','Nanum Gothic','Noto Sans','Noto Sans Arabic','Noto Sans Devanagari','Noto Sans Display','Noto Sans JP','Noto Sans KR','Noto Sans SC','Noto Sans TC','Nunito','Nunito Sans','Open Sans','Oswald','Outfit','Overpass','Oxygen','Poppins','Plus Jakarta Sans','PT Sans','Quicksand','Raleway','Red Hat Display','Roboto','Roboto Condensed','Rubik','Schibsted Grotesk','Signika','Source Sans 3','Space Grotesk','Sora','Tajawal','Teko','Titillium Web','Ubuntu','Work Sans','Yanone Kaffeesatz' ),
			'serif' => array( 'Abhaya Libre','Alegreya','Alice','Amiri','Arvo','Bitter','Bodoni Moda','Cardo','Cormorant','Cormorant Garamond','Crimson Pro','Crimson Text','DM Serif Text','EB Garamond','Fraunces','Libre Baskerville','Lora','Martel','Merriweather','Noto Serif','Noto Serif Display','Playfair Display','PT Serif','Roboto Serif','Roboto Slab','Source Serif 4','Spectral','Vollkorn','Zilla Slab' ),
			'display' => array( 'Abril Fatface','Acme','Alfa Slab One','Anton','Archivo Black','Bebas Neue','DM Serif Display','Fjalla One','Fredoka','Gloock' ),
			'handwriting' => array( 'Amatic SC','Gloria Hallelujah','Great Vibes','Pacifico','Sacramento','Satisfy' ),
			'monospace' => array( 'DM Mono','Fira Code','Fira Mono','IBM Plex Mono','Inconsolata','Roboto Mono','Source Code Pro','Space Mono','Ubuntu Mono' ),
		);
		$out = array();
		$rank = 1;
		foreach ( $groups as $category => $families ) {
			foreach ( $families as $family ) {
				$out[] = array(
					'family'     => $family,
					'stack'      => self::stack_for( $family, $category ),
					'category'   => $category,
					'source'     => 'google',
					'weights'    => array( 400, 700 ),
					'variable'   => false,
					'weightMin'  => null,
					'weightMax'  => null,
					'popularity' => $rank++,
				);
			}
		}
		usort( $out, static function ( $a, $b ) { return strcasecmp( $a['family'], $b['family'] ); } );
		return $out;
	}

	public static function first_family( $stack ) {
		$first = trim( explode( ',', (string) $stack, 2 )[0] ?? '' );
		return trim( $first, " \t\n\r\0\x0B\"'" );
	}

	private static function is_system_family( $family ) {
		$needle = strtolower( trim( (string) $family ) );
		$generic = array( 'system ui','system-ui','-apple-system','blinkmacsystemfont','segoe ui','arial','helvetica','verdana','tahoma','trebuchet ms','georgia','times new roman','times','palatino','palatino linotype','garamond','baskerville','courier new','courier','consolas','monaco','sans-serif','serif','monospace','cursive','fantasy','ui-sans-serif','ui-serif','ui-monospace' );
		return in_array( $needle, $generic, true );
	}

	private static function find_google_font( $family, $allow_remote ) {
		$record = self::catalog_record( (bool) $allow_remote );
		foreach ( $record['fonts'] as $font ) {
			if ( 0 === strcasecmp( (string) $font['family'], (string) $family ) ) return $font;
		}
		return null;
	}

	private static function stack_for( $family, $category ) {
		$fallback = 'serif' === $category ? 'serif' : ( 'monospace' === $category ? 'monospace' : 'sans-serif' );
		return '"' . str_replace( '"', '', (string) $family ) . '", ' . $fallback;
	}

	public static function google_css_url( $font ) {
		if ( ! is_array( $font ) || empty( $font['family'] ) ) return '';
		$family = str_replace( '%20', '+', rawurlencode( (string) $font['family'] ) );
		$spec = $family;
		if ( ! empty( $font['variable'] ) && is_numeric( $font['weightMin'] ?? null ) && is_numeric( $font['weightMax'] ?? null ) ) {
			$min = (int) $font['weightMin'];
			$max = (int) $font['weightMax'];
			if ( $min < $max ) $spec .= ':wght@' . $min . '..' . $max;
		} else {
			$weights = array_values( array_filter( array_map( 'absint', (array) ( $font['weights'] ?? array() ) ) ) );
			$weights = array_values( array_unique( array_filter( $weights, static function ( $weight ) { return $weight >= 100 && $weight <= 900; } ) ) );
			sort( $weights, SORT_NUMERIC );
			if ( $weights ) $spec .= ':wght@' . implode( ';', $weights );
		}
		return 'https://fonts.googleapis.com/css2?family=' . $spec . '&display=swap';
	}
}
