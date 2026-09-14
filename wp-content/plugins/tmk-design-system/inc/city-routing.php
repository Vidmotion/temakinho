<?php
/**
 * City routing — the Collection (City) page's URL + template wiring. Mirrors inc/location-routing.php.
 *
 * URL  /ristoranti/{city}/  (IA §2 sitemap). The `city` taxonomy already registers a matching term
 * archive, but two other rules also match a single /ristoranti/{x}/ segment and both sort ABOVE it:
 * the `location` CPT's own single-segment rule and its numeric-page variant. So without this, every
 * /ristoranti/{city}/ URL resolved to a `location` query for a post named after the city — which
 * does not exist — and 404'd. A single top-priority rule makes one segment authoritatively a CITY;
 * the canonical two-segment /ristoranti/{city}/{location}/ (inc/location-routing.php, also `top`)
 * keeps resolving to the restaurant because its regex requires two segments. This is the correct IA:
 * one segment is the city, two segments the restaurant.
 *
 * TEMPLATE  A taxonomy term archive has no Elementor Theme Builder document, and Hello Elementor's
 * archive.php would wrap the body in a constrained, title-carrying container that breaks the
 * full-bleed hero. So this ships a plugin-owned full-width template (inc/templates/taxonomy-city.php)
 * that renders only the shell header/footer locations + [tmk_city] inside <main id="main">. Same
 * portability contract and same accepted trade-off as the Location single (PROJECT_CONTEXT §20.2).
 *
 * SINGLE-LOCATION CITIES collapse toward the Terminal: a city term with exactly one restaurant
 * 301-redirects to that restaurant instead of rendering a one-card collection (CityMilano.jsx:
 * "Single-location cities collapse toward Terminal"). This is also why tmk_city_href() can point
 * every city at /ristoranti/{slug}/ unconditionally now — the one-location cities bounce onward.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** One /ristoranti/{segment}/ is a city. Registered before the plugin's version-gated flush. */
add_action( 'init', function () {
	add_rewrite_rule( '^ristoranti/([^/]+)/?$', 'index.php?city=$matches[1]', 'top' );
}, 10 );

/**
 * Una citta' vale quanto le sue sedi ANCORA PUBBLICHE, e il conteggio da cui dipende e' quello
 * del modello (tmk_city_active_locations), non $term->count.
 *
 * Tre esiti, in quest'ordine:
 *
 *   0 sedi attive -> 404. E' il caso di una citta' che ha chiuso: il termine resta nel database
 *     con la sua foto e il suo testo, ma /ristoranti/{citta}/ non ha piu' niente da collezionare.
 *     Senza questo ramo la pagina rispondeva 200 e mostrava una collezione VUOTA — peggio di un
 *     404, perche' e' una pagina pubblica che afferma l'esistenza di qualcosa che non c'e' piu',
 *     e i motori di ricerca la tengono indicizzata. set_404() azzera i flag condizionali, quindi
 *     is_tax() diventa falsa da qui in poi e il 404 del sito viene servito dal suo template
 *     normale: nessun redirect verso una pagina arbitraria, nessuna pagina bianca.
 *
 *   1 sede attiva -> il 301 verso il Terminal che questo file gia' faceva (CityMilano.jsx,
 *     "Single-location cities collapse toward Terminal"). Ora il conteggio e' quello delle sedi
 *     ATTIVE, cosi' una citta' che ne aveva due e ne ha chiusa una collassa correttamente
 *     sull'unica rimasta invece di mostrare una collezione da una carta sola.
 *
 *   2+ -> la pagina Collezione, come prima.
 *
 * Il ramo 404 e' la logica GENERALE delle chiusure applicata alla rotta della citta': non
 * nomina nessuna sede e si riattiva da solo se la citta' torna ad averne una pubblicata.
 */
add_action( 'template_redirect', function () {
	if ( ! is_tax( 'city' ) ) {
		return;
	}
	$term = get_queried_object();
	if ( ! $term instanceof WP_Term ) {
		return;
	}

	$active = tmk_city_active_locations( $term );

	if ( ! $active ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
		return;
	}

	if ( 1 === count( $active ) ) {
		wp_safe_redirect( get_permalink( $active[0]->ID ), 301 );
		exit;
	}
} );

/** Full-width, shell-wrapped template for the City term archive. Priority 99, the last word. */
add_filter( 'template_include', function ( $template ) {
	if ( is_tax( 'city' ) ) {
		$custom = TMK_DS_PATH . 'inc/templates/taxonomy-city.php';
		if ( file_exists( $custom ) ) {
			return $custom;
		}
	}
	/*
	 * /ristoranti/ itself — the `location` post-type archive, i.e. the PARENT of every
	 * /ristoranti/{city}/ this file routes. It had no template at all and fell through to Hello
	 * Elementor's generic archive.php, rendering the raw WordPress loop (found by the pre-launch
	 * browser QA: HTTP 200, zero <h1>, body class post-type-archive-location). It is linked from
	 * the breadcrumb of every restaurant page and from the footer, so it is not a corner case.
	 * Same template shape and same reasoning as the city archive above; the assembler is
	 * tmk_render_restaurants_index() in inc/city-render.php.
	 */
	if ( is_post_type_archive( 'location' ) ) {
		$custom = TMK_DS_PATH . 'inc/templates/archive-location.php';
		if ( file_exists( $custom ) ) {
			return $custom;
		}
	}
	return $template;
}, 99 );
