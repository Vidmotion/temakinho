<?php
/**
 * Gallery - direct translation of components/content/Gallery.jsx.
 * "Immersive photography beyond the hero - grid with lightbox, or full-bleed scroll strip."
 *
 * ONE DESIGN DECISION MADE IN THE IMPLEMENTATION, worth reading before changing it: the source is
 * React and keeps a single Modal whose contents it re-renders from an open-index state. Server-
 * rendered HTML has no state, so the same behaviour is produced by giving every photograph its own
 * Modal instance and pointing its thumbnail at it. The result is identical for the user, it needs
 * NO gallery-specific JavaScript at all (js/interactions/modal.js already handles open, Escape,
 * scrim, focus trap and focus return for any number of instances), and with loading="lazy" inside
 * a [hidden] ancestor no full-size file is fetched until a photograph is actually opened. The
 * alternative - a stage element plus a script to swap the image - would add a file to the
 * components layer to re-implement behaviour the interactions layer already owns.
 *
 * @param int[]  $ids     Attachment IDs, in the order the design's data lists them.
 * @param string $variant 'grid' (default) | 'full-bleed-scroll'.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function tmk_render_gallery( $atts = array() ) {
	$atts = wp_parse_args( $atts, array(
		'ids'     => array(),
		'variant' => 'grid',
		'sizes'   => '(min-width: 1024px) 470px, calc(100vw - 64px)',
		'label'   => 'Galleria fotografica',
	) );

	$ids = array_values( array_filter( array_map( 'intval', (array) $atts['ids'] ) ) );
	if ( ! $ids ) {
		return '';
	}

	static $instance = 0;
	$instance++;
	$base    = 'tmk-gallery-' . $instance;
	$variant = 'full-bleed-scroll' === $atts['variant'] ? 'full-bleed-scroll' : 'grid';

	ob_start();
	?>
	<div class="tmk-gallery tmk-gallery--<?php echo esc_attr( $variant ); ?>">
		<div class="tmk-gallery__items" role="group" aria-label="<?php echo esc_attr( $atts['label'] ); ?>">
			<?php foreach ( $ids as $i => $id ) :
				$alt      = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
				$name     = $alt ? $alt : sprintf( 'foto %d', $i + 1 );
				$modal_id = $base . '-' . ( $i + 1 );
				?>
				<button type="button" class="tmk-gallery__item" data-tmk-modal-trigger="<?php echo esc_attr( $modal_id ); ?>"
					aria-label="<?php echo esc_attr( 'Apri: ' . $name ); ?>" aria-haspopup="dialog">
					<?php echo wp_get_attachment_image( $id, 'large', false, array(
						'alt'      => '',
						'loading'  => 'lazy',
						'decoding' => 'async',
						'sizes'    => $atts['sizes'],
					) ); ?>
				</button>
			<?php endforeach; ?>
		</div>

		<?php foreach ( $ids as $i => $id ) :
			$alt      = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
			$name     = $alt ? $alt : sprintf( 'foto %d', $i + 1 );
			$modal_id = $base . '-' . ( $i + 1 );
			?>
			<div class="tmk-modal tmk-modal--lightbox" data-tmk-modal id="<?php echo esc_attr( $modal_id ); ?>" hidden>
				<div class="tmk-modal__scrim" data-tmk-modal-scrim></div>
				<div class="tmk-modal__panel" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr( $name ); ?>">
					<button type="button" class="tmk-modal__close" data-tmk-modal-close aria-label="Chiudi">&#10005;</button>
					<figure class="tmk-gallery__stage">
						<?php echo wp_get_attachment_image( $id, 'full', false, array(
							'alt'      => $alt,
							'loading'  => 'lazy',
							'decoding' => 'async',
							'sizes'    => '(min-width: 1080px) 1032px, 92vw',
						) ); ?>
						<?php if ( $alt ) : ?><figcaption><?php echo esc_html( $alt ); ?></figcaption><?php endif; ?>
					</figure>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Gallery, carousel variant (client review §4). Deliberately NOT another branch inside
 * tmk_render_gallery() above: this delegates to Elementor Pro's OWN Media_Carousel widget
 * class, instantiated standalone with settings built from real per-post gallery_ids, so the
 * arrows and the synced thumbnail strip are Elementor Pro's real swiper.js engine (already
 * enqueued sitewide via get_style_depends()/get_script_depends() on TMK_Gallery_Widget) - not
 * a hand-rolled slider. skin=>'slideshow' makes Media_Carousel::render() auto-enable the
 * thumbnail strip whenever count($slides) > 1 (its own logic, unmodified) - a single image
 * renders as a plain carousel with no orphaned/broken thumbnail row, and any count from 3
 * (Capo d'Orlando) to 9 (Borgo) works identically since this is a plain loop over $ids, not a
 * fixed-row repeater.
 *
 * @param int[] $ids Attachment IDs.
 * @return string
 */
