<?php
/**
 * TMK Eventi — the dynamic blocks the Theme Builder templates hold.
 *
 * WHY THIS FILE EXISTS
 * --------------------
 * _BUILD/106 built the Event Hub (#561) and the Event Detail (#562) with the five seeded events
 * BAKED IN as hand-authored Elementor containers. That was the right call at the time — the Loop
 * widget's control naming had no verified precedent in this codebase — but it has one consequence
 * the client now hits directly: an event created in wp-admin can never appear on the hub, and a
 * deleted or drafted one can never disappear from it. The hub was a picture of a calendar.
 *
 * These shortcodes render the same markup the baked containers produced (same `tmk-event-*`
 * classes, so css/pages/event.css styles them byte-identically) from the live `event` records.
 * _BUILD/200 swaps the baked children for these, and the section wrappers — padding, background,
 * decoration anchors — stay exactly as they were.
 *
 * THE GOVERNING RULE, applied everywhere below: a field that is empty produces NO markup. Not a
 * placeholder, not an empty box, not a heading over nothing. That is what made the old Event
 * Detail read as unfinished, and it is the one thing this file must never reintroduce.
 *
 * D-32: every booking reference here is a DESTINATION resolved from data the client chose
 * (a page ID, or a URL they typed). No routing, no provider, no iframe, no TheFork logic.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================================================
   DATA RESOLUTION
   ============================================================================================== */

/**
 * Where an event is, resolved once.
 *
 * A linked Temakinho venue WINS over the manual fields: the restaurant's own address is verified
 * data that already renders on its own page, so deriving from it is the only way an event and a
 * restaurant can never disagree. The manual fields remain for events that genuinely are not in
 * one of our rooms.
 *
 * @return array venue, address, accessibility, location_id, from_location (bool)
 */
function tmk_event_place( $event_id ) {
	$event_id = (int) $event_id;
	$loc_id   = (int) get_post_meta( $event_id, 'tmk_event_location_id', true );

	if ( $loc_id && 'location' === get_post_type( $loc_id ) ) {
		$city = wp_get_post_terms( $loc_id, 'city', array( 'fields' => 'names' ) );
		$city = ( ! is_wp_error( $city ) && $city ) ? $city[0] : '';
		$venue = trim( implode( ' · ', array_filter( array( $city, get_the_title( $loc_id ) ) ) ) );
		return array(
			'venue'         => $venue,
			'address'       => (string) get_post_meta( $loc_id, 'address', true ),
			'accessibility' => (string) get_post_meta( $event_id, 'event_accessibility', true ) ?: (string) get_post_meta( $loc_id, 'accessibility_notes', true ),
			'map_url'       => (string) get_post_meta( $loc_id, 'map_url', true ),
			'map_coords'    => (string) get_post_meta( $loc_id, 'map_coords', true ),
			'location_id'   => $loc_id,
			'from_location' => true,
		);
	}

	return array(
		'venue'         => (string) get_post_meta( $event_id, 'event_venue', true ),
		'address'       => (string) get_post_meta( $event_id, 'event_address', true ),
		'accessibility' => (string) get_post_meta( $event_id, 'event_accessibility', true ),
		'map_url'       => '',
		'map_coords'    => '',
		'location_id'   => 0,
		'from_location' => false,
	);
}

/**
 * The event's participation destination, in the client's own order of preference:
 * chosen page -> typed URL -> nothing.
 *
 * Deliberately NO site-wide fallback. tmk_event_booking_href() (inc/event-render.php, the dormant
 * shortcode path) falls back to TMK_BOOKING_URL, which is how the old Event Detail ended up
 * offering "Prenota" on an event that has no booking at all. Here, no destination means no button.
 */
function tmk_event_join_href( $event_id ) {
	$event_id = (int) $event_id;
	$page     = (int) get_post_meta( $event_id, 'tmk_event_booking_page_id', true );
	if ( $page && 'publish' === get_post_status( $page ) ) {
		return (string) get_permalink( $page );
	}
	$url = trim( (string) get_post_meta( $event_id, 'booking_url', true ) );
	return $url ? $url : '';
}

