<?php
/**
 * Curated design-intelligence knowledge for Cresco Canvas.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Small, provenance-safe design knowledge base owned by Cresco.
 *
 * The catalogue intentionally does not copy third-party datasets. It captures
 * general design heuristics in a compact format that can evolve with Cresco's
 * own widget and token systems.
 */
final class DesignIntelligenceCatalog {
	public static function industries() {
		return array(
			'general' => array(
				'label' => 'General Business',
				'signals' => array( 'business', 'company', 'corporate', 'enterprise' ),
				'defaultGoal' => 'lead-generation', 'pattern' => 'lead-trust', 'style' => 'professional-modern',
				'palette' => 'blue-trust', 'typography' => 'modern-sans', 'variance' => 4, 'density' => 5, 'motion' => 3,
				'antiPatterns' => array( 'Too many competing calls to action', 'Decorative motion without a user benefit', 'Low-contrast secondary text' ),
			),
			'home-services' => array(
				'label' => 'Home & Local Services',
				'signals' => array( 'plumber', 'plumbing', 'electrician', 'roofing', 'roof', 'damp', 'mould', 'mold', 'construction', 'contractor', 'cleaning', 'repair', 'hvac', 'pest', 'landscaping', 'local service', 'home service' ),
				'defaultGoal' => 'lead-generation', 'pattern' => 'lead-trust', 'style' => 'professional-modern',
				'palette' => 'blue-trust', 'typography' => 'modern-sans', 'variance' => 3, 'density' => 4, 'motion' => 2,
				'antiPatterns' => array( 'Hiding contact details below the fold', 'Using vague calls to action instead of quote or booking intent', 'Overly decorative effects that reduce trust' ),
			),
			'saas' => array(
				'label' => 'SaaS & Software',
				'signals' => array( 'saas', 'software', 'app', 'platform', 'dashboard', 'developer tool', 'api', 'cloud', 'ai product', 'startup' ),
				'defaultGoal' => 'signup', 'pattern' => 'saas-conversion', 'style' => 'technical-clean',
				'palette' => 'indigo-product', 'typography' => 'modern-sans', 'variance' => 5, 'density' => 6, 'motion' => 4,
				'antiPatterns' => array( 'Feature dumping without a clear outcome', 'Generic AI gradients as the only brand signal', 'Long animations that delay product understanding' ),
			),
			'ecommerce' => array(
				'label' => 'E-commerce',
				'signals' => array( 'ecommerce', 'e-commerce', 'online store', 'shop', 'store', 'product catalog', 'woocommerce', 'retail', 'fashion store' ),
				'defaultGoal' => 'commerce', 'pattern' => 'commerce-discovery', 'style' => 'commerce-clean',
				'palette' => 'commerce-neutral', 'typography' => 'commerce-sans', 'variance' => 4, 'density' => 6, 'motion' => 3,
				'antiPatterns' => array( 'Hiding price or purchase intent', 'Inconsistent product-card hierarchy', 'Motion that interferes with product comparison' ),
			),
			'healthcare' => array(
				'label' => 'Healthcare',
				'signals' => array( 'clinic', 'medical', 'healthcare', 'health', 'doctor', 'dental', 'dentist', 'therapy', 'therapist', 'pharmacy', 'veterinary', 'hospital' ),
				'defaultGoal' => 'booking', 'pattern' => 'booking-trust', 'style' => 'calm-clinical',
				'palette' => 'health-calm', 'typography' => 'humanist-sans', 'variance' => 2, 'density' => 4, 'motion' => 2,
				'antiPatterns' => array( 'Aggressive urgency for sensitive decisions', 'Low-contrast text', 'Dense decorative layouts that compete with care information' ),
			),
			'finance' => array(
				'label' => 'Finance & Insurance',
				'signals' => array( 'bank', 'banking', 'finance', 'financial', 'fintech', 'insurance', 'investment', 'accounting', 'wealth', 'mortgage' ),
				'defaultGoal' => 'authority', 'pattern' => 'authority-proof', 'style' => 'institutional-modern',
				'palette' => 'finance-trust', 'typography' => 'modern-sans', 'variance' => 2, 'density' => 5, 'motion' => 2,
				'antiPatterns' => array( 'Speculative visual language that weakens trust', 'Ambiguous financial claims', 'Excessive novelty in critical actions' ),
			),
			'legal' => array(
				'label' => 'Legal & Professional Services',
				'signals' => array( 'law firm', 'lawyer', 'solicitor', 'attorney', 'legal', 'consulting', 'consultant', 'professional service', 'advisory' ),
				'defaultGoal' => 'authority', 'pattern' => 'authority-proof', 'style' => 'editorial-authority',
				'palette' => 'navy-authority', 'typography' => 'editorial-serif', 'variance' => 3, 'density' => 4, 'motion' => 2,
				'antiPatterns' => array( 'Trendy effects that reduce authority', 'Long introductions before expertise is established', 'Weak contact or consultation path' ),
			),
			'hospitality' => array(
				'label' => 'Hospitality & Travel',
				'signals' => array( 'hotel', 'resort', 'travel', 'tour', 'vacation', 'holiday rental', 'airbnb', 'hospitality', 'guest house' ),
				'defaultGoal' => 'booking', 'pattern' => 'visual-booking', 'style' => 'visual-premium',
				'palette' => 'warm-premium', 'typography' => 'editorial-serif', 'variance' => 6, 'density' => 3, 'motion' => 4,
				'antiPatterns' => array( 'Small imagery for a visual purchase decision', 'Booking actions hidden behind multiple screens', 'Overlays that reduce image or text clarity' ),
			),
			'restaurant' => array(
				'label' => 'Restaurant & Food',
				'signals' => array( 'restaurant', 'cafe', 'coffee shop', 'bakery', 'bar', 'food', 'catering', 'menu', 'dining' ),
				'defaultGoal' => 'booking', 'pattern' => 'visual-booking', 'style' => 'warm-editorial',
				'palette' => 'food-warm', 'typography' => 'editorial-serif', 'variance' => 6, 'density' => 4, 'motion' => 3,
				'antiPatterns' => array( 'Menu information that is hard to scan', 'Low-quality visual hierarchy around food imagery', 'Reservation details buried in navigation' ),
			),
			'real-estate' => array(
				'label' => 'Real Estate',
				'signals' => array( 'real estate', 'property', 'properties', 'realtor', 'estate agent', 'letting', 'listing', 'homes for sale' ),
				'defaultGoal' => 'lead-generation', 'pattern' => 'listing-trust', 'style' => 'property-premium',
				'palette' => 'property-neutral', 'typography' => 'modern-sans', 'variance' => 4, 'density' => 5, 'motion' => 3,
				'antiPatterns' => array( 'Property imagery without clear metadata', 'Overly complex filtering on small screens', 'Weak enquiry path' ),
			),
			'education' => array(
				'label' => 'Education & Courses',
				'signals' => array( 'school', 'university', 'college', 'course', 'academy', 'education', 'learning', 'training', 'tutor', 'bootcamp' ),
				'defaultGoal' => 'enrollment', 'pattern' => 'education-outcomes', 'style' => 'friendly-structured',
				'palette' => 'education-bright', 'typography' => 'humanist-sans', 'variance' => 5, 'density' => 5, 'motion' => 3,
				'antiPatterns' => array( 'Curriculum before outcomes are clear', 'Dense copy without progressive disclosure', 'Unclear enrollment path' ),
			),
			'creative' => array(
				'label' => 'Creative, Agency & Portfolio',
				'signals' => array( 'agency', 'portfolio', 'designer', 'design studio', 'creative studio', 'photographer', 'photography', 'architect', 'branding', 'artist' ),
				'defaultGoal' => 'portfolio', 'pattern' => 'portfolio-story', 'style' => 'bold-editorial',
				'palette' => 'creative-contrast', 'typography' => 'editorial-sans', 'variance' => 8, 'density' => 4, 'motion' => 5,
				'antiPatterns' => array( 'Visual novelty without readable project context', 'Animation that blocks portfolio browsing', 'Inconsistent case-study structure' ),
			),
			'beauty-wellness' => array(
				'label' => 'Beauty & Wellness',
				'signals' => array( 'spa', 'salon', 'beauty', 'wellness', 'massage', 'skincare', 'yoga', 'meditation', 'aesthetic clinic' ),
				'defaultGoal' => 'booking', 'pattern' => 'visual-booking', 'style' => 'soft-wellness',
				'palette' => 'wellness-soft', 'typography' => 'elegant-serif', 'variance' => 5, 'density' => 3, 'motion' => 3,
				'antiPatterns' => array( 'Harsh high-frequency motion', 'Overly clinical presentation for a relaxation-led brand', 'Weak booking visibility' ),
			),
			'nonprofit' => array(
				'label' => 'Nonprofit & Community',
				'signals' => array( 'nonprofit', 'non-profit', 'charity', 'foundation', 'community', 'donation', 'donate', 'ngo', 'fundraising' ),
				'defaultGoal' => 'fundraising', 'pattern' => 'mission-impact', 'style' => 'human-impact',
				'palette' => 'impact-warm', 'typography' => 'humanist-sans', 'variance' => 4, 'density' => 4, 'motion' => 2,
				'antiPatterns' => array( 'Mission statements without measurable impact', 'Donation flows hidden behind generic navigation', 'Emotion without evidence or accountability' ),
			),
		);
	}

