<?php
/**
 * Location page - the MASTER LOCATION TEMPLATE assembler.
 * Direct translation of pages/LocationRimini.jsx (the canonical Terminal-template instance) +
 * shell/composition-templates.js "Terminal Master Template - Depth 2".
 *
 * COMPOSITION from Claude Design, DATASET from WordPress - the same doctrine tmk_render_city_bands()
 * follows for the Homepage. The prototype hardcodes Rimini's content in pages/rimini-data.js; this
 * renders ANY `location` post from its real CPT fields (inc/content-model.php), so one template
 * serves all 14 restaurants and every future one. Concept is expressed via config only
 * (data-concept on the page root remaps the accent token) - never a separate template, per the
 * governing architectural decision in PROJECT_VALIDATION_REPORT §1 and IA §6.
 *
 * Section order is the Terminal template's fixed sequence and is NEVER reordered (Composition
 * System §5): Arrival(Hero) -> Orientation(Narrative + Gallery, and Practical Info + Map) ->
 * Content(Menu preview) -> Trust -> Conversion(Booking) -> Closure(Nearby). Every stage after the
 * Hero is CONDITIONAL on real data and simply omitted when a field is empty ("every dynamic
 * component degrades to a hidden empty state", PROJECT_VALIDATION_REPORT §6) - never faked.
 *
 * DELIBERATE COMPOSITION NOTES (reconciled against the prototype):
 *  - The prototype lists <Gallery> and a separate <MapBlock> alongside <PracticalInfoBand>. In this
 *    build PracticalInfoBand renders the Map Block itself (inc/practical-info-render.php, faithful
 *    to the DS component whose JSX composes the map internally), so this assembler must NOT add a
 *    second Map - it calls the band only.
 *  - The Newsletter that the Terminal template marks mandatory in Closure is delivered by the
 *    site-wide shell Footer ([tmk_newsletter] inside [tmk_footer]); a second newsletter section
 *    here would duplicate the form and its element IDs (the exact defect Fix 39 fixed on the
 *    Homepage), so it is intentionally not repeated. One newsletter, in the footer, site-wide.
 *  - There is no Location<->MenuItem relationship in the seeded data model (IA §42 flags the m:m as
 *    an assumption), so the Menu preview is a small on-brand SELECTION of real dishes that links to
 *    the full, faceted Menu Hub - not an invented per-restaurant menu. Price is always visible.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** Sitewide, byte-identical across all 14 Locations (archive "Menu block" paragraph) — stored once
 *  here, never as per-restaurant postmeta. */
if ( ! defined( 'TMK_LOCATION_MENU_INTRO' ) ) {
	define( 'TMK_LOCATION_MENU_INTRO', 'Ogni tavola ha il suo menu: quello che stai per sfogliare vive solo qui, nel ristorante. Per delivery e asporto, l’esperienza cambia — e anche i piatti.' );
}

/**
 * Where a restaurant's "Prenota" goes — resolved from what the client chose, in order.
 *
 *   1. the PAGE they picked (tmk_location_booking_page_id), through get_permalink() so the link
 *      survives a slug change — which is the whole reason the field is a page reference and not
 *      a URL string;
 *   2. the URL stored before this existed (`booking_url`), so nothing configured earlier changes
 *      behaviour. Every one of the 14 restaurants had this empty (backed up regardless);
 *   3. the site-wide destination, exactly as before.
 *
 * D-32 perimeter, authorised 2026-09-04: this resolves a DESTINATION and nothing else. No
 * routing, no provider, no iframe, no TheFork logic, no /prenota-*\/ page content.
 */
