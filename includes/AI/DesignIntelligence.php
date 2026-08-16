<?php
/**
 * Deterministic design-recipe generator for Cresco Canvas.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DesignIntelligence {
	const SCHEMA = 'cresco-design-recipe/v1';
	const VERSION = 1;

	/** Generate one bounded, machine-readable design recipe from a natural-language request. */
	public static function recommend( $request, $options = array() ) {
		$request = trim( wp_strip_all_tags( (string) $request ) );
		$options = is_array( $options ) ? $options : array();
		$normalized = self::expand_language_aliases( self::normalize( $request ) );

		$industry_match = self::best_match( $normalized, DesignIntelligenceCatalog::industries(), 'general' );
		$industry_id = $industry_match['id'];
		$industry = $industry_match['item'];
		$goal_match = self::best_match( $normalized, DesignIntelligenceCatalog::goals(), (string) $industry['defaultGoal'] );
		$goal_id = $goal_match['id'];

		$variance = self::dial( 'variance', $normalized, $options, (int) $industry['variance'] );
		$density = self::dial( 'density', $normalized, $options, (int) $industry['density'] );
		$motion = self::dial( 'motion', $normalized, $options, (int) $industry['motion'] );
		$mode = self::mode( $normalized, $options );

		$patterns = DesignIntelligenceCatalog::patterns();
		$goals = DesignIntelligenceCatalog::goals();
		$pattern_id = $goal_id === (string) $industry['defaultGoal']
			? (string) $industry['pattern']
			: (string) ( $goals[ $goal_id ]['pattern'] ?? $industry['pattern'] );
		if ( ! isset( $patterns[ $pattern_id ] ) ) $pattern_id = (string) $industry['pattern'];
		$pattern = $patterns[ $pattern_id ];

		$styles = DesignIntelligenceCatalog::styles();
		$style_id = self::resolve_style( $normalized, (string) $industry['style'], $variance, $styles );
		$style = $styles[ $style_id ] ?? reset( $styles );
		$palette = DesignIntelligenceCatalog::palettes()[ (string) $industry['palette'] ] ?? array();
		if ( 'dark' === $mode ) $palette = self::dark_palette( $palette );
		$typography = DesignIntelligenceCatalog::typography()[ (string) $industry['typography'] ] ?? array();
		$spacing = self::spacing_scale( $density );
		$recommended = self::recommended_widgets( $pattern['sections'] );

		return array(
			'schema' => self::SCHEMA,
			'version' => self::VERSION,
			'source' => 'cresco-curated-design-rules',
			'classification' => array(
				'industry' => array( 'id' => $industry_id, 'label' => $industry['label'], 'confidence' => $industry_match['confidence'], 'signals' => $industry_match['signals'] ),
				'goal' => array( 'id' => $goal_id, 'label' => $goals[ $goal_id ]['label'] ?? $goal_id, 'confidence' => $goal_match['confidence'], 'signals' => $goal_match['signals'] ),
			),
			'pattern' => array(
				'id' => $pattern_id,
				'label' => $pattern['label'],
				'sections' => array_values( $pattern['sections'] ),
				'primaryCtaPlacement' => self::cta_placement( $goal_id ),
			),
			'style' => array(
				'id' => $style_id,
				'label' => $style['label'],
				'mode' => $mode,
				'keywords' => array_values( $style['keywords'] ),
				'shadow' => $style['shadow'],
				'border' => $style['border'],
			),
			'colors' => $palette,
			'typography' => $typography,
			'spacing' => $spacing,
			'radius' => $style['radius'],
			'motion' => self::motion_recipe( $motion ),
			'dials' => array( 'variance' => $variance, 'density' => $density, 'motion' => $motion ),
			'recommendedWidgets' => $recommended,
			'rules' => array(
				'preferNativeWidgets' => true,
				'preferDesignTokens' => true,
				'responsiveByDefault' => true,
				'accessibilityFirst' => true,
				'minimizeCustomCSS' => true,
				'customJSForNativeInteractions' => false,
			),
			'antiPatterns' => array_values( array_unique( array_merge(
				(array) $industry['antiPatterns'],
				self::dial_antipatterns( $variance, $density, $motion )
			) ) ),
			'constraints' => self::constraints( $industry_id, $goal_id ),
			'guidance' => self::guidance( $industry, $goal_id, $pattern, $variance, $density, $motion ),
		);
	}

	/** Add design intelligence to an AI Context v3 package without widening edit scope. */
	public static function augment_context( $package ) {
		if ( ! is_array( $package ) || 'cresco-ai-context/v3' !== (string) ( $package['schema'] ?? '' ) ) return $package;
		if ( isset( $package['scopePackage']['designIntelligence'] ) ) return $package;
		$request = (string) ( $package['task']['request'] ?? '' );
		$recipe = self::recommend( $request );
		$package['scopePackage']['designIntelligence'] = $recipe;

		// Keep optimized Context v3 useful: expose a small number of recipe-relevant
		// full contracts instead of asking the model to infer them from catalogIndex.
		if ( isset( $package['scopePackage']['contracts']['recommended'] ) && class_exists( ContractRegistry::class ) ) {
			$recommended = (array) $package['scopePackage']['contracts']['recommended'];
			$current = (array) ( $package['scopePackage']['contracts']['current'] ?? array() );
			$known = array_fill_keys( array_merge( array_keys( $current ), array_keys( $recommended ) ), true );
			$additional = array();
			foreach ( (array) $recipe['recommendedWidgets'] as $types ) {
				foreach ( (array) $types as $type ) {
					if ( isset( $known[ $type ] ) ) continue;
					$known[ $type ] = true;
					$additional[] = $type;
					if ( count( $additional ) >= 8 ) break 2;
				}
			}
			if ( $additional ) {
				$package['scopePackage']['contracts']['recommended'] = array_merge( $recommended, ContractRegistry::for_types( $additional ) );
			}
		}

		$package['authoringPolicy']['designIntelligence'] = array(
			'role' => 'recommended-design-direction',
			'priority' => array( 'explicit user request', 'reference image intent', 'Cresco technical contracts', 'design intelligence recommendation' ),
			'neverWidensScope' => true,
			'note' => 'Use the recipe to make coherent design decisions. It is guidance, never permission to invent unsupported widgets or properties.',
		);
		return $package;
	}

	private static function expand_language_aliases( $text ) {
		$aliases = array(
			'chống thấm' => ' damp waterproofing home service', 'ẩm mốc' => ' mould home service', 'điện nước' => ' plumbing electrician home service',
			'phần mềm' => ' software saas', 'ứng dụng' => ' app software', 'nền tảng' => ' platform software', 'bảng điều khiển' => ' dashboard',
			'thương mại điện tử' => ' ecommerce online store', 'cửa hàng trực tuyến' => ' online store ecommerce', 'mua sắm' => ' shop commerce',
			'phòng khám' => ' clinic healthcare', 'y tế' => ' healthcare', 'bác sĩ' => ' doctor healthcare', 'nha khoa' => ' dental healthcare',
			'tài chính' => ' finance', 'ngân hàng' => ' banking finance', 'bảo hiểm' => ' insurance finance', 'đầu tư' => ' investment finance',
			'luật sư' => ' lawyer legal', 'pháp lý' => ' legal', 'tư vấn' => ' consultation consulting',
			'khách sạn' => ' hotel hospitality', 'du lịch' => ' travel hospitality', 'nhà hàng' => ' restaurant', 'ẩm thực' => ' food restaurant',
			'bất động sản' => ' real estate property', 'nhà đất' => ' real estate property', 'giáo dục' => ' education', 'khóa học' => ' course education', 'đào tạo' => ' training education',
			'thiết kế' => ' design creative', 'sáng tạo' => ' creative', 'nhiếp ảnh' => ' photography creative', 'làm đẹp' => ' beauty wellness', 'thẩm mỹ' => ' beauty wellness',
			'từ thiện' => ' charity nonprofit', 'quyên góp' => ' donate fundraising', 'ủng hộ' => ' donate support',
			'báo giá' => ' quote lead', 'liên hệ' => ' contact lead', 'đăng ký' => ' signup', 'dùng thử' => ' free trial', 'đặt lịch' => ' booking appointment', 'đặt chỗ' => ' booking reservation',
			'mua hàng' => ' buy commerce', 'giỏ hàng' => ' cart commerce', 'thanh toán' => ' checkout commerce', 'uy tín' => ' trust authority', 'chuyên gia' => ' expert authority',
			'tuyển sinh' => ' enrollment admission', 'hồ sơ năng lực' => ' portfolio', 'gây quỹ' => ' fundraising', 'tin tức' => ' news content', 'bài viết' => ' articles content',
			'tối giản' => ' minimal', 'đơn giản' => ' simple', 'táo bạo' => ' bold', 'phá cách' => ' experimental', 'thoáng' => ' spacious', 'cao cấp' => ' luxury premium',
			'dày đặc' => ' dense', 'nhiều dữ liệu' => ' data heavy dashboard', 'hoạt ảnh' => ' animated', 'sinh động' => ' dynamic', 'chuyển động nhẹ' => ' subtle motion',
			'chế độ tối' => ' dark mode', 'giao diện tối' => ' dark theme',
		);
		foreach ( $aliases as $source => $expansion ) if ( false !== strpos( $text, $source ) ) $text .= $expansion;
		return $text;
	}

	private static function normalize( $value ) {
		$value = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $value, 'UTF-8' ) : strtolower( (string) $value );
		$value = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $value );
		return trim( preg_replace( '/\s+/', ' ', (string) $value ) );
	}

	private static function best_match( $text, $catalog, $fallback ) {
		$best_id = $fallback;
		$best = $catalog[ $fallback ] ?? reset( $catalog );
		$best_score = 0;
		$signals = array();
		foreach ( $catalog as $id => $item ) {
			$score = 0; $hits = array();
			foreach ( (array) ( $item['signals'] ?? array() ) as $signal ) {
				$needle = self::normalize( $signal );
				if ( '' !== $needle && false !== strpos( ' ' . $text . ' ', ' ' . $needle . ' ' ) ) {
					$score += max( 2, substr_count( $needle, ' ' ) + 2 );
					$hits[] = $signal;
				} elseif ( '' !== $needle && false !== strpos( $text, $needle ) ) {
					$score += 1;
					$hits[] = $signal;
				}
			}
			if ( $score > $best_score ) {
				$best_id = $id; $best = $item; $best_score = $score; $signals = $hits;
			}
		}
		$confidence = 0 === $best_score ? 0.45 : min( 0.98, 0.58 + ( $best_score * 0.07 ) );
		return array( 'id' => $best_id, 'item' => $best, 'score' => $best_score, 'confidence' => round( $confidence, 2 ), 'signals' => array_values( array_unique( $signals ) ) );
	}

	private static function dial( $name, $text, $options, $fallback ) {
		if ( isset( $options[ $name ] ) && is_numeric( $options[ $name ] ) ) return self::clamp( (int) $options[ $name ] );
		$rules = array(
			'variance' => array(
				'low' => array( 'minimal', 'simple', 'conservative', 'classic', 'clean', 'restrained', 'tối giản', 'đơn giản', 'gọn gàng' ),
				'high' => array( 'bold', 'experimental', 'creative', 'asymmetric', 'expressive', 'brutalist', 'táo bạo', 'sáng tạo', 'phá cách' ),
			),
			'density' => array(
				'low' => array( 'spacious', 'airy', 'luxury', 'premium', 'calm', 'thoáng', 'cao cấp', 'sang trọng' ),
				'high' => array( 'dense', 'compact', 'dashboard', 'data heavy', 'information rich', 'dày đặc', 'gọn', 'nhiều dữ liệu' ),
			),
			'motion' => array(
				'low' => array( 'static', 'subtle motion', 'reduced motion', 'calm', 'restrained', 'tĩnh', 'chuyển động nhẹ', 'nhẹ nhàng' ),
				'high' => array( 'animated', 'dynamic', 'interactive motion', 'expressive motion', 'cinematic', 'hoạt ảnh', 'chuyển động', 'sinh động' ),
			),
		);
		$value = $fallback;
		foreach ( $rules[ $name ]['low'] as $signal ) if ( false !== strpos( $text, $signal ) ) $value -= 2;
		foreach ( $rules[ $name ]['high'] as $signal ) if ( false !== strpos( $text, $signal ) ) $value += 2;
		return self::clamp( $value );
	}

	private static function clamp( $value ) { return max( 1, min( 10, (int) $value ) ); }

	private static function mode( $text, $options ) {
		if ( isset( $options['mode'] ) && in_array( $options['mode'], array( 'light', 'dark' ), true ) ) return $options['mode'];
		foreach ( array( 'dark mode', 'dark theme', 'night mode', 'dark ui', 'oled', 'chế độ tối', 'giao diện tối' ) as $signal ) if ( false !== strpos( $text, $signal ) ) return 'dark';
		return 'light';
	}

	private static function resolve_style( $text, $fallback, $variance, $styles ) {
		if ( $variance >= 8 && isset( $styles['bold-editorial'] ) ) return 'bold-editorial';
		if ( false !== strpos( $text, 'luxury' ) || false !== strpos( $text, 'premium visual' ) ) return isset( $styles['visual-premium'] ) ? 'visual-premium' : $fallback;
		if ( false !== strpos( $text, 'minimal' ) && isset( $styles['professional-modern'] ) ) return 'professional-modern';
		return isset( $styles[ $fallback ] ) ? $fallback : array_key_first( $styles );
	}

	private static function dark_palette( $base ) {
		$base = (array) $base;
		$base['background'] = '#0B1120';
		$base['surface'] = '#111827';
		$base['foreground'] = '#F8FAFC';
		$base['muted'] = '#CBD5E1';
		$base['border'] = '#334155';
		$base['focus'] = '#60A5FA';
		$base['modeDerivation'] = 'cresco-dark-surfaces';
		return $base;
	}

	private static function spacing_scale( $density ) {
		if ( $density <= 3 ) return array( 'density' => $density, 'xs' => '4px', 'sm' => '8px', 'md' => '20px', 'lg' => '32px', 'xl' => '48px', 'section' => '80px', 'contentMax' => '72rem' );
		if ( $density >= 8 ) return array( 'density' => $density, 'xs' => '2px', 'sm' => '4px', 'md' => '8px', 'lg' => '12px', 'xl' => '20px', 'section' => '48px', 'contentMax' => '80rem' );
		return array( 'density' => $density, 'xs' => '4px', 'sm' => '8px', 'md' => '16px', 'lg' => '24px', 'xl' => '32px', 'section' => '64px', 'contentMax' => '76rem' );
	}

	private static function motion_recipe( $motion ) {
		if ( $motion <= 3 ) return array( 'intensity' => $motion, 'duration' => '180ms', 'easing' => 'ease-out', 'allowed' => array( 'opacity', 'transform' ), 'reducedMotion' => 'disable-nonessential' );
		if ( $motion >= 8 ) return array( 'intensity' => $motion, 'duration' => '320ms', 'easing' => 'cubic-bezier(0.22,1,0.36,1)', 'allowed' => array( 'opacity', 'transform', 'clip-path' ), 'reducedMotion' => 'disable-nonessential' );
		return array( 'intensity' => $motion, 'duration' => '240ms', 'easing' => 'cubic-bezier(0.2,0.8,0.2,1)', 'allowed' => array( 'opacity', 'transform' ), 'reducedMotion' => 'disable-nonessential' );
	}

	private static function recommended_widgets( $sections ) {
		$map = DesignIntelligenceCatalog::section_widgets();
		$catalog = class_exists( ContractRegistry::class ) ? ContractRegistry::all() : array();
		$output = array();
		foreach ( (array) $sections as $section ) {
			$chosen = array();
			foreach ( (array) ( $map[ $section ] ?? array( 'container', 'heading', 'text', 'button' ) ) as $type ) {
				if ( ! $catalog || isset( $catalog[ $type ] ) ) $chosen[] = $type;
			}
			if ( ! $chosen ) {
				foreach ( array( 'container', 'heading', 'text', 'button' ) as $type ) if ( ! $catalog || isset( $catalog[ $type ] ) ) $chosen[] = $type;
			}
			$output[ $section ] = array_values( array_unique( $chosen ) );
		}
		return $output;
	}

	private static function cta_placement( $goal ) {
		return in_array( $goal, array( 'lead-generation', 'signup', 'booking', 'commerce', 'enrollment', 'fundraising' ), true ) ? 'above-fold-and-after-proof' : 'after-primary-proof';
	}

	private static function dial_antipatterns( $variance, $density, $motion ) {
		$output = array();
		if ( $variance >= 8 ) $output[] = 'Do not sacrifice scanning order or semantics for asymmetry';
		if ( $density >= 8 ) $output[] = 'Do not compress touch targets or essential labels to achieve density';
		if ( $motion >= 6 ) $output[] = 'Do not animate layout in a way that shifts focus or blocks interaction';
		$output[] = 'Do not use color as the only carrier of meaning';
		return $output;
	}

	private static function constraints( $industry, $goal ) {
		$output = array( 'Keep essential text readable at 200% zoom', 'Preserve visible keyboard focus', 'Respect prefers-reduced-motion', 'Avoid horizontal overflow at narrow widths' );
		if ( in_array( $industry, array( 'healthcare', 'finance', 'legal' ), true ) ) $output[] = 'Prioritize clarity and trust over visual novelty';
		if ( in_array( $goal, array( 'lead-generation', 'booking', 'signup', 'commerce', 'enrollment', 'fundraising' ), true ) ) $output[] = 'Maintain one clearly dominant primary action per section';
		return $output;
	}

	private static function guidance( $industry, $goal, $pattern, $variance, $density, $motion ) {
		return array(
			'Build the page around the ' . $pattern['label'] . ' pattern.',
			'Use a ' . strtolower( (string) $industry['label'] ) . ' visual tone appropriate to the audience.',
			'Keep design variance at ' . $variance . '/10, density at ' . $density . '/10, and motion at ' . $motion . '/10.',
			'Optimize the hierarchy for the ' . str_replace( '-', ' ', $goal ) . ' goal before adding decorative detail.',
			'Prefer Cresco native widgets, responsive controls, states, and design tokens before Custom CSS or JavaScript.',
		);
	}

	private function __construct() {}
}
