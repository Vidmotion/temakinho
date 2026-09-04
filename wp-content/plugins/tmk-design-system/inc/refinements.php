<?php
/**
 * TMK Refinements — the terminal layer's server side.
 *
 * Mirrors css/refinements/shell.css and js/refinements/shell.js: the shell-level chrome that
 * Claude Design's ShellFrame.jsx owns but Elementor's Theme Builder header/footer templates do
 * not — the ambient botanical edge-bleed (PageDecor) and the site search overlay that the
 * header's existing search control already advertises via data-tmk-search-trigger.
 *
 * Layer discipline: nothing here re-implements a design-system component. PageDecor is two
 * decorative images; the overlay is a dialog wrapper around WordPress' own search form. Both are
 * printed by core hooks (wp_body_open / wp_footer) instead of being baked into the Header
 * template, so they are page-agnostic, survive any Theme Builder edit, and add no markup to a
 * component that would then have to know about them.
 *
 * @package tmk-design-system
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Attachment ID for one of the plugin's own brand assets, by its media slug.
 *
 * The Footer resolves its garland by hand-writing an /wp-content/uploads/YYYY/MM/ URL, which
 * breaks the moment the file is re-uploaded into a different month and loses srcset, intrinsic
 * dimensions and lazy loading with it. Resolving the ID instead lets every asset go through
 * wp_get_attachment_image(). Memoised per request because the same asset is asked for on every
 * page and the lookup is a slug query.
 *
 * @param string $slug Attachment post slug, e.g. 'snake'.
 * @return int Attachment ID, or 0 when the asset has not been uploaded yet.
 */
function tmk_asset_id( $slug ) {
	static $cache = array();
	if ( isset( $cache[ $slug ] ) ) {
		return $cache[ $slug ];
	}
	$attachment = get_page_by_path( $slug, OBJECT, 'attachment' );
	$cache[ $slug ] = $attachment ? (int) $attachment->ID : 0;
	return $cache[ $slug ];
}

/**
 * Ambient illustration art — the URL half of the shared decor system (css/components/decor.css).
 *
 * REPLACES the old tmk_render_page_decor(), which printed exactly two <img> elements into
 * wp_body_open and let css/refinements/shell.css pin them at top:26% and top:64% of TOTAL
 * DOCUMENT HEIGHT. Two pieces is not a composition, and a percentage of document height puts the
 * same rule in a different place on every page and moves it again the moment an editor adds a
 * paragraph — which is exactly the "sticker messi casualmente / tagliati male" the client
 * reported, and the same drift that had already forced review round 3 to pull both pieces out of
 * the Location template and re-anchor them locally. The composition now lives in CSS, anchored to
 * real sections (see css/components/decor.css for the full rationale), so there is no markup left
 * to print here.
 *
 * What still has to come from PHP is the one thing a stylesheet cannot know: where the uploads
 * directory is. Each illustration is resolved once and published as a custom property, so the
 * component stylesheet can reference var(--tmk-art-leaves) and stay a static, cacheable file.
 *
 * A missing asset is published as `none`, not as a broken URL: background-image:none simply does
 * not paint, so a motif whose art has not been uploaded degrades to nothing rather than to a
 * broken-image box. (garland-frame, the one illustration never imported, is not referenced by any
 * motif — the five that are, are all present: #26, #46, #47, #229, #557.)
 *
 * `large` rather than `full`: every piece renders at 150–560 CSS px and the originals are up to
 * 2048px wide. Ambient art is the last thing on the page that should be spending bandwidth.
 */
function tmk_decor_art_vars() {
	$art = array(
		'leaves'  => 'leaves-banana',
		'snake'   => 'snake',
		'koi'     => 'koi',
		'garland' => 'garland-horizontal',
		'macaw'   => 'macaw',
	);
	$decls = '';
	foreach ( $art as $name => $slug ) {
		$id  = tmk_asset_id( $slug );
		$src = $id ? wp_get_attachment_image_url( $id, 'large' ) : '';
		$decls .= '--tmk-art-' . $name . ':' . ( $src ? "url('" . esc_url( $src ) . "')" : 'none' ) . ';';
	}
	echo "<style id=\"tmk-decor-art\">:root{" . $decls . "}</style>\n";
}
add_action( 'wp_head', 'tmk_decor_art_vars', 5 );

