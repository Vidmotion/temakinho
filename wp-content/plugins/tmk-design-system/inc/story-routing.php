<?php
/**
 * Stories routing — the magazine's URLs + templates. Mirrors inc/location-routing.php.
 *
 * The blog IS the magazine (IA §10), so native `post`s live under /storie/{slug}/ rather than the
 * WordPress-default /{slug}/: a post_link filter rewrites the canonical permalink and a matching
 * top-priority rewrite rule resolves it back. The hub is a real Page at /storie/ (so the header nav
 * "Storie" resolves to it automatically via tmk_nav_destination()); the single-segment page and the
 * two-segment... no: the hub is one segment /storie/ and an article is /storie/{slug}/, so the
 * article rule requires a sub-segment and never shadows the hub page.
 *
 * TEMPLATES  full-width, shell-wrapped plugin templates (get_header()/[shortcode]/get_footer()),
 * the same portability contract as the Location single — Hello Elementor stays untouched and the
 * TMK header/footer come from the site-wide Elementor Theme Builder locations.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** Canonical article permalink: /storie/{slug}/. */
add_filter( 'post_link', function ( $permalink, $post ) {
	if ( $post instanceof WP_Post && 'post' === $post->post_type && 'publish' === $post->post_status ) {
		return home_url( user_trailingslashit( 'storie/' . $post->post_name ) );
	}
	return $permalink;
}, 10, 2 );

/** Resolve /storie/{slug}/ to the post. Registered before the version-gated flush (init prio 20). */
add_action( 'init', function () {
	add_rewrite_rule( '^storie/([^/]+)/?$', 'index.php?post_type=post&name=$matches[1]', 'top' );
}, 10 );

/** Full-width, shell-wrapped templates for the hub Page and the single article. Priority 99. */
add_filter( 'template_include', function ( $template ) {
	if ( is_page( 'storie' ) ) {
		$custom = TMK_DS_PATH . 'inc/templates/page-story-hub.php';
		if ( file_exists( $custom ) ) return $custom;
	}
	if ( is_singular( 'post' ) ) {
		$custom = TMK_DS_PATH . 'inc/templates/single-story.php';
		if ( file_exists( $custom ) ) return $custom;
	}
	return $template;
}, 99 );