/** Published events, soonest-first; events with no date sort last instead of vanishing. */
function tmk_events_published() {
	$dated = get_posts( array(
		'post_type' => 'event', 'post_status' => 'publish', 'posts_per_page' => -1,
		'meta_key' => 'event_start', 'orderby' => 'meta_value', 'order' => 'ASC',
		'meta_query' => array( array( 'key' => 'event_start', 'value' => '', 'compare' => '!=' ) ),
	) );
	$undated = get_posts( array(
		'post_type' => 'event', 'post_status' => 'publish', 'posts_per_page' => -1,
		'orderby' => 'date', 'order' => 'DESC',
		'meta_query' => array( 'relation' => 'OR',
			array( 'key' => 'event_start', 'compare' => 'NOT EXISTS' ),
			array( 'key' => 'event_start', 'value' => '', 'compare' => '=' ),
		),
	) );
	return array_merge( $dated, $undated );
}

/** "12/09/2026 · 20:30" — the project's own numeric Italian format (see _BUILD/195). */
function tmk_event_when_numeric( $start ) {
	$ts = strtotime( (string) $start );
	if ( ! $ts ) { return ''; }
	$out = gmdate( 'd/m/Y', $ts );
	if ( '00:00' !== gmdate( 'H:i', $ts ) ) { $out .= ' · ' . gmdate( 'H:i', $ts ); }
	return $out;
}

/**
 * [tmk_event_kicker] — "Degustazione · Bologna · Temakinho Bologna · 25/09/2026 · 19:00"
 *
 * A SHORTCODE AND NOT A DYNAMIC TAG, for two reasons that both bite in practice:
 *
 * 1. THE DATE HAS TO BE COMPUTED, NOT STORED. _BUILD/195 fixed the raw `2026-09-25 19:00:00`
 *    that the old kicker printed by writing a pre-formatted `tmk_event_when` snapshot and
 *    pointing a Post Custom Field tag at it. That was right for a page nobody could edit — and
 *    wrong the moment the client can change the date, because the snapshot would keep showing
 *    the old one. Formatting live from `event_start` cannot go stale. The `tmk_event_when` meta
 *    is left in place (no data is deleted) but is no longer read by anything.
 * 2. THE VENUE HAS TO BE DERIVED. With a Temakinho restaurant linked, the place comes from that
 *    restaurant (tmk_event_place); a raw `event_venue` tag would print nothing at all for an
 *    event whose venue was chosen from the list instead of typed.
 *
 * Empty parts drop out, so there is never a dangling separator.
 */
function tmk_render_event_kicker( $atts = array() ) {
	$atts = shortcode_atts( array( 'event_id' => 0 ), $atts, 'tmk_event_kicker' );
	$id   = (int) $atts['event_id'] ?: (int) get_the_ID();
	if ( ! $id || 'event' !== get_post_type( $id ) ) {
		return '';
	}
	$place = tmk_event_place( $id );
	$parts = array_filter( array(
		trim( (string) get_post_meta( $id, 'event_kind', true ) ),
		$place['venue'],
		tmk_event_when_numeric( get_post_meta( $id, 'event_start', true ) ),
	) );
	if ( ! $parts ) {
		return '';
	}
	return '<p class="tmk-event__kicker-line">' . esc_html( implode( ' · ', $parts ) ) . '</p>';
}
add_shortcode( 'tmk_event_kicker', 'tmk_render_event_kicker' );

/* ================================================================================================
   HUB BLOCKS
   ============================================================================================== */