/**
 * One ambient illustration, positioned by the component-layer stylesheet relative to its OWN
 * container instead of the whole page (client review §7 — see the guard at the top of
 * tmk_render_page_decor() for why: a page-percentage position drifts over text whenever a page's
 * content length differs from the page it was tuned against; a local position can't drift because
 * it is never measured against total page height). Same decorative contract as the sitewide
 * piece: aria-hidden wrapper expected from the caller, empty alt, lazy + async.
 *
 * @param string $slug  Attachment slug, e.g. 'snake'.
 * @param string $class CSS class the component-layer stylesheet positions by.
 * @return string
 */
function tmk_render_local_decor( $slug, $class ) {
	$id = tmk_asset_id( $slug );
	if ( ! $id ) {
		return '';
	}
	return wp_get_attachment_image( $id, 'large', false, array(
		'class'    => $class,
		'alt'      => '',
		'loading'  => 'lazy',
		'decoding' => 'async',
	) );
}

/**
 * Site search overlay.
 *
 * The Header has always rendered a search control (data-tmk-search-trigger) because the design
 * has one, but nothing was listening to it: a button that announces itself as "Cerca" and then
 * does nothing is a dead control and an accessibility failure, not just a missing feature.
 * ShellFrame.jsx answers it with a Modal containing a SearchBar; the honest WordPress equivalent
 * is a dialog around a real GET form pointed at the site's own search results, so the feature
 * works with JavaScript disabled as well (the control is a link-less button, so the overlay is
 * additive rather than a replacement for a working page).
 *
 * Semantics: labelled role="dialog" + aria-modal, a real <form role="search"> with a visually
 * hidden <label> bound to the input, and a close control with an accessible name. Focus
 * movement, focus trapping, Escape and scroll locking live in js/refinements/shell.js.
 */
function tmk_render_search_overlay() {
	?>
	<div class="tmk-search-overlay" data-tmk-search-overlay hidden>
		<div class="tmk-search-overlay__scrim" data-tmk-search-close></div>
		<div class="tmk-search-overlay__panel" role="dialog" aria-modal="true" aria-labelledby="tmk-search-title">
			<button type="button" class="tmk-search-overlay__close" aria-label="<?php esc_attr_e( 'Chiudi la ricerca', 'tmk-design-system' ); ?>" data-tmk-search-close>&times;</button>
			<h2 class="tmk-search-overlay__title" id="tmk-search-title"><?php esc_html_e( 'Cerca', 'tmk-design-system' ); ?></h2>
			<form role="search" method="get" class="tmk-search-overlay__form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				<label class="tmk-sr-only" for="tmk-search-field"><?php esc_html_e( 'Cerca su Temakinho', 'tmk-design-system' ); ?></label>
				<input type="search" id="tmk-search-field" class="tmk-search-overlay__input" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php esc_attr_e( 'Ristoranti, piatti, storie, eventi…', 'tmk-design-system' ); ?>" autocomplete="off">
				<button type="submit" class="tmk-cta tmk-cta--primary"><?php esc_html_e( 'Cerca', 'tmk-design-system' ); ?></button>
			</form>
			<p class="tmk-search-overlay__hint"><?php esc_html_e( 'Suggerimenti: “Milano”, “ceviche”, “eventi privati”, “lavora con noi”.', 'tmk-design-system' ); ?></p>
		</div>
	</div>
	<?php
}
add_action( 'wp_footer', 'tmk_render_search_overlay' );

/**
 * Mark the document as JavaScript-alive, before any stylesheet can paint.
 *
 * css/animations/reveal.css scopes its opacity:0 hidden states to .tmk-js, so the shared reveal
 * mechanism is only able to hide content on a page where JS is genuinely running. See the note at
 * the foot of that file: ungated, opacity:0 is the DEFAULT state of the Homepage's city bands and
 * both of its card grids, so one failure in the footer's JS left the whole content area blank
 * behind an HTTP 200.
 *
 * Printed inline in wp_head at priority 0 rather than enqueued, deliberately: an external script
 * cannot be guaranteed to run before first paint, and the entire point is that the class is
 * already there the first time the reveal rules are evaluated - otherwise the page flashes its
 * content and then hides it. One statement, no dependencies, no render-blocking request.
 */
function tmk_ds_mark_js_active() {
	echo "<script>document.documentElement.classList.add('tmk-js');</script>\n";
}
add_action( 'wp_head', 'tmk_ds_mark_js_active', 0 );
