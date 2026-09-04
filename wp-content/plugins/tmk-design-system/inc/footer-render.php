<?php
/**
 * Renders the global Footer — direct translation of components/navigation/Footer.jsx.
 * Newsletter column delegates to [tmk_newsletter] (inc/newsletter-render.php) rather than
 * inlining that markup here, since NewsletterCapture is a shared component used elsewhere too.
 * Exposed as the [tmk_footer] shortcode for an Elementor Pro Theme Builder Footer template.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function tmk_render_footer( $atts = array() ) {
	$atts = shortcode_atts( array(), $atts );

	// The garland is a real attachment, so it goes through wp_get_attachment_image() like every
	// other image in this plugin. Measured before: the 2048x404 original was downloaded in full
	// (192KB) for an 880px-wide slot, eagerly, on first paint - for decoration that sits at the
	// very bottom of every page. Now it gets srcset (right file for the slot), intrinsic
	// width/height (so it cannot shift the footer as it arrives) and loading=lazy.
	// Resolved by SLUG via tmk_asset_id() rather than a hand-written /uploads/YYYY/MM/ path, which
	// breaks as soon as the file is re-uploaded in a different month. The literal URL is kept only
	// as a fallback so the footer degrades to today's behaviour instead of losing its artwork.
	$garland_id = function_exists( 'tmk_asset_id' ) ? tmk_asset_id( 'garland-horizontal' ) : 0;
	$garland    = home_url( '/wp-content/uploads/2026/07/garland-horizontal.png' );
	$columns = array(
		array( 'title' => 'Esplora', 'links' => array(
			'Ristoranti' => home_url( '/ristoranti/' ),
			'Menù' => ( function_exists( 'tmk_menu_page_id' ) && tmk_menu_page_id() ) ? tmk_menu_url( tmk_menu_lang() ) : home_url( '/#menu' ),
			'Storie' => home_url( '/storie/' ),
			'Eventi' => home_url( '/eventi/' ),
		) ),
		array( 'title' => 'Il mondo TMK', 'links' => array(
			'Giappone–Brasile' => home_url( '/la-nostra-storia/' ),
			'Lavora con Noi' => home_url( '/lavora-con-noi/' ),
			'Shop' => home_url( '/shop/' ),
		) ),
		array( 'title' => 'Supporto', 'links' => array(
			'Contatti' => home_url( '/contattaci/' ),
			'Prenotazioni' => defined( 'TMK_BOOKING_URL' ) ? TMK_BOOKING_URL : home_url( '/#prenota' ),
			'Delivery' => defined( 'TMK_ORDER_URL' ) ? TMK_ORDER_URL : home_url( '/#ordina' ),
		) ),
	);
	/*
	 * PATCH 10 (2026-09-04): i documenti legali sono nella Media Library, non piu' sul CDN
	 * Shopify del vecchio sito. Gli URL sono risolti dall'ID dell'allegato
	 * (tmk_legal_doc_url(), inc/legal-docs-render.php) a ogni render; se un documento non
	 * risulta importato la voce viene semplicemente omessa, mai stampata rotta.
	 * "Whistleblowing" = l'informativa; "Segnala" = la piattaforma di segnalazione, che resta
	 * raggiungibile (decisione di Carlo, 2026-09-04).
	 * Questo e' il percorso shortcode [tmk_footer], oggi dormiente: il footer servito e' il
	 * template Elementor #27, aggiornato dallo script _BUILD/201-legal-documents.php.
	 */
	$legal = array();
	foreach ( array(
		'Privacy'        => 'privacy',
		'Cookie'         => 'cookie',
		'Codice Etico'   => 'etico',
	) as $label => $slug ) {
		$u = function_exists( 'tmk_legal_doc_url' ) ? tmk_legal_doc_url( $slug ) : '';
		if ( $u ) { $legal[ $label ] = $u; }
	}
	$legal['Allergeni'] = home_url( '/allergeni/' );
	$wb = function_exists( 'tmk_legal_doc_url' ) ? tmk_legal_doc_url( 'whistleblowing' ) : '';
	if ( $wb ) { $legal['Whistleblowing'] = $wb; }
	$legal['Segnala'] = 'https://temakinho.integrityline.com/';
	$social = array(
		'Instagram' => 'https://instagram.com/',
		'Facebook' => 'https://facebook.com/',
		'Spotify' => 'https://open.spotify.com/',
	);

	ob_start();
	/*
	 * NOTE (Homepage audit, 2026-07-29): the shell below is a <div>, not <footer
	 * role="contentinfo">. Elementor Pro's Theme Builder already wraps this template in its own
	 * <footer class="elementor elementor-27"> element, so a nested <footer> - plus an explicit
	 * role="contentinfo" on it - exposed TWO `contentinfo` landmarks to assistive technology.
	 * The contentinfo landmark is now owned by Elementor's wrapper (the faithful equivalent of
	 * Claude Design's own top-level <footer>), and .tmk-footer stays a pure style/layout hook.
	 * Mirrors the same change in inc/header-render.php.
	 */
	?>
	<div class="tmk-footer" lang="it">
		<div class="tmk-footer__garland" aria-hidden="true"><?php echo $garland_id ? wp_get_attachment_image( $garland_id, 'full', false, array( 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async', 'sizes' => '(min-width: 880px) 880px, 100vw' ) ) : '<img src="' . esc_url( $garland ) . '" alt="" loading="lazy" decoding="async" />'; ?></div>
		<div class="tmk-footer__grid">
			<div class="tmk-footer__brand">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Temakinho — home"><?php echo tmk_get_logo_svg_markup(); ?></a>
				<p class="tmk-footer__tagline">Japanese sushi, Brazilian soul. Dal 2012, zero compromessi.</p>
				<div class="tmk-footer__social">
					<?php foreach ( $social as $label => $href ) : ?>
						<a href="<?php echo esc_url( $href ); ?>"><?php echo esc_html( $label ); ?></a>
					<?php endforeach; ?>
				</div>
			</div>

			<?php foreach ( $columns as $col ) : ?>
				<nav class="tmk-footer__col" aria-label="<?php echo esc_attr( $col['title'] ); ?>">
					<h3><?php echo esc_html( $col['title'] ); ?></h3>
					<ul>
						<?php foreach ( $col['links'] as $label => $href ) : ?>
							<li><a href="<?php echo esc_url( $href ); ?>"><?php echo esc_html( $label ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</nav>
			<?php endforeach; ?>

			<div class="tmk-footer__col">
				<h3>La newsletter</h3>
				<?php echo tmk_render_newsletter_capture( array( 'variant' => 'footer' ) ); ?>
			</div>
		</div>
		<div class="tmk-footer__bottom">
			<div class="tmk-footer__bottom-inner">
				<span>&copy; <?php echo esc_html( date_i18n( 'Y' ) ); ?> Temakinho &middot; P.IVA 03134960305</span>
				<span class="tmk-footer__legal">
					<?php foreach ( $legal as $label => $href ) : ?>
						<a href="<?php echo esc_url( $href ); ?>"><?php echo esc_html( $label ); ?></a>
					<?php endforeach; ?>
				</span>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'tmk_footer', function ( $atts ) {
	return tmk_render_footer( $atts );
} );
