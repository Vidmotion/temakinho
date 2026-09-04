<?php
/**
 * Practical Info Band - direct translation of components/bands/PracticalInfoBand.jsx.
 *
 * "Hospitality-framed practical facts - fixed order: hours, address, phone, accessibility, map."
 * The order is part of the component, not of the caller, which is why nothing here is sortable:
 * a guest wants to know WHEN before WHERE, and the map is the last thing they need, not the first.
 *
 * The Map Block is rendered BY this band (the source composes it internally), so a page must not
 * add a second one next to it - see the deviation note in inc/location-render.php.
 *
 * @param array $hours Ordered rows, each array( 'label' => 'Lun-Gio', 'value' => '19:00-23:30' ).
 *                     Plain array( 'Lun-Gio', '19:00-23:30' ) pairs are accepted too, because the
 *                     design's own data is written that way.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function tmk_practical_hours_rows( $hours ) {
	$rows = array();
	foreach ( (array) $hours as $row ) {
		if ( is_array( $row ) ) {
			$label = isset( $row['label'] ) ? $row['label'] : ( isset( $row[0] ) ? $row[0] : '' );
			$value = isset( $row['value'] ) ? $row['value'] : ( isset( $row[1] ) ? $row[1] : '' );
		} else {
			$parts = array_map( 'trim', explode( '|', (string) $row, 2 ) );
			$label = $parts[0];
			$value = isset( $parts[1] ) ? $parts[1] : '';
		}
		if ( '' !== $label || '' !== $value ) {
			$rows[] = array( 'label' => $label, 'value' => $value );
		}
	}
	return $rows;
}

/**
 * Destination for the clickable address.
 *
 * Phase 2 (audit 2026-09-01). Order matters: a verified place URL identifies the venue itself and
 * never drifts, so it wins whenever one is stored. The generated ?q= search is kept only as the
 * fallback for venues that have no verified URL - it hands the address to Google as free text and
 * lets it guess, which is why it is second choice and not the default any more.
 *
 * The address is collapsed to single spaces before encoding. Fiumicino's address carries a real
 * newline ("...Leonardo da Vinci\nTerminal 1..."); rawurlencode() turned it into %0A and Google
 * read 'Vinci"Terminal 1' as one token. Only the query string is normalised - the address printed
 * on the page keeps its line break.
 *
 * @param string $address Address as published.
 * @param string $map_url Verified place URL, or '' when none is stored.
 * @return string
 */
function tmk_practical_map_href( $address, $map_url = '' ) {
	$map_url = trim( (string) $map_url );
	if ( '' !== $map_url ) {
		return $map_url;
	}
	$query = trim( preg_replace( '/\s+/u', ' ', (string) $address ) );
	return 'https://maps.google.com/?q=' . rawurlencode( $query );
}

