<?php
/**
 * Searchable web-font catalog and safe frontend loader.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Styles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GlobalWebFonts {
	const HANDLE = 'cresco-canvas-global-web-font';

	public function register() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_selected_font' ), 8 );
	}

	public static function system_fonts() {
		return array(
			array( 'family' => 'System UI', 'category' => 'system', 'stack' => 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif' ),
			array( 'family' => 'Arial', 'category' => 'system', 'stack' => 'Arial, Helvetica, sans-serif' ),
			array( 'family' => 'Helvetica', 'category' => 'system', 'stack' => 'Helvetica, Arial, sans-serif' ),
			array( 'family' => 'Verdana', 'category' => 'system', 'stack' => 'Verdana, Geneva, sans-serif' ),
			array( 'family' => 'Trebuchet MS', 'category' => 'system', 'stack' => '"Trebuchet MS", Arial, sans-serif' ),
			array( 'family' => 'Georgia', 'category' => 'system', 'stack' => 'Georgia, "Times New Roman", serif' ),
			array( 'family' => 'Times New Roman', 'category' => 'system', 'stack' => '"Times New Roman", Times, serif' ),
			array( 'family' => 'Courier New', 'category' => 'system', 'stack' => '"Courier New", Courier, monospace' ),
		);
	}

	public static function catalog() {
		$groups = array(
			'sans' => array(
				'ABeeZee','Abel','Albert Sans','Alegreya Sans','Alegreya Sans SC','Alexandria','Almarai','Archivo','Archivo Black','Archivo Narrow','Assistant','Atkinson Hyperlegible','Barlow','Barlow Condensed','Barlow Semi Condensed','Be Vietnam Pro','Bellota Sans','Biryani','Cabin','Cabin Condensed','Cairo','Cantarell','Catamaran','Chivo','Commissioner','Comme','DM Sans','Darker Grotesque','Dosis','Encode Sans','Encode Sans Condensed','Exo','Exo 2','Figtree','Fira Sans','Fira Sans Condensed','Fira Sans Extra Condensed','Geologica','Golos Text','Heebo','Hind','Hind Guntur','Hind Madurai','Hind Siliguri','Hind Vadodara','IBM Plex Sans','IBM Plex Sans Condensed','Inter','Inter Tight','Istok Web','Jost','Kanit','Karla','Kumbh Sans','Lato','Lexend','Lexend Deca','Libre Franklin','Manrope','Maven Pro','Montserrat','Montserrat Alternates','Mukta','Mukta Mahee','Mukta Malar','Mukta Vaani','Mulish','Murecho','Noto Sans','Noto Sans Arabic','Noto Sans Bengali','Noto Sans Devanagari','Noto Sans Display','Noto Sans Hebrew','Noto Sans JP','Noto Sans KR','Noto Sans SC','Noto Sans TC','Noto Sans Thai','Nunito','Nunito Sans','Open Sans','Outfit','Overpass','Oxygen','Plus Jakarta Sans','Poppins','Prompt','Public Sans','Questrial','Quicksand','Raleway','Red Hat Display','Red Hat Text','Roboto','Roboto Condensed','Roboto Flex','Rubik','Sarabun','Schibsted Grotesk','Signika','Source Sans 3','Space Grotesk','Spline Sans','Titillium Web','Ubuntu','Ubuntu Condensed','Urbanist','Varela Round','Work Sans','Yantramanav','Ysabeau','Zen Kaku Gothic New'
			),
			'serif' => array(
				'Alegreya','Aleo','Amiri','Arvo','Bitter','Bodoni Moda','Bree Serif','Brygada 1918','Cardo','Cormorant','Cormorant Garamond','Crimson Pro','Crimson Text','DM Serif Display','DM Serif Text','Domine','EB Garamond','Faustina','Fira Serif','Frank Ruhl Libre','Fraunces','Gentium Book Plus','IBM Plex Serif','Inria Serif','Labrada','Libre Baskerville','Libre Caslon Display','Libre Caslon Text','Literata','Lora','Lusitana','Merriweather','Merriweather Sans','Noto Serif','Noto Serif Display','Noto Serif JP','Noto Serif KR','Noto Serif SC','Noto Serif TC','Old Standard TT','Petrona','Playfair Display','Prata','PT Serif','Roboto Serif','Rokkitt','Source Serif 4','Spectral','STIX Two Text','Tinos','Vollkorn','Young Serif','Zilla Slab'
			),
			'display' => array(
				'Acme','Alfa Slab One','Anton','Archivo Black','Bebas Neue','Bowlby One SC','Bungee','Bungee Shade','Cinzel','Comfortaa','Concert One','Contrail One','Days One','Fjalla One','Forum','Fredoka','Graduate','Josefin Sans','Koulen','League Spartan','Lilita One','Londrina Solid','Michroma','Monoton','Orbitron','Oswald','Passion One','Patua One','Permanent Marker','Philosopher','Poiret One','Press Start 2P','Righteous','Russo One','Saira Condensed','Secular One','Squada One','Syncopate','Teko','Unbounded','Viga','Yeseva One'
			),
			'mono' => array(
				'Azeret Mono','Cousine','Cutive Mono','DM Mono','Fira Code','Fira Mono','IBM Plex Mono','Inconsolata','JetBrains Mono','Martian Mono','Noto Sans Mono','Overpass Mono','PT Mono','Roboto Mono','Share Tech Mono','Source Code Pro','Space Mono','Ubuntu Mono','Victor Mono'
			),
			'handwriting' => array(
				'Allura','Amatic SC','Bad Script','Caveat','Cookie','Courgette','Dancing Script','Great Vibes','Handlee','Indie Flower','Kaushan Script','Marck Script','Nothing You Could Do','Pacifico','Parisienne','Patrick Hand','Sacramento','Satisfy','Shadows Into Light','Yellowtail'
			),
		);

		$out = array();
		$seen = array();
		foreach ( $groups as $category => $families ) {
			foreach ( $families as $family ) {
				$key = strtolower( $family );
				if ( isset( $seen[ $key ] ) ) continue;
				$seen[ $key ] = true;
				$out[] = array(
					'family'   => $family,
					'category' => $category,
					'stack'    => self::stack_for( $family, $category ),
				);
			}
		}
		usort( $out, static function ( $a, $b ) { return strcasecmp( $a['family'], $b['family'] ); } );
		return $out;
	}

	public static function stack_for( $family, $category = 'sans' ) {
		$fallback = 'serif' === $category ? 'serif' : ( 'mono' === $category ? 'monospace' : ( 'handwriting' === $category ? 'cursive' : 'sans-serif' ) );
		return '"' . str_replace( '"', '', (string) $family ) . '", ' . $fallback;
	}

	public static function family_from_stack( $stack ) {
		$first = trim( explode( ',', (string) $stack )[0] ?? '' );
		return trim( $first, " \t\n\r\0\x0B\"'" );
	}

	public static function find( $family ) {
		foreach ( self::catalog() as $font ) {
			if ( 0 === strcasecmp( $font['family'], (string) $family ) ) return $font;
		}
		return null;
	}

	public static function google_css_url( $family ) {
		$font = self::find( $family );
		if ( ! $font ) return '';
		return 'https://fonts.googleapis.com/css2?family=' . str_replace( '%20', '+', rawurlencode( $font['family'] ) ) . '&display=swap';
	}

	public function enqueue_selected_font() {
		$global_styles = new GlobalStyles();
		if ( ! $global_styles->is_canvas_page() ) return;
		$settings = GlobalStyles::get_settings();
		$family = self::family_from_stack( $settings['fontFamily'] ?? '' );
		$url = self::google_css_url( $family );
		if ( $url ) wp_enqueue_style( self::HANDLE, $url, array(), null );
	}
}
