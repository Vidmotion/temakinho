<?php
/**
 * Menu Hub — pages/MenuHub.jsx bound to the real production menu.
 *
 * SOURCE OF CONTENT. The canonical content is the live production menu (temakinho.it/menu/),
 * imported verbatim in Session 6: 19 categories, 12 published sub-groups, 167 dishes, every
 * description, every price, in both published languages. Nothing here is invented and nothing
 * was summarised. The Claude Design prototype's own menu-data.js (5 categories, 12 invented
 * dishes) was used for LAYOUT and COPY-VOICE only — hero wording, the editorial spread, the
 * conversion moment — never for content. PROJECT_CONTEXT 19 records the whole diff.
 *
 * WHERE THE CONTENT LIVES. In the CPT, not here: menu_item posts + the hierarchical
 * menu_category taxonomy that inc/content-model.php already registered for exactly this
 * purpose. A dish is editorial content with a price the client must be able to change, so it
 * is a post. Three things are deliberately NOT posts and are declared in this file instead:
 *
 *   1. the 14 EU allergen names, because dish descriptions reference them BY NUMBER. The
 *      numbering is a regulated, fixed vocabulary; if an editor could reorder or delete a
 *      legend row, every "(1, 3, 6)" on the page would silently start pointing at the wrong
 *      allergen. That is a food-safety failure mode, so the vocabulary is code.
 *   2. the two legal notices (defrosted products, Reg. CE 853/2004 blast-freezing), for the
 *      same reason — they are compliance text, not editorial copy.
 *   3. the UI strings of the two languages.
 *
 * BILINGUAL. The source publishes one menu twice. Rather than install a multilingual plugin
 * and duplicate 167 posts + 31 terms into a second tree the client would have to keep in sync,
 * the second language rides on the same post as title_en / short_description_en meta (and
 * name_en on the term). Two real URLs, /menu/ and /menu/en/, are produced by one rewrite rule
 * so each language is a crawlable, linkable, cacheable page with its own canonical and a
 * reciprocal hreflang pair — which the source page, a JS tab switcher over a single URL, is not.
 *
 * Exposed as [tmk_menu_hub].
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * The one slug this file is coupled to. The Info & allergeni category is a published category
 * (it must appear in the quick-nav) but it holds no dishes, so the renderer has to recognise it.
 * Coupling to a slug is the cheapest of the bad options: the alternative is another term meta
 * flag that an editor can unset, on a section that is legally required to be there.
 */
define( 'TMK_MENU_INFO_SLUG', 'info-allergeni' );
define( 'TMK_MENU_EN_SEGMENT', 'en' );

/** it | en — the only two languages the source publishes. */
function tmk_menu_lang() {
	return ( 'en' === get_query_var( 'tmk_lang' ) ) ? 'en' : 'it';
}

/** UI strings. Not content — chrome. */
function tmk_menu_i18n( $lang ) {
	$s = array(
		'it' => array(
			'lang_nav'    => 'Lingua del menu',
			'it'          => 'Italiano',
			'en'          => 'English',
			'index_nav'   => 'Vai alla categoria',
			'count_one'   => '%s proposta',
			'count_many'  => '%s proposte',
			'search_label'=> 'Cerca nel menu',
			'search_ph'   => 'Cerca un piatto o un ingrediente',
			'search_clear'=> 'Azzera la ricerca',
			'empty_title' => 'Nessun risultato',
			'empty_copy'  => 'Nessuna proposta corrisponde a questa ricerca. Prova con un altro ingrediente.',
			'allergens'   => 'Allergeni',
			'featured'    => 'In evidenza',
			'alcohol_free'=> 'Alcohol free',
			'veg'         => 'Veg',
			'menu_label'  => 'Il menu',
		),
		'en' => array(
			'lang_nav'    => 'Menu language',
			'it'          => 'Italiano',
			'en'          => 'English',
			'index_nav'   => 'Jump to a section',
			'count_one'   => '%s item',
			'count_many'  => '%s items',
			'search_label'=> 'Search the menu',
			'search_ph'   => 'Search a dish or an ingredient',
			'search_clear'=> 'Clear search',
			'empty_title' => 'Nothing found',
			'empty_copy'  => 'No item matches this search. Try another ingredient.',
			'allergens'   => 'Allergens',
			'featured'    => 'Featured',
			'alcohol_free'=> 'Alcohol free',
			'veg'         => 'Veg',
			'menu_label'  => 'The menu',
		),
	);
	return $s[ $lang ];
}

