<?php
/**
 * Stories — the editorial magazine: Story Hub (/storie/) + the Editorial Article (/storie/{slug}/).
 * Direct translation of pages/StoryHub.jsx and pages/EditorialArticle.jsx (Blueprint §5.6 family).
 *
 * COMPOSITION from Claude Design, DATASET from WordPress — the same doctrine as the Homepage /
 * Location / City assemblers. Stories are native `post`s (IA §10, §25: the blog IS the magazine,
 * extended not replaced); the hub is a curated magazine view of them, not a dated archive loop.
 *
 * Section orders are the approved templates' fixed sequences and are never reordered:
 *   HUB      Hero -> Cover story (diptych) -> Features (2) -> Briefs (index) -> Related cities ->
 *            Trust -> Closure. The Closure newsletter is the site-wide shell Footer's (one
 *            newsletter site-wide, the reconciled Location doctrine, PROJECT_CONTEXT §20.2), not a
 *            second inline form here.
 *   ARTICLE  Editorial hero (image + kicker + Londrina H1 + standfirst + byline) -> Breadcrumb ->
 *            the post body (post_content: lead, prose, pull quotes as blockquotes, full-bleed
 *            figures) -> heritage motif -> Related city bridge -> (footer newsletter).
 *
 * Every dynamic beat degrades to nothing when its data is absent (no posts -> empty state; no
 * related_city -> no bridge), never faked.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Italian long date ("18 luglio 2026") independent of the WordPress site locale. The site chrome
 * and every editorial string are Italian (the header declares lang="it"), but get_the_date() follows
 * Settings > Site Language, which here is not it_IT — so a story byline read "18 July 2026". Rather
 * than flip a global admin setting (and require the it_IT language pack), the month is mapped
 * deterministically. Shared by Stories and Events. Accepts a post or a Unix timestamp.
 */
function tmk_date_it( $post_or_ts ) {
	$ts = is_numeric( $post_or_ts ) ? (int) $post_or_ts : (int) get_post_time( 'U', false, $post_or_ts );
	$months = array( 1 => 'gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre' );
	return (int) wp_date( 'j', $ts ) . ' ' . $months[ (int) wp_date( 'n', $ts ) ] . ' ' . wp_date( 'Y', $ts );
}

/** Reading time in whole minutes from a post's body (~200 wpm, floored at 1). */
function tmk_reading_time( $post ) {
	$words = str_word_count( wp_strip_all_tags( get_post_field( 'post_content', $post ) ) );
	return max( 1, (int) round( $words / 200 ) );
}

/**
 * The story kicker. Two registers, one function.
 *
 * The long form — "Categoria · data · N min" — belongs to a piece the reader has committed to:
 * the cover story and the article's own header, where reading time is a genuine decision aid.
 *
 * The card form swaps reading time for the story's PLACE — "Categoria · Milano · 29 maggio 2026".
 * That is the client's "maggiore connessione tra articoli e ristoranti", and it costs no invented
 * copy: `related_city` is already set on all seven published stories and is the same meta the
 * article template's own "Dove vivere questa storia" bridge resolves. A card that says Milano is
 * a card that connects to a restaurant; a card that says "3 min" connects to nothing. Reading
 * time is not lost, it moves to where it is actually used.
 *
 * Every segment degrades to nothing when its data is absent — a story with no category, no city
 * or no date renders a shorter kicker, never a placeholder.
 *
 * @param WP_Post|int $post
 * @param string      $form 'long' (category · date · reading time) or 'card' (category · city · date).
 */
function tmk_story_kicker( $post, $form = 'long' ) {
	$parts = array();
	$cats  = get_the_terms( $post, 'category' );
	if ( $cats && ! is_wp_error( $cats ) && 'uncategorized' !== $cats[0]->slug ) {
		$parts[] = $cats[0]->name;
	}
	if ( 'card' === $form ) {
		$city = tmk_story_city( $post );
		if ( $city ) {
			$parts[] = $city->name;
		}
	}
	$parts[] = tmk_date_it( $post );
	if ( 'card' !== $form ) {
		$parts[] = tmk_reading_time( $post ) . ' min';
	}
	return implode( ' · ', $parts );
}

/**
 * The `city` term a story is bound to, or null.
 *
 * One lookup, one place: the hub's related-cities row, the card kicker and the article bridge all
 * resolve `related_city` through here rather than each re-reading the meta and re-querying the
 * taxonomy. A slug that no longer matches a term (a city removed from the taxonomy) resolves to
 * null and every caller simply omits its city beat.
 */
function tmk_story_city( $post ) {
	$slug = (string) get_post_meta( is_object( $post ) ? $post->ID : (int) $post, 'related_city', true );
	if ( '' === $slug ) {
		return null;
	}
	$term = get_term_by( 'slug', $slug, 'city' );
	return ( $term && ! is_wp_error( $term ) ) ? $term : null;
}

