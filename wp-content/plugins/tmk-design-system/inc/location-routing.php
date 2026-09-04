<?php
/**
 * Location routing — the terminal Location page's URL + template wiring.
 *
 * NESTED PERMALINK  /ristoranti/{city}/{location}/  (IA §2 sitemap; the "custom WP rewrite logic"
 * PROJECT_VALIDATION_REPORT §8.1 flagged as the real schedule risk). The `location` CPT registers a
 * single-segment /ristoranti/{location}/ by default; here the canonical permalink gains the city
 * segment via post_type_link, and a matching top-priority rewrite rule resolves the two-segment URL
 * back to the location post. The single-segment default rule is left in place as a graceful fallback,
 * so an old /ristoranti/{location}/ link never 404s. The city segment is not validated in the rule —
 * the location slug is unique and resolves on its own; a wrong city segment could be canonical-
 * redirected later, but that belongs with the City page (a separate, not-yet-built deliverable).
 *
 * TEMPLATE  A CPT single has no Elementor Theme Builder document, and Hello Elementor's own single.php
 * would wrap the body in a constrained .site-main with the post title — breaking the full-bleed hero.
 * So this ships a plugin-owned full-width template (inc/templates/single-location.php) that renders
 * only the shell header/footer locations + the [tmk_location] shortcode inside <main id="main"> (the
 * skip-link target). The theme stays unmodified — the same portability contract as the rest of the
 * plugin — and the whole location body is one dynamic, data-driven render, so there is nothing to
 * hand-edit in a page builder anyway (the same accepted trade-off as the Header/Footer shortcodes,
 * PROJECT_CONTEXT §4 / decision 13).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** Canonical permalink: /ristoranti/{city}/{location}/ (falls back to the CPT default if no city). */
add_filter( 'post_type_link', function ( $link, $post ) {
	if ( ! $post instanceof WP_Post || 'location' !== $post->post_type ) {
		return $link;
	}
	$terms = get_the_terms( $post->ID, 'city' );
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return $link;
	}
	return home_url( user_trailingslashit( 'ristoranti/' . $terms[0]->slug . '/' . $post->post_name ) );
}, 10, 2 );

/** Resolve the two-segment nested URL to the location post. Registered before the plugin's
 *  version-gated flush_rewrite_rules() (tmk-design-system.php, init priority 20). */
add_action( 'init', function () {
	add_rewrite_rule( '^ristoranti/([^/]+)/([^/]+)/?$', 'index.php?location=$matches[2]', 'top' );
}, 10 );

/**
 * Location single template.
 *
 * Session 9 (Elementor-first): the Location single is now an Elementor Pro Theme Builder Single
 * template (display condition: singular/location), so Elementor renders it via
 * elementor_theme_do_location('single') and the client edits the layout visually. The plugin's own
 * full-width template is therefore only a FALLBACK for an environment WITHOUT Elementor Pro (so a
 * location never renders through Hello Elementor's bare default). With Pro active — always, here —
 * this filter yields and the Theme Builder template wins. The single-location.php file + [tmk_location]
 * shortcode are retained for that fallback and for any direct shortcode use.
 */
add_filter( 'template_include', function ( $template ) {
	if ( is_singular( 'location' ) && ! defined( 'ELEMENTOR_PRO_VERSION' ) ) {
		$custom = TMK_DS_PATH . 'inc/templates/single-location.php';
		if ( file_exists( $custom ) ) {
			return $custom;
		}
	}
	return $template;
}, 99 );
