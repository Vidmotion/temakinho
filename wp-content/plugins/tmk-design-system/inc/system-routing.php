<?php
/**
 * System pages routing — the 404 and Search results templates. Both replace Hello Elementor's
 * defaults with full-width, shell-wrapped plugin templates (get_header()/[render]/get_footer()).
 * Priority 99, the last word. WordPress sets the 404 status before template_include runs, so the
 * plugin template keeps the correct HTTP 404 while restyling the body.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'template_include', function ( $template ) {
	if ( is_404() ) {
		$custom = TMK_DS_PATH . 'inc/templates/404.php';
		if ( file_exists( $custom ) ) return $custom;
	}
	if ( is_search() ) {
		$custom = TMK_DS_PATH . 'inc/templates/search.php';
		if ( file_exists( $custom ) ) return $custom;
	}
	return $template;
}, 99 );
