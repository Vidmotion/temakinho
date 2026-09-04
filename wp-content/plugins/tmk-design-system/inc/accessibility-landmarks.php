<?php
/**
 * Sitewide skip-link target.
 *
 * Originally this wrapped singular page content in <main id="main">. The Homepage browser
 * audit (2026-07-29) showed that produced a NESTED landmark: Hello Elementor's page template
 * already emits <main id="content" class="site-main">, so the document exposed two `main`
 * landmarks - invalid HTML and ambiguous for assistive technology. The wrapper is therefore
 * now a plain <div id="main" tabindex="-1">: the single `main` landmark stays the theme's,
 * while the TMK header's skip-link (href="#main", inc/header-render.php) keeps a real,
 * focusable target. tabindex="-1" is required so the fragment jump actually moves focus and
 * not just the scroll position.
 *
 * Hooked on `the_content` at a late priority so it wraps Elementor's own rendered output
 * rather than running before it; scoped to is_page() + in_the_loop() + is_main_query() so
 * archives, excerpts and Elementor's editor/preview requests are never double-wrapped.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/*
 * WHICH TAG, AND WHY IT IS NOW CONDITIONAL (Session 6).
 *
 * The rule above holds only while the THEME emits the main landmark. Hello Elementor's default
 * page template does. Elementor's own page templates — 'elementor_header_footer' (Elementor Full
 * Width) and 'elementor_canvas' — do not: they print the content with no <main> at all. The Menu
 * Hub is the first page on this site to use one of them (it needs the theme's content container
 * out of the way so the full-bleed Hero can actually reach the viewport edges), and on that
 * template the page shipped with ZERO main landmarks — the opposite defect to the one the note
 * above fixed, and a worse one: a skip link that lands on a plain div gives a screen-reader user
 * no way to know they have reached the content.
 *
 * So the tag follows the template: a <div> where the theme already owns <main>, a real <main>
 * where nothing else provides it. Either way there is exactly one main landmark and #main stays
 * a focusable skip-link target.
 */
add_filter( 'the_content', function ( $content ) {
	if ( is_admin() || ! is_page() || ! in_the_loop() || ! is_main_query() ) return $content;
	$elementor_templates = array( 'elementor_header_footer', 'elementor_canvas' );
	$theme_owns_main     = ! in_array( (string) get_page_template_slug(), $elementor_templates, true );
	$tag                 = $theme_owns_main ? 'div' : 'main';
	return '<' . $tag . ' id="main" tabindex="-1">' . $content . '</' . $tag . '>';
}, 100 );