	public static function goals() {
		return array(
			'lead-generation' => array( 'label' => 'Lead Generation', 'signals' => array( 'lead', 'quote', 'estimate', 'enquiry', 'inquiry', 'contact', 'call us', 'local service' ), 'pattern' => 'lead-trust' ),
			'signup' => array( 'label' => 'Signup / Demo', 'signals' => array( 'signup', 'sign up', 'free trial', 'demo', 'request demo', 'start free' ), 'pattern' => 'saas-conversion' ),
			'booking' => array( 'label' => 'Booking', 'signals' => array( 'book', 'booking', 'appointment', 'reservation', 'schedule' ), 'pattern' => 'booking-trust' ),
			'commerce' => array( 'label' => 'Commerce', 'signals' => array( 'shop', 'buy', 'purchase', 'cart', 'checkout', 'product sales', 'store' ), 'pattern' => 'commerce-discovery' ),
			'authority' => array( 'label' => 'Authority & Trust', 'signals' => array( 'trust', 'authority', 'expert', 'expertise', 'consultation', 'professional' ), 'pattern' => 'authority-proof' ),
			'enrollment' => array( 'label' => 'Enrollment', 'signals' => array( 'enroll', 'enrol', 'admission', 'apply', 'course signup', 'student' ), 'pattern' => 'education-outcomes' ),
			'portfolio' => array( 'label' => 'Portfolio & Enquiry', 'signals' => array( 'portfolio', 'case study', 'case studies', 'work', 'projects', 'creative' ), 'pattern' => 'portfolio-story' ),
			'fundraising' => array( 'label' => 'Fundraising', 'signals' => array( 'donate', 'donation', 'fundraising', 'support our', 'campaign' ), 'pattern' => 'mission-impact' ),
			'content' => array( 'label' => 'Content Discovery', 'signals' => array( 'blog', 'news', 'magazine', 'articles', 'content', 'newsletter' ), 'pattern' => 'content-discovery' ),
		);
	}

