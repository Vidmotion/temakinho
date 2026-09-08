<?php
/**
 * Booking Widget - direct translation of components/conversion/BookingWidget.jsx.
 *
 * "Booking action across all three integration tiers; identical front-end contract." The design
 * ships one component whose tier is a config switch, never a template fork (PROJECT_VALIDATION
 * §9: "Booking Widget tier is a config switch - do not fork templates"). So this is one render
 * function with a `tier` attribute, exactly like the source's `tier` prop.
 *
 *  - Tier 1 (default): the governed primary "Prenota" CTA to the booking destination. This is what
 *    the canonical Location page uses (pages/LocationRimini.jsx mounts <BookingWidget tier={1} .../>),
 *    and it is the honest production default for a chain that books through an external provider
 *    (booking_url location meta -> TheFork / SevenRooms / etc.).
 *  - Tier 2/3: an inline date/time/party form. The source mocks an in-page "confirmed" state with a
 *    setTimeout; there is no first-party reservation backend on this install yet, so faking that
 *    confirmation would be exactly the "demo that silently does nothing" the Newsletter decision
 *    (PROJECT_CONTEXT §8.7) rejected. Instead the form forwards the chosen slot to the external
 *    booking_url as query params via a plain GET - it works with JavaScript disabled and hands the
 *    provider a pre-filled request. Swap the form action for a first-party endpoint when a real
 *    Tier 2/3 integration is provisioned; the front-end contract does not change (IA §42, tier-agnostic).
 *
 * No tier needs its own JavaScript, so nothing is enqueued for it: Tier 1 is a link, Tier 2/3 is a
 * native form that degrades to a normal navigation.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function tmk_render_booking_widget( $atts = array() ) {
	$atts = shortcode_atts( array(
		'tier'          => 1,
		'location_name' => '',
		'booking_url'   => '',
		'label'         => 'Prenota',
	), $atts );

	$tier  = (int) $atts['tier'];
	// 2026-09-07: ripiego sulla destinazione governata invece dell'ancora /#prenota, che non esiste.
	$url   = $atts['booking_url'] ? $atts['booking_url'] : ( defined( 'TMK_BOOKING_URL' ) ? TMK_BOOKING_URL : home_url( '/#prenota' ) );
	$label = $atts['label'] ? $atts['label'] : 'Prenota';

	if ( $tier <= 1 ) {
		return sprintf(
			'<a class="tmk-cta tmk-cta--primary" href="%s">%s</a>',
			esc_url( $url ),
			esc_html( $label )
		);
	}

	ob_start();
	?>
	<form class="tmk-booking" method="get" action="<?php echo esc_url( $url ); ?>">
		<?php if ( $atts['location_name'] ) : ?>
			<input type="hidden" name="loc" value="<?php echo esc_attr( $atts['location_name'] ); ?>">
		<?php endif; ?>
		<label class="tmk-booking__field">
			<span class="tmk-booking__label">Data</span>
			<input class="tmk-booking__input" type="date" name="date" required>
		</label>
		<label class="tmk-booking__field">
			<span class="tmk-booking__label">Ora</span>
			<input class="tmk-booking__input" type="time" name="time" value="20:30">
		</label>
		<label class="tmk-booking__field">
			<span class="tmk-booking__label">Persone</span>
			<input class="tmk-booking__input tmk-booking__input--num" type="number" name="party" min="1" max="20" value="2">
		</label>
		<button type="submit" class="tmk-cta tmk-cta--primary"><?php echo esc_html( $label ); ?></button>
	</form>
	<?php
	return ob_get_clean();
}

/** [tmk_booking_widget tier="1" booking_url="https://..." location_name="Rimini" label="Prenota"] */
add_shortcode( 'tmk_booking_widget', function ( $atts ) {
	return tmk_render_booking_widget( $atts );
} );
