<?php
/**
 * Static content Pages routing — any Page flagged with meta `_tmk_template = fullwidth` renders
 * through the plugin's generic full-width template (inc/templates/page-fullwidth.php) instead of
 * Hello Elementor's constrained page template, so its TMK shortcode composition is full-bleed and
 * shell-wrapped. Used by Heritage, Contact, Shop. The Story Hub page keeps its own dedicated
 * template (inc/story-routing.php); this filter only matches the meta flag, so they never collide.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'template_include', function ( $template ) {
	if ( is_page() ) {
		$id  = get_queried_object_id();
		$tpl = $id ? get_post_meta( $id, '_tmk_template', true ) : '';
		$map = array(
			'fullwidth' => 'inc/templates/page-fullwidth.php',
			'legal'     => 'inc/templates/page-legal.php',
		);
		if ( isset( $map[ $tpl ] ) ) {
			$custom = TMK_DS_PATH . $map[ $tpl ];
			if ( file_exists( $custom ) ) {
				return $custom;
			}
		}
	}
	return $template;
}, 98 );
