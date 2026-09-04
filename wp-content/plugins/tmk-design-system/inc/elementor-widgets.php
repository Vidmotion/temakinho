<?php
/**
 * TMK Elementor widgets — the presentation layer's bridge to the design system.
 *
 * ARCHITECTURE (Session 9): Elementor Pro is the frontend builder; this plugin is the backend
 * engine. Each TMK design-system component is exposed here as a NATIVE Elementor widget that a
 * non-technical editor drags, moves, duplicates and edits from the panel — while render() simply
 * calls the existing tmk_render_*() function, so the produced markup, the css/ layer and the js/
 * behaviours (reveal/stagger, gallery lightbox, booking) are byte-for-byte the approved design.
 * No layout HTML is authored here; the widgets are thin control-surfaces over the renderers.
 *
 * Two data sources where it matters (Hero, Narrative, Gallery, Practical, Booking, Breadcrumb,
 * Nearby): 'manual' (the editor types the content — for marketing pages) or 'current_post' (the
 * widget reads the queried record — for the Theme Builder Single templates that render one
 * location/story/event from real CPT data). One widget serves both a hand-built page and a dynamic
 * template, which is exactly the Elementor-first split the migration calls for.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** The queried location on a Location single (for current_post mode); null otherwise. */
function tmk_current_location() {
	$o = get_queried_object();
	return ( $o instanceof WP_Post && 'location' === $o->post_type ) ? $o : null;
}

/** Location field bundle, mirroring the mapping in tmk_render_location(). */
function tmk_location_fields( $post ) {
	$id = $post->ID;
	$city_terms = wp_get_post_terms( $id, 'city' );
	$city = ( ! is_wp_error( $city_terms ) && $city_terms ) ? $city_terms[0] : null;
	$concept = '';
	$ct = wp_get_post_terms( $id, 'concept' );
	if ( ! is_wp_error( $ct ) && $ct ) {
		$token = get_term_meta( $ct[0]->term_id, 'accent_token', true );
		$concept = $token ? $token : $ct[0]->slug;
		if ( 'standard' === $concept ) $concept = '';
	}
	$booking = tmk_location_booking_href( $id );
	$logotype_id = (int) get_post_meta( $id, 'tmk_location_logotype_id', true );
	return array(
		'id'       => $id,
		'title'    => get_the_title( $post ),
		'tagline'  => (string) get_post_meta( $id, 'tagline', true ),
		'short'    => (string) get_post_meta( $id, 'short_description', true ),
		'address'  => (string) get_post_meta( $id, 'address', true ),
		'phone'    => (string) get_post_meta( $id, 'phone', true ),
		// Phase 2 (audit 2026-09-01). Verified Google place URL. Stored since the import but never
		// read, so every page fell through to the generated ?q= address search - see
		// tmk_practical_map_href(). Empty on venues with no verified URL (Linate, Fiumicino).
		'map_url'  => (string) get_post_meta( $id, 'map_url', true ),
		// 2026-09-02. "lat,lng" read from the old site's own place URL - see tmk_render_map_embed().
		'map_coords' => (string) get_post_meta( $id, 'map_coords', true ),
		// Human-readable phone exactly as the old site published it. Display only; 'phone' above
		// stays the dialable value behind the tel: href. Empty where the old site never printed
		// one - the raw value is shown then, rather than a formatting we would have invented.
		'phone_display' => (string) get_post_meta( $id, 'phone_display', true ),
		'email'    => (string) get_post_meta( $id, 'email', true ),
		'neighbourhood_note' => (string) get_post_meta( $id, 'tmk_location_neighbourhood_note', true ),
		'access'   => (string) get_post_meta( $id, 'accessibility_notes', true ),
		'hours'    => tmk_location_hours_rows( get_post_meta( $id, 'hours', true ) ),
		'gallery'  => array_values( array_filter( array_map( 'intval', (array) get_post_meta( $id, 'gallery_ids', true ) ) ) ),
		'media_id' => (int) get_post_thumbnail_id( $id ),
		'logotype_id'  => $logotype_id,
		'logotype_alt' => $logotype_id ? (string) get_post_meta( $logotype_id, '_wp_attachment_image_alt', true ) : '',
		'has_music' => (bool) get_post_meta( $id, 'tmk_location_has_live_music', true ),
		'city'     => $city,
		'concept'  => $concept,
		'booking'  => $booking,
	);
}

add_action( 'elementor/elements/categories_registered', function ( $mgr ) {
	$mgr->add_category( 'tmk', array( 'title' => 'Temakinho', 'icon' => 'eicon-food' ) );
} );

add_action( 'elementor/widgets/register', function ( $mgr ) {
	if ( ! class_exists( '\Elementor\Widget_Base' ) ) return;
	require_once __DIR__ . '/elementor/class-tmk-widgets.php';
	foreach ( tmk_elementor_widget_classes() as $cls ) {
		if ( class_exists( $cls ) ) $mgr->register( new $cls() );
	}
} );