	public static function patterns() {
		return array(
			'lead-trust' => array( 'label' => 'Hero + Trust + Services + Proof', 'sections' => array( 'hero', 'trust', 'services', 'process', 'testimonials', 'faq', 'cta' ) ),
			'saas-conversion' => array( 'label' => 'Product-led SaaS Conversion', 'sections' => array( 'hero', 'social-proof', 'features', 'product-demo', 'integrations', 'pricing', 'faq', 'cta' ) ),
			'booking-trust' => array( 'label' => 'Service + Trust + Booking', 'sections' => array( 'hero', 'services', 'trust', 'process', 'testimonials', 'booking', 'faq', 'cta' ) ),
			'visual-booking' => array( 'label' => 'Visual Story + Booking', 'sections' => array( 'hero', 'experience', 'gallery', 'services', 'social-proof', 'booking', 'faq', 'cta' ) ),
			'commerce-discovery' => array( 'label' => 'Commerce Discovery', 'sections' => array( 'hero', 'categories', 'featured-products', 'benefits', 'social-proof', 'faq', 'cta' ) ),
			'authority-proof' => array( 'label' => 'Authority + Evidence + Consultation', 'sections' => array( 'hero', 'expertise', 'proof', 'services', 'case-studies', 'testimonials', 'faq', 'contact' ) ),
			'listing-trust' => array( 'label' => 'Listings + Trust + Enquiry', 'sections' => array( 'hero', 'search', 'featured-listings', 'areas', 'proof', 'testimonials', 'faq', 'cta' ) ),
			'education-outcomes' => array( 'label' => 'Outcomes + Curriculum + Enrollment', 'sections' => array( 'hero', 'outcomes', 'programs', 'curriculum', 'proof', 'testimonials', 'faq', 'cta' ) ),
			'portfolio-story' => array( 'label' => 'Portfolio Story', 'sections' => array( 'hero', 'featured-work', 'services', 'process', 'case-studies', 'testimonials', 'about', 'cta' ) ),
			'mission-impact' => array( 'label' => 'Mission + Impact + Support', 'sections' => array( 'hero', 'mission', 'impact', 'programs', 'stories', 'trust', 'donate', 'faq' ) ),
			'content-discovery' => array( 'label' => 'Content Discovery', 'sections' => array( 'hero', 'featured-content', 'categories', 'content-grid', 'newsletter' ) ),
		);
	}