/**
 * The 14 EU allergens, in the published order — the order IS the numbering that every dish
 * description refers to. Stored in sentence case, not the published capitals: the legend is
 * uppercased by CSS (presentation), while the same strings are read out verbatim inside each
 * dish for assistive technology, where shouting fourteen words is not an improvement.
 */
function tmk_menu_allergens( $lang ) {
	$list = array(
		'it' => array( 'cereali contenenti glutine', 'crostacei', 'uova', 'pesce', 'arachidi', 'soia', 'latte', 'frutta a guscio', 'sedano', 'senape', 'semi di sesamo', 'anidride solforosa e solfiti', 'lupini', 'molluschi' ),
		'en' => array( 'cereals containing gluten', 'crustaceans', 'eggs', 'fish', 'peanuts', 'soybeans', 'milk & dairy', 'nuts', 'celery', 'mustard', 'sesame', 'sulphur dioxide & sulphites', 'lupin', 'mollusks' ),
	);
	return $list[ $lang ];
}

/** Compliance block of the Info & allergeni section, verbatim from the source. */
function tmk_menu_info_content( $lang ) {
	$c = array(
		'it' => array(
			'cover'       => 'COPERTO CON PANE CALDO* E BURRO* ARTIGIANALE',
			'cover_price' => '2.5',
			'defrosted'   => 'Tutti i prodotti evidenziati in "grassetto*" sono decongelati*.',
			'raw_fish'    => 'Tutti i prodotti ittici destinati al consumo a crudo sono stati sottoposti al trattamento di bonifica preventiva conforme alle prescrizioni del Reg.CE 853/2004',
			'legend'      => 'LISTA ALLERGENI',
		),
		'en' => array(
			'cover'       => 'COVER CHARGE INCLUDES WARM BREAD* AND ARTISAN BUTTER*',
			'cover_price' => '2.5',
			'defrosted'   => 'Items marked in bold* have been previously frozen*.',
			'raw_fish'    => 'All seafood served raw has been blast-frozen in accordance with EU health regulations (Reg. CE 853/2004) to ensure food safety.',
			'legend'      => 'ALLERGEN LIST',
		),
	);
	return $c[ $lang ];
}

/**
 * The source publishes bare numbers ("5.5", "9"). The number is the content and is stored
 * untouched in the price meta; this only typesets it — one currency mark, two decimals, and the
 * decimal separator of the language being read. A menu that prints "5.5" is not a shipped menu.
 * A non-numeric price is echoed exactly as stored, so an editor can still write "s.q." or "10/15".
 */
function tmk_menu_price( $raw, $lang ) {
	$raw = trim( (string) $raw );
	if ( '' === $raw ) {
		return '';
	}
	$n = str_replace( ',', '.', $raw );
	if ( ! is_numeric( $n ) ) {
		return $raw;
	}
	return "\u{20AC}\u{00A0}" . number_format( (float) $n, 2, ( 'it' === $lang ) ? ',' : '.', '' );
}

/** Permalink of one language of the hub. */
function tmk_menu_url( $lang ) {
	$base = get_permalink( tmk_menu_page_id() );
	if ( ! $base ) {
		return home_url( '/' );
	}
	return ( 'en' === $lang ) ? trailingslashit( $base ) . TMK_MENU_EN_SEGMENT . '/' : $base;
}

/** The page the hub is composed on, found by the shortcode it contains — not by a stored ID. */
function tmk_menu_page_id() {
	$cached = wp_cache_get( 'tmk_menu_page_id' );
	if ( false !== $cached ) {
		return (int) $cached;
	}
	$found = 0;
	$pages = get_posts( array( 'post_type' => 'page', 'posts_per_page' => -1, 'post_status' => 'publish', 'fields' => 'ids' ) );
	foreach ( $pages as $pid ) {
		if ( has_shortcode( (string) get_post_field( 'post_content', $pid ), 'tmk_menu_hub' ) ) {
			$found = $pid;
			break;
		}
		$edata = get_post_meta( $pid, '_elementor_data', true );
		if ( is_string( $edata ) && false !== strpos( $edata, 'tmk_menu_hub' ) ) {
			$found = $pid;
			break;
		}
	}
	wp_cache_set( 'tmk_menu_page_id', $found );
	return $found;
}

