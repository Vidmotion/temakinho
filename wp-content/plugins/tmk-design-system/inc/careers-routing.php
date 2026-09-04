<?php
/**
 * Careers routing — the `job` CPT already registers its archive (/lavora-con-noi/) and single
 * (/lavora-con-noi/{slug}/), so this only swaps in the full-width, shell-wrapped plugin templates.
 * Mirrors inc/event-routing.php. Priority 99.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'template_include', function ( $template ) {
	if ( is_post_type_archive( 'job' ) ) {
		$custom = TMK_DS_PATH . 'inc/templates/archive-job.php';
		if ( file_exists( $custom ) ) return $custom;
	}
	if ( is_singular( 'job' ) ) {
		$custom = TMK_DS_PATH . 'inc/templates/single-job.php';
		if ( file_exists( $custom ) ) return $custom;
	}
	return $template;
}, 99 );
