<?php
/**
 * Events routing — the `event` CPT already registers its archive (/eventi/) and single
 * (/eventi/{slug}/) permalinks (inc/content-model.php: has_archive 'eventi', rewrite slug 'eventi'),
 * so no rewrite rules are needed here — only the full-width, shell-wrapped plugin templates that
 * replace Hello Elementor's default archive/single, mirroring inc/location-routing.php. Priority 99.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'template_include', function ( $template ) {
	if ( is_post_type_archive( 'event' ) ) {
		$custom = TMK_DS_PATH . 'inc/templates/archive-event.php';
		if ( file_exists( $custom ) ) return $custom;
	}
	if ( is_singular( 'event' ) ) {
		$custom = TMK_DS_PATH . 'inc/templates/single-event.php';
		if ( file_exists( $custom ) ) return $custom;
	}
	return $template;
}, 99 );
