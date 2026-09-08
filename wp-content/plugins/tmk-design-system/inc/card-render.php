<?php
/**
 * Card — direct translation of components/cards/Card.jsx. One base contract, six variants
 * (location/city are the --overlay shape; menu/story/event/job are the default content shape —
 * see css/components/card.css). tmk_render_card() is the generic primitive; tmk_render_city_card()
 * binds it to the real `city` taxonomy for the Homepage's "Le città" section so this stays real
 * WP data, not the Claude Design demo array. Exposed generically as [tmk_card].
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function tmk_render_card( $atts = array() ) {
	$atts = shortcode_atts( array(
		'variant'    => 'location', // location | city | menu | story | event | job
		'media_id'   => 0,
		'eyebrow'    => '',
		'title'      => '',
		'supporting' => '',
		'price'      => '',
		'tags'       => '', // comma-separated
		'action'     => '',
		'href'       => '#',
		'concept'    => '',
		// 'lazy' (default) | 'eager'. A card grid is almost always below the fold, so lazy is the
		// right default; pass 'eager' for a card that is genuinely above it, so the browser does
		// not defer a visible image and cause a blank-then-pop. The Homepage's first city card is
		// above the fold at >=1024px viewports, so tmk_render_city_cards() marks it eager.
		'loading'    => 'lazy',
			/*
			 * Responsive-image hint. Defaults to the widest instance (a full-width overlay
			 * banner) because that was the only shape this component had when it was written.
			 * Every caller that renders a card inside a grid MUST pass its own: a 286px card
			 * asking for a 1140px slot makes the browser pick a 1140px file, which on the
			 * Homepage meant four ~1180px photographs downloaded to fill four thumbnails.
			 */
			'sizes'      => '',
			/*
			 * Extra classes from the caller, e.g. an opt-in to the shared scroll reveal. The
			 * composition that places a card decides that, not the card.
			 */
			'class'      => '',
			/*
			 * Extra data-* attributes for the card's own <a>, as name => value. Used by a
			 * composition that has to address its cards from script without inventing a second
			 * class taxonomy for it — the Careers Hub's category filter passes
			 * array( 'data-tmk-job-area' => 'ristoranti' ). Keys are emitted verbatim after
			 * esc_attr(); a caller that needs a data attribute names it in full, so nothing here
			 * has to guess a prefix. Empty values are dropped rather than emitted as="".
			 */
			'data'       => array(),
	), $atts );

	$overlay = in_array( $atts['variant'], array( 'location', 'city' ), true );
	$default_actions = array( 'location' => 'Prenota ora', 'city' => 'Esplora', 'menu' => '', 'story' => 'Leggi', 'event' => 'Scopri', 'job' => 'Vedi il ruolo' );
	$action = $atts['action'] ?: ( $default_actions[ $atts['variant'] ] ?? '' );
	/*
	 * Media is rendered through wp_get_attachment_image() rather than a bare <img src>, so the
	 * card inherits WordPress' own responsive-image pipeline. Fixed during the Homepage audit
	 * (2026-07-29): the previous hardcoded 'large' size caps at 1024px, while the overlay shapes
	 * render at the full container width (1140px, and up to ~1416px at --bp-wide), so every city
	 * banner was being upscaled - and shipped with no srcset, no width/height and no lazy
	 * loading. 'full' + srcset lets the browser pick the right file, width/height give the
	 * intrinsic ratio (CLS), and lazy loading is correct for every card: on the Homepage the
	 * first one sits well below the fold behind the Hero and the Narrative Band.
	 * alt is intentionally empty - the card's accessible name comes from its title/eyebrow text
	 * inside the same link, so the photo is decorative to assistive technology.
	 */
	$media_html = $atts['media_id'] ? wp_get_attachment_image(
		(int) $atts['media_id'],
		'full',
		false,
		array(
			'alt'      => '',
			'loading'  => $atts['loading'],
			'decoding' => 'async',
			'sizes'    => $atts['sizes'] ? $atts['sizes'] : '(min-width: 1024px) 1140px, 100vw',
		)
	) : '';
	$tags = array_filter( array_map( 'trim', explode( ',', $atts['tags'] ) ) );
	$concept_attr = $atts['concept'] ? ' data-concept="' . esc_attr( $atts['concept'] ) . '"' : '';
	if ( is_array( $atts['data'] ) ) {
		foreach ( $atts['data'] as $name => $value ) {
			if ( '' === $value || null === $value ) {
				continue;
			}
			$concept_attr .= ' ' . esc_attr( $name ) . '="' . esc_attr( $value ) . '"';
		}
	}
	$shape_class = $overlay ? 'tmk-card--overlay tmk-card--' . esc_attr( $atts['variant'] ) : 'tmk-card--content';
	if ( $atts['class'] ) {
		$shape_class .= ' ' . esc_attr( $atts['class'] );
	}

	ob_start();

	if ( $overlay ) : ?>
		<a href="<?php echo esc_url( $atts['href'] ); ?>" class="tmk-card <?php echo $shape_class; ?>"<?php echo $concept_attr; ?>>
			<?php if ( $media_html ) : ?><div class="tmk-card__media"><?php echo $media_html; ?></div><?php endif; ?>
			<div class="tmk-card__scrim"></div>
			<div class="tmk-card__body">
				<?php if ( $atts['eyebrow'] ) : ?><span class="tmk-card__eyebrow"><?php echo esc_html( $atts['eyebrow'] ); ?></span><?php endif; ?>
				<span class="tmk-card__title"><?php echo esc_html( $atts['title'] ); ?></span>
				<?php if ( $action ) : ?><span class="tmk-card__action"><?php echo esc_html( $action ); ?></span><?php endif; ?>
			</div>
			<?php if ( $atts['concept'] ) : ?><span class="tmk-card__concept-badge"><?php echo esc_html( $atts['concept'] ); ?></span><?php endif; ?>
		</a>
	<?php else : ?>
		<a href="<?php echo esc_url( $atts['href'] ); ?>" class="tmk-card <?php echo $shape_class; ?>"<?php echo $concept_attr; ?>>
			<?php if ( $media_html ) : ?><div class="tmk-card__media"><?php echo $media_html; ?></div><?php endif; ?>
			<div class="tmk-card__body">
				<?php if ( $atts['eyebrow'] ) : ?><span class="tmk-card__eyebrow"><?php echo esc_html( $atts['eyebrow'] ); ?></span><?php endif; ?>
				<span class="tmk-card__title"><?php echo esc_html( $atts['title'] ); ?></span>
				<?php if ( $atts['supporting'] || $atts['price'] ) : ?>
					<span class="tmk-card__meta">
						<span><?php echo esc_html( $atts['supporting'] ); ?></span>
						<?php if ( $atts['price'] ) : ?><strong class="tmk-card__price"><?php echo esc_html( $atts['price'] ); ?></strong><?php endif; ?>
					</span>
				<?php endif; ?>
				<?php if ( $tags ) : ?>
					<span class="tmk-card__tags"><?php foreach ( $tags as $tag ) : ?><span class="tmk-card__tag"><?php echo esc_html( $tag ); ?></span><?php endforeach; ?></span>
				<?php endif; ?>
				<?php if ( $action ) : ?><span class="tmk-card__action"><?php echo esc_html( $action ); ?> &rarr;</span><?php endif; ?>
			</div>
		</a>
	<?php endif;

	return ob_get_clean();
}
add_shortcode( 'tmk_card', function ( $atts ) {
	return tmk_render_card( $atts );
} );

