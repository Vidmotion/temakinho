<?php
/**
 * Events — the Event Hub (/eventi/) + the Event Detail (/eventi/{slug}/). Direct translation of
 * pages/EventHub.jsx and pages/EventDetail.jsx (Blueprint §5.5 / §5.8 family). Experiential, editorial
 * (inherits the Stories language), with a strengthened conversion; not a calendar, not ticketing.
 *
 * COMPOSITION from Claude Design, DATASET from the real `event` CPT (inc/content-model.php). Section
 * orders are the templates' fixed sequences, never reordered:
 *   HUB     Hero -> Featured (soonest) -> Grid (all) -> Conversion (private events).
 *   DETAIL  Hero -> Breadcrumb -> Intro -> Narrative -> Immersive Gallery -> Practical Info(+Map) ->
 *           Trust -> Booking -> Related events.
 * Every beat after the Hero is conditional on real data and omitted when empty. The Practical Info
 * band renders its own Map (the Location reconciliation, PROJECT_CONTEXT §20.2), so none is added
 * separately; the Closure newsletter is the site-wide footer's, not repeated here.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** Italian short weekday for a timestamp (Lun…Dom). */
function tmk_weekday_it( $ts, $long = false ) {
	$short = array( 'Dom', 'Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab' );
	$full  = array( 'Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato' );
	$w = (int) wp_date( 'w', $ts );
	return $long ? $full[ $w ] : $short[ $w ];
}

/** "Ven 12 settembre · 20:30" (short, no year) — the hub/card WHEN. */
function tmk_event_when_short( $start ) {
	$ts = strtotime( (string) $start );
	if ( ! $ts ) return '';
	$out = tmk_weekday_it( $ts ) . ' ' . (int) wp_date( 'j', $ts ) . ' ' . tmk_event_month_it( $ts );
	if ( wp_date( 'H:i', $ts ) !== '00:00' ) $out .= ' · ' . wp_date( 'H:i', $ts );
	return $out;
}

/** "Venerdì 12 settembre 2026 · 20:30" (long, with year) — the detail hero WHEN. */
function tmk_event_when_long( $start ) {
	$ts = strtotime( (string) $start );
	if ( ! $ts ) return '';
	$out = tmk_weekday_it( $ts, true ) . ' ' . (int) wp_date( 'j', $ts ) . ' ' . tmk_event_month_it( $ts ) . ' ' . wp_date( 'Y', $ts );
	if ( wp_date( 'H:i', $ts ) !== '00:00' ) $out .= ' · ' . wp_date( 'H:i', $ts );
	return $out;
}

/** Italian month name for a timestamp (reuses the Stories helper's month table indirectly). */
function tmk_event_month_it( $ts ) {
	$m = array( 1 => 'gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre' );
	return $m[ (int) wp_date( 'n', $ts ) ];
}

/** All events, soonest-first by event_start (fallback to post date for events without a start). */
function tmk_get_events() {
	return get_posts( array(
		'post_type'      => 'event',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'meta_key'       => 'event_start',
		'orderby'        => 'meta_value',
		'order'          => 'ASC',
	) );
}

/** Booking href for an event (its own booking_url, else the site's production booking page). */
function tmk_event_booking_href( $id ) {
	$b = (string) get_post_meta( $id, 'booking_url', true );
	return $b ? $b : ( defined( 'TMK_BOOKING_URL' ) ? TMK_BOOKING_URL : home_url( '/#prenota' ) );
}

/** A single event card (Card "story" variant), bound to a real event. */
function tmk_event_card( $event, $variant = 'story', $sizes = '' ) {
	$kind = (string) get_post_meta( $event->ID, 'event_kind', true );
	$when = tmk_event_when_short( get_post_meta( $event->ID, 'event_start', true ) );
	$venue = (string) get_post_meta( $event->ID, 'event_venue', true );
	$eyebrow = trim( implode( ' · ', array_filter( array( $kind, $when ) ) ) );
	return tmk_render_card( array(
		'variant'    => $variant,
		'media_id'   => (int) get_post_thumbnail_id( $event ),
		'eyebrow'    => 'story' === $variant ? $eyebrow : $when,
		'title'      => get_the_title( $event ),
		'supporting' => 'story' === $variant ? trim( ( $venue ? $venue . ' — ' : '' ) . get_the_excerpt( $event ) ) : '',
		'action'     => 'Scopri',
		'href'       => get_permalink( $event ),
		'sizes'      => $sizes,
	) );
}

