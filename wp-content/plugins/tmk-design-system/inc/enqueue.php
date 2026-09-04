<?php
/**
 * TMK Design System — the single enforced dependency chain for the whole plugin:
 *
 *   tokens -> base -> utilities -> animations -> components -> layout -> pages
 *
 * Tokens never reference pages. Components never reference pages. Pages consume components.
 * Components consume tokens. The chain below is a literal enforcement of that rule: every
 * later layer's stylesheets/scripts depend on the full chain before them, so it is structurally
 * impossible to load a component before its tokens, or a page before its layout.
 *
 * Before adding a new file, ask which layer it belongs to:
 *   tokens      — pure custom properties, no selectors (css/tokens/)
 *   base        — element resets / default typography, tokens only (css/base/)
 *   utilities   — small single-purpose classes, no component identity (css/utilities/)
 *   animations  — shared, cross-component motion (reveal/stagger), not a single component's
 *                 own @keyframes — those stay co-located with the component that owns them
 *                 (e.g. Drawer's slide-in keyframes live in css/components/drawer.css) (css/animations/)
 *   components  — reusable UI widgets, consumed by many pages/layouts (css/components/)
 *   layout      — singleton page-shell regions that compose components (Header, Footer, Container) (css/layout/)
 *   pages       — page-specific rules only when a page genuinely can't be built from the above (css/pages/, currently empty)
 *
 * JS mirrors this: js/animations (shared motion), js/interactions (generic, reusable —
 * Drawer/Accordion), js/navigation (header/nav-specific), js/components (single-component
 * behaviour, e.g. Newsletter). Loaded on the public site AND inside the Elementor editor
 * preview, so what a builder sees while editing always matches the live front end.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function tmk_ds_token_handles() {
	return array(
		'tmk-fonts'      => 'css/tokens/fonts.css',
		'tmk-colors'     => 'css/tokens/colors.css',
		'tmk-typography' => 'css/tokens/typography.css',
		'tmk-spacing'    => 'css/tokens/spacing.css',
		'tmk-layout-tok' => 'css/tokens/layout.css',
		'tmk-elevation'  => 'css/tokens/elevation.css',
		'tmk-motion-tok' => 'css/tokens/motion.css',
	);
}

function tmk_ds_base_handles() {
	return array(
		'tmk-base' => 'css/base/base.css',
	);
}

function tmk_ds_utility_handles() {
	return array(
		'tmk-a11y' => 'css/utilities/accessibility.css',
	);
}

function tmk_ds_animation_handles() {
	return array(
		'tmk-reveal-css' => 'css/animations/reveal.css',
	);
}

/** Reusable UI components — consumed by layouts and pages, never the other way around. */
function tmk_ds_component_handles() {
	return array(
		'tmk-cta'        => 'css/components/cta.css',
		'tmk-accordion'  => 'css/components/accordion.css',
		'tmk-drawer'     => 'css/components/drawer.css',
		'tmk-logo'         => 'css/components/logo.css',
		'tmk-newsletter'   => 'css/components/newsletter.css',
		'tmk-hero'         => 'css/components/hero.css',
		'tmk-narrative'    => 'css/components/narrative-band.css',
		'tmk-card'         => 'css/components/card.css',
		'tmk-decor'        => 'css/components/decor.css',
		'tmk-trust-band'   => 'css/components/trust-band.css',
		'tmk-cta-banner'   => 'css/components/cta-banner.css',
		'tmk-modal'        => 'css/components/modal.css',
		'tmk-breadcrumb'   => 'css/components/breadcrumb.css',
		'tmk-gallery'      => 'css/components/gallery.css',
		'tmk-map-block'    => 'css/components/map-block.css',
		'tmk-practical'    => 'css/components/practical-info-band.css',
		'tmk-booking'      => 'css/components/booking-widget.css',
		'tmk-system-state' => 'css/components/system-state.css',
		'tmk-form'         => 'css/components/form.css',
		'tmk-timeline'     => 'css/components/timeline.css',
	);
}

/** Singleton page-shell regions — compose components, never redefine their styles. */
function tmk_ds_layout_handles() {
	return array(
		'tmk-container' => 'css/layout/container.css',
		'tmk-header'    => 'css/layout/header.css',
		'tmk-footer'    => 'css/layout/footer.css',
	);
}

/**
 * Page-specific overrides — intentionally empty. A page should be buildable entirely from
 * tokens/base/utilities/components/layout; only add an entry here when something is genuinely
 * one-page-only, and prefer promoting it to css/components/ the moment a second page needs it.
 */