/* ================================================================================================
   CARD CITTA' — Card's "city" variant bound to the real `city` taxonomy + `location` CPT count,
   for the Homepage's city section and for /ristoranti/. Real WP data in place of Claude Design's
   ui_kits demo array.

   2026-09-07, DUE correzioni, entrambe segnalate dal cliente sul sito in produzione.

   1. IL CONTEGGIO. Era $term->count, cioe' il conteggio di WordPress: conta le sedi assegnate al
      termine e non sa nulla delle chiusure. Ora conta le sedi ATTIVE (tmk_city_active_count), cosi'
      una citta' non puo' annunciare un ristorante che non c'e' piu'.

   2. LA FOTO DI COPERTINA. La foto della card e' la meta `hero_image_id` del termine citta'.
      Verificato in produzione: Milano 29, Roma 31, Bologna 30, Lione 32, Rimini 33 — ma Reggio
      Calabria e Capo d'Orlando hanno hero_image_id = 0, quindi su /ristoranti/ le loro card
      uscivano SENZA immagine. Era esattamente la segnalazione "manca la foto di copertina di
      Reggio Calabria": non un problema di template, ma un'associazione mai fatta.

      Qui non si inventa nulla e non si sceglie una foto al posto del cliente: se il termine non ha
      una propria immagine, si usa l'immagine in evidenza della sua prima sede attiva — cioe' LA
      STESSA foto che la scheda di quel ristorante gia' pubblica come apertura. E' la logica di
      copertina che il sito usa gia' altrove, applicata al caso in cui il dato piu' specifico
      manchi. Appena qualcuno imposta una vera foto di citta', quella torna a vincere.
   ============================================================================================== */

