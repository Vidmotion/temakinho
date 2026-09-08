<?php
/**
 * Renders the Header + MegaMenu + MobileNav as real markup bound to live WP data
 * (location CPT grouped by city taxonomy, concept taxonomy for badges).
 * DOM structure, classes-as-tokens and copy are a direct translation of the current
 * Claude Design source: components/navigation/Header.jsx, MegaMenu.jsx, MobileNav.jsx,
 * conversion/CTA.jsx, overlays/Drawer.jsx, content/Accordion.jsx (Priority 1).
 * Exposed as the [tmk_header] shortcode so it can sit inside an Elementor Shortcode
 * widget within an Elementor Pro Theme Builder Header template.
 * Interactivity is generic, not header-specific — mega menu toggle lives in
 * js/navigation/mega-menu.js, drawer open/close in js/interactions/drawer.js, and the mobile
 * nav's city accordion in js/interactions/accordion.js (scoped by the data-tmk-accordion group
 * wrapper below, so it never interferes with unrelated accordions elsewhere on the page).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/*
 * 2026-09-07: le sedi elencate qui passano dal filtro delle chiusure (tmk_city_active_locations),
 * come ogni altro elenco pubblico, cosi' un ristorante che ha finito il servizio non resta nel
 * menu a tendina delle citta'.
 *
 * ATTENZIONE, per chi legge dopo: il menu di testata SERVITO in produzione NON viene da qui. La
 * testata e' un template Elementor (#25) con un widget nav-menu, e quel menu e' una voce di menu
 * di WordPress salvata nel database: va aggiornata in Aspetto -> Menu. Questa funzione e' la
 * versione del plugin, oggi non usata dal front end.
 */
function tmk_get_cities_with_locations() {
	$cities = get_terms( array( 'taxonomy' => 'city', 'hide_empty' => false, 'orderby' => 'name' ) );
	$out = array();
	foreach ( $cities as $city ) {
		$locations = tmk_city_active_locations( $city );
		if ( empty( $locations ) ) continue;
		$locs = array();
		foreach ( $locations as $loc ) {
			$concepts = get_the_terms( $loc->ID, 'concept' );
			$concept_slug = ( $concepts && ! is_wp_error( $concepts ) && $concepts[0]->slug !== 'standard' ) ? $concepts[0]->slug : '';
			$locs[] = array(
				'name' => $loc->post_title,
				'href' => get_permalink( $loc->ID ),
				'concept' => $concept_slug,
			);
		}
		$out[] = array( 'name' => $city->name, 'locations' => $locs );
	}
	return $out;
}

function tmk_slugify( $label ) {
	$slug = remove_accents( $label );
	$slug = strtolower( $slug );
	$slug = preg_replace( '/[^a-z0-9]+/', '-', $slug );
	return trim( $slug, '-' );
}

/**
 * Where a primary/mobile nav label actually goes.
 *
 * Until Session 6 every label resolved to '#' . slug — placeholders, because no destination page
 * existed. The Menu Hub is the first real one, so instead of hardcoding a URL the header asks the
 * site: if a published page answers to the label's slug the nav points at its real permalink and
 * marks itself current when that page is the one being read; the hub additionally gets the
 * permalink of the LANGUAGE being read, so switching to /menu/en/ does not silently send the
 * visitor back to Italian. Every label with no page keeps its placeholder anchor — this adds a
 * destination, it does not invent navigation that has not been built yet.
 */
function tmk_nav_destination( $label ) {
	$slug = tmk_slugify( $label );
	if ( function_exists( 'tmk_menu_page_id' ) ) {
		$menu_id = tmk_menu_page_id();
		if ( $menu_id && get_post_field( 'post_name', $menu_id ) === $slug ) {
			return array( 'href' => tmk_menu_url( tmk_menu_lang() ), 'current' => is_page( $menu_id ) );
		}
	}
	$page = get_page_by_path( $slug );
	if ( $page && 'publish' === $page->post_status ) {
		return array( 'href' => get_permalink( $page ), 'current' => is_page( $page->ID ) );
	}
	return array( 'href' => '#' . $slug, 'current' => false );
}

