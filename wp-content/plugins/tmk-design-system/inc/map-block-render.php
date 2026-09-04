<?php
/**
 * Map Block - direct translation of components/content/MapBlock.jsx.
 *
 * "Location display inside Practical Info - supplementary to the textual address, never the only
 * carrier." (the component's own docblock). It is a STYLISED map, not a real one: the design
 * ships hand-drawn road curves and a pin, not an embed. That is deliberate - a third-party map
 * iframe would drag a cross-origin script, a cookie and a layout shift onto every Location page,
 * and the real, actionable address is already a maps link in the Practical Info Band next to it.
 * The 'map_embed' postmeta registered in inc/content-model.php stays unused for exactly this
 * reason; wiring it up would be a design change, not an implementation of one.
 *
 * The SVG's <pattern> id is per-instance. Two map blocks on one page would otherwise both
 * reference the same fragment id, and the second one's grid would resolve to the first one's
 * pattern - a real bug the moment a City page renders a map per venue.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function tmk_render_map_block( $atts = array() ) {
	$atts = shortcode_atts( array(
		'address' => '',
		'label'   => '',
		// Optional decorative pins: "12%,40% | 60%,72%" - x,y pairs in any CSS length.
		'pins'    => '',
	), $atts );

	static $instance = 0;
	$instance++;
	$grid_id = 'tmk-map-grid-' . $instance;

	$name = $atts['label'] ? $atts['label'] : $atts['address'];
	$pins = array();
	foreach ( array_filter( array_map( 'trim', explode( '|', $atts['pins'] ) ) ) as $pin ) {
		$xy = array_map( 'trim', explode( ',', $pin, 2 ) );
		if ( 2 === count( $xy ) ) {
			$pins[] = $xy;
		}
	}

	ob_start();
	?>
	<div class="tmk-map-block" role="img" aria-label="<?php echo esc_attr( 'Mappa: ' . $name ); ?>">
		<svg class="tmk-map-block__canvas" aria-hidden="true" focusable="false" width="100%" height="100%" preserveAspectRatio="none">
			<defs>
				<pattern id="<?php echo esc_attr( $grid_id ); ?>" width="48" height="48" patternUnits="userSpaceOnUse">
					<path class="tmk-map-block__grid" d="M48 0H0V48" fill="none" stroke-width="1" />
				</pattern>
			</defs>
			<rect width="100%" height="100%" fill="url(#<?php echo esc_attr( $grid_id ); ?>)" />
			<path class="tmk-map-block__road tmk-map-block__road--major" d="M-20 70 C 90 40, 180 130, 320 90 S 560 140, 720 80" fill="none" stroke-width="10" stroke-linecap="round" />
			<path class="tmk-map-block__road tmk-map-block__road--minor" d="M40 300 C 120 220, 260 260, 380 190 S 600 240, 760 170" fill="none" stroke-width="7" stroke-linecap="round" />
		</svg>
		<div class="tmk-map-block__marker">
			<svg class="tmk-map-block__pin" aria-hidden="true" focusable="false" width="34" height="34" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
				<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0" />
				<circle cx="12" cy="10" r="3" />
			</svg>
			<?php if ( $atts['label'] ) : ?>
				<span class="tmk-map-block__label"><?php echo esc_html( $atts['label'] ); ?></span>
			<?php endif; ?>
		</div>
		<?php foreach ( $pins as $pin ) : ?>
			<span class="tmk-map-block__dot" style="left:<?php echo esc_attr( $pin[0] ); ?>;top:<?php echo esc_attr( $pin[1] ); ?>">
				<svg aria-hidden="true" focusable="false" width="22" height="22" viewBox="0 0 24 24">
					<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0" />
				</svg>
			</span>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
}
/**
 * Real, interactive Google Maps embed for a venue - added 2026-09-02, requested by Gianluca.
 *
 * WHY THIS EXISTS ALONGSIDE tmk_render_map_block() ABOVE
 *   The block above is a hand-drawn SVG: a grid, two fixed bezier "roads" and a pin centred by
 *   flexbox. It carries NO geographic data at all - the same drawing renders on all 14 restaurant
 *   pages, which is why the position read as generic. It was never wrong, it was never a map.
 *   That component is left untouched because inc/event-render.php still uses it (events are
 *   outside this task's perimeter, so their output must not change).
 *
 * PRECISION - where the coordinates come from
 *   Not geocoded, not guessed. Each venue's lat/lng is read out of the Google place URL the OLD
 *   site published for that venue (the `!3d<lat>!4d<lng>` pair, which is the place's own point,
 *   not the viewport centre). For the three venues whose old link was a maps.app.goo.gl short
 *   link, the coordinates come from that short link's own 302 redirect target. Every value is
 *   therefore traceable to temakinho.com, and each one was cross-checked against the venue's
 *   published street address. Stored as the `map_coords` postmeta, "lat,lng".
 *
 * NO API KEY
 *   `output=embed` is the keyless Google Maps embed. The Embed API proper
 *   (maps/embed/v1/place) would need a key and a billing account; it is deliberately not used.
 *
 * NO COORDS -> NO MAP. Linate and Fiumicino published no map link on the old site, so they have
 *   no map_coords and this function returns ''. Their address stays a link. Inventing a pin for
 *   an airport departures area would be fabricating a location, not rendering one.
 *
 * PRIVACY: this loads an iframe from google.com, which sets Google cookies for the visitor. The
 *   original MapBlock docblock above rejected a third-party map iframe for exactly that reason.
 *   That decision is being reversed here on explicit instruction. `loading="lazy"` keeps it out
 *   of the initial page load; it does NOT make it consent-free. Flagged, not silently accepted.
 *
 * @param array $atts coords (required, "lat,lng"), map_url, address, label, zoom.
 * @return string Empty string when no usable coordinates are given.
 */