/**
 * La foto di copertina di una citta': la sua `hero_image_id`, altrimenti l'immagine in evidenza
 * della prima sede ancora attiva. Zero solo se non c'e' davvero nessuna fotografia da usare — e in
 * quel caso Card omette del tutto il livello immagine, come ha sempre fatto.
 *
 * @param WP_Term $term
 * @return int
 */
function tmk_city_cover_media_id( $term ) {
	$media_id = (int) get_term_meta( $term->term_id, 'hero_image_id', true );
	if ( $media_id ) {
		return $media_id;
	}
	$active = tmk_city_active_locations( $term );
	return $active ? (int) get_post_thumbnail_id( $active[0]->ID ) : 0;
}

/**
 * @param WP_Term $term
 * @return string
 */
function tmk_render_city_card( $term, $loading = 'lazy', $class = '', $sizes = '' ) {
	$location_count = tmk_city_active_count( $term );

	return tmk_render_card( array(
		'variant'  => 'city',
		'media_id' => tmk_city_cover_media_id( $term ),
		'title'    => $term->name,
		'eyebrow'  => $location_count . ' ' . ( 1 === $location_count ? 'ristorante' : 'ristoranti' ),
		'href'     => tmk_city_href( $term ),
		'action'   => 'Esplora',
		'loading'  => $loading,
		'class'    => $class,
		// The banner is the full width of the content column: 1216px inside --container-wide.
		'sizes'    => $sizes ? $sizes : '(min-width: 1280px) 1216px, calc(100vw - 64px)',
	) );
}

/**
 * Homepage's "Le città" section — real `city` taxonomy terms, in the same order and city
 * selection as the approved Claude Design Homepage (ui_kits/website/data.js: Milano, Bologna,
 * Roma, Lione, Rimini). Single-location cities not in that demo set (Capo d'Orlando, Reggio
 * Calabria) surface elsewhere (mega menu, future City Hub) rather than on the Homepage, matching
 * Claude Design's own scope exactly rather than mechanically listing every seeded city.
 */
function tmk_render_city_cards( $atts = array() ) {
	$slugs = array( 'milano', 'bologna', 'roma', 'lione', 'rimini' );
	ob_start();
	?>
	<section aria-label="Le città" class="tmk-city-cards">
		<?php foreach ( $slugs as $index => $slug ) :
			$term = get_term_by( 'slug', $slug, 'city' );
			if ( $term ) echo tmk_render_city_card( $term, 0 === $index ? 'eager' : 'lazy' );
		endforeach; ?>
	</section>
	<?php
	return ob_get_clean();
}
add_shortcode( 'tmk_city_cards', function ( $atts ) {
	return tmk_render_city_cards( $atts );
} );

/**
 * CardGrid — direct translation of components/cards/CardGrid.jsx: one shared, configurable
 * container for any set of Cards.
 *
 * The JSX's whole API is a column count, and it stays one here — but as an explicit count per
 * breakpoint rather than the source's auto-fill density hint. See the long note in
 * css/components/card.css for why: auto-fill fixes the track width and lets the browser decide
 * how many items land on the last row, which is how Milano's fifth restaurant ended up alone and
 * flush left under a row of four.
 *
 * @param string $inner_html      Pre-rendered cards. Elementor's Shortcode widget cannot nest
 *                                widgets, so composition happens in PHP; the enclosing
 *                                [tmk_card_grid columns="3"]…[/tmk_card_grid] form exists for
 *                                hand-authored [tmk_card] content.
 * @param int    $columns         Columns from --bp-desktop up.
 * @param int    $columns_tablet  Columns for tablet (600-1023). Defaults to half the desktop
 *                                count, floored at 2 — the density that keeps a 4:3 card wide
 *                                enough to still be a photograph on a 768px viewport.
 * @return string
 */