/**
 * One query for the whole menu, grouped in PHP.
 *
 * The obvious shape — a WP_Query per category — is 19 queries plus 19 term lookups on a page
 * that is 100% cacheable and never changes between requests. Instead: one posts query ordered
 * by the published sequence, and WP_Query's own term/meta cache priming, then grouping in
 * memory. Because sort_order is unique and ascending WITHIN a category, iterating the whole set
 * in that order and bucketing by category preserves each category's published order, and the
 * sub-group runs stay contiguous, so group order falls out for free.
 */
function tmk_menu_tree() {
	$parents = get_terms( array(
		'taxonomy'   => 'menu_category',
		'parent'     => 0,
		'hide_empty' => false,
		'meta_key'   => 'sort_order',
		'orderby'    => 'meta_value_num',
		'order'      => 'ASC',
	) );
	if ( is_wp_error( $parents ) || ! $parents ) {
		return array();
	}

	$tree = array();
	foreach ( $parents as $t ) {
		$tree[ $t->term_id ] = array( 'term' => $t, 'groups' => array(), 'count' => 0 );
	}

	$q = new WP_Query( array(
		'post_type'      => 'menu_item',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'meta_key'       => 'sort_order',
		'orderby'        => 'meta_value_num',
		'order'          => 'ASC',
		'no_found_rows'  => true,
		'ignore_sticky_posts' => true,
	) );

	foreach ( $q->posts as $post ) {
		$terms = get_the_terms( $post, 'menu_category' );
		if ( ! $terms || is_wp_error( $terms ) ) {
			continue;
		}
		$parent_id = 0;
		$child     = null;
		foreach ( $terms as $t ) {
			if ( 0 === (int) $t->parent && isset( $tree[ $t->term_id ] ) ) {
				$parent_id = $t->term_id;
			} elseif ( (int) $t->parent > 0 ) {
				$child = $t;
			}
		}
		if ( ! $parent_id && $child ) {
			$parent_id = (int) $child->parent;
		}
		if ( ! isset( $tree[ $parent_id ] ) ) {
			continue;
		}
		$key = $child ? 'g' . $child->term_id : 'g0';
		if ( ! isset( $tree[ $parent_id ]['groups'][ $key ] ) ) {
			$tree[ $parent_id ]['groups'][ $key ] = array( 'term' => $child, 'items' => array() );
		}
		$tree[ $parent_id ]['groups'][ $key ]['items'][] = $post;
		$tree[ $parent_id ]['count']++;
	}
	return $tree;
}

/** Category / sub-group label in the language being read; falls back to the Italian source. */
function tmk_menu_term_label( $term, $lang ) {
	if ( 'en' === $lang ) {
		$en = get_term_meta( $term->term_id, 'name_en', true );
		if ( $en ) {
			return $en;
		}
	}
	return $term->name;
}

function tmk_menu_count_label( $n, $lang ) {
	$s = tmk_menu_i18n( $lang );
	return sprintf( 1 === (int) $n ? $s['count_one'] : $s['count_many'], number_format_i18n( $n ) );
}

/**
 * One dish row.
 *
 * Deliberately NOT the Card component. Card is an <a> — its whole surface is one click target —
 * and a dish has no destination. Wrapping 167 dishes in links to /prenota, as the prototype's
 * placeholder 12 did, would put 167 identical link targets in the tab order and in every screen
 * reader's link list. So the dish reuses Card's typographic contract (and Card's own
 * .tmk-card__tag chip, unchanged) inside list semantics, which is what a menu is: a counted list.
 *
 * The two published markers become data, not glyphs: the source writes a highlight as a star glyph inside
 * the dish name and "ALCOHOL FREE" in Mathematical-Italic codepoints, neither of which survives
 * assistive technology. Both are now real chips with real words.
 *
 * Allergen numbers stay visible exactly as published, "(1, 3, 6, 10)", and are simultaneously
 * expanded into the allergen names for assistive technology — the same information, twice
 * encoded, so nobody has to scroll to the legend and back to decode a dish.
 */