	public static function styles() {
		return array(
			'professional-modern' => array( 'label' => 'Professional Modern', 'keywords' => array( 'clean', 'trustworthy', 'structured' ), 'radius' => array( 'card' => '12px', 'button' => '7px' ), 'shadow' => 'subtle', 'border' => 'soft' ),
			'technical-clean' => array( 'label' => 'Technical Clean', 'keywords' => array( 'product-led', 'precise', 'modern' ), 'radius' => array( 'card' => '14px', 'button' => '8px' ), 'shadow' => 'subtle', 'border' => 'defined' ),
			'commerce-clean' => array( 'label' => 'Commerce Clean', 'keywords' => array( 'product-first', 'scannable', 'neutral' ), 'radius' => array( 'card' => '10px', 'button' => '6px' ), 'shadow' => 'minimal', 'border' => 'defined' ),
			'calm-clinical' => array( 'label' => 'Calm Clinical', 'keywords' => array( 'clear', 'reassuring', 'accessible' ), 'radius' => array( 'card' => '14px', 'button' => '8px' ), 'shadow' => 'minimal', 'border' => 'soft' ),
			'institutional-modern' => array( 'label' => 'Institutional Modern', 'keywords' => array( 'stable', 'credible', 'restrained' ), 'radius' => array( 'card' => '8px', 'button' => '6px' ), 'shadow' => 'minimal', 'border' => 'defined' ),
			'editorial-authority' => array( 'label' => 'Editorial Authority', 'keywords' => array( 'editorial', 'credible', 'premium' ), 'radius' => array( 'card' => '6px', 'button' => '4px' ), 'shadow' => 'minimal', 'border' => 'defined' ),
			'visual-premium' => array( 'label' => 'Visual Premium', 'keywords' => array( 'immersive', 'spacious', 'premium' ), 'radius' => array( 'card' => '16px', 'button' => '8px' ), 'shadow' => 'soft', 'border' => 'soft' ),
			'warm-editorial' => array( 'label' => 'Warm Editorial', 'keywords' => array( 'warm', 'visual', 'human' ), 'radius' => array( 'card' => '12px', 'button' => '7px' ), 'shadow' => 'soft', 'border' => 'soft' ),
			'property-premium' => array( 'label' => 'Property Premium', 'keywords' => array( 'spacious', 'refined', 'image-led' ), 'radius' => array( 'card' => '10px', 'button' => '6px' ), 'shadow' => 'subtle', 'border' => 'soft' ),
			'friendly-structured' => array( 'label' => 'Friendly Structured', 'keywords' => array( 'approachable', 'clear', 'energetic' ), 'radius' => array( 'card' => '14px', 'button' => '8px' ), 'shadow' => 'soft', 'border' => 'soft' ),
			'bold-editorial' => array( 'label' => 'Bold Editorial', 'keywords' => array( 'expressive', 'asymmetric', 'portfolio' ), 'radius' => array( 'card' => '8px', 'button' => '4px' ), 'shadow' => 'selective', 'border' => 'defined' ),
			'soft-wellness' => array( 'label' => 'Soft Wellness', 'keywords' => array( 'calm', 'organic', 'premium' ), 'radius' => array( 'card' => '20px', 'button' => '999px' ), 'shadow' => 'soft', 'border' => 'soft' ),
			'human-impact' => array( 'label' => 'Human Impact', 'keywords' => array( 'human', 'hopeful', 'evidence-led' ), 'radius' => array( 'card' => '12px', 'button' => '7px' ), 'shadow' => 'soft', 'border' => 'soft' ),
		);
	}