/** A single story card (Card "story" variant), bound to a real post. */
function tmk_story_card( $post, $sizes = '' ) {
	return tmk_render_card( array(
		'variant'    => 'story',
		'media_id'   => (int) get_post_thumbnail_id( $post ),
		'eyebrow'    => tmk_story_kicker( $post, 'card' ),
		'title'      => get_the_title( $post ),
		'supporting' => get_the_excerpt( $post ),
		'action'     => 'Leggi',
		'href'       => get_permalink( $post ),
		'sizes'      => $sizes,
	) );
}

/**
 * The city cards the magazine bridges to ("Dove vivere queste storie").
 *
 * WAS HARD-CODED: array( 'rimini', 'milano' ), two slugs typed into the signature, shown to every
 * visitor whatever the magazine was actually about. The client asked for the link between stories
 * and restaurants to be real and intuitive; a fixed pair is neither. The row is now DERIVED from
 * the stories on the page — each one's own `related_city`, in the order the stories appear,
 * de-duplicated. Reading three stories about Bologna and being offered Rimini was the defect.
 *
 * Same component as everywhere else on the site: tmk_render_city_card() -> the design system's
 * .tmk-card--overlay.tmk-card--city, i.e. the exact card the Location page's "Altri ristoranti"
 * row and the city bands are built from — same photo, same scrim, same Voice title, same Esplora
 * chip, same hover, same responsive contract. No Storie-only restaurant card exists.
 *
 * Degrades honestly: no stories, or no story with a city, renders nothing at all and the caller
 * drops the whole section rather than showing an empty band.
 *
 * @param WP_Post[] $posts Stories on the page, in display order.
 * @param int       $max   Cards to show. Three is the row the CardGrid centres cleanly.
 */
function tmk_story_related_cities( $posts, $max = 3 ) {
	$seen  = array();
	$terms = array();
	foreach ( $posts as $p ) {
		$term = tmk_story_city( $p );
		if ( ! $term || isset( $seen[ $term->term_id ] ) ) {
			continue;
		}
		$seen[ $term->term_id ] = true;
		$terms[]                = $term;
		if ( count( $terms ) >= $max ) {
			break;
		}
	}
	$cards = '';
	$sizes = count( $terms ) > 1 ? '(min-width:1024px) 460px, calc(100vw - 64px)' : '(min-width:1024px) 1400px, calc(100vw - 64px)';
	foreach ( $terms as $term ) {
		$cards .= tmk_render_city_card( $term, 'lazy', 'tmk-reveal', $sizes );
	}
	return array( $cards, count( $terms ) );
}

/**
 * The story's own restaurant card — the SHARED component, on the single-story page.
 *
 * WHAT IT REPLACES. Template #560 shipped a hand-built Elementor container (tmkea014) holding two
 * headings: "Esplora i nostri ristoranti" and an "Esplora" chip, linking to a city URL composed
 * from the post's `related_city` meta. No photograph, no city name, no restaurant count — a
 * text block shaped like a card. _BUILD/105 recorded exactly why: no native Elementor Pro dynamic
 * tag can resolve a slug -> a term -> that term's hero image, so a generic link was the honest
 * fallback at the time and was documented as a step down from the design.
 *
 * PHP has no such limitation. This renders tmk_render_city_card() — the same
 * .tmk-card--overlay.tmk-card--city that the Storie hub, the City pages, the Location page's
 * "Altri ristoranti" row and the Careers hub all use — from the same real `city` term, with the
 * real hero photograph and the real restaurant count. One component, one data source, five
 * placements: which is the whole of the brief's point 6.
 *
 * The heading travels WITH the card rather than staying as a separate Elementor widget, because
 * the block has to be able to disappear whole: a post with no `related_city` would otherwise
 * leave "Dove vivere questa storia" standing over nothing. Same conditional-block reasoning as
 * tmk_render_job_requirements(). All seven published stories currently have a city, so today this
 * always renders — the guard is for the eighth.
 */
function tmk_render_story_city( $atts = array() ) {
	$atts = shortcode_atts( array( 'post_id' => 0, 'title' => 'Dove vivere questa storia' ), $atts, 'tmk_story_city' );
	$post = $atts['post_id'] ? get_post( (int) $atts['post_id'] ) : get_post();
	if ( ! $post instanceof WP_Post ) {
		return '';
	}
	$term = tmk_story_city( $post );
	if ( ! $term ) {
		return '';
	}
	$id = 'tmk-story-city-' . $post->ID;
	return '<section class="tmk-story-city" aria-labelledby="' . esc_attr( $id ) . '">'
		. '<h2 class="tmk-section-title" id="' . esc_attr( $id ) . '">' . esc_html( $atts['title'] ) . '</h2>'
		. tmk_render_card_grid(
			tmk_render_city_card( $term, 'lazy', 'tmk-reveal', '(min-width:1024px) 1400px, calc(100vw - 64px)' ),
			1
		)
		. '</section>';
}
add_shortcode( 'tmk_story_city', 'tmk_render_story_city' );