/**
 * EVENT HUB — Hero -> Featured (soonest) -> Grid -> Conversion. Translation of pages/EventHub.jsx.
 */
function tmk_render_event_hub() {
	$events   = tmk_get_events();
	$featured = $events ? array_shift( $events ) : null;

	ob_start();

	echo tmk_render_hero( array(
		'variant'     => 'full-bleed',
		'media_id'    => function_exists( 'tmk_asset_id' ) ? tmk_asset_id( 'interior-rimini-bar' ) : 0,
		'media_alt'   => 'Il bancone illuminato d’ambra durante una serata evento',
		'eyebrow'     => 'Eventi',
		'headline'    => 'Serate che sanno di festa',
		'subheadline' => 'Cene a quattro mani, notti carioca e degustazioni al bancone. Il calendario che non ti aspetti.',
		'cta_label'   => '',
		'height'      => 'min(58vh, 520px)',
	) );
	?>

	<div class="tmk-stack">

		<?php if ( ! $featured ) : ?>
			<section aria-label="Eventi">
				<?php echo tmk_render_system_state( array(
					'kind'         => 'empty',
					'title'        => 'Nessun evento in programma',
					'copy'         => 'Il prossimo appuntamento è dietro l’angolo. Nel frattempo, prenota una tavola.',
					'action_label' => 'Prenota',
					'action_href'  => tmk_event_booking_href( 0 ),
				) ); ?>
			</section>
		<?php else : ?>

			<section aria-label="In evidenza">
				<h2 class="tmk-section-eyebrow">Il prossimo appuntamento</h2>
				<?php echo tmk_render_card_grid( tmk_event_card( $featured, 'story', '(min-width:1024px) 1000px, calc(100vw - 48px)' ), 1 ); ?>
			</section>

			<?php if ( $events ) : ?>
			<section aria-label="Tutti gli eventi">
				<h2 class="tmk-section-eyebrow">In calendario</h2>
				<?php
				$cards = '';
				foreach ( $events as $e ) $cards .= tmk_event_card( $e, 'story', '(min-width:1024px) 320px, calc(100vw - 48px)' );
				echo tmk_render_card_grid( $cards, 3 );
				?>
			</section>
			<?php endif; ?>

		<?php endif; ?>

		<?php /* CONVERSION — private events, one primary action (Scrivici). */ ?>
		<section aria-label="Eventi privati">
			<?php echo tmk_render_cta_banner( array(
				'variant'   => 'booking',
				'title'     => 'Vuoi un evento tutto tuo?',
				'copy'      => 'Compleanni, aziende, cene private: costruiamo la serata con te.',
				'cta_label' => 'Scrivici',
				'cta_href'  => home_url( '/contattaci/' ),
			) ); ?>
		</section>

	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'tmk_event_hub', 'tmk_render_event_hub' );

/**
 * EVENT DETAIL — the Terminal event page. Translation of pages/EventDetail.jsx.
 */
