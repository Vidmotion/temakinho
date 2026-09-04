<?php
/**
 * City page — the MASTER CITY TEMPLATE assembler (Collection template, Depth 1).
 * Direct translation of pages/CityMilano.jsx (the canonical Collection instance, "Milano") +
 * shell/composition-templates.js "Collection Master Template — Depth 1".
 *
 * COMPOSITION from Claude Design, DATASET from WordPress — the same doctrine the Homepage
 * (tmk_render_city_bands) and the Location page (tmk_render_location) already follow. The prototype
 * hardcodes Milano's content in pages/milano-data.js; this renders ANY `city` term from its real
 * taxonomy fields (inc/content-model.php) + the `location` posts filed under it, so one template
 * serves every multi-location city and every future one.
 *
 * The City page is the bridge between the Homepage's editorial rhythm and the Location page's
 * component density: the restaurants (the concept houses) are the protagonists, and the page is
 * discovery, not a directory. Blueprint §5.2 fixed order, never reordered:
 *   Arrival(Hero) -> Percorso(Breadcrumb) -> Orientation(Narrative) -> Content(Collection of
 *   Locations) -> Conversion(Booking).
 * Every stage after the Hero is CONDITIONAL on real data and simply omitted when a field is empty
 * ("every dynamic component degrades to a hidden empty state", PROJECT_VALIDATION_REPORT §6).
 *
 * SINGLE-LOCATION CITIES collapse toward the Terminal (Location) template rather than render a
 * one-card collection — handled in inc/city-routing.php, which 301-redirects a one-restaurant city
 * term to that restaurant. This assembler therefore always renders a genuine collection.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** The `location` posts filed under a city term, in a stable order (title ASC, as the Homepage). */
function tmk_city_locations( $term ) {
	return get_posts( array(
		'post_type'      => 'location',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'tax_query'      => array( array( 'taxonomy' => 'city', 'field' => 'term_id', 'terms' => $term->term_id ) ),
	) );
}

function tmk_render_city( $term = null ) {
	$term = $term ? get_term( $term, 'city' ) : get_queried_object();
	if ( ! $term instanceof WP_Term || 'city' !== $term->taxonomy ) {
		return '';
	}

	// --- Dataset: real city term fields (IA §27 + the Collection-template fields) ---------------
	$name      = $term->name;
	$region    = (string) get_term_meta( $term->term_id, 'region', true );          // hero eyebrow, e.g. "Milano · Lombardia"
	$tagline   = (string) get_term_meta( $term->term_id, 'tagline', true );         // hero subheadline
	$nar_title = (string) get_term_meta( $term->term_id, 'narrative_title', true ); // orientation beat title
	$intro     = (string) get_term_meta( $term->term_id, 'intro_copy', true );      // orientation beat body (IA §27)
	$hero_id   = (int) get_term_meta( $term->term_id, 'hero_image_id', true );

	$locations    = tmk_city_locations( $term );
	$booking_href = home_url( '/#prenota' );

	ob_start();

	/* ARRIVAL — full-bleed photographic city hero; its <h1> (the city name) is the page's sole H1. */
	echo tmk_render_hero( array(
		'variant'         => 'full-bleed',
		'media_id'        => $hero_id,
		'media_alt'       => sprintf( 'La sala di Temakinho a %s', $name ),
		'eyebrow'         => $region,
		'headline'        => $name,
		'subheadline'     => $tagline,
		'cta_label'       => 'Prenota',
		'cta_href'        => $booking_href,
		'secondary_label' => $locations ? 'Scopri i ristoranti' : '',
		'secondary_href'  => '#ristoranti',
	) );
	?>

	<div class="tmk-city">

		<?php
		/* Percorso — derived hierarchy trail, never hand-authored (Breadcrumb.jsx contract). */
		echo '<div class="tmk-city__band">' . tmk_render_breadcrumb( array(
			array( 'label' => 'Home', 'href' => home_url( '/' ) ),
			array( 'label' => 'Ristoranti', 'href' => home_url( '/ristoranti/' ) ),
			array( 'label' => $name ),
		) ) . '</div>';
		?>

		<?php /* ORIENTATION — the city intro as an editorial beat. Omitted whole when there is no copy. */
		if ( $intro || $nar_title ) : ?>
			<section class="tmk-city__band tmk-city__orientation tmk-decor-l-leaves tmk-decor--keep" aria-labelledby="tmk-city-intro">
				<h2 id="tmk-city-intro" class="tmk-sr-only">La città</h2>
				<?php echo tmk_render_narrative_band(
					array( 'eyebrow' => 'La città', 'title' => $nar_title, 'align' => 'start' ),
					$intro
				); ?>
			</section>
		<?php endif; ?>

		<?php /* CONTENT — the Collection: the city's restaurants as protagonists (photo-led discovery).
		         media is the city photograph for every card, so the row reads as one photographic
		         family — faithful to CityMilano.jsx (media={M.hero.media} on each Card). */
		if ( $locations ) : ?>
			<section class="tmk-city__band tmk-city__collection tmk-decor-r-koi" aria-labelledby="tmk-city-collection" id="ristoranti">
				<h2 id="tmk-city-collection" class="tmk-eyebrow tmk-city__eyebrow"><?php echo esc_html( sprintf( 'Le nostre tavole a %s', $name ) ); ?></h2>
				<?php
				$sizes = '(min-width: 1024px) 380px, calc(100vw - 48px)';
				$cards = '';
				foreach ( $locations as $loc ) {
					$cards .= tmk_render_location_card( $loc, $term, $hero_id, 'lazy', $sizes );
				}
				echo tmk_render_card_grid( $cards, 3, 2 );
				?>
			</section>
		<?php endif; ?>

		<?php /* CONVERSION — the one primary action (Prenota), scoped to the city, via a Booking Widget
		         inside the booking banner (the canonical <CTABanner><BookingWidget/></CTABanner>). */ ?>
		<section class="tmk-city__band" aria-labelledby="tmk-city-book">
			<h2 id="tmk-city-book" class="tmk-sr-only">Prenota</h2>
			<?php echo tmk_render_cta_banner(
				array(
					'variant' => 'booking',
					'title'   => sprintf( 'Prenota la tua tavola a %s', $name ),
					'copy'    => 'Scegli il concept, la data e l’ora. Al resto pensiamo noi.',
				),
				tmk_render_booking_widget( array( 'tier' => 1, 'location_name' => $name, 'booking_url' => $booking_href ) )
			); ?>
		</section>

	</div>
	<?php
	return ob_get_clean();
}