function tmk_menu_dish_html( $post, $lang, $s, $allergens ) {
	$id    = $post->ID;
	$title = ( 'en' === $lang ) ? ( get_post_meta( $id, 'title_en', true ) ?: $post->post_title ) : $post->post_title;
	$desc  = ( 'en' === $lang ) ? get_post_meta( $id, 'short_description_en', true ) : get_post_meta( $id, 'short_description', true );
	if ( 'en' === $lang && '' === (string) $desc ) {
		$desc = get_post_meta( $id, 'short_description', true );
	}
	$price  = tmk_menu_price( get_post_meta( $id, 'price', true ), $lang );
	// Allergen codes diverge between languages on the published menu: the EN cards list codes
	// the IT cards omit (GYOZA DE CARNE +2, the two PICANHA plates and GRAMADO). The published
	// content is canonical, so each language keeps its own code list and EN falls back to IT.
	$acsv   = ( 'en' === $lang ) ? (string) get_post_meta( $id, 'allergens_en', true ) : '';
	if ( '' === $acsv ) {
		$acsv = (string) get_post_meta( $id, 'allergens', true );
	}
	$codes  = array_filter( array_map( 'intval', explode( ',', $acsv ) ) );
	$labels = array_filter( array_map( 'trim', explode( ',', (string) get_post_meta( $id, 'labels', true ) ) ) );
	$chips  = array(
		'star'         => $s['featured'],
		'alcohol_free' => $s['alcohol_free'],
		'veg'          => $s['veg'],
	);
	// Published label slugs are hyphenated ('alcohol-free'); the chip vocabulary is keyed with
	// underscores. Normalising here keeps the stored data verbatim without leaking slugs to the page.

	$names = array();
	foreach ( $codes as $n ) {
		if ( isset( $allergens[ $n - 1 ] ) ) {
			$names[] = $allergens[ $n - 1 ];
		}
	}

	// Lowercased haystack for the client-side filter, so it never has to touch the DOM text.
	$haystack = strtolower( $title . ' ' . $desc );

	ob_start();
	?>
	<li class="tmk-menu-dish" data-menu-dish data-menu-find="<?php echo esc_attr( $haystack ); ?>">
		<p class="tmk-menu-dish__head">
			<span class="tmk-menu-dish__name"><?php echo esc_html( $title ); ?></span>
			<span class="tmk-menu-dish__leader" aria-hidden="true"></span>
			<?php if ( $price ) : ?><span class="tmk-menu-dish__price"><?php echo esc_html( $price ); ?></span><?php endif; ?>
		</p>
		<?php if ( $labels ) : ?>
		<span class="tmk-card__tags">
			<?php foreach ( $labels as $l ) : ?>
				<span class="tmk-card__tag"><?php $k = str_replace( '-', '_', $l ); echo esc_html( isset( $chips[ $k ] ) ? $chips[ $k ] : $l ); ?></span>
			<?php endforeach; ?>
		</span>
		<?php endif; ?>
		<?php if ( $desc ) : ?><p class="tmk-menu-dish__desc"><?php echo esc_html( $desc ); ?></p><?php endif; ?>
		<?php if ( $codes ) : ?>
		<p class="tmk-menu-dish__allergens">
			<span aria-hidden="true">(<?php echo esc_html( implode( ', ', $codes ) ); ?>)</span>
			<span class="tmk-sr-only"><?php echo esc_html( $s['allergens'] . ': ' . implode( ', ', $names ) . '.' ); ?></span>
		</p>
		<?php endif; ?>
	</li>
	<?php
	return ob_get_clean();
}