function tmk_render_event( $post = null ) {
	$post = $post ? get_post( $post ) : get_queried_object();
	if ( ! $post instanceof WP_Post || 'event' !== $post->post_type ) {
		return '';
	}
	$id      = $post->ID;
	$kind    = (string) get_post_meta( $id, 'event_kind', true );
	$venue   = (string) get_post_meta( $id, 'event_venue', true );
	$start   = (string) get_post_meta( $id, 'event_start', true );
	$formula = (string) get_post_meta( $id, 'event_formula', true );
	$address = (string) get_post_meta( $id, 'event_address', true );
	$phone   = (string) get_post_meta( $id, 'event_phone', true );
	$access  = (string) get_post_meta( $id, 'event_accessibility', true );
	$gallery = array_values( array_filter( array_map( 'intval', (array) get_post_meta( $id, 'event_gallery_ids', true ) ) ) );
	$media   = (int) get_post_thumbnail_id( $id );
	$booking = tmk_event_booking_href( $id );
	$intro   = get_the_excerpt( $post );

	// Practical rows built from the event's own fields, in the band's fixed hospitality order.
	$rows = array();
	if ( $start )   $rows[] = array( 'label' => 'Data', 'value' => tmk_event_when_long( $start ) );
	if ( $start && wp_date( 'H:i', strtotime( $start ) ) !== '00:00' ) $rows[] = array( 'label' => 'Arrivo', 'value' => 'ore ' . wp_date( 'H:i', strtotime( $start ) ) . ', turno unico' );
	if ( $formula ) $rows[] = array( 'label' => 'Formula', 'value' => $formula );

	ob_start();

	echo tmk_render_hero( array(
		'variant'     => 'full-bleed',
		'media_id'    => $media,
		'media_alt'   => get_the_title( $post ),
		'eyebrow'     => trim( implode( ' · ', array_filter( array( $kind, $venue ) ) ) ),
		'headline'    => get_the_title( $post ),
		'subheadline' => tmk_event_when_long( $start ),
		'cta_label'   => 'Prenota il tavolo',
		'cta_href'    => $booking,
	) );
	?>

	<article class="tmk-event">

		<div class="tmk-event__column">
			<?php echo tmk_render_breadcrumb( array(
				array( 'label' => 'Home', 'href' => home_url( '/' ) ),
				array( 'label' => 'Eventi', 'href' => home_url( '/eventi/' ) ),
				array( 'label' => get_the_title( $post ) ),
			) ); ?>
		</div>

		<?php if ( $intro ) : ?>
			<div class="tmk-event__column tmk-event__intro">
				<p><?php echo esc_html( $intro ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( $post->post_content ) : ?>
			<div class="tmk-event__column tmk-event__body">
				<?php echo apply_filters( 'the_content', $post->post_content ); ?>
			</div>
		<?php endif; ?>

		<?php if ( $gallery ) : ?>
			<div class="tmk-event__wide">
				<?php echo tmk_render_gallery( array( 'ids' => $gallery, 'variant' => 'grid' ) ); ?>
			</div>
		<?php endif; ?>

		<?php if ( $rows || $address || $phone || $access ) : ?>
			<div class="tmk-event__wide">
				<section aria-labelledby="tmk-event-practical">
					<h2 id="tmk-event-practical" class="tmk-sr-only">Informazioni pratiche</h2>
					<?php echo tmk_render_practical_info_band( array(
						'hours'         => $rows,
						'address'       => $address,
						'phone'         => $phone,
						'accessibility' => $access,
						'map_label'     => $venue ? $venue : get_the_title( $post ),
					) ); ?>
				</section>
			</div>
		<?php endif; ?>

		<div class="tmk-event__wide">
			<section aria-labelledby="tmk-event-trust">
				<h2 id="tmk-event-trust" class="tmk-sr-only">Cosa dicono di noi</h2>
				<?php echo tmk_render_trust_band( array( 'rotating' => false ) ); ?>
			</section>
		</div>

		<div class="tmk-event__wide">
			<section aria-labelledby="tmk-event-book">
				<h2 id="tmk-event-book" class="tmk-sr-only">Prenota</h2>
				<?php echo tmk_render_cta_banner(
					array( 'variant' => 'booking', 'title' => 'Prenota la tua serata', 'copy' => 'Turno unico, posti limitati. Assicurati il tavolo prima che finiscano.' ),
					tmk_render_booking_widget( array( 'tier' => 1, 'location_name' => get_the_title( $post ), 'booking_url' => $booking ) )
				); ?>
			</section>
		</div>

		<?php
		$others = array();
		foreach ( tmk_get_events() as $e ) {
			if ( $e->ID !== $id ) $others[] = $e;
		}
		$others = array_slice( $others, 0, 2 );
		if ( $others ) : ?>
			<div class="tmk-event__wide">
				<section aria-labelledby="tmk-event-related">
					<h2 id="tmk-event-related" class="tmk-section-eyebrow">Altri appuntamenti</h2>
					<?php
					$cards = '';
					foreach ( $others as $e ) $cards .= tmk_event_card( $e, 'city', '(min-width:1024px) 500px, calc(100vw - 48px)' );
					echo tmk_render_card_grid( $cards, 2 );
					?>
				</section>
			</div>
		<?php endif; ?>

	</article>
	<?php
	return ob_get_clean();
}
add_shortcode( 'tmk_event', function () {
	return tmk_render_event();
} );
