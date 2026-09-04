<?php
/**
 * Mirrors the TMK semantic color/type roles into Elementor's Global Colors & Fonts
 * (the active Kit's `_elementor_page_settings`), so builders pick tokens from Elementor's
 * own UI pickers instead of typing raw values. Source of truth for every value below:
 * tokens/colors.css and tokens/typography.css (TMK Design System, Claude Design project).
 * Idempotent — safe to re-run (e.g. plugin reactivation on a new environment).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Elementor prints a live fonts.googleapis.com request for ANY font_family it doesn't
 * recognize as one of its own SYSTEM-safelisted names — it checks `Fonts::get_font_type()`
 * on every font found in a compiled Kit/page CSS file (core/files/css/base.php ~line 493),
 * regardless of whether that control was authored as "custom" or "google" in the editor UI.
 * 'Londrina Solid' happens to BE a real Google Fonts family name (includes/fonts.php), so
 * without this, every page load fetches Google's hosted copy alongside our self-hosted one —
 * Google's version ships only weight 400, conflicting with our 100/300/400/900, plus an
 * avoidable third-party request (GDPR-relevant for an EU site) for a font we already serve.
 * This is a closed, fully self-hosted design system — Google Fonts should never load here,
 * for this family or any future one — so disable it at the root via Elementor's own setting
 * rather than patch around today's one known collision.
 */
function tmk_ds_disable_elementor_google_fonts() {
	if ( '0' !== get_option( 'elementor_google_font' ) ) {
		update_option( 'elementor_google_font', '0' );
	}
}

function tmk_ds_global_colors() {
	return array(
		'system_colors' => array(
			array( '_id' => 'primary',   'title' => 'TMK — Action (ember-500)', 'color' => '#E7591D' ),
			array( '_id' => 'secondary', 'title' => 'TMK — Ink (secondary action / text)', 'color' => '#241C13' ),
			array( '_id' => 'text',      'title' => 'TMK — Text secondary (tatami-700)', 'color' => '#5C4530' ),
			array( '_id' => 'accent',    'title' => 'TMK — Accent Concept default (tatami-700, Standard)', 'color' => '#5C4530' ),
		),
		'custom_colors' => array(
			array( '_id' => 'tmk_surface',          'title' => 'TMK Surface (tatami-100)',         'color' => '#F7F1E3' ),
			array( '_id' => 'tmk_surface_raised',   'title' => 'TMK Surface raised (tatami-50)',   'color' => '#FDFBF5' ),
			array( '_id' => 'tmk_surface_sunken',   'title' => 'TMK Surface sunken (tatami-200)',  'color' => '#EFE5CF' ),
			array( '_id' => 'tmk_surface_inverse',  'title' => 'TMK Surface inverse / ink',        'color' => '#241C13' ),
			array( '_id' => 'tmk_text_primary',     'title' => 'TMK Text primary (ink)',           'color' => '#241C13' ),
			array( '_id' => 'tmk_text_inverse',     'title' => 'TMK Text inverse (tatami-50)',     'color' => '#FDFBF5' ),
			array( '_id' => 'tmk_border_default',   'title' => 'TMK Border default (tatami-300)',  'color' => '#E2D3B6' ),
			array( '_id' => 'tmk_border_strong',    'title' => 'TMK Border strong (tatami-700)',   'color' => '#5C4530' ),
			array( '_id' => 'tmk_action_hover',     'title' => 'TMK Action hover (ember-600)',     'color' => '#C74814' ),
			array( '_id' => 'tmk_action_active',    'title' => 'TMK Action active (ember-700)',    'color' => '#9E3910' ),
			array( '_id' => 'tmk_action_disabled',  'title' => 'TMK Action disabled (tatami-300)', 'color' => '#E2D3B6' ),
			array( '_id' => 'tmk_on_primary',       'title' => 'TMK Text on action (tatami-50)',   'color' => '#FDFBF5' ),
			array( '_id' => 'tmk_accent_aqua',      'title' => 'TMK Concept — Aqua (erva green)',   'color' => '#50643B' ),
			array( '_id' => 'tmk_accent_arya',      'title' => 'TMK Concept — Arya (erva green)',   'color' => '#50643B' ),
			array( '_id' => 'tmk_accent_tierra',    'title' => 'TMK Concept — Tierra (ember orange)','color' => '#C74814' ),
			array( '_id' => 'tmk_accent_fuego',     'title' => 'TMK Concept — Fuego (ember orange)', 'color' => '#C74814' ),
			array( '_id' => 'tmk_feedback_success', 'title' => 'TMK Feedback success',             'color' => '#2F7D53' ),
			array( '_id' => 'tmk_feedback_warning', 'title' => 'TMK Feedback warning',             'color' => '#B98424' ),
			array( '_id' => 'tmk_feedback_error',   'title' => 'TMK Feedback error (burnt orange)', 'color' => '#722A0D' ),
			array( '_id' => 'tmk_feedback_info',    'title' => 'TMK Feedback info (green)',         'color' => '#50643B' ),
		),
	);
}