function tmk_render_card_grid( $inner_html, $columns = 3, $columns_tablet = 0 ) {
	$columns = max( 1, (int) $columns );
	$columns_tablet = $columns_tablet ? max( 1, (int) $columns_tablet ) : max( 2, (int) ceil( $columns / 2 ) );
	$columns_tablet = min( $columns_tablet, $columns );

	/*
	 * The grid opts every set of cards into the shared stagger reveal. That is not motion being
	 * smuggled into a component: css/animations/reveal.css names Card Grid as the mechanism's
	 * intended consumer, and a grid is the one place where a per-child delay is meaningful. The
	 * class is inert without js/animations/reveal.js and collapses to nothing under
	 * prefers-reduced-motion, so it can never leave cards invisible.
	 */
	return '<div class="tmk-card-grid tmk-reveal-stagger" style="--tmk-cols-desktop:' . esc_attr( $columns )
		. ';--tmk-cols-tablet:' . esc_attr( $columns_tablet ) . '">' . $inner_html . '</div>';
}
add_shortcode( 'tmk_card_grid', function ( $atts, $content = '' ) {
	$atts = shortcode_atts( array( 'columns' => 3, 'columns_tablet' => 0 ), $atts );
	return tmk_render_card_grid( do_shortcode( (string) $content ), $atts['columns'], $atts['columns_tablet'] );
} );

/**
 * Where a city term points. Since Session 8 the master City page (inc/city-render.php) exists at
 * /ristoranti/{slug}/, so every city — one restaurant or many — links to its own City page. The
 * earlier special-case (single-location cities linked straight to the restaurant "because no City
 * page exists yet", multi-location cities used a /ristoranti/#slug anchor) is retired: a
 * single-location city's City URL 301-redirects to that restaurant in inc/city-routing.php, so the
 * one destination is correct for both and the caller never has to know a city's restaurant count.
 */
function tmk_city_href( $term ) {
	return home_url( '/ristoranti/' . $term->slug . '/' );
}

/**
 * Location card bound to a real `location` post, as used inside a CityBlock.
 * Canonical CityBlock (pages/Homepage.jsx) passes the CITY photo as each location card's media
 * (media={c.img}), not a per-location image, so one city's row reads as a single photographic
 * family — reproduced here rather than reaching for a featured image the design does not use.
 * `concept` is the concept term's accent_token (falling back to its slug); "standard" is the
 * root default rather than a badge, matching homepage-data.js where non-concept locations
 * (Linate, Fiumicino) simply carry no concept key.
 */
function tmk_render_location_card( $post, $city_term, $media_id, $loading = 'lazy', $sizes = '' ) {
	$concept       = '';
	$concept_terms = wp_get_post_terms( $post->ID, 'concept' );
	if ( ! is_wp_error( $concept_terms ) && $concept_terms ) {
		$token   = get_term_meta( $concept_terms[0]->term_id, 'accent_token', true );
		$concept = $token ? $token : $concept_terms[0]->slug;
		if ( 'standard' === $concept ) $concept = '';
	}
	return tmk_render_card( array(
		'variant'  => 'location',
		'media_id' => (int) $media_id,
		'concept'  => $concept,
		'eyebrow'  => $city_term->name,
		'title'    => get_the_title( $post ),
		'action'   => 'Prenota',
		'href'     => get_permalink( $post ),
		'loading'  => $loading,
		'sizes'    => $sizes,
	) );
}

/**
 * Canonical city order — the IA §1 sitemap order, transcribed from Claude Design's
 * shell/shell-data.js, which is the design's own single source for the city → location network
 * and is what the mega menu is already built from. The Homepage bands follow it so the site never
 * presents the same network in two different orders.
 *
 * Cities NOT in this list are not excluded: they are appended alphabetically. Opening a
 * restaurant in a new city must never require a code change for it to appear on the Homepage.
 *
 * @return string[]
 */
function tmk_canonical_city_slugs() {
	return array( 'milano', 'roma', 'bologna', 'lione', 'rimini', 'reggio-calabria', 'capo-dorlando' );
}

/**
 * Every city term that actually has a restaurant APERTO, in canonical order.
 *
 * `hide_empty` toglie le citta' senza sedi assegnate; il filtro aggiunto qui toglie anche quelle
 * le cui sedi hanno superato il proprio ultimo giorno di servizio (tmk_location_is_active), che
 * per WordPress restano "piene" perche' il post e' ancora pubblicato. Senza questo passaggio una
 * citta' chiusa continuerebbe a comparire in "Altri ristoranti Temakinho" e fra le bande citta'.
 *
 * @return WP_Term[]
 */