function tmk_render_practical_info_band( $atts = array() ) {
	$atts = wp_parse_args( $atts, array(
		'hours'              => array(),
		'address'            => '',
		'phone'              => '',
		'email'              => '',
		'neighbourhood_note' => '',
		'accessibility'      => '',
		'map_label'          => '',
		'closed_today'       => false,
		// Phase 2 (audit 2026-09-01): a verified Google place URL for this venue. When present it
		// wins over the generated text query - a place URL pins the restaurant, a ?q= search only
		// asks Google to guess from the address string. Empty for callers that have no such URL
		// (events, manual shortcode use), which keeps the old behaviour as the fallback.
		'map_url'            => '',
		// 2026-09-02. "lat,lng" from the old site's own place URL. Present -> a real interactive
		// Google embed replaces the decorative drawing. Absent -> no map at all (Linate,
		// Fiumicino: the old site published no map link for them).
		'map_coords'         => '',
		// 'decorative' keeps the hand-drawn SVG, which is what inc/event-render.php still wants
		// and gets by default, so events render byte-identically. Location pages pass 'embed'.
		'map_style'          => 'decorative',
		// Phone as published for humans ("+39 02 7201 6158"). Display only - the tel: href is still
		// built from 'phone', which stays the canonical dialable value.
		'phone_display'      => '',
	) );

	$rows = tmk_practical_hours_rows( $atts['hours'] );
	if ( ! $rows && ! $atts['address'] && ! $atts['phone'] && ! $atts['email'] && ! $atts['accessibility'] ) {
		return '';
	}

	$decor = '';
	if ( is_singular( 'location' ) && function_exists( 'tmk_render_local_decor' ) ) {
		// Client review §7: this card is confirmed free of body text at any content length - a
		// safe home for the ambient snake illustration that used to drift over the atmosphere
		// block under the old page-percentage positioning (see inc/refinements.php).
		$piece = tmk_render_local_decor( 'snake', 'tmk-page-decor__snake--local' );
		$decor = $piece ? '<span aria-hidden="true">' . $piece . '</span>' : '';
	}

	ob_start();
	?>
	<section class="tmk-practical-info tmk-practical-info--decorated">
		<?php echo $decor; ?>
		<div class="tmk-practical-info__facts">
			<?php if ( $rows ) : ?>
				<div class="tmk-practical-info__cell">
					<h3 class="tmk-practical-info__label">Orari<?php if ( $atts['closed_today'] ) : ?> <span class="tmk-practical-info__closed">&middot; oggi chiuso</span><?php endif; ?></h3>
					<table class="tmk-practical-info__hours">
						<tbody>
							<?php foreach ( $rows as $row ) : ?>
								<tr>
									<th scope="row" class="tmk-practical-info__day"><?php echo esc_html( $row['label'] ); ?></th>
									<td class="tmk-practical-info__time"><?php echo esc_html( $row['value'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

			<?php if ( $atts['address'] ) : ?>
				<div class="tmk-practical-info__cell">
					<h3 class="tmk-practical-info__label">Indirizzo</h3>
					<address class="tmk-practical-info__address">
						<a href="<?php echo esc_url( tmk_practical_map_href( $atts['address'], $atts['map_url'] ) ); ?>"><?php echo esc_html( $atts['address'] ); ?></a>
					</address>
					<?php if ( $atts['neighbourhood_note'] ) : ?><p class="tmk-practical-info__neighbourhood"><?php echo esc_html( $atts['neighbourhood_note'] ); ?></p><?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( $atts['phone'] ) : ?>
				<div class="tmk-practical-info__cell">
					<h3 class="tmk-practical-info__label">Telefono</h3>
					<a class="tmk-practical-info__phone" href="<?php echo esc_url( 'tel:' . preg_replace( '/\\s+/', '', $atts['phone'] ) ); ?>"><?php echo esc_html( '' !== $atts['phone_display'] ? $atts['phone_display'] : $atts['phone'] ); ?></a>
				</div>
			<?php endif; ?>

			<?php if ( $atts['email'] ) : ?>
				<div class="tmk-practical-info__cell">
					<h3 class="tmk-practical-info__label">Email</h3>
					<a class="tmk-practical-info__email" href="<?php echo esc_url( 'mailto:' . $atts['email'] ); ?>"><?php echo esc_html( $atts['email'] ); ?></a>
				</div>
			<?php endif; ?>

			<?php if ( $atts['accessibility'] ) : ?>
				<div class="tmk-practical-info__cell">
					<h3 class="tmk-practical-info__label">Accessibilit&agrave;</h3>
					<p class="tmk-practical-info__note"><?php echo esc_html( $atts['accessibility'] ); ?></p>
				</div>
			<?php endif; ?>
		</div>

		<?php
		/* The map used to be the grid's SECOND COLUMN - half the card, filled with a drawing that
		   contained no location. As a real map it needs room to be usable, so in 'embed' mode it
		   becomes a full-width row under the facts (grid-column:1/-1 in css/components/map-block.css)
		   instead of a column beside them. 'decorative' mode is unchanged: same call, same column. */
		if ( 'embed' === $atts['map_style'] ) {
			echo tmk_render_map_embed( array(
				'coords'  => $atts['map_coords'],
				'map_url' => $atts['map_url'],
				'address' => $atts['address'],
				'label'   => $atts['map_label'],
			) );
		} else {
			echo tmk_render_map_block( array( 'address' => $atts['address'], 'label' => $atts['map_label'] ) );
		}
		?>
	</section>
	<?php
	return ob_get_clean();
}

/** [tmk_practical_info hours="Lun-Gio|19:00-23:30, Dom|12:30-15:00" address="..." phone="..."] */
add_shortcode( 'tmk_practical_info', function ( $atts ) {
	$atts = shortcode_atts( array(
		'hours'         => '',
		'address'       => '',
		'phone'         => '',
		'accessibility' => '',
		'map_label'     => '',
		'closed_today'  => '',
	), $atts );
	$atts['hours']        = array_filter( array_map( 'trim', explode( ',', $atts['hours'] ) ) );
	$atts['closed_today'] = filter_var( $atts['closed_today'], FILTER_VALIDATE_BOOLEAN );
	return tmk_render_practical_info_band( $atts );
} );
