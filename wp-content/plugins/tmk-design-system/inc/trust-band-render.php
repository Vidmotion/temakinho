<?php
/**
 * Trust Band — direct translation of components/conversion/TrustBand.jsx. Queries the real
 * `testimonial` CPT (inc/content-model.php) rather than Claude Design's ui_kits demo array.
 * Exposed as [tmk_trust_band] and the tmk-trust Elementor widget.
 *
 * Targeting (client review "wire the testimonial targeting system"): this band shows NOTHING
 * unless a testimonial is actually assigned to the page it's rendering on — no generic
 * fallback quote (this file used to hardcode one; that's why Rimini's quote used to render on
 * Aqua). Context is auto-detected from the current query by default, so the tmk-trust widget
 * (which passes none) resolves correctly wherever it's placed; 'context'/'location_id' atts
 * can force it, mainly for testing.
 *   - a location page (is_singular('location'))          -> testimonial.location_id === this post
 *   - the homepage (is_front_page())                      -> testimonial.tmk_testimonial_homepage
 *   - a careers page (is_singular('job') or its archive)  -> testimonial.tmk_testimonial_scope === 'staff'
 *   - every other context                                 -> nothing
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function tmk_render_trust_band( $atts = array() ) {
	$atts = shortcode_atts( array(
		'rotating'    => false,
		'limit'       => 5,
		'context'     => '',
		'location_id' => 0,
	), $atts );

	$context     = $atts['context'];
	$location_id = (int) $atts['location_id'];

	if ( '' === $context ) {
		if ( is_singular( 'location' ) ) {
			$context     = 'location';
			$location_id = $location_id ? $location_id : get_queried_object_id();
		} elseif ( is_front_page() ) {
			$context = 'homepage';
		} elseif ( is_singular( 'job' ) || is_post_type_archive( 'job' ) ) {
			$context = 'careers';
		}
	}

	$meta_query = array();
	if ( 'location' === $context ) {
		if ( ! $location_id ) {
			return '';
		}
		$meta_query[] = array( 'key' => 'location_id', 'value' => $location_id );
	} elseif ( 'homepage' === $context ) {
		$meta_query[] = array( 'key' => 'tmk_testimonial_homepage', 'value' => '1' );
	} elseif ( 'careers' === $context ) {
		$meta_query[] = array( 'key' => 'tmk_testimonial_scope', 'value' => 'staff' );
	} else {
		return ''; // every other page: nothing.
	}

	$posts = get_posts( array(
		'post_type'      => 'testimonial',
		'posts_per_page' => (int) $atts['limit'],
		'orderby'        => 'date',
		'order'          => 'DESC',
		'meta_query'     => $meta_query,
	) );

	$items = array();
	foreach ( $posts as $p ) {
		$quote = get_post_meta( $p->ID, 'quote', true );
		if ( ! $quote ) continue;
		$items[] = array( 'quote' => $quote, 'author' => get_post_meta( $p->ID, 'author', true ) );
	}
	if ( ! $items ) {
		return '';
	}

	$rotating = filter_var( $atts['rotating'], FILTER_VALIDATE_BOOLEAN ) && count( $items ) > 1;

	ob_start();
	?>
	<figure class="tmk-trust-band" <?php echo $rotating ? 'data-tmk-trust-band' : ''; ?>>
		<div class="tmk-trust-band__mark" aria-hidden="true">&#8220;</div>
		<?php foreach ( $items as $i => $item ) : ?>
			<div class="tmk-trust-band__item<?php echo ( 0 === $i ) ? ' is-active' : ''; ?>" <?php echo $rotating ? 'data-tmk-trust-band-item' : ''; ?>>
				<blockquote class="tmk-trust-band__quote"><?php echo esc_html( $item['quote'] ); ?></blockquote>
				<figcaption class="tmk-trust-band__caption"><?php echo esc_html( $item['author'] ); ?></figcaption>
			</div>
		<?php endforeach; ?>
		<?php if ( $rotating ) : ?>
			<div class="tmk-trust-band__nav">
				<button type="button" class="tmk-trust-band__nav-btn" aria-label="Precedente" data-tmk-trust-band-prev>&#8249;</button>
				<span class="tmk-trust-band__count" aria-live="polite" data-tmk-trust-band-count>1 / <?php echo count( $items ); ?></span>
				<button type="button" class="tmk-trust-band__nav-btn" aria-label="Successiva" data-tmk-trust-band-next>&#8250;</button>
			</div>
		<?php endif; ?>
	</figure>
	<?php
	return ob_get_clean();
}
add_shortcode( 'tmk_trust_band', function ( $atts ) {
	return tmk_render_trust_band( $atts );
} );