function tmk_render_header( $atts = array() ) {
	$atts = shortcode_atts( array(
		'active' => '',
		// 2026-09-07: ripiego sulla destinazione governata invece dell'ancora /#prenota.
		'booking_href' => defined( 'TMK_BOOKING_URL' ) ? TMK_BOOKING_URL : home_url( '/#prenota' ),
	), $atts );

	$primary_items = array( 'Ristoranti', 'Menù', 'Storie', 'Eventi', 'Shop', 'Lavora con Noi' );
	$mobile_items  = array( 'Menù', 'Storie', 'Eventi', 'Shop', 'Lavora con Noi' );
	$cities = tmk_get_cities_with_locations();

	ob_start();
	/*
	 * NOTE (Homepage audit, 2026-07-29): the inner shell below is a <div>, not <header
	 * role="banner">. Elementor Pro's Theme Builder already wraps this whole template in its
	 * own <header class="elementor elementor-25"> element, so a nested <header> - and worse, an
	 * explicit role="banner" on it - exposed TWO `banner` landmarks to assistive technology.
	 * The banner landmark is now owned by Elementor's wrapper (the faithful equivalent of
	 * Claude Design's own top-level <header>), and .tmk-header stays a pure style/layout hook.
	 * Do not restore the <header> tag or role="banner" here while this renders inside the
	 * Theme Builder header template.
	 *
	 * The root carries lang="it". The site chrome is Italian in every variant, including the
	 * English menu at /menu/en/, where the document itself declares lang="en". Without this,
	 * WCAG 3.1.2 (Language of Parts) is not met and a screen reader announces Ristoranti,
	 * Lavora con Noi and Prenota ora with an English voice.
	 */
	?>
	<div class="tmk-header-root" lang="it">
		<a href="#main" class="tmk-skip-link">Vai al contenuto</a>
		<div class="tmk-header" id="tmk-header">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="tmk-header__logo" aria-label="Temakinho — home">
				<?php echo tmk_get_logo_svg_markup(); ?>
			</a>

			<nav aria-label="Principale" class="tmk-header__nav">
				<?php foreach ( $primary_items as $label ) :
					$dest      = tmk_nav_destination( $label );
			$is_active = ( $label === $atts['active'] ) || $dest['current'];
					$has_mega  = ( 'Ristoranti' === $label && ! empty( $cities ) );
					if ( $has_mega ) : ?>
						<button type="button" class="tmk-navlink tmk-navlink--mega<?php echo $is_active ? ' is-active' : ''; ?>"
							aria-current="<?php echo $is_active ? 'page' : 'false'; ?>" aria-expanded="false" aria-controls="tmk-megamenu" data-tmk-mega-trigger>
							<?php echo esc_html( $label ); ?>
							<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
						</button>
					<?php else : ?>
						<a href="<?php echo esc_url( $dest['href'] ); ?>" class="tmk-navlink<?php echo $is_active ? ' is-active' : ''; ?>" <?php echo $is_active ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
					<?php endif;
				endforeach; ?>
			</nav>

			<div class="tmk-header__utility">
				<button type="button" class="tmk-icon-btn" aria-label="Cerca" data-tmk-search-trigger>
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
				</button>
				<button type="button" class="tmk-icon-btn tmk-mobile-only" aria-label="Apri il menu di navigazione" aria-expanded="false" aria-controls="tmk-mobile-drawer" data-tmk-drawer-trigger="tmk-mobile-drawer">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
				</button>
				<a href="<?php echo esc_url( $atts['booking_href'] ); ?>" class="tmk-cta tmk-cta--primary tmk-cta--sm"><?php esc_html_e( 'Prenota ora', 'tmk-design-system' ); ?></a>
			</div>
		</div>

		<?php if ( ! empty( $cities ) ) : ?>
		<?php
		/*
		 * The city label is a <p>, not an <h3> (Session 6 accessibility review). The mega menu
		 * renders inside the site header, i.e. BEFORE the page H1 in document order, so a heading
		 * here opened every document on the site at level 3 and pushed the real H1 into an
		 * out-of-order outline. A mega menu is navigation: its structure is carried by the region
		 * label, the lists and the links, and screen-reader users move through it by list, not by
		 * heading. The visual treatment is unchanged (css/layout/header.css).
		 */
		?>
		<div id="tmk-megamenu" role="region" aria-label="Ristoranti per città" class="tmk-megamenu" data-tmk-mega hidden>
			<?php foreach ( $cities as $city ) : ?>
				<div class="tmk-megamenu__col">
					<p class="tmk-megamenu__city"><?php echo esc_html( $city['name'] ); ?></p>
					<ul>
						<?php foreach ( $city['locations'] as $loc ) : ?>
							<li<?php echo $loc['concept'] ? ' data-concept="' . esc_attr( $loc['concept'] ) . '"' : ''; ?>>
								<a href="<?php echo esc_url( $loc['href'] ); ?>">
									<?php if ( $loc['concept'] ) : ?><span class="tmk-megamenu__dot" aria-hidden="true"></span><?php endif; ?>
									<?php echo esc_html( $loc['name'] ); ?>
								</a>
								<a href="<?php echo esc_url( $atts['booking_href'] ); ?>" class="tmk-megamenu__book">Prenota</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>

		<div class="tmk-drawer-scrim tmk-mobile-only" data-tmk-drawer-scrim="tmk-mobile-drawer" hidden></div>
		<aside role="complementary" aria-label="Menu di navigazione" id="tmk-mobile-drawer" class="tmk-drawer tmk-drawer--left tmk-mobile-only" data-tmk-drawer hidden>
			<div class="tmk-drawer__close-row">
				<button type="button" class="tmk-drawer__close" aria-label="Chiudi" data-tmk-drawer-close>&times;</button>
			</div>
			<nav aria-label="Principale" class="tmk-mobilenav" data-tmk-accordion>
				<?php foreach ( $cities as $i => $city ) : ?>
					<div class="tmk-accordion-item">
						<button type="button" class="tmk-accordion-trigger" aria-expanded="false" data-tmk-accordion-trigger>
							<?php echo esc_html( $city['name'] ); ?>
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
						</button>
						<div class="tmk-accordion-panel" hidden>
							<ul>
								<?php foreach ( $city['locations'] as $loc ) : ?>
									<li><a href="<?php echo esc_url( $loc['href'] ); ?>"><?php echo esc_html( $loc['name'] ); ?></a></li>
								<?php endforeach; ?>
							</ul>
						</div>
					</div>
				<?php endforeach; ?>
				<?php foreach ( $mobile_items as $label ) : ?>
					<a href="<?php echo esc_url( tmk_nav_destination( $label )['href'] ); ?>" class="tmk-mobilenav__link"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
				<a href="<?php echo esc_url( $atts['booking_href'] ); ?>" class="tmk-cta tmk-cta--primary tmk-cta--full" style="margin-top:var(--space-4)">Prenota ora</a>
			</nav>
		</aside>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'tmk_header', function ( $atts ) {
	return tmk_render_header( $atts );
} );
