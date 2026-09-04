<?php
/**
 * Narrative Band — direct translation of components/bands/NarrativeBand.jsx.
 * Exposed as [tmk_narrative_band], body copy as the shortcode's enclosed content.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function tmk_render_narrative_band( $atts = array(), $content = '' ) {
	$atts = shortcode_atts( array(
		'eyebrow'   => '',
		'title'     => '',
		'align'     => 'center', // center | start
		'motif_id'  => 0,
		'motif_alt' => '',
	), $atts );

	$align_class = 'center' === $atts['align'] ? 'tmk-narrative-band--center' : 'tmk-narrative-band--start';
	$motif_url = $atts['motif_id'] ? wp_get_attachment_image_url( (int) $atts['motif_id'], 'medium' ) : '';
	/* Optical alignment (client review §3): the DOM box is already correctly aligned with the
	   text below it, but most of these logotype PNGs carry baked-in transparent canvas padding
	   on their left edge (3%-14% of canvas width, computed per-image by _BUILD/144-logotype-
	   optical-trim.php from real pixel-alpha data - never guessed). This pulls the VISIBLE
	   artwork flush with the text axis without touching the image files themselves. */
	$motif_trim  = $atts['motif_id'] ? (int) get_post_meta( (int) $atts['motif_id'], '_tmk_optical_trim_px', true ) : 0;
	$motif_style = $motif_trim ? ' style="margin-left:-' . $motif_trim . 'px"' : '';

	ob_start();
	?>
	<section class="tmk-narrative-band <?php echo esc_attr( $align_class ); ?>">
		<?php if ( $motif_url ) : ?><img class="tmk-narrative-band__motif" src="<?php echo esc_url( $motif_url ); ?>" alt="<?php echo esc_attr( $atts['motif_alt'] ); ?>"<?php echo $motif_style; ?> /><?php endif; ?>
		<?php if ( $atts['eyebrow'] ) : ?><span class="tmk-eyebrow"><?php echo esc_html( $atts['eyebrow'] ); ?></span><?php endif; ?>
		<?php if ( $atts['title'] ) : ?><h2 class="tmk-narrative-band__title"><?php echo esc_html( $atts['title'] ); ?></h2><?php endif; ?>
		<?php if ( $content ) : ?><div class="tmk-narrative-band__body"><?php echo wp_kses_post( wpautop( trim( $content ) ) ); ?></div><?php endif; ?>
	</section>
	<?php
	return ob_get_clean();
}
add_shortcode( 'tmk_narrative_band', function ( $atts, $content = '' ) {
	return tmk_render_narrative_band( $atts, $content );
} );