/**
 * STORY HUB — the magazine cover. Featured = newest post; the next two are Features; the rest are
 * the Briefs index. Direct translation of pages/StoryHub.jsx.
 */
function tmk_render_story_hub() {
	$posts = get_posts( array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
	) );

	$featured = $posts ? array_shift( $posts ) : null;
	$features = array_slice( $posts, 0, 2 );
	$briefs   = array_slice( $posts, 2 );

	ob_start();

	echo tmk_render_hero( array(
		'variant'     => 'full-bleed',
		'media_id'    => function_exists( 'tmk_asset_id' ) ? tmk_asset_id( 'interior-roma' ) : 0,
		'media_alt'   => 'Sala Temakinho a Roma: legni caldi, fiori freschi, luce d’ambra',
		'eyebrow'     => 'Il magazine di Temakinho',
		'headline'    => 'Storie',
		'subheadline' => 'Cultura, tecnica e persone: il Giappone e il Brasile raccontati un piatto alla volta.',
		'cta_label'   => '', // magazine cover carries no CTA (ctaLabel={null} in the source)
		'height'      => 'min(58vh, 520px)',
	) );
	?>

	<div class="tmk-stack tmk-story-hub">

		<?php if ( ! $featured ) : ?>
			<section class="tmk-band-airy" aria-label="Storie">
				<?php echo tmk_render_system_state( array(
					'kind'         => 'empty',
					'title'        => 'Il magazine sta per aprire',
					'copy'         => 'Le prime storie arrivano a breve. Nel frattempo, c’è una tavola che ti aspetta.',
					'action_label' => 'Esplora i ristoranti',
					'action_href'  => home_url( '/ristoranti/' ),
				) ); ?>
			</section>
		<?php else : ?>

			<?php /* COVER - the lead story as a cinematic diptych (one link). */ ?>
			<section class="tmk-band-airy tmk-story-hub__cover tmk-decor-l-leaves tmk-decor--keep" aria-labelledby="tmk-story-cover-h">
				<h2 class="tmk-section-title" id="tmk-story-cover-h">In evidenza</h2>
				<a class="tmk-cover tmk-reveal" href="<?php echo esc_url( get_permalink( $featured ) ); ?>" aria-label="<?php echo esc_attr( get_the_title( $featured ) ); ?>">
					<span class="tmk-cover__media">
						<?php echo get_the_post_thumbnail( $featured, 'full', array( 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async', 'sizes' => '(min-width:1024px) 760px, 100vw' ) ); ?>
					</span>
					<span class="tmk-cover__text">
						<span class="tmk-kicker"><?php echo esc_html( tmk_story_kicker( $featured ) ); ?></span>
						<span class="tmk-cover__title"><?php echo esc_html( get_the_title( $featured ) ); ?></span>
						<span class="tmk-standfirst"><?php echo esc_html( get_the_excerpt( $featured ) ); ?></span>
						<span class="tmk-cover__readmore">Leggi la storia &rarr;</span>
					</span>
				</a>
			</section>

			<?php if ( $features ) : ?>
			<section class="tmk-band-dense tmk-story-hub__features tmk-decor-r-koi" aria-labelledby="tmk-story-features-h">
				<h2 class="tmk-section-title" id="tmk-story-features-h">Dal magazine</h2>
				<?php
				$cards = '';
				foreach ( $features as $p ) $cards .= tmk_story_card( $p, '(min-width:1024px) 690px, calc(100vw - 64px)' );
				echo tmk_render_card_grid( $cards, 2 );
				?>
			</section>
			<?php endif; ?>

			<?php if ( $briefs ) : ?>
			<section class="tmk-band-dense tmk-story-hub__briefs tmk-decor-l-vine" aria-labelledby="tmk-story-briefs-h">
				<h2 class="tmk-section-title" id="tmk-story-briefs-h">Altre storie</h2>
				<?php
				$cards = '';
				foreach ( $briefs as $p ) $cards .= tmk_story_card( $p, '(min-width:1024px) 340px, calc(100vw - 64px)' );
				echo tmk_render_card_grid( $cards, 4, 2 );
				?>
			</section>
			<?php endif; ?>

		<?php endif; ?>

		<?php
		/* RELATED - the storytelling -> conversion bridge. Derived from the stories above (see
		   tmk_story_related_cities): the row answers "these stories, where do I live them",
		   which is only true if it is built from THESE stories. Dropped whole when the page has
		   no story with a city rather than shown empty. */
		$all_shown = array_filter( array_merge( $featured ? array( $featured ) : array(), $features, $briefs ) );
		list( $city_cards, $city_count ) = tmk_story_related_cities( $all_shown );
		if ( $city_cards ) : ?>
		<section class="tmk-band-airy tmk-story-hub__cities tmk-decor-r-snake" aria-labelledby="tmk-story-cities-h">
			<h2 class="tmk-section-title" id="tmk-story-cities-h">Dove vivere queste storie</h2>
			<p class="tmk-section-title__note tmk-lead">Le città che queste storie raccontano, e i ristoranti dove ritrovarle.</p>
			<?php echo tmk_render_card_grid( $city_cards, min( 3, max( 1, $city_count ) ), min( 2, max( 1, $city_count ) ) ); ?>
		</section>
		<?php endif; ?>

		<?php /* TRUST - a sourced quote deserves air. */ ?>
		<section class="tmk-band-airy tmk-story-hub__trust" aria-label="Cosa dicono di noi">
			<?php echo tmk_render_trust_band( array( 'rotating' => false ) ); ?>
		</section>

	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'tmk_story_hub', 'tmk_render_story_hub' );

/**
 * EDITORIAL ARTICLE — the Terminal editorial template. Direct translation of
 * pages/EditorialArticle.jsx. The body is post_content so editors compose the long-form piece
 * (lead, prose, pull-quote blockquotes, full-bleed figures) with the normal editor; article.css
 * styles those primitives into the Kinfolk/Cereal register. Renders the queried post.
 */
function tmk_render_article( $post = null ) {
	$post = $post ? get_post( $post ) : get_queried_object();
	if ( ! $post instanceof WP_Post || 'post' !== $post->post_type ) {
		return '';
	}
	$id       = $post->ID;
	$kicker   = tmk_story_kicker( $post );
	$standfirst = get_the_excerpt( $post );
	$byline   = (string) get_post_meta( $id, 'byline', true );
	if ( '' === $byline ) $byline = (string) get_the_author_meta( 'display_name', $post->post_author );
	$media_id = (int) get_post_thumbnail_id( $id );
	$motif_id = function_exists( 'tmk_asset_id' ) ? tmk_asset_id( 'koi' ) : 0;

	ob_start();
	?>
	<article class="tmk-article">

		<?php if ( $media_id ) : ?>
			<div class="tmk-article__hero-media">
				<?php echo wp_get_attachment_image( $media_id, 'full', false, array( 'alt' => get_the_title( $post ), 'loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async', 'sizes' => '100vw' ) ); ?>
			</div>
		<?php endif; ?>

		<header class="tmk-article__header">
			<?php echo tmk_render_breadcrumb( array(
				array( 'label' => 'Home', 'href' => home_url( '/' ) ),
				array( 'label' => 'Storie', 'href' => home_url( '/storie/' ) ),
				array( 'label' => get_the_title( $post ) ),
			) ); ?>
			<?php if ( $kicker ) : ?><p class="tmk-article__kicker"><?php echo esc_html( $kicker ); ?></p><?php endif; ?>
			<h1 class="tmk-article__title"><?php echo esc_html( get_the_title( $post ) ); ?></h1>
			<?php if ( $standfirst ) : ?><p class="tmk-article__standfirst"><?php echo esc_html( $standfirst ); ?></p><?php endif; ?>
			<?php if ( $byline ) : ?><p class="tmk-article__byline"><?php echo esc_html( 'di ' . $byline . ' · ' . tmk_date_it( $post ) ); ?></p><?php endif; ?>
		</header>

		<div class="tmk-article__body">
			<?php echo apply_filters( 'the_content', $post->post_content ); ?>
		</div>

		<?php if ( $motif_id ) : ?>
			<div class="tmk-article__motif" aria-hidden="true"><?php echo wp_get_attachment_image( $motif_id, 'medium', false, array( 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async' ) ); ?></div>
		<?php endif; ?>

		<?php
		/* RELATED — the city this story lets you live. Omitted whole when no related_city is set. */
		$city = tmk_story_city( $post );
		if ( $city ) : ?>
			<section class="tmk-article__related tmk-decor-r-koi" aria-labelledby="tmk-article-related">
				<h2 id="tmk-article-related" class="tmk-section-title">Dove vivere questa storia</h2>
				<?php echo tmk_render_card_grid( tmk_render_city_card( $city, 'lazy', 'tmk-reveal', '(min-width:1024px) 1000px, calc(100vw - 48px)' ), 1 ); ?>
			</section>
		<?php endif; ?>

	</article>
	<?php
	return ob_get_clean();
}
add_shortcode( 'tmk_article', function () {
	return tmk_render_article();
} );
