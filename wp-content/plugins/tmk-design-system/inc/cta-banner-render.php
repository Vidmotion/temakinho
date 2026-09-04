<?php
/**
 * CTA Banner — direct translation of components/conversion/CTABanner.jsx. Section-level
 * conversion moment; variant only changes the copy defaults, never the layout. Exposed as
 * [tmk_cta_banner variant="booking|newsletter|delivery"].
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function tmk_cta_banner_defaults( $variant ) {
	$defaults = array(
		'booking'    => array( 'title' => 'Il tuo tavolo ti aspetta', 'copy' => 'Scegli data e ora. Nessuna sorpresa, solo alegria.', 'label' => 'Prenota ora', 'href' => home_url( '/#prenota' ) ),
		'newsletter' => array( 'title' => 'Resta nel nostro mondo', 'copy' => 'Storie, aperture e inviti — una volta al mese, niente di più.', 'label' => 'Iscriviti', 'href' => '#newsletter' ),
		'delivery'   => array( 'title' => 'Temakinho a casa tua', 'copy' => 'La stessa cucina, il tuo divano.', 'label' => 'Ordina', 'href' => home_url( '/#ordina' ) ),
	);
	return $defaults[ $variant ] ?? $defaults['booking'];
}

/**
 * @param array  $atts        Standard banner attributes (variant/title/copy/cta_label/cta_href).
 * @param string $action_html Optional pre-rendered action markup that REPLACES the default CTA link.
 *                            The canonical Location page composes the banner with a Booking Widget
 *                            as its action (pages/LocationRimini.jsx: <CTABanner><BookingWidget/></CTABanner>),
 *                            which is exactly this parameter. When empty the banner renders its own
 *                            governed primary CTA as before, so every existing caller is unchanged.
 *                            The one-primary-CTA law still holds: the injected action is itself the
 *                            single primary action, not an addition to a default one.
 */
function tmk_render_cta_banner( $atts = array(), $action_html = '' ) {
	$atts = shortcode_atts( array(
		'variant'  => 'booking',
		'title'    => '',
		'copy'     => '',
		'cta_label' => '',
		'cta_href'  => '',
	), $atts );

	$defaults = tmk_cta_banner_defaults( $atts['variant'] );
	$title = $atts['title'] ?: $defaults['title'];
	$copy = $atts['copy'] ?: $defaults['copy'];
	$cta_label = $atts['cta_label'] ?: $defaults['label'];
	$cta_href = $atts['cta_href'] ?: $defaults['href'];

	ob_start();
	?>
	<section class="tmk-cta-banner">
		<div class="tmk-cta-banner__copy">
			<h2 class="tmk-cta-banner__title"><?php echo esc_html( $title ); ?></h2>
			<p class="tmk-cta-banner__body"><?php echo esc_html( $copy ); ?></p>
		</div>
		<?php if ( $action_html ) : ?>
			<div class="tmk-cta-banner__action"><?php echo $action_html; // pre-rendered, already-escaped component markup ?></div>
		<?php else : ?>
			<a href="<?php echo esc_url( $cta_href ); ?>" class="tmk-cta tmk-cta--primary"><?php echo esc_html( $cta_label ); ?></a>
		<?php endif; ?>
	</section>
	<?php
	return ob_get_clean();
}
add_shortcode( 'tmk_cta_banner', function ( $atts ) {
	return tmk_render_cta_banner( $atts );
} );