function tmk_ordered_city_terms() {
	$terms = get_terms( array( 'taxonomy' => 'city', 'hide_empty' => true, 'orderby' => 'name' ) );
	if ( is_wp_error( $terms ) || ! $terms ) {
		return array();
	}
	$terms = array_values( array_filter( $terms, function ( $term ) {
		return tmk_city_active_count( $term ) > 0;
	} ) );
	if ( ! $terms ) {
		return array();
	}
	$rank    = array_flip( tmk_canonical_city_slugs() );
	$ranked  = array();
	$unknown = array();
	foreach ( $terms as $term ) {
		if ( isset( $rank[ $term->slug ] ) ) {
			$ranked[ $rank[ $term->slug ] ] = $term;
		} else {
			$unknown[] = $term;
		}
	}
	ksort( $ranked );
	return array_merge( array_values( $ranked ), $unknown );
}

/**
 * The Homepage's city composition — direct translation of pages/Homepage.jsx + homepage-data.js.
 *
 * Structure, verbatim from the source: every city with more than one restaurant ("an anchor")
 * earns a full-width 21:8 banner followed by a tight row of its restaurant cards; the
 * single-restaurant cities compose ONE editorial row inside <section aria-label="Le altre
 * città"> with a caption-scale H2, as location-variant (4:3) cards rather than banners.
 *
 * COMPOSITION from Claude Design, DATASET from WordPress. The prototype hardcodes five cities
 * (Milano, Bologna, Roma, Lione, Rimini) because that is what its demo array contains, and this
 * function used to hardcode the same five slugs to match it. It no longer does: Temakinho has
 * seven cities and fourteen restaurants, and Reggio Calabria and Capo d'Orlando were being hidden
 * from the Homepage to make a screenshot match. Production data outranks the prototype's sample
 * of it — the design specifies the SHAPE of the composition, not how many restaurants the company
 * has. Every city with a restaurant now appears, and a new one appears on its own.
 *
 * Column counts: restaurant rows are 4-up on desktop so that every restaurant card on the page is
 * the same size regardless of which city it belongs to (Milano's 5 and Roma's 4 would otherwise
 * render at different widths); an incomplete last row is centred by CardGrid rather than left
 * hanging. "Le altre città" is a single row of up to 5 compact cards — denser and smaller than a
 * restaurant row on purpose, because it is the secondary tier of the same navigation.
 *
 * The sizes hints below are written out in pixels because the responsive-images spec has no
 * access to custom properties: 64 is the wrapper's --space-6 inline padding doubled and 24 is
 * --grid-gutter. If either token changes, these change with it.
 *
 * Taglines: homepage-data.js gives every city a tagline and Homepage.jsx passes it as Card's
 * "supporting" prop, but Card's overlay shape never renders "supporting" — so no tagline appears
 * in the approved design either. Verified against the design-system bundle. Not rendered here.
 */