/** Info & allergeni — the compliance section, rendered from code (see the file header). */
function tmk_menu_info_html( $term, $lang, $s ) {
	$c    = tmk_menu_info_content( $lang );
	$list = tmk_menu_allergens( $lang );
	$slug = 'menu-' . $term->slug;
	ob_start();
	?>
	<section class="tmk-menu-spread tmk-menu-spread--info tmk-collapse" id="<?php echo esc_attr( $slug ); ?>" aria-labelledby="<?php echo esc_attr( $slug ); ?>-title" data-menu-section>
		<div class="tmk-menu-spread__rail">
			<h2 class="tmk-menu-spread__title" id="<?php echo esc_attr( $slug ); ?>-title"><?php echo esc_html( tmk_menu_term_label( $term, $lang ) ); ?></h2>
			<span class="tmk-menu-spread__rule" aria-hidden="true"></span>
		</div>
		<div class="tmk-menu-spread__body">
			<ul class="tmk-menu-dishes tmk-menu-dishes--single">
				<li class="tmk-menu-dish">
					<p class="tmk-menu-dish__head">
						<span class="tmk-menu-dish__name"><?php echo esc_html( $c['cover'] ); ?></span>
						<span class="tmk-menu-dish__leader" aria-hidden="true"></span>
						<span class="tmk-menu-dish__price"><?php echo esc_html( tmk_menu_price( $c['cover_price'], $lang ) ); ?></span>
					</p>
				</li>
			</ul>
			<p class="tmk-menu-note"><?php echo esc_html( $c['defrosted'] ); ?></p>
			<p class="tmk-menu-note tmk-menu-note--fine"><?php echo esc_html( $c['raw_fish'] ); ?></p>
			<h3 class="tmk-menu-group__title"><?php echo esc_html( $c['legend'] ); ?></h3>
			<ol class="tmk-menu-legend">
				<?php foreach ( $list as $i => $name ) : ?>
					<li class="tmk-menu-legend__item">
						<span class="tmk-menu-legend__num"><?php echo esc_html( (string) ( $i + 1 ) ); ?></span>
						<span class="tmk-menu-legend__name"><?php echo esc_html( $name ); ?></span>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
	</section>
	<?php
	return ob_get_clean();
}

/**
 * The hub.
 *
 * Structure, and why it differs from the prototype in two places:
 *
 *   - The prototype's Filters facet bar is not rendered. Its three facets were Categoria,
 *     Citta and Regime. Categoria is already the page's own spine (19 quick-nav pills straight
 *     to 19 anchored spreads, which also works with JS off). Citta and Regime cannot be built:
 *     the production menu publishes no per-restaurant availability and no dietary flags, only
 *     numeric allergen references — and deriving "senza glutine" from "no gluten number was
 *     printed" would be inventing a food-safety claim. Filtering by a facet that does not exist
 *     in the source would mean inventing content, which the brief forbids. What 167 items DO
 *     need is a text filter, so that is what is offered, progressively enhanced.
 *   - The prototype's 3-column CardGrid of link-cards becomes a two-column priced list. See
 *     tmk_menu_dish_html() for why a dish is not a link. The editorial spread itself — identity
 *     rail, display H2, the short accent rule, the counted set — is kept exactly.
 */