function tmk_render_gallery_carousel( array $ids ) {
	$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
	if ( ! $ids || ! class_exists( '\ElementorPro\Modules\Carousel\Widgets\Media_Carousel' ) ) {
		return '';
	}
	$slides = array();
	foreach ( $ids as $id ) {
		$slides[] = array(
			'_id'   => 'tmkslide' . $id,
			'type'  => 'image',
			'image' => array( 'id' => $id, 'url' => wp_get_attachment_image_url( $id, 'large' ) ),
		);
	}
	$widget = new \ElementorPro\Modules\Carousel\Widgets\Media_Carousel(
		array(
			'id'       => substr( 'tmkgal' . md5( implode( ',', $ids ) ), 0, 12 ),
			'settings' => array( 'skin' => 'slideshow', 'slides' => $slides ),
		),
		array()
	);
	ob_start();
	$widget->render_content();
	$inner = ob_get_clean();
	/* Elementor's OWN frontend.js only auto-initialises swiper for elements whose data-widget_type
	   matches a REGISTERED widget name (media-carousel.default) - since this markup is printed by
	   OUR tmk-gallery widget, that hook never fires (confirmed: swiper-initialized class absent,
	   main swiper collapses to 0 height). Wrapping in a stable class gives js/interactions/
	   carousel.js a scoped, reliable hook to initialise the real swiper.js library directly
	   (already enqueued - TMK_Gallery_Widget::get_script_depends()) instead of faking Elementor's
	   internal event system, which is undocumented and version-fragile.

	   The 'skin' control (settings above) is declared with prefix_class=>'elementor-skin-', which
	   Elementor normally adds to the WIDGET'S OWN wrapper element inside Widget_Base::render() -
	   a step render_content() alone skips. widget-media-carousel.min.css's height rule
	   (.elementor-skin-slideshow .elementor-main-swiper{height:450px}) is scoped to that class, so
	   without it the swiper container has no height at all (confirmed: swiper initialises and
	   slides correctly, but every rect is 0 tall). Adding the class to our own wrapper - a
	   descendant selector, so it doesn't need to be the exact element - restores it. */

	/* Lightbox (client review §6): clicking the main image must open a full-view popup.
	   Tested first, not assumed: Elementor's own lightbox never binds here for the same reason
	   swiper's own init didn't - it keys off registered widget names, and this markup is printed
	   by our tmk-gallery widget. Rather than reverse-engineer Elementor's lightbox JS (version-
	   fragile, same trap already avoided for the swiper init), this reuses js/interactions/
	   modal.js - the generic, already-audited engine (open/Escape/scrim/focus-trap/focus-return)
	   the grid gallery variant already uses - via its existing declarative contract
	   (data-tmk-modal-trigger + [data-tmk-modal]). Needs zero new JS for the modal itself.
	   Scoped to the string BEFORE 'elementor-thumbnails-swiper' so only the MAIN swiper's slide
	   images become triggers - the thumbnail strip's copies keep their existing job (driving the
	   main swiper), not a second lightbox trigger. */
	$thumbs_pos = strpos( $inner, 'elementor-thumbnails-swiper' );
	$main_part  = false !== $thumbs_pos ? substr( $inner, 0, $thumbs_pos ) : $inner;
	$rest_part  = false !== $thumbs_pos ? substr( $inner, $thumbs_pos ) : '';

	$lightbox_modals = '';
	$slide_index     = 0;
	$main_part = preg_replace_callback(
		'/<div class="elementor-carousel-image"([^>]*)>/',
		function ( $m ) use ( $ids, &$lightbox_modals, &$slide_index ) {
			$id = $ids[ $slide_index ] ?? 0;
			$slide_index++;
			if ( ! $id ) {
				return $m[0];
			}
			$alt      = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
			$modal_id = 'tmkcarlb' . $id . '_' . $slide_index;
			$attrs    = preg_replace( '/role="img"/', 'role="button"', $m[1] );
			$attrs    = preg_replace( '/aria-label="[^"]*"/', 'aria-label="' . esc_attr( 'Apri: ' . ( $alt ?: ( 'foto ' . $slide_index ) ) ) . '"', $attrs );
			$lightbox_modals .= tmk_render_carousel_lightbox_modal( $id, $modal_id, $alt );
			return '<div class="elementor-carousel-image" tabindex="0" data-tmk-modal-trigger="' . esc_attr( $modal_id ) . '"' . $attrs . '>';
		},
		$main_part
	);
	$inner = $main_part . $rest_part;

	$decor = '';
	if ( is_singular( 'location' ) && function_exists( 'tmk_render_local_decor' ) ) {
		// Client review §7: this container is confirmed free of body text at any content length -
		// a safe home for the ambient leaves illustration that used to drift over the atmosphere
		// block under the old page-percentage positioning (see inc/refinements.php).
		$piece = tmk_render_local_decor( 'leaves-banana', 'tmk-page-decor__leaves--local' );
		$decor = $piece ? '<span aria-hidden="true">' . $piece . '</span>' : '';
	}
	return '<div class="tmk-gallery-carousel elementor-skin-slideshow tmk-gallery-carousel--decorated">' . $inner . $decor . $lightbox_modals . '</div>';
}