	public static function palettes() {
		return array(
			'blue-trust' => array( 'primary' => '#0B4F8A', 'onPrimary' => '#FFFFFF', 'secondary' => '#2D6EA3', 'accent' => '#E87924', 'onAccent' => '#1B130C', 'background' => '#F5F8FC', 'surface' => '#FFFFFF', 'foreground' => '#172033', 'muted' => '#667085', 'border' => '#D7DEE8', 'focus' => '#2563EB', 'destructive' => '#B42318' ),
			'indigo-product' => array( 'primary' => '#4F46E5', 'onPrimary' => '#FFFFFF', 'secondary' => '#2563EB', 'accent' => '#06B6D4', 'onAccent' => '#082F49', 'background' => '#F8FAFC', 'surface' => '#FFFFFF', 'foreground' => '#0F172A', 'muted' => '#64748B', 'border' => '#E2E8F0', 'focus' => '#4F46E5', 'destructive' => '#DC2626' ),
			'commerce-neutral' => array( 'primary' => '#111827', 'onPrimary' => '#FFFFFF', 'secondary' => '#475569', 'accent' => '#EA580C', 'onAccent' => '#FFFFFF', 'background' => '#F8FAFC', 'surface' => '#FFFFFF', 'foreground' => '#111827', 'muted' => '#64748B', 'border' => '#E2E8F0', 'focus' => '#2563EB', 'destructive' => '#DC2626' ),
			'health-calm' => array( 'primary' => '#0F766E', 'onPrimary' => '#FFFFFF', 'secondary' => '#0369A1', 'accent' => '#D97706', 'onAccent' => '#FFFFFF', 'background' => '#F7FBFA', 'surface' => '#FFFFFF', 'foreground' => '#15312F', 'muted' => '#5E7774', 'border' => '#D7E6E3', 'focus' => '#0F766E', 'destructive' => '#B42318' ),
			'finance-trust' => array( 'primary' => '#123B64', 'onPrimary' => '#FFFFFF', 'secondary' => '#315D82', 'accent' => '#B58A2A', 'onAccent' => '#1F1605', 'background' => '#F7F9FC', 'surface' => '#FFFFFF', 'foreground' => '#142536', 'muted' => '#607080', 'border' => '#D8E0E8', 'focus' => '#1D4ED8', 'destructive' => '#B42318' ),
			'navy-authority' => array( 'primary' => '#18324A', 'onPrimary' => '#FFFFFF', 'secondary' => '#5A6F82', 'accent' => '#9C7B3C', 'onAccent' => '#FFFFFF', 'background' => '#F7F5F1', 'surface' => '#FFFFFF', 'foreground' => '#1E2933', 'muted' => '#6B7280', 'border' => '#D8D3CB', 'focus' => '#1D4ED8', 'destructive' => '#B42318' ),
			'warm-premium' => array( 'primary' => '#46372E', 'onPrimary' => '#FFFFFF', 'secondary' => '#77685E', 'accent' => '#B9884F', 'onAccent' => '#1E1510', 'background' => '#F8F4EE', 'surface' => '#FFFDF9', 'foreground' => '#2C2520', 'muted' => '#746A63', 'border' => '#E4D9CC', 'focus' => '#7C5C3B', 'destructive' => '#A9362B' ),
			'food-warm' => array( 'primary' => '#6B2D1A', 'onPrimary' => '#FFFFFF', 'secondary' => '#7C5A3A', 'accent' => '#D49B32', 'onAccent' => '#241A08', 'background' => '#FBF6EE', 'surface' => '#FFFFFF', 'foreground' => '#2E241D', 'muted' => '#79695D', 'border' => '#E8DCCB', 'focus' => '#8A3D26', 'destructive' => '#B42318' ),
			'property-neutral' => array( 'primary' => '#263B45', 'onPrimary' => '#FFFFFF', 'secondary' => '#61747D', 'accent' => '#B27A42', 'onAccent' => '#FFFFFF', 'background' => '#F5F6F4', 'surface' => '#FFFFFF', 'foreground' => '#1C2B31', 'muted' => '#69777D', 'border' => '#D9DFDD', 'focus' => '#2563EB', 'destructive' => '#B42318' ),
			'education-bright' => array( 'primary' => '#3157B7', 'onPrimary' => '#FFFFFF', 'secondary' => '#0F766E', 'accent' => '#F59E0B', 'onAccent' => '#2D2105', 'background' => '#F7F9FF', 'surface' => '#FFFFFF', 'foreground' => '#17213B', 'muted' => '#65708A', 'border' => '#DDE3F1', 'focus' => '#3157B7', 'destructive' => '#C2413A' ),
			'creative-contrast' => array( 'primary' => '#141414', 'onPrimary' => '#FFFFFF', 'secondary' => '#4B5563', 'accent' => '#FF5A3C', 'onAccent' => '#1B0B08', 'background' => '#F6F4EF', 'surface' => '#FFFFFF', 'foreground' => '#141414', 'muted' => '#686868', 'border' => '#D8D4CC', 'focus' => '#2563EB', 'destructive' => '#C2413A' ),
			'wellness-soft' => array( 'primary' => '#7A5C64', 'onPrimary' => '#FFFFFF', 'secondary' => '#72856F', 'accent' => '#C99B65', 'onAccent' => '#281C10', 'background' => '#FBF7F4', 'surface' => '#FFFFFF', 'foreground' => '#322A2D', 'muted' => '#786E72', 'border' => '#E7DDE0', 'focus' => '#7A5C64', 'destructive' => '#A63D40' ),
			'impact-warm' => array( 'primary' => '#255C4A', 'onPrimary' => '#FFFFFF', 'secondary' => '#486B61', 'accent' => '#D97706', 'onAccent' => '#FFFFFF', 'background' => '#F8FAF7', 'surface' => '#FFFFFF', 'foreground' => '#1F312B', 'muted' => '#66756F', 'border' => '#DCE5E1', 'focus' => '#2563EB', 'destructive' => '#B42318' ),
		);
	}