function tmk_ds_page_handles() {
	return array(
		'tmk-page-home' => 'css/pages/homepage.css',
		'tmk-page-menu'  => 'css/pages/menu.css',
		'tmk-page-location' => 'css/pages/location.css',
		'tmk-page-city'     => 'css/pages/city.css',
		'tmk-page-story'    => 'css/pages/story.css',
		'tmk-page-article'  => 'css/pages/article.css',
		'tmk-page-event'    => 'css/pages/event.css',
		'tmk-page-careers'  => 'css/pages/careers.css',
		'tmk-page-heritage' => 'css/pages/heritage.css',
		'tmk-page-contact'  => 'css/pages/contact.css',
		'tmk-page-shop'     => 'css/pages/shop.css',
		'tmk-page-legal'    => 'css/pages/legal.css',
		'tmk-page-system'   => 'css/pages/system.css',
	);
}

/**
 * Refinements — the TERMINAL layer, printed after every design-system layer above.
 *
 * Port of Claude Design's shell/refinements.css, which the design source itself loads LAST,
 * after the whole design system: site-wide shell chrome behaviour, motion and visual polish
 * that deliberately sits on top of the components it refines. It is a layer of its own rather
 * than edits inside components/ or layout/ because its rules are cross-cutting (they touch the
 * header, the heroes and the page background at once) and because being last is the whole
 * mechanism — it wins on source order, so it needs no specificity war and almost no !important.
 *
 * What belongs here: shell-level behaviour (sticky/condensing chrome, page decor, search
 * overlay), shared motion, and polish that is a directorial pass over the system.
 * What does NOT belong here: anything a single component owns (that goes in its own file),
 * any new custom property (that goes in css/tokens/), and any page-only composition (that
 * goes in css/pages/). Nothing above may depend on this layer.
 */
function tmk_ds_refinement_handles() {
	return array(
		'tmk-refinements' => 'css/refinements/shell.css',
	);
}

function tmk_ds_enqueue_style_chain( $handles, $dep ) {
	foreach ( $handles as $handle => $rel_path ) {
		wp_register_style( $handle, TMK_DS_URL . $rel_path, $dep ? array( $dep ) : array(), TMK_DS_VERSION );
		wp_enqueue_style( $handle );
		$dep = $handle;
	}
	return $dep;
}