/** One calendar card, in the markup _BUILD/106's baked containers produced. */
function tmk_event_hub_card( $e, $featured = false ) {
	$place = tmk_event_place( $e->ID );
	$kind  = trim( (string) get_post_meta( $e->ID, 'event_kind', true ) );
	$when  = tmk_event_when_numeric( get_post_meta( $e->ID, 'event_start', true ) );
	$kicker = implode( ' · ', array_filter( array( $kind, $when ) ) );
	$thumb  = (int) get_post_thumbnail_id( $e );
	$excerpt = trim( (string) get_the_excerpt( $e ) );

	ob_start();
	?>
	<a class="tmk-event-card<?php echo $featured ? ' tmk-event-card--featured' : ''; ?>" href="<?php echo esc_url( get_permalink( $e ) ); ?>">
		<?php if ( $thumb ) : ?>
			<div class="tmk-event-card__media">
				<?php echo wp_get_attachment_image( $thumb, 'large', false, array(
					'alt'   => '',
					'sizes' => $featured ? '(min-width:1024px) 590px, calc(100vw - 32px)' : '(min-width:1024px) 548px, calc(100vw - 32px)',
					'loading' => $featured ? 'eager' : 'lazy',
				) ); ?>
			</div>
		<?php endif; ?>
		<div class="tmk-event-card__body">
			<?php if ( $kicker ) : ?><p class="tmk-event-card__kicker"><?php echo esc_html( $kicker ); ?></p><?php endif; ?>
			<h3 class="tmk-event-card__title"><?php echo esc_html( get_the_title( $e ) ); ?></h3>
			<?php if ( $place['venue'] ) : ?><p class="tmk-event-card__venue"><?php echo esc_html( $place['venue'] ); ?></p><?php endif; ?>
			<?php if ( $excerpt ) : ?><p class="tmk-event-card__text"><?php echo esc_html( $excerpt ); ?></p><?php endif; ?>
		</div>
	</a>
	<?php
	return ob_get_clean();
}

/**
 * [tmk_event_featured] — "Il prossimo appuntamento", or the EMPTY STATE for the whole hub.
 *
 * The empty state lives here rather than in its own section so that a hub with no events renders
 * exactly one block instead of a heading, a gap and another heading. Its CTA is "Scopri i
 * ristoranti" per the brief — not "Prenota", which is what the dormant renderer offered and which
 * would be a booking CTA on a page that has nothing to book.
 */
function tmk_render_event_featured() {
	$events = tmk_events_published();

	if ( ! $events ) {
		ob_start();
		?>
		<section class="tmk-event-empty" aria-labelledby="tmk-event-empty-title">
			<p class="tmk-event-empty__eyebrow">Il calendario</p>
			<h2 class="tmk-event-empty__title" id="tmk-event-empty-title">Stiamo scrivendo le prossime serate</h2>
			<p class="tmk-event-empty__copy">Cene a quattro mani, notti carioca e degustazioni al bancone: quando il prossimo appuntamento &egrave; pronto, lo trovi qui. Nel frattempo, le nostre tavole ti aspettano tutti i giorni.</p>
			<p class="tmk-event-empty__actions">
				<a class="tmk-cta tmk-cta--primary" href="<?php echo esc_url( home_url( '/ristoranti/' ) ); ?>">Scopri i ristoranti</a>
			</p>
		</section>
		<?php
		return ob_get_clean();
	}

	$featured = $events[0];
	ob_start();
	?>
	<h2 class="tmk-event-hub__label tmk-section-rule">Il prossimo appuntamento</h2>
	<?php echo tmk_event_hub_card( $featured, true ); ?>
	<?php
	return ob_get_clean();
}
add_shortcode( 'tmk_event_featured', 'tmk_render_event_featured' );

/** [tmk_event_calendar] — every published event after the featured one. Nothing when there is none. */
function tmk_render_event_calendar() {
	$events = tmk_events_published();
	if ( count( $events ) < 2 ) { return ''; }
	$rest = array_slice( $events, 1 );
	ob_start();
	?>
	<h2 class="tmk-event-hub__label tmk-section-rule">In calendario</h2>
	<div class="tmk-event-grid">
		<?php foreach ( $rest as $e ) { echo tmk_event_hub_card( $e ); } ?>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'tmk_event_calendar', 'tmk_render_event_calendar' );

/* ================================================================================================
   DETAIL BLOCKS
   ============================================================================================== */

/**
 * [tmk_event_practical] — "Informazioni importanti" + the map.
 *
 * The whole section, heading included, is absent when there is nothing real to put in it. That is
 * the defect this replaces: four dynamic text widgets that each printed an empty <p>, so the
 * container was never empty for Elementor and the H2 with its ember rule sat over nothing on four
 * of the five events.
 */