function tmk_render_menu_hub( $atts = array() ) {
	$lang      = tmk_menu_lang();
	$s         = tmk_menu_i18n( $lang );
	$allergens = tmk_menu_allergens( $lang );
	$tree      = tmk_menu_tree();
	if ( ! $tree ) {
		return '';
	}

	$total = 0;
	$index = array();
	foreach ( $tree as $node ) {
		$total  += $node['count'];
		$index[] = array( 'slug' => 'menu-' . $node['term']->slug, 'label' => tmk_menu_term_label( $node['term'], $lang ) );
	}

	ob_start();
	?>
	<div class="tmk-menu-hub" data-menu-hub lang="<?php echo esc_attr( $lang ); ?>"
	     data-menu-count-one="<?php echo esc_attr( $s['count_one'] ); ?>"
	     data-menu-count-many="<?php echo esc_attr( $s['count_many'] ); ?>">

		<div class="tmk-menu-hub__tools tmk-collapse">
			<nav class="tmk-menu-lang" aria-label="<?php echo esc_attr( $s['lang_nav'] ); ?>">
				<?php foreach ( array( 'it', 'en' ) as $l ) : ?>
					<a class="tmk-menu-lang__opt<?php echo $l === $lang ? ' is-active' : ''; ?>"
					   href="<?php echo esc_url( tmk_menu_url( $l ) ); ?>"
					   lang="<?php echo esc_attr( $l ); ?>" hreflang="<?php echo esc_attr( $l ); ?>"
					   <?php echo $l === $lang ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $s[ $l ] ); ?></a>
				<?php endforeach; ?>
			</nav>

			<form class="tmk-menu-search" role="search" data-menu-search hidden>
				<label class="tmk-sr-only" for="tmk-menu-q"><?php echo esc_html( $s['search_label'] ); ?></label>
				<input class="tmk-menu-search__input" id="tmk-menu-q" type="search" autocomplete="off"
				       placeholder="<?php echo esc_attr( $s['search_ph'] ); ?>">
			</form>

			<p class="tmk-menu-hub__count" data-menu-count aria-live="polite"><?php echo esc_html( tmk_menu_count_label( $total, $lang ) ); ?></p>

			<nav class="tmk-menu-index" aria-label="<?php echo esc_attr( $s['index_nav'] ); ?>">
				<?php foreach ( $index as $i ) : ?>
					<a class="tmk-menu-index__pill" href="#<?php echo esc_attr( $i['slug'] ); ?>" data-menu-pill="<?php echo esc_attr( $i['slug'] ); ?>"><?php echo esc_html( $i['label'] ); ?></a>
				<?php endforeach; ?>
			</nav>
		</div>

		<p class="tmk-menu-empty" data-menu-empty hidden>
			<strong class="tmk-menu-empty__title"><?php echo esc_html( $s['empty_title'] ); ?></strong>
			<span class="tmk-menu-empty__copy"><?php echo esc_html( $s['empty_copy'] ); ?></span>
		</p>

		<?php foreach ( $tree as $node ) :
			$term = $node['term'];
			$slug = 'menu-' . $term->slug;
			if ( TMK_MENU_INFO_SLUG === $term->slug ) {
				echo tmk_menu_info_html( $term, $lang, $s );
				continue;
			}
			?>
			<section class="tmk-menu-spread tmk-collapse" id="<?php echo esc_attr( $slug ); ?>" aria-labelledby="<?php echo esc_attr( $slug ); ?>-title" data-menu-section>
				<div class="tmk-menu-spread__rail">
					<span class="tmk-menu-spread__count" data-menu-section-count><?php echo esc_html( tmk_menu_count_label( $node['count'], $lang ) ); ?></span>
					<h2 class="tmk-menu-spread__title" id="<?php echo esc_attr( $slug ); ?>-title"><?php echo esc_html( tmk_menu_term_label( $term, $lang ) ); ?></h2>
					<span class="tmk-menu-spread__rule" aria-hidden="true"></span>
				</div>
				<div class="tmk-menu-spread__body">
					<?php foreach ( $node['groups'] as $group ) : ?>
						<?php if ( $group['term'] ) : ?>
							<div class="tmk-menu-group" data-menu-group>
								<h3 class="tmk-menu-group__title"><?php echo esc_html( tmk_menu_term_label( $group['term'], $lang ) ); ?></h3>
								<ul class="tmk-menu-dishes">
									<?php foreach ( $group['items'] as $post ) { echo tmk_menu_dish_html( $post, $lang, $s, $allergens ); } ?>
								</ul>
							</div>
						<?php else : ?>
							<div class="tmk-menu-group" data-menu-group>
								<ul class="tmk-menu-dishes">
									<?php foreach ( $group['items'] as $post ) { echo tmk_menu_dish_html( $post, $lang, $s, $allergens ); } ?>
								</ul>
							</div>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'tmk_menu_hub', 'tmk_render_menu_hub' );

/* ---------------------------------------------------------------------------------------------
 * TWO REAL URLS FOR TWO REAL LANGUAGES
 *
 * The source page switches language with JS on one URL, so only the Italian menu is indexable
 * and neither language can be linked to. One rewrite rule fixes that without a multilingual
 * plugin: /menu/en/ resolves to the same page with tmk_lang=en, and each language declares its
 * own canonical plus a reciprocal hreflang pair. Rewrite rules are flushed by the version guard
 * in tmk-design-system.php, so bumping TMK_DS_VERSION is all that is needed to register this.
 * ------------------------------------------------------------------------------------------ */

add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'tmk_lang';
	return $vars;
} );

add_action( 'init', function () {
	$page = get_post( tmk_menu_page_id() );
	if ( ! $page ) {
		return;
	}
	add_rewrite_rule(
		'^' . preg_quote( $page->post_name, '/' ) . '/' . TMK_MENU_EN_SEGMENT . '/?$',
		'index.php?pagename=' . $page->post_name . '&tmk_lang=' . TMK_MENU_EN_SEGMENT,
		'top'
	);
}, 5 ); // BEFORE the rewrite-flush guard in tmk-design-system.php, which runs on init at 20.