function tmk_ds_enqueue_design_system() {
	$dep = null;
	$dep = tmk_ds_enqueue_style_chain( tmk_ds_token_handles(), $dep );
	$dep = tmk_ds_enqueue_style_chain( tmk_ds_base_handles(), $dep );
	$dep = tmk_ds_enqueue_style_chain( tmk_ds_utility_handles(), $dep );
	$dep = tmk_ds_enqueue_style_chain( tmk_ds_animation_handles(), $dep );
	$dep = tmk_ds_enqueue_style_chain( tmk_ds_component_handles(), $dep );
	$dep = tmk_ds_enqueue_style_chain( tmk_ds_layout_handles(), $dep );
	$dep = tmk_ds_enqueue_style_chain( tmk_ds_page_handles(), $dep );
	$dep = tmk_ds_enqueue_style_chain( tmk_ds_refinement_handles(), $dep );

	// JS — animations/interactions are generic and reusable; navigation/components are
	// specific to the one region/widget they name.
	wp_register_script( 'tmk-reveal-js', TMK_DS_URL . 'js/animations/reveal.js', array(), TMK_DS_VERSION, true );
	wp_enqueue_script( 'tmk-reveal-js' );

	wp_register_script( 'tmk-drawer-js', TMK_DS_URL . 'js/interactions/drawer.js', array(), TMK_DS_VERSION, true );
	wp_enqueue_script( 'tmk-drawer-js' );

	wp_register_script( 'tmk-accordion-js', TMK_DS_URL . 'js/interactions/accordion.js', array(), TMK_DS_VERSION, true );
	wp_enqueue_script( 'tmk-accordion-js' );

	// Modal — generic, multi-instance (open/Escape/scrim/focus-trap/focus-return). First consumer is
	// the Location page's Gallery lightbox; reusable by any future dialog. Sits in the interactions
	// layer alongside Drawer/Accordion, unconditional like the rest of the chain (it is inert with no
	// [data-tmk-modal] present).
	wp_register_script( 'tmk-modal-js', TMK_DS_URL . 'js/interactions/modal.js', array(), TMK_DS_VERSION, true );
	wp_enqueue_script( 'tmk-modal-js' );

	// Gallery carousel (client review §4) — unconditional like the rest of this chain; it is inert
	// with no [.tmk-gallery-carousel] present. Deliberately NOT dependent on the 'swiper' handle:
	// that would force swiper.js to load on every page sitewide (it's only actually enqueued,
	// via TMK_Gallery_Widget::get_script_depends(), on pages that use the gallery carousel
	// variant). The script's own `typeof window.Swiper === 'undefined'` guard no-ops safely
	// everywhere else; on pages where both ARE present, ordinary DOMContentLoaded timing already
	// guarantees swiper.js has run first.
	wp_register_script( 'tmk-carousel-js', TMK_DS_URL . 'js/interactions/carousel.js', array(), TMK_DS_VERSION, true );
	wp_enqueue_script( 'tmk-carousel-js' );

	wp_register_script( 'tmk-mega-menu-js', TMK_DS_URL . 'js/navigation/mega-menu.js', array(), TMK_DS_VERSION, true );
	wp_enqueue_script( 'tmk-mega-menu-js' );

	wp_register_script( 'tmk-newsletter-js', TMK_DS_URL . 'js/components/newsletter.js', array(), TMK_DS_VERSION, true );
	wp_enqueue_script( 'tmk-newsletter-js' );

	// Generic AJAX lead forms (Contact IntentForm, Job Application). Inert with no [data-tmk-ajax-form].
	wp_register_script( 'tmk-forms-js', TMK_DS_URL . 'js/components/forms.js', array(), TMK_DS_VERSION, true );
	wp_enqueue_script( 'tmk-forms-js' );

	// Careers Hub category filter. Same unconditional chain as every other script; it returns
	// immediately when [data-tmk-job-filter] is absent, and the markup it enhances is complete
	// without it (every role is rendered server-side; this only reveals the filter bar).
	wp_register_script( 'tmk-careers-filter-js', TMK_DS_URL . 'js/pages/careers-filter.js', array(), TMK_DS_VERSION, true );
	wp_enqueue_script( 'tmk-careers-filter-js' );

	wp_register_script( 'tmk-trust-band-js', TMK_DS_URL . 'js/components/trust-band.js', array(), TMK_DS_VERSION, true );
	wp_enqueue_script( 'tmk-trust-band-js' );

	// Menu Hub page behaviour. Enqueued in the same unconditional chain as every other script
	// rather than behind an is_page() test: the chain's determinism is the architecture, and one
	// 4.9KB file is not worth an exception to it. The script returns immediately when
	// [data-menu-hub] is absent, so it costs nothing on any other page.
	wp_register_script( 'tmk-menu-hub-js', TMK_DS_URL . 'js/pages/menu-hub.js', array(), TMK_DS_VERSION, true );
	wp_enqueue_script( 'tmk-menu-hub-js' );

	// Refinements runtime — terminal layer, mirrors css/refinements/shell.css. Declared after
	// tmk-reveal-js because it hands that observer its targets: it tags the shell's blocks with
	// the DS reveal classes at parse time, while reveal.js only starts observing on
	// DOMContentLoaded, so the tags always exist before the observer looks for them.
	wp_register_script( 'tmk-refinements-js', TMK_DS_URL . 'js/refinements/shell.js', array( 'tmk-reveal-js' ), TMK_DS_VERSION, true );
	wp_enqueue_script( 'tmk-refinements-js' );
}
/**
 * Priority 20, deliberately NOT 5.
 *
 * The design system must be the LAST word on element-level defaults. Hello Elementor's
 * style.min.css (wp_enqueue_scripts, priority 10) resets body{background-color:#fff;color:#333;
 * font-family:<system sans>} and h1,h2,h3,h4,h5,h6{font-family:inherit;font-weight:500} plus a
 * rem font-size scale, all with bare element selectors. At priority 5 the TMK chain printed
 * BEFORE it, lost every specificity tie, and the whole site rendered in the system sans at
 * weight 500 on a white surface instead of Voice/Structure on --color-surface.
 *
 * This changes only WHEN the chain is printed. The chain's INTERNAL order
 * (tokens -> base -> utilities -> animations -> components -> layout -> pages) is guaranteed by
 * the wp_register_style dependency graph in tmk_ds_enqueue_style_chain(), not by this priority.
 */
add_action( 'wp_enqueue_scripts', 'tmk_ds_enqueue_design_system', 20 );

// Load inside the Elementor editor iframe/preview too, so builders design against real tokens.
add_action( 'elementor/editor/before_enqueue_scripts', 'tmk_ds_enqueue_design_system' );
add_action( 'elementor/preview/enqueue_styles', 'tmk_ds_enqueue_design_system' );

/**
 * Preload the two above-the-fold-critical font files (WOFF2 — what real browsers actually
 * fetch, per tokens/fonts.css's src order). CSS discovery alone means the browser only starts
 * the font request after parsing the stylesheet and reaching the glyph that needs it; a preload
 * hint starts that fetch in parallel with CSS parsing, which matters for these two specifically
 * since they paint the very first thing visible: Londrina Solid Black (H1/display headlines,
 * css/base/base.css) and Basis Grotesque Regular (body text, on every page, every viewport).
 * Every other weight is used further down the page or only on some pages, so preloading them
 * would cost bandwidth on the critical path for no first-paint benefit.
 */
function tmk_ds_preload_critical_fonts() {
	$fonts = array(
		'assets/fonts/LondrinaSolid-Black.woff2',
		'assets/fonts/BasisGrotesqueArabicPro-Regular.woff2',
	);
	foreach ( $fonts as $rel_path ) {
		printf(
			'<link rel="preload" as="font" type="font/woff2" href="%s" crossorigin="anonymous">' . "\n",
			esc_url( TMK_DS_URL . $rel_path )
		);
	}
}
add_action( 'wp_head', 'tmk_ds_preload_critical_fonts', 1 );