function tmk_render_event_practical( $atts = array() ) {
	$atts = shortcode_atts( array( 'event_id' => 0 ), $atts, 'tmk_event_practical' );
	$id   = (int) $atts['event_id'] ?: (int) get_the_ID();
	if ( ! $id || 'event' !== get_post_type( $id ) ) { return ''; }

	$place = tmk_event_place( $id );
	$rows  = array();
	$when  = tmk_event_when_numeric( get_post_meta( $id, 'event_start', true ) );
	$end   = tmk_event_when_numeric( get_post_meta( $id, 'tmk_event_end', true ) );
	if ( $when ) { $rows[] = array( 'Quando', $end ? $when . ' — fino a ' . $end : $when ); }
	if ( $place['venue'] )   { $rows[] = array( 'Dove', $place['venue'] ); }
	if ( $place['address'] ) { $rows[] = array( 'Indirizzo', $place['address'], tmk_practical_map_href( $place['address'], $place['map_url'] ) ); }
	foreach ( array(
		'event_formula'      => 'Formula',
		'tmk_event_price'    => 'Prezzo',
		'tmk_event_capacity' => 'Disponibilit&agrave;',
		'event_phone'        => 'Telefono',
	) as $key => $label ) {
		$v = trim( (string) get_post_meta( $id, $key, true ) );
		if ( '' !== $v ) { $rows[] = array( $label, $v ); }
	}
	if ( $place['accessibility'] ) { $rows[] = array( 'Accessibilit&agrave;', $place['accessibility'] ); }
	$notes = trim( (string) get_post_meta( $id, 'tmk_event_notes', true ) );

	$map = ( $place['address'] || $place['map_coords'] )
		? tmk_render_map_embed( array(
			'coords'  => $place['map_coords'],
			'map_url' => $place['map_url'],
			'address' => $place['address'],
			'label'   => $place['venue'] ?: get_the_title( $id ),
		) )
		: '';

	if ( ! $rows && '' === $notes && '' === $map ) { return ''; }

	ob_start();
	?>
	<section class="tmk-event-info" aria-labelledby="tmk-event-info-<?php echo (int) $id; ?>">
		<h2 class="tmk-event-info__label tmk-section-rule" id="tmk-event-info-<?php echo (int) $id; ?>">Informazioni importanti</h2>
		<?php if ( $rows ) : ?>
			<dl class="tmk-event-info__list">
				<?php foreach ( $rows as $r ) : ?>
					<div class="tmk-event-info__row">
						<dt><?php echo wp_kses_post( $r[0] ); ?></dt>
						<dd><?php
							if ( ! empty( $r[2] ) ) {
								printf( '<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url( $r[2] ), esc_html( $r[1] ) );
							} else {
								echo nl2br( esc_html( $r[1] ) );
							}
						?></dd>
					</div>
				<?php endforeach; ?>
			</dl>
		<?php endif; ?>
		<?php if ( '' !== $notes ) : ?>
			<p class="tmk-event-info__notes"><?php echo nl2br( esc_html( $notes ) ); ?></p>
		<?php endif; ?>
		<?php echo $map; ?>
	</section>
	<?php
	return ob_get_clean();
}
add_shortcode( 'tmk_event_practical', 'tmk_render_event_practical' );

/** [tmk_event_gallery] — only when there are real images. Reuses the shared gallery component. */
function tmk_render_event_gallery_block( $atts = array() ) {
	$atts = shortcode_atts( array( 'event_id' => 0 ), $atts, 'tmk_event_gallery' );
	$id   = (int) $atts['event_id'] ?: (int) get_the_ID();
	if ( ! $id || 'event' !== get_post_type( $id ) ) { return ''; }
	$ids = array_values( array_filter( array_map( 'intval', (array) get_post_meta( $id, 'event_gallery_ids', true ) ) ) );
	if ( ! $ids ) { return ''; }
	/*
	 * LA GRIGLIA, non il carosello — provato e misurato, non scelto a priori.
	 *
	 * Le pagine ristorante usano tmk_render_gallery_carousel(), quindi la prima versione di
	 * questo blocco faceva lo stesso. Misurato sulla pagina evento: swiper si inizializza (6
	 * slide, 3 thumbnail) ma il carosello principale esce alto 48px e la striscia delle
	 * thumbnail 1440px, cioe' illeggibile. Causa: su una pagina ristorante Elementor Pro
	 * accoda da se' i propri fogli del carosello (swiper.min.css, e-swiper.min.css,
	 * widget-media-carousel.min.css, widget-carousel-module-base.min.css — verificato nel
	 * markup servito); sulla pagina evento, dove il markup nasce da uno shortcode dentro un
	 * widget shortcode, quella registrazione non avviene e i quattro fogli non ci sono. E'
	 * la stessa trappola dell'istanziazione "standalone" gia' a verbale per il carosello.
	 * Accodarli a mano vorrebbe dire indovinare gli handle interni di Elementor Pro.
	 *
	 * La griglia non ha dipendenze: e' markup del design system, con il proprio lightbox
	 * condiviso, e funziona identica su qualunque pagina. Il carosello anche sugli eventi
	 * resta un'opzione aperta, registrata nel report.
	 */
	if ( ! function_exists( 'tmk_render_gallery' ) ) { return ''; }
	return '<section class="tmk-event-gallery" aria-label="Immagini dell’evento">'
		. tmk_render_gallery( array( 'ids' => $ids, 'label' => get_the_title( $id ), 'sizes' => '(min-width:1024px) 360px, calc(100vw - 64px)' ) )
		. '</section>';
}
add_shortcode( 'tmk_event_gallery', 'tmk_render_event_gallery_block' );

/**
 * [tmk_event_cta] — participation. Button only when a destination exists; contact line only when
 * one was typed; the whole block absent when neither.
 */
function tmk_render_event_cta( $atts = array() ) {
	$atts = shortcode_atts( array( 'event_id' => 0 ), $atts, 'tmk_event_cta' );
	$id   = (int) $atts['event_id'] ?: (int) get_the_ID();
	if ( ! $id || 'event' !== get_post_type( $id ) ) { return ''; }

	$href    = tmk_event_join_href( $id );
	$label   = trim( (string) get_post_meta( $id, 'tmk_event_cta_label', true ) );
	$contact = trim( (string) get_post_meta( $id, 'tmk_event_contact', true ) );
	if ( '' === $href && '' === $contact ) { return ''; }

	ob_start();
	?>
	<section class="tmk-event-join" aria-labelledby="tmk-event-join-<?php echo (int) $id; ?>">
		<h2 class="tmk-event-join__title" id="tmk-event-join-<?php echo (int) $id; ?>">Vuoi esserci?</h2>
		<?php if ( '' !== $href ) : ?>
			<p class="tmk-event-join__actions">
				<a class="tmk-cta tmk-cta--primary" href="<?php echo esc_url( $href ); ?>"><?php echo esc_html( $label ?: 'Prenota' ); ?></a>
			</p>
		<?php endif; ?>
		<?php if ( '' !== $contact ) : ?>
			<p class="tmk-event-join__contact">Preferisci scriverci o chiamare? <span><?php echo esc_html( $contact ); ?></span></p>
		<?php endif; ?>
	</section>
	<?php
	return ob_get_clean();
}
add_shortcode( 'tmk_event_cta', 'tmk_render_event_cta' );

/** [tmk_event_back] — return to the calendar, and to the restaurant when the event has one. */
function tmk_render_event_back( $atts = array() ) {
	$atts = shortcode_atts( array( 'event_id' => 0 ), $atts, 'tmk_event_back' );
	$id   = (int) $atts['event_id'] ?: (int) get_the_ID();
	if ( ! $id || 'event' !== get_post_type( $id ) ) { return ''; }
	$place = tmk_event_place( $id );
	ob_start();
	?>
	<nav class="tmk-event-back" aria-label="Continua a esplorare">
		<a class="tmk-event-back__link" href="<?php echo esc_url( home_url( '/eventi/' ) ); ?>">Tutti gli eventi</a>
		<?php if ( $place['location_id'] ) : ?>
			<a class="tmk-event-back__link" href="<?php echo esc_url( get_permalink( $place['location_id'] ) ); ?>">Scopri <?php echo esc_html( get_the_title( $place['location_id'] ) ); ?></a>
		<?php endif; ?>
	</nav>
	<?php
	return ob_get_clean();
}
add_shortcode( 'tmk_event_back', 'tmk_render_event_back' );