function tmk_location_booking_href( $location_id ) {
	$location_id = (int) $location_id;
	$page        = (int) get_post_meta( $location_id, 'tmk_location_booking_page_id', true );
	if ( $page && 'publish' === get_post_status( $page ) ) {
		$url = get_permalink( $page );
		if ( $url ) {
			return (string) $url;
		}
	}
	$legacy = trim( (string) get_post_meta( $location_id, 'booking_url', true ) );
	if ( '' !== $legacy ) {
		return $legacy;
	}
	return defined( 'TMK_BOOKING_URL' ) ? TMK_BOOKING_URL : home_url( '/#prenota' );
}

/** Normalise the `hours` postmeta (registered as an array of objects) into the label/value rows the
 *  Practical Info Band expects, accepting the handful of key spellings real seed data uses. */
function tmk_location_hours_rows( $hours ) {
	$rows = array();
	foreach ( (array) $hours as $row ) {
		if ( is_array( $row ) ) {
			$label = $row['label'] ?? $row['day'] ?? $row['days'] ?? ( $row[0] ?? '' );
			$value = $row['value'] ?? $row['hours'] ?? $row['time'] ?? ( $row[1] ?? '' );
		} else {
			$parts = array_map( 'trim', explode( '|', (string) $row, 2 ) );
			$label = $parts[0];
			$value = $parts[1] ?? '';
		}
		if ( '' !== trim( (string) $label ) || '' !== trim( (string) $value ) ) {
			$rows[] = array( 'label' => $label, 'value' => $value );
		}
	}
	return $rows;
}

/** A small, deterministic on-brand selection of real dishes for a Location's Menu preview. */
function tmk_location_menu_selection( $limit = 6 ) {
	return get_posts( array(
		'post_type'      => 'menu_item',
		'post_status'    => 'publish',
		'posts_per_page' => (int) $limit,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'meta_query'     => array( array( 'key' => 'price', 'value' => '', 'compare' => '!=' ) ),
	) );
}