/** Canonical of the language being read, not of the Italian default. */
add_filter( 'get_canonical_url', function ( $url, $post ) {
	if ( $post && tmk_menu_page_id() === (int) $post->ID && 'en' === tmk_menu_lang() ) {
		return tmk_menu_url( 'en' );
	}
	return $url;
}, 10, 2 );

/** Reciprocal hreflang pair, so each language is offered to the right audience. */
add_action( 'wp_head', function () {
	$pid = tmk_menu_page_id();
	if ( ! $pid || ! is_page( $pid ) ) {
		return;
	}
	foreach ( array( 'it' => tmk_menu_url( 'it' ), 'en' => tmk_menu_url( 'en' ) ) as $code => $href ) {
		printf( '<link rel="alternate" hreflang="%s" href="%s">' . "\n", esc_attr( $code ), esc_url( $href ) );
	}
	printf( '<link rel="alternate" hreflang="x-default" href="%s">' . "\n", esc_url( tmk_menu_url( 'it' ) ) );
}, 2 );

/**
 * Document language.
 *
 * The install ships with the en-US locale, so every page - including the Italian editorial
 * content - declared lang="en-US". That is a WCAG 3.1.1 (Language of Page) failure, and it also
 * degrades hyphenation, quote substitution and screen-reader pronunciation. The editorial
 * language of this site is Italian, so that is the default; the English menu is the one document
 * that legitimately declares English. The WordPress admin locale is deliberately left untouched.
 */
add_filter( 'language_attributes', function ( $output ) {
	$pid  = tmk_menu_page_id();
	$lang = ( $pid && is_page( $pid ) && 'en' === tmk_menu_lang() ) ? 'en' : 'it-IT';
	if ( false !== strpos( $output, 'lang=' ) ) {
		return preg_replace( '/lang="[^"]*"/', 'lang="' . $lang . '"', $output, 1 );
	}
	return trim( $output . ' lang="' . $lang . '"' );
} );

/** The English menu is a separate document, so it needs its own title rather than the Italian one. */
add_filter( 'document_title_parts', function ( $parts ) {
	$pid = tmk_menu_page_id();
	if ( $pid && is_page( $pid ) && 'en' === tmk_menu_lang() ) {
		$parts['title'] = 'Menu';
	}
	return $parts;
} );

/**
 * Menu structured data.
 *
 * A restaurant menu is one of the few page types with a first-class schema.org vocabulary, and
 * the whole menu is already in memory when the page renders, so emitting it costs one extra
 * pass over an array. Sub-groups become nested MenuSections, which is exactly what they are.
 * Only the language being read is emitted — the other language has its own URL and its own JSON-LD.
 */
add_action( 'wp_footer', function () {
	$pid = tmk_menu_page_id();
	if ( ! $pid || ! is_page( $pid ) ) {
		return;
	}
	$lang = tmk_menu_lang();
	$tree = tmk_menu_tree();
	if ( ! $tree ) {
		return;
	}
	$sections = array();
	foreach ( $tree as $node ) {
		if ( TMK_MENU_INFO_SLUG === $node['term']->slug ) {
			continue;
		}
		$section = array(
			'@type' => 'MenuSection',
			'name'  => tmk_menu_term_label( $node['term'], $lang ),
		);
		$items = array();
		$subs  = array();
		foreach ( $node['groups'] as $group ) {
			$rows = array();
			foreach ( $group['items'] as $post ) {
				$title = ( 'en' === $lang ) ? ( get_post_meta( $post->ID, 'title_en', true ) ?: $post->post_title ) : $post->post_title;
				$desc  = ( 'en' === $lang ) ? get_post_meta( $post->ID, 'short_description_en', true ) : get_post_meta( $post->ID, 'short_description', true );
				$price = trim( (string) get_post_meta( $post->ID, 'price', true ) );
				$row   = array( '@type' => 'MenuItem', 'name' => $title );
				if ( $desc ) {
					$row['description'] = $desc;
				}
				if ( is_numeric( str_replace( ',', '.', $price ) ) ) {
					$row['offers'] = array( '@type' => 'Offer', 'price' => number_format( (float) str_replace( ',', '.', $price ), 2, '.', '' ), 'priceCurrency' => 'EUR' );
				}
				$rows[] = $row;
			}
			if ( $group['term'] ) {
				$subs[] = array( '@type' => 'MenuSection', 'name' => tmk_menu_term_label( $group['term'], $lang ), 'hasMenuItem' => $rows );
			} else {
				$items = array_merge( $items, $rows );
			}
		}
		if ( $items ) {
			$section['hasMenuItem'] = $items;
		}
		if ( $subs ) {
			$section['hasMenuSection'] = $subs;
		}
		$sections[] = $section;
	}
	$data = array(
		'@context'       => 'https://schema.org',
		'@type'          => 'Menu',
		'name'           => get_the_title( $pid ),
		'url'            => tmk_menu_url( $lang ),
		'inLanguage'     => $lang,
		'hasMenuSection' => $sections,
	);
	echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
}, 30 );