	public static function typography() {
		return array(
			'modern-sans' => array( 'label' => 'Modern Sans', 'heading' => 'Inter, ui-sans-serif, system-ui, sans-serif', 'body' => 'Inter, ui-sans-serif, system-ui, sans-serif', 'headingWeight' => '700', 'bodyWeight' => '400' ),
			'commerce-sans' => array( 'label' => 'Commerce Sans', 'heading' => 'Arial, Helvetica, sans-serif', 'body' => 'Arial, Helvetica, sans-serif', 'headingWeight' => '700', 'bodyWeight' => '400' ),
			'humanist-sans' => array( 'label' => 'Humanist Sans', 'heading' => '"Trebuchet MS", Arial, sans-serif', 'body' => 'Arial, Helvetica, sans-serif', 'headingWeight' => '700', 'bodyWeight' => '400' ),
			'editorial-serif' => array( 'label' => 'Editorial Serif', 'heading' => 'Georgia, "Times New Roman", serif', 'body' => 'Arial, Helvetica, sans-serif', 'headingWeight' => '700', 'bodyWeight' => '400' ),
			'editorial-sans' => array( 'label' => 'Editorial Sans', 'heading' => '"Arial Narrow", Arial, sans-serif', 'body' => 'Arial, Helvetica, sans-serif', 'headingWeight' => '800', 'bodyWeight' => '400' ),
			'elegant-serif' => array( 'label' => 'Elegant Serif', 'heading' => 'Georgia, "Times New Roman", serif', 'body' => '"Trebuchet MS", Arial, sans-serif', 'headingWeight' => '600', 'bodyWeight' => '400' ),
		);
	}