/**
 * One lightbox modal for a carousel slide - same shape as tmk_render_gallery()'s own lightbox
 * stage above, so it reuses that already-styled/audited markup (.tmk-modal--lightbox,
 * .tmk-gallery__stage) rather than inventing a second pattern.
 */
function tmk_render_carousel_lightbox_modal( $id, $modal_id, $alt ) {
	ob_start();
	?>
	<div class="tmk-modal tmk-modal--lightbox" data-tmk-modal id="<?php echo esc_attr( $modal_id ); ?>" hidden>
		<div class="tmk-modal__scrim" data-tmk-modal-scrim></div>
		<div class="tmk-modal__panel" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr( $alt ?: 'Fotografia' ); ?>">
			<button type="button" class="tmk-modal__close" data-tmk-modal-close aria-label="Chiudi">&#10005;</button>
			<figure class="tmk-gallery__stage">
				<?php echo wp_get_attachment_image( $id, 'full', false, array(
					'alt'      => $alt,
					'loading'  => 'lazy',
					'decoding' => 'async',
					'sizes'    => '(min-width: 1080px) 1032px, 92vw',
				) ); ?>
				<?php if ( $alt ) : ?><figcaption><?php echo esc_html( $alt ); ?></figcaption><?php endif; ?>
			</figure>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/** [tmk_gallery ids="227,228,33" variant="grid"] */
add_shortcode( 'tmk_gallery', function ( $atts ) {
	$atts = shortcode_atts( array( 'ids' => '', 'variant' => 'grid', 'sizes' => '', 'label' => '' ), $atts );
	$args = array(
		'ids'     => array_filter( array_map( 'intval', explode( ',', (string) $atts['ids'] ) ) ),
		'variant' => $atts['variant'],
	);
	if ( $atts['sizes'] ) $args['sizes'] = $atts['sizes'];
	if ( $atts['label'] ) $args['label'] = $atts['label'];
	return tmk_render_gallery( $args );
} );