function tmk_ds_global_typography() {
	$voice     = array( 'typography_typography' => 'custom', 'typography_font_family' => 'Londrina Solid', 'typography_letter_spacing' => array( 'unit' => 'em', 'size' => 0.01 ) );
	$structure = array( 'typography_typography' => 'custom', 'typography_font_family' => 'Basis Grotesque' );

	return array(
		'system_typography' => array(
			array_merge( array( '_id' => 'primary',   'title' => 'TMK Voice (Londrina Solid — display/headline moments)' ), $voice,     array( 'typography_font_weight' => '900', 'typography_font_size' => array( 'unit' => 'rem', 'size' => 3.052 ) ) ),
			array_merge( array( '_id' => 'secondary', 'title' => 'TMK Structure (Basis Grotesque — everything else)' ), $structure, array( 'typography_font_weight' => '400', 'typography_font_size' => array( 'unit' => 'rem', 'size' => 1 ) ) ),
			array_merge( array( '_id' => 'text',      'title' => 'TMK Body (Structure, 400, 1rem/1.55)' ), $structure,             array( 'typography_font_weight' => '400', 'typography_font_size' => array( 'unit' => 'rem', 'size' => 1 ), 'typography_line_height' => array( 'unit' => 'em', 'size' => 1.55 ) ) ),
			array_merge( array( '_id' => 'accent',    'title' => 'TMK CTA label (Structure, 700, caption, caps)' ), $structure,   array( 'typography_font_weight' => '700', 'typography_font_size' => array( 'unit' => 'rem', 'size' => 0.75 ), 'typography_letter_spacing' => array( 'unit' => 'em', 'size' => 0.08 ), 'typography_text_transform' => 'uppercase' ) ),
		),
		'custom_typography' => array(
			array_merge( array( '_id' => 'tmk_h1', 'title' => 'TMK H1 (Voice, 900)' ), $voice, array( 'typography_font_weight' => '900', 'typography_font_size' => array( 'unit' => 'rem', 'size' => 2.441 ), 'typography_line_height' => array( 'unit' => 'em', 'size' => 1.02 ) ) ),
			array_merge( array( '_id' => 'tmk_h2', 'title' => 'TMK H2 (Voice, 900)' ), $voice, array( 'typography_font_weight' => '900', 'typography_font_size' => array( 'unit' => 'rem', 'size' => 1.953 ), 'typography_line_height' => array( 'unit' => 'em', 'size' => 1.08 ) ) ),
			array_merge( array( '_id' => 'tmk_h3', 'title' => 'TMK H3 (Voice, 900)' ), $voice, array( 'typography_font_weight' => '900', 'typography_font_size' => array( 'unit' => 'rem', 'size' => 1.563 ), 'typography_line_height' => array( 'unit' => 'em', 'size' => 1.15 ) ) ),
			array_merge( array( '_id' => 'tmk_h4', 'title' => 'TMK H4 (Voice, 900)' ), $voice, array( 'typography_font_weight' => '900', 'typography_font_size' => array( 'unit' => 'rem', 'size' => 1.25 ), 'typography_line_height' => array( 'unit' => 'em', 'size' => 1.25 ) ) ),
			array_merge( array( '_id' => 'tmk_body_small', 'title' => 'TMK Body small (Structure, 400)' ), $structure, array( 'typography_font_weight' => '400', 'typography_font_size' => array( 'unit' => 'rem', 'size' => 0.875 ), 'typography_line_height' => array( 'unit' => 'em', 'size' => 1.5 ) ) ),
			array_merge( array( '_id' => 'tmk_caption', 'title' => 'TMK Caption (Structure, 400)' ), $structure, array( 'typography_font_weight' => '400', 'typography_font_size' => array( 'unit' => 'rem', 'size' => 0.75 ), 'typography_line_height' => array( 'unit' => 'em', 'size' => 1.4 ) ) ),
			array_merge( array( '_id' => 'tmk_eyebrow', 'title' => 'TMK Eyebrow (Structure, 700, caps)' ), $structure, array( 'typography_font_weight' => '700', 'typography_font_size' => array( 'unit' => 'rem', 'size' => 0.75 ), 'typography_letter_spacing' => array( 'unit' => 'em', 'size' => 0.08 ), 'typography_text_transform' => 'uppercase' ) ),
		),
	);
}