function tmk_render_city_bands( $atts = array() ) {
	$anchors = array();
	$others  = array();
	foreach ( tmk_ordered_city_terms() as $term ) {
		// Sedi ATTIVE, non $term->count: una citta' che resta con una sola sede aperta deve
		// scendere fra "Le altre citta'" invece di tenere la propria banda a tutta larghezza.
		if ( tmk_city_active_count( $term ) > 1 ) {
			$anchors[] = $term;
		} else {
			$others[] = $term;
		}
	}

	// A restaurant card in a 4-up row is 286px wide at 1280px up - but it deliberately asks for the
	// SAME width as the city banner above it, which is not a mistake and not over-fetching.
	// Every location card in a block is fed that block's city photograph (media={c.img} in
	// Homepage.jsx), so card and banner are the same file. Requesting the same candidate makes the
	// whole restaurant row resolve to the banner's already-downloaded, already-decoded bitmap: one
	// HTTP request and one decode serve the band and all of its cards, and the row paints instantly
	// instead of popping in. Measured before: interior-roma.jpg was fetched twice, full (125KB) for
	// the band plus 768w (59KB) for the cards - 184KB for one photograph. After: 125KB, and the
	// cards are sharper, because a 4:3 crop of a 21:8 source needs ~1.61x the box width in source
	// pixels (object-fit:cover scales to cover the BOX) and srcset selection is width-only, so the
	// literal 286px hint had been selecting a 300x140 file and upscaling it 1.61x.
	// Requires inc/media.php's wp_img_tag_add_auto_sizes filter, or sizes="auto" discards all of this.
	$row_sizes = '(min-width: 1280px) 1216px, calc(100vw - 64px)';

	ob_start();
	?>
	<div class="tmk-city-bands">
		<?php
		$is_first = true;
		foreach ( $anchors as $term ) :
			// 2026-09-08: stesso helper delle card (tmk_city_cover_media_id) invece della lettura
			// diretta di hero_image_id. Questo $media_id NON e' la foto della banda — quella la
			// risolve gia' tmk_render_city_card() — ma la fotografia passata alle card ristorante
			// dentro la banda: senza ripiego, una citta' con 2+ sedi e senza foto di termine le
			// renderebbe tutte senza immagine. Milano e Roma hanno la propria foto, quindi oggi il
			// risultato non cambia: chiude un difetto latente, non un difetto visibile.
			$media_id  = tmk_city_cover_media_id( $term );
			// Le sole sedi ancora in servizio, dalla stessa fonte usata da ogni altro elenco.
			$locations = tmk_city_active_locations( $term );
			?>
			<div class="tmk-city-bands__block">
<?php
/*
 * Heading structure. Card renders its title as a <span> (faithful to Card.jsx), so a city band
 * contributes nothing to the document outline: before this, the Homepage exposed only the sr-only
 * H1, the Narrative Band, "Le altre citta", the CTA Banner and the Newsletter to heading
 * navigation - the city bands, which ARE the page's primary navigation, were unreachable that way.
 * A visually hidden H2 per band restores the outline at the same level as "Le altre citta" while
 * changing not one rendered pixel and not touching Card's contract. Same technique, and same
 * .tmk-sr-only utility, as the page's canonical sr-only H1.
 */
?>
<h2 class="tmk-sr-only"><?php echo esc_html( $term->name ); ?></h2>
				<?php
				/*
				 * The first banner is the first photograph below the hero and is in view on a
				 * laptop before any scrolling, so it loads eagerly; the rest stay lazy.
				 * tmk-reveal opts the banner into the shared scroll reveal — its restaurant row
				 * gets the staggered variant from CardGrid instead.
				 */
				echo tmk_render_city_card( $term, $is_first ? 'eager' : 'lazy', 'tmk-reveal' );

				if ( count( $locations ) > 1 ) {
					$cards = '';
					foreach ( $locations as $location ) {
						$cards .= tmk_render_location_card( $location, $term, $media_id, 'lazy', $row_sizes );
					}
					echo tmk_render_card_grid( $cards, 4, 2 );
				}
				?>
			</div>
			<?php
			$is_first = false;
		endforeach;
		?>

		<?php if ( $others ) :
			$columns    = max( 1, min( count( $others ), 5 ) );
			$gutters    = ( $columns - 1 ) * 24;
			$others_sizes = sprintf(
				'(min-width: 1280px) %dpx, (min-width: 1024px) calc(((100vw - %dpx) / %d) * 1.61), (min-width: 600px) calc(((100vw - 112px) / 3) * 1.61), calc((100vw - 64px) * 1.61)',
				(int) ceil( ( ( 1216 - $gutters ) / $columns ) * 1.61 ),
				64 + $gutters,
				$columns
			);
			?>
			<section class="tmk-city-bands__others" aria-label="Le altre città">
				<h2 class="tmk-city-bands__others-title tmk-reveal">Le altre città</h2>
				<?php
				$cards = '';
				foreach ( $others as $term ) {
					// Stesse due regole della card citta' qui sopra: conteggio delle sedi ATTIVE e
					// foto di ripiego quando il termine non ha una propria immagine (Reggio
					// Calabria non ne ha una, e senza questo la card usciva vuota).
					$count  = tmk_city_active_count( $term );
					$cards .= tmk_render_card( array(
						'variant'  => 'location',
						'media_id' => tmk_city_cover_media_id( $term ),
						'eyebrow'  => $count . ' ' . ( 1 === $count ? 'ristorante' : 'ristoranti' ),
						'title'    => $term->name,
						'action'   => 'Esplora',
						'href'     => tmk_city_href( $term ),
						'sizes'    => $others_sizes,
					) );
				}
				echo tmk_render_card_grid( $cards, $columns, 3 );
				?>
			</section>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'tmk_city_bands', function ( $atts ) {
	return tmk_render_city_bands( $atts );
} );
