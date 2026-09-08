<?php
/**
 * Hero — direct translation of components/hero/Hero.jsx. Three variants sharing one contract;
 * see css/components/hero.css for the corresponding CSS. Exposed as [tmk_hero].
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function tmk_render_hero( $atts = array() ) {
	$atts = shortcode_atts( array(
		'variant'         => 'full-bleed', // full-bleed | contained | minimal
		'media_id'        => 0,            // attachment ID; omitted entirely if 0/missing
		'media_alt'       => '',
		'headline'        => '',
		'subheadline'     => '',
		'eyebrow'         => '',
		'concept'         => '',
		'concept_label'   => '',
		'cta_label'       => 'Prenota ora',
		// 2026-09-07: ripiego sulla destinazione governata invece dell'ancora /#prenota.
		'cta_href'        => defined( 'TMK_BOOKING_URL' ) ? TMK_BOOKING_URL : home_url( '/#prenota' ),
		'secondary_label' => '',
		'secondary_href'  => '#',
		'height'          => '',
	), $atts );

	/*
	 * Media through wp_get_attachment_image() rather than a bare <img src>, for exactly the
	 * reason Card was changed in §14 fix 8: srcset + a sizes hint so the browser downloads a
	 * file that fits the box, and intrinsic width/height so a multi-megabyte illustration
	 * cannot shift the page while it decodes (the Homepage measured CLS 0 — keep it there).
	 * A Hero image is above the fold by definition, so it is eager + fetchpriority="high";
	 * this is the follow-up recorded in PROJECT_CONTEXT §15 for the moment the Hero
	 * illustration finally landed and became the LCP element.
	 * sizes mirrors each variant's own CSS: --contained is max-width:min(760px,92%) centred on
	 * the cream stage, --full-bleed covers the whole viewport.
	 */
	$media_html = '';
	if ( $atts['media_id'] ) {
		$media_html = wp_get_attachment_image( (int) $atts['media_id'], 'full', false, array(
			'class'         => 'tmk-hero__media',
			'alt'           => $atts['media_alt'],
			'loading'       => 'eager',
			'decoding'      => 'async',
			'fetchpriority' => 'high',
			'sizes'         => 'contained' === $atts['variant'] ? '(min-width: 840px) 760px, 92vw' : '100vw',
		) );
	}
	$variant_class = 'tmk-hero--' . preg_replace( '/[^a-z-]/', '', $atts['variant'] );
	$concept_attr = $atts['concept'] ? ' data-concept="' . esc_attr( $atts['concept'] ) . '"' : '';

	ob_start();

	if ( 'minimal' === $atts['variant'] ) : ?>
		<header class="tmk-hero <?php echo esc_attr( $variant_class ); ?>"<?php echo $concept_attr; ?>>
			<?php if ( $atts['eyebrow'] ) : ?><span class="tmk-hero__eyebrow"><?php echo esc_html( $atts['eyebrow'] ); ?></span><?php endif; ?>
			<?php if ( $atts['headline'] ) : ?><h1><?php echo esc_html( $atts['headline'] ); ?></h1><?php endif; ?>
			<?php if ( $atts['subheadline'] ) : ?><p><?php echo esc_html( $atts['subheadline'] ); ?></p><?php endif; ?>
		</header>
	<?php elseif ( 'contained' === $atts['variant'] ) : ?>
		<header class="tmk-hero <?php echo esc_attr( $variant_class ); ?>"<?php echo $concept_attr; ?>>
			<?php echo $media_html; ?>
			<?php if ( $atts['concept_label'] || $atts['eyebrow'] ) : ?>
				<div class="tmk-hero__meta">
					<?php if ( $atts['concept_label'] ) : ?><span class="tmk-hero__badge"><?php echo esc_html( $atts['concept_label'] ); ?></span><?php endif; ?>
					<?php if ( $atts['eyebrow'] ) : ?><span class="tmk-hero__eyebrow"><?php echo esc_html( $atts['eyebrow'] ); ?></span><?php endif; ?>
				</div>
			<?php endif; ?>
			<?php if ( $atts['headline'] ) : ?><h1 class="tmk-display"><?php echo esc_html( $atts['headline'] ); ?></h1><?php endif; ?>
			<?php if ( $atts['subheadline'] ) : ?><p><?php echo esc_html( $atts['subheadline'] ); ?></p><?php endif; ?>
			<?php if ( $atts['cta_label'] ) : ?><a href="<?php echo esc_url( $atts['cta_href'] ); ?>" class="tmk-cta tmk-cta--primary"><?php echo esc_html( $atts['cta_label'] ); ?></a><?php endif; ?>
		</header>
	<?php else : ?>
		<header class="tmk-hero <?php echo esc_attr( $variant_class ); ?>"<?php echo $concept_attr; ?><?php echo $atts['height'] ? ' style="min-height:' . esc_attr( $atts['height'] ) . '"' : ''; ?>>
			<?php echo $media_html; ?>
			<div class="tmk-hero__scrim"></div>
			<div class="tmk-hero__content">
				<?php if ( $atts['concept_label'] || $atts['eyebrow'] ) : ?>
					<div class="tmk-hero__meta">
						<?php if ( $atts['concept_label'] ) : ?><span class="tmk-hero__badge"><?php echo esc_html( $atts['concept_label'] ); ?></span><?php endif; ?>
						<?php if ( $atts['eyebrow'] ) : ?><span class="tmk-hero__eyebrow"><?php echo esc_html( $atts['eyebrow'] ); ?></span><?php endif; ?>
					</div>
				<?php endif; ?>
				<?php if ( $atts['headline'] ) : ?><h1 class="tmk-display"><?php echo esc_html( $atts['headline'] ); ?></h1><?php endif; ?>
				<?php if ( $atts['subheadline'] ) : ?><p><?php echo esc_html( $atts['subheadline'] ); ?></p><?php endif; ?>
				<div class="tmk-hero__actions">
					<?php if ( $atts['cta_label'] ) : ?><a href="<?php echo esc_url( $atts['cta_href'] ); ?>" class="tmk-cta tmk-cta--primary"><?php echo esc_html( $atts['cta_label'] ); ?></a><?php endif; ?>
					<?php if ( $atts['secondary_label'] ) : ?><a href="<?php echo esc_url( $atts['secondary_href'] ); ?>" class="tmk-cta tmk-cta--secondary"><?php echo esc_html( $atts['secondary_label'] ); ?></a><?php endif; ?>
				</div>
			</div>
		</header>
	<?php endif;

	return ob_get_clean();
}
add_shortcode( 'tmk_hero', function ( $atts ) {
	return tmk_render_hero( $atts );
} );