	public static function section_widgets() {
		return array(
			'hero' => array( 'container', 'heading', 'text', 'button', 'image' ),
			'trust' => array( 'logo-grid', 'stats-card', 'icon-list' ),
			'social-proof' => array( 'logo-grid', 'testimonial-carousel', 'stats-card' ),
			'services' => array( 'nested-card', 'icon-list', 'cta' ),
			'features' => array( 'nested-card', 'icon-list', 'nested-tabs' ),
			'process' => array( 'steps', 'icon-list' ),
			'testimonials' => array( 'testimonial-carousel', 'testimonial', 'carousel' ),
			'faq' => array( 'faq', 'nested-accordion', 'accordion' ),
			'cta' => array( 'cta', 'button' ),
			'contact' => array( 'form', 'cta', 'map' ),
			'booking' => array( 'form', 'cta' ),
			'pricing' => array( 'pricing-table', 'comparison-table' ),
			'product-demo' => array( 'video-popup', 'image', 'tabs' ),
			'integrations' => array( 'logo-grid', 'icon-list' ),
			'categories' => array( 'filterable-grid', 'nested-card' ),
			'featured-products' => array( 'woo-products', 'loop-grid' ),
			'benefits' => array( 'icon-list', 'nested-card' ),
			'expertise' => array( 'stats-card', 'icon-list', 'team-member' ),
			'proof' => array( 'stats-card', 'testimonial-carousel', 'logo-grid' ),
			'case-studies' => array( 'filterable-grid', 'loop-grid', 'nested-card' ),
			'search' => array( 'site-search', 'form' ),
			'featured-listings' => array( 'loop-grid', 'filterable-grid' ),
			'areas' => array( 'map', 'icon-list' ),
			'outcomes' => array( 'stats-card', 'icon-list' ),
			'programs' => array( 'nested-card', 'tabs' ),
			'curriculum' => array( 'nested-tabs', 'accordion' ),
			'featured-work' => array( 'filterable-grid', 'gallery', 'nested-card' ),
			'about' => array( 'team-member', 'image', 'text' ),
			'experience' => array( 'gallery', 'video-popup', 'nested-card' ),
			'gallery' => array( 'gallery', 'before-after', 'video-popup' ),
			'mission' => array( 'heading', 'text', 'image', 'stats-card' ),
			'impact' => array( 'stats-card', 'timeline', 'steps' ),
			'stories' => array( 'testimonial-carousel', 'loop-grid' ),
			'donate' => array( 'cta', 'button', 'form' ),
			'featured-content' => array( 'loop-grid', 'nested-card' ),
			'content-grid' => array( 'loop-grid', 'filterable-grid' ),
			'newsletter' => array( 'form', 'cta' ),
		);
	}

	public static function summary() {
		return array(
			'schema' => 'cresco-design-intelligence-catalog/v1',
			'industries' => array_map( static function ( $item ) { return $item['label']; }, self::industries() ),
			'goals' => array_map( static function ( $item ) { return $item['label']; }, self::goals() ),
			'patterns' => array_map( static function ( $item ) { return $item['label']; }, self::patterns() ),
			'styles' => array_map( static function ( $item ) { return $item['label']; }, self::styles() ),
		);
	}

	private function __construct() {}
}