function tmk_render_map_embed( $atts = array() ) {
	$atts = wp_parse_args( $atts, array(
		'coords'  => '',
		'map_url' => '',
		'address' => '',
		'label'   => '',
		'zoom'    => 17,
	) );

	/*
	 * TWO WAYS TO POINT AT A PLACE, in order of precision. Changed 2026-09-04 on instruction
	 * ("se non esiste, genera automaticamente la mappa visibile dalla stringa dell'indirizzo, con
	 * query URL encoded; non inventare coordinate, place ID o indirizzi").
	 *
	 * 1. VERIFIED COORDINATES -> a pin. Accepted only as a strict "lat,lng" pair inside real-world
	 *    bounds, so a malformed or half-filled meta value can never render a pin in the sea off
	 *    Africa.
	 * 2. THE PUBLISHED ADDRESS -> a Google search for that exact string, URL-encoded. This is not
	 *    a guess at a location: it is the restaurant's own address, the same string the page prints
	 *    above the map and the same one the "apri in mappe" link has always used
	 *    (tmk_practical_map_href). Nothing is geocoded here and no coordinate is invented — Google
	 *    resolves its own query, and if it resolves it loosely the map is loose in exactly the way
	 *    the data is.
	 *
	 * WHY IT MATTERS: before this, Linate and Fiumicino rendered NO map at all, because the old
	 * site published no place URL for them and this function required coordinates. Their addresses
	 * were always present and always correct. An airport terminal is precisely the case where a
	 * fabricated pin would have been wrong and an address search is right.
	 *
	 * Still no API key: `output=embed` is the keyless embed. The Embed API proper would need a key
	 * and a billing account and is deliberately not used.
	 */
	$zoom = max( 1, min( 21, (int) $atts['zoom'] ) );
	$query = '';
	if ( preg_match( '/^\s*(-?\d{1,3}(?:\.\d+)?)\s*,\s*(-?\d{1,3}(?:\.\d+)?)\s*$/', (string) $atts['coords'], $m ) ) {
		$lat = (float) $m[1];
		$lng = (float) $m[2];
		if ( $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180 ) {
			$query = $lat . ',' . $lng;
		}
	}
	if ( '' === $query ) {
		// Newlines are real in the archived addresses (Fiumicino has two lines): collapse them to
		// spaces for the query, never drop them.
		$addr = trim( preg_replace( '/\s+/u', ' ', (string) $atts['address'] ) );
		if ( '' === $addr ) {
			return '';
		}
		$query = $addr;
		// An address search needs a wider view than a pin, or the frame opens inside a building.
		$zoom = min( $zoom, 16 );
	}

	// add_query_arg() URL-encodes the value itself (build_query -> urlencode); encoding $query
	// first would double-encode every space into %2520 and Google would search for the escapes.
	$src = add_query_arg( array(
		'q'      => $query,
		'z'      => $zoom,
		'hl'     => 'it',
		'output' => 'embed',
	), 'https://www.google.com/maps' );

	$name  = $atts['label'] ? $atts['label'] : $atts['address'];
	$title = $name ? sprintf( 'Mappa di %s', $name ) : 'Mappa della sede';

	ob_start();
	?>
	<div class="tmk-map-embed">
		<div class="tmk-map-embed__frame">
			<iframe
				src="<?php echo esc_url( $src ); ?>"
				title="<?php echo esc_attr( $title ); ?>"
				loading="lazy"
				referrerpolicy="no-referrer-when-downgrade"
				allowfullscreen></iframe>
		</div>
		<?php if ( $atts['map_url'] ) : ?>
			<p class="tmk-map-embed__actions">
				<a class="tmk-cta tmk-cta--secondary tmk-map-embed__open" href="<?php echo esc_url( $atts['map_url'] ); ?>" target="_blank" rel="noopener">
					Apri in Google Maps
				</a>
			</p>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

add_shortcode( 'tmk_map_block', function ( $atts ) {
	return tmk_render_map_block( $atts );
} );