/* ---------------------------------------------------------------------------------------------
 * ARRIVAL AND CONVERSION
 *
 * The Menu page is composed as three Elementor containers (one per section, the site's Rule 15):
 * [tmk_menu_hero] / [tmk_menu_hub] / [tmk_menu_conversion]. The two bookends could have been the
 * generic [tmk_hero] and [tmk_cta_banner] with copy typed into Elementor, and that is what the
 * Homepage does — but this page exists at two URLs in two languages, and copy typed into a
 * builder cannot know which one it is being read in. Rather than teach two generic components a
 * second language they have no other use for, the page wraps them: the components stay generic,
 * and every string of this page lives in this one file next to the rest of its content.
 *
 * The copy itself is the prototype's, kept verbatim in Italian (pages/menu-data.js hero and
 * conversion blocks) because it is editorial direction from Claude Design, not placeholder text.
 * The English is a translation of that same voice, not of the Italian menu.
 * ------------------------------------------------------------------------------------------ */

function tmk_menu_chrome_copy( $lang ) {
	$c = array(
		'it' => array(
			'eyebrow'     => 'A tavola',
			'headline'    => 'Il Menu',
			'subheadline' => 'Ingredienti, tecnica e prezzo — sempre in chiaro. Dal temaki alla brace, il Giappone che balla la samba.',
			'media_alt'   => 'Il bancone curvo di Temakinho illuminato d\'ambra, tra verde e velluti',
			'cta_title'   => 'Ti è venuta fame?',
			'cta_copy'    => 'Scegli la città e prenota il tavolo — il menu ti aspetta.',
			'cta_label'   => 'Prenota',
		),
		'en' => array(
			'eyebrow'     => 'At the table',
			'headline'    => 'The Menu',
			'subheadline' => 'Ingredients, technique and price — always in plain sight. From temaki to the grill, Japan dancing the samba.',
			'media_alt'   => 'Temakinho\'s curved bar lit amber, between greenery and velvet',
			'cta_title'   => 'Hungry yet?',
			'cta_copy'    => 'Pick your city and book your table — the menu is waiting.',
			'cta_label'   => 'Book a table',
		),
	);
	return $c[ $lang ];
}

/** Attachment ID of the hub's hero photograph, resolved by slug like every other TMK asset. */
function tmk_menu_hero_media_id() {
	return function_exists( 'tmk_asset_id' ) ? (int) tmk_asset_id( 'interior-rimini-wide' ) : 0;
}

add_shortcode( 'tmk_menu_hero', function () {
	$lang = tmk_menu_lang();
	$c    = tmk_menu_chrome_copy( $lang );
	return tmk_render_hero( array(
		'variant'     => 'full-bleed',
		'media_id'    => tmk_menu_hero_media_id(),
		'media_alt'   => $c['media_alt'],
		'eyebrow'     => $c['eyebrow'],
		'headline'    => $c['headline'],
		'subheadline' => $c['subheadline'],
		'cta_label'   => '',
		'height'      => 'min(58vh, 520px)',
	) );
} );

add_shortcode( 'tmk_menu_conversion', function () {
	$lang = tmk_menu_lang();
	$c    = tmk_menu_chrome_copy( $lang );
	return tmk_render_cta_banner( array(
		'variant'   => 'booking',
		'title'     => $c['cta_title'],
		'copy'      => $c['cta_copy'],
		'cta_label' => $c['cta_label'],
	) );
} );