/**
 * Site Settings + Theme Style defaults, from the TMK tokens. These make Elementor's own element
 * defaults (Button, Form Field, Image widgets, content width, widget gap) match the design system,
 * so a client dropping a native widget gets an on-brand result with nothing to configure. TMK's own
 * components keep their component CSS; this governs the generic Elementor widgets.
 */
function tmk_ds_theme_style() {
	$radius_soft = array( 'unit' => 'px', 'top' => '8', 'right' => '8', 'bottom' => '8', 'left' => '8', 'isLinked' => true );
	$radius_pill = array( 'unit' => 'px', 'top' => '999', 'right' => '999', 'bottom' => '999', 'left' => '999', 'isLinked' => true );
	return array(
		// Layout — content width = TMK --container-wide (1280); default widget gap = --space-5 (24).
		'container_width'       => array( 'unit' => 'px', 'size' => 1280 ),
		'space_between_widgets' => array( 'unit' => 'px', 'size' => 24, 'column' => '24', 'row' => '24' ),

		// Buttons — ember fill, cream label, pill, Structure caps caption (the .tmk-cta register).
		'button_typography_typography'     => 'custom',
		'button_typography_font_family'    => 'Basis Grotesque',
		'button_typography_font_weight'    => '700',
		'button_typography_font_size'      => array( 'unit' => 'rem', 'size' => 0.75 ),
		'button_typography_text_transform' => 'uppercase',
		'button_typography_letter_spacing' => array( 'unit' => 'em', 'size' => 0.08 ),
		'button_background_color'          => '#E7591D',
		'button_text_color'                => '#FDFBF5',
		'button_border_radius'             => $radius_pill,
		'button_padding'                   => array( 'unit' => 'px', 'top' => '14', 'right' => '28', 'bottom' => '14', 'left' => '28', 'isLinked' => false ),
		'button_hover_background_color'    => '#9E3910',
		'button_hover_text_color'          => '#FDFBF5',

		// Images — soft radius, matching the DS.
		'image_border_radius' => $radius_soft,

		// Form fields (Elementor Pro) — raised surface, tatami border, soft radius, Structure.
		'form_field_typography_typography'  => 'custom',
		'form_field_typography_font_family' => 'Basis Grotesque',
		'form_field_typography_font_size'   => array( 'unit' => 'rem', 'size' => 1 ),
		'form_field_text_color'             => '#241C13',
		'form_field_background_color'       => '#FDFBF5',
		'form_field_border_color'           => '#E2D3B6',
		'form_field_border_width'           => array( 'unit' => 'px', 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'isLinked' => true ),
		'form_field_border_radius'          => $radius_soft,
	);
}

/**
 * Writes the above into the active Kit's page settings, preserving any settings
 * this function doesn't own (site-identity fields Elementor itself manages, etc.).
 */
function tmk_ds_sync_elementor_kit() {
	if ( ! did_action( 'elementor/loaded' ) && ! class_exists( '\Elementor\Plugin' ) ) return false;

	tmk_ds_disable_elementor_google_fonts();

	$kit_id = get_option( 'elementor_active_kit' );
	if ( ! $kit_id ) return false;

	$settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
	if ( ! is_array( $settings ) ) $settings = array();

	$colors = tmk_ds_global_colors();
	$fonts  = tmk_ds_global_typography();

	$settings['system_colors']     = $colors['system_colors'];
	$settings['custom_colors']     = $colors['custom_colors'];
	$settings['system_typography'] = $fonts['system_typography'];
	$settings['custom_typography'] = $fonts['custom_typography'];
	$settings['default_generic_fonts'] = 'sans-serif';

	// Site Settings + Theme Style so NATIVE Elementor widgets a client adds inherit the TMK look
	// (buttons, form fields, images, content width, widget spacing) without hardcoding. Values from
	// tokens/*.css. Elementor globals still own colours/fonts; this only sets the element defaults.
	foreach ( tmk_ds_theme_style() as $k => $v ) {
		$settings[ $k ] = $v;
	}

	update_post_meta( $kit_id, '_elementor_page_settings', $settings );

	// Bust Elementor's compiled CSS cache so the new globals take effect immediately.
	if ( class_exists( '\Elementor\Plugin' ) ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}
	return true;
}
register_activation_hook( TMK_DS_PATH . 'tmk-design-system.php', 'tmk_ds_sync_elementor_kit' );