function tmk_render_location( $post = null ) {
	$post = $post ? get_post( $post ) : get_queried_object();
	if ( ! $post instanceof WP_Post || 'location' !== $post->post_type ) {
		return '';
	}
	$id = $post->ID;

	// --- Dataset: real CPT fields -------------------------------------------------------------
	$title    = get_the_title( $post );
	$tagline  = (string) get_post_meta( $id, 'tagline', true );
	$short    = (string) get_post_meta( $id, 'short_description', true );
	$address  = (string) get_post_meta( $id, 'address', true );
	$phone    = (string) get_post_meta( $id, 'phone', true );
	// Phase 2 (audit 2026-09-01) - see tmk_practical_map_href() and tmk_location_fields().
	$phone_display = (string) get_post_meta( $id, 'phone_display', true );
	$map_url  = (string) get_post_meta( $id, 'map_url', true );
	$map_coords = (string) get_post_meta( $id, 'map_coords', true );
	$access   = (string) get_post_meta( $id, 'accessibility_notes', true );
	$booking  = tmk_location_booking_href( $id );
	$hours    = tmk_location_hours_rows( get_post_meta( $id, 'hours', true ) );
	$gallery  = array_values( array_filter( array_map( 'intval', (array) get_post_meta( $id, 'gallery_ids', true ) ) ) );
	$media_id = (int) get_post_thumbnail_id( $id );
	$email        = (string) get_post_meta( $id, 'email', true );
	$neighbourhood = (string) get_post_meta( $id, 'tmk_location_neighbourhood_note', true );
	$logotype_id  = (int) get_post_meta( $id, 'tmk_location_logotype_id', true );
	$has_music    = (bool) get_post_meta( $id, 'tmk_location_has_live_music', true );
	$logotype_alt = $logotype_id ? (string) get_post_meta( $logotype_id, '_wp_attachment_image_alt', true ) : '';

	$city_terms = wp_get_post_terms( $id, 'city' );
	$city       = ( ! is_wp_error( $city_terms ) && $city_terms ) ? $city_terms[0] : null;

	$concept_terms = wp_get_post_terms( $id, 'concept' );
	$concept       = '';
	if ( ! is_wp_error( $concept_terms ) && $concept_terms ) {
		$token   = get_term_meta( $concept_terms[0]->term_id, 'accent_token', true );
		$concept = $token ? $token : $concept_terms[0]->slug;
		if ( 'standard' === $concept ) $concept = '';
	}

	$narrative_body = $short ? $short : ( $post->post_content ? wp_strip_all_tags( $post->post_content ) : '' );
	// tmk_location_booking_href() restituisce sempre una destinazione reale; il ripiego resta solo
	// per sicurezza ed e' allineato a quello di tutto il resto del sito (2026-09-07).
	$booking_href   = $booking ? $booking : ( defined( 'TMK_BOOKING_URL' ) ? TMK_BOOKING_URL : home_url( '/#prenota' ) );
	$menu_url       = home_url( '/menu/' );
	$concept_attr   = $concept ? ' data-concept="' . esc_attr( $concept ) . '"' : '';

	ob_start();

	/* ARRIVAL - full-bleed photographic hero (place-first); its <h1> is the page's sole H1. */
	echo tmk_render_hero( array(
		'variant'         => 'full-bleed',
		'media_id'        => $media_id,
		'media_alt'       => $title,
		'eyebrow'         => $city ? $city->name : '',
		'headline'        => $title,
		'subheadline'     => $tagline,
		'concept'         => $concept,
		'cta_label'       => 'Prenota',
		'cta_href'        => $booking_href,
		'secondary_label' => 'Scopri il menu',
		'secondary_href'  => '#menu',
	) );
	?>

	<div class="tmk-location"<?php echo $concept_attr; ?>>

		<?php
		/* Percorso - derived hierarchy trail, never hand-authored. */
		$trail = array( array( 'label' => 'Home', 'href' => home_url( '/' ) ), array( 'label' => 'Ristoranti', 'href' => home_url( '/ristoranti/' ) ) );
		if ( $city ) $trail[] = array( 'label' => $city->name, 'href' => tmk_city_href( $city ) );
		$trail[] = array( 'label' => $title );
		echo '<div class="tmk-location__band">' . tmk_render_breadcrumb( $trail ) . '</div>';
		?>

		<?php /* ORIENTATION - atmosphere: narrative beat + photo gallery. Omitted whole if no data. */
		if ( $narrative_body || $gallery ) : ?>
			<section class="tmk-location__band tmk-location__atmosphere" aria-labelledby="tmk-loc-atmosphere">
				<h2 id="tmk-loc-atmosphere" class="tmk-sr-only">L&rsquo;atmosfera</h2>
				<?php if ( $narrative_body ) : ?>
					<?php echo tmk_render_narrative_band( array( 'eyebrow' => 'L&rsquo;atmosfera', 'align' => 'start', 'motif_id' => $logotype_id, 'motif_alt' => $logotype_alt ), $narrative_body ); ?>
				<?php endif; ?>
				<?php if ( $gallery ) : ?>
					<?php echo tmk_render_gallery( array( 'ids' => $gallery, 'variant' => 'grid' ) ); ?>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<?php /* ORIENTATION - practical hospitality facts BEFORE the menu (a11y reading order); the
		         band renders its own Map Block, so none is added here. */
		if ( $hours || $address || $phone || $access || $email ) : ?>
			<section class="tmk-location__band" aria-labelledby="tmk-loc-practical">
				<h2 id="tmk-loc-practical" class="tmk-sr-only">Informazioni pratiche</h2>
				<?php echo tmk_render_practical_info_band( array(
					'hours'              => $hours,
					'address'            => $address,
					'phone'              => $phone,
					'phone_display'      => $phone_display,
					'email'              => $email,
					'neighbourhood_note' => $neighbourhood,
					'accessibility'      => $access,
					'map_label'          => $title,
					'map_url'            => $map_url,
					'map_coords'         => $map_coords,
					'map_style'          => 'embed',
				) ); ?>
				<?php if ( $has_music ) : ?>
					<p class="tmk-location__music"><?php esc_html_e( 'Musica & atmosfera', 'tmk' ); ?></p>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<?php /* CONTENT - menu preview, price always visible; links to the full faceted Menu Hub. */
		$dishes = tmk_location_menu_selection( 6 );
		if ( $dishes ) : ?>
			<section class="tmk-location__band tmk-location__menu" id="menu" aria-labelledby="tmk-loc-menu">
				<h2 id="tmk-loc-menu" class="tmk-eyebrow tmk-location__eyebrow">Il menu &middot; una selezione</h2>
				<p class="tmk-location__menu-intro"><?php echo esc_html( TMK_LOCATION_MENU_INTRO ); ?></p>
				<?php
				$cards = '';
				foreach ( $dishes as $dish ) {
					$cats    = wp_get_post_terms( $dish->ID, 'menu_category', array( 'number' => 1 ) );
					$eyebrow = ( ! is_wp_error( $cats ) && $cats ) ? html_entity_decode( $cats[0]->name, ENT_QUOTES ) : '';
					$cards  .= tmk_render_card( array(
						'variant'    => 'menu',
						'eyebrow'    => $eyebrow,
						'title'      => get_the_title( $dish ),
						'supporting' => (string) get_post_meta( $dish->ID, 'short_description', true ),
						'price'      => (string) get_post_meta( $dish->ID, 'price', true ),
						'action'     => '',
						'href'       => $menu_url,
					) );
				}
				echo tmk_render_card_grid( $cards, 3 );
				?>
				<p class="tmk-location__menu-more"><a class="tmk-cta tmk-cta--secondary" href="<?php echo esc_url( $menu_url ); ?>">Vedi il men&ugrave; completo</a></p>
			</section>
		<?php endif; ?>

		<?php /* TRUST - sourced testimonials (the band falls back gracefully if the CPT is empty). */ ?>
		<section class="tmk-location__band" aria-labelledby="tmk-loc-trust">
			<h2 id="tmk-loc-trust" class="tmk-sr-only">Cosa dicono di noi</h2>
			<?php echo tmk_render_trust_band( array( 'rotating' => false ) ); ?>
		</section>

		<?php /* CONVERSION - the one primary action: a Booking Widget inside the booking banner. */ ?>
		<section class="tmk-location__band" aria-labelledby="tmk-loc-book">
			<h2 id="tmk-loc-book" class="tmk-sr-only">Prenota</h2>
			<?php echo tmk_render_cta_banner(
				array( 'variant' => 'booking' ),
				tmk_render_booking_widget( array( 'tier' => 1, 'location_name' => $title, 'booking_url' => $booking_href ) )
			); ?>
		</section>

		<?php
		/* CLOSURE - nearby: other Temakinho cities as a compact photo-card row. */
		$others = array();
		if ( $city ) {
			foreach ( tmk_ordered_city_terms() as $t ) {
				if ( $t->term_id !== $city->term_id ) $others[] = $t;
			}
		}
		$others = array_slice( $others, 0, 4 );
		if ( $others ) : ?>
			<section class="tmk-location__band tmk-location__nearby" aria-labelledby="tmk-loc-nearby">
				<h2 id="tmk-loc-nearby" class="tmk-eyebrow tmk-location__eyebrow">Altri ristoranti Temakinho</h2>
				<?php
				$cards = '';
				foreach ( $others as $t ) {
					$cards .= tmk_render_city_card( $t, 'lazy', '', '(min-width: 1024px) 280px, calc(100vw - 48px)' );
				}
				echo tmk_render_card_grid( $cards, 4, 2 );
				?>
			</section>
		<?php endif; ?>

	</div>
	<?php
	return ob_get_clean();
}

/** [tmk_location] - renders the queried location single. Also callable as tmk_render_location($id). */
add_shortcode( 'tmk_location', function () {
	return tmk_render_location();
} );