/** [tmk_city] — renders the queried city term. Also callable as tmk_render_city($term). */
add_shortcode( 'tmk_city', function () {
	return tmk_render_city();
} );

/**
 * RESTAURANTS INDEX — /ristoranti/, the `location` post-type archive.
 *
 * FOUND BY THE PRE-LAUNCH BROWSER QA, not by reading code: the route returned HTTP 200 with
 * **zero <h1>** at all five breakpoints and a body class of `post-type-archive-location`, i.e. it
 * was falling through to Hello Elementor's generic archive.php and rendering the raw WordPress
 * loop — a bare list of `<h2 class="entry-title">` location titles, no hero, no design, no shell
 * composition. It is not an obscure corner: it is linked from NINE places in this plugin alone,
 * including the breadcrumb of EVERY restaurant page ("Home / Ristoranti / Monti") and the footer.
 *
 * Assembled from components that already exist — nothing new was designed and no copy was
 * invented:
 *   - the Hero's own `minimal` variant, which css/components/hero.css documents as
 *     "title-only, for utility/hub pages". No photograph to choose, no standfirst to write. The
 *     single <h1> is the word the breadcrumb and the footer already use for this destination.
 *   - the shared restaurant card, tmk_render_city_card() -> .tmk-card--overlay.tmk-card--city,
 *     the identical component used by the Homepage's language, Storie, the single story, the City
 *     pages and the Location page's "Altri ristoranti" row.
 *   - the shared CardGrid and the shared page grid (.tmk-city).
 *
 * The cities are DERIVED from the taxonomy, in the site's own geographic order, not typed: the
 * legacy [tmk_city_cards] shortcode hard-codes five slugs and would silently omit Reggio Calabria
 * and Capo d'Orlando. Terms with no published restaurant are skipped, so the page cannot show a
 * card that leads nowhere. Single-location cities keep their existing 301 to the restaurant
 * itself (inc/city-routing.php), so every card resolves to a real page either way.
 */
function tmk_render_restaurants_index() {
	$terms = get_terms( array(
		'taxonomy'   => 'city',
		'hide_empty' => true,
		'orderby'    => 'count',
		'order'      => 'DESC',
	) );
	if ( is_wp_error( $terms ) ) {
		$terms = array();
	}

	ob_start();

	echo tmk_render_hero( array(
		'variant'   => 'minimal',
		'headline'  => 'Ristoranti',
		'cta_label' => '', // no CTA on an index: the cards ARE the call to action
	) );
	?>
	<div class="tmk-city tmk-restaurants-index">
		<?php if ( ! $terms ) : ?>
			<section class="tmk-city__band">
				<?php echo tmk_render_system_state( array(
					'kind'         => 'empty',
					'title'        => 'Nessun ristorante pubblicato',
					'copy'         => 'Torna a trovarci: stiamo aggiornando le nostre sedi.',
					'action_label' => 'Torna alla home',
					'action_href'  => home_url( '/' ),
				) ); ?>
			</section>
		<?php else : ?>
			<section class="tmk-city__band tmk-decor-l-leaves tmk-decor--keep" aria-label="Le nostre citt&agrave;">
				<?php
				$cards = '';
				$i     = 0;
				foreach ( $terms as $term ) {
					$cards .= tmk_render_city_card(
						$term,
						0 === $i ? 'eager' : 'lazy',
						'tmk-reveal',
						'(min-width:1024px) 700px, calc(100vw - 64px)'
					);
					$i++;
				}
				echo tmk_render_card_grid( $cards, 2, 2 );
				?>
			</section>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'tmk_restaurants_index', 'tmk_render_restaurants_index' );
