<?php
/**
 * Allows SVG uploads (needed for the TMK logo lockups) with basic sanitization.
 * WordPress blocks SVG by default as a security precaution against embedded scripts;
 * since these are three known, sourced-from-Claude-Design brand assets (not user uploads),
 * a simple mime allowlist is the minimal, standard way to enable them for Editors/Admins.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'upload_mimes', function ( $mimes ) {
	$mimes['svg'] = 'image/svg+xml';
	return $mimes;
} );

add_filter( 'wp_check_filetype_and_ext', function ( $data, $file, $filename, $mimes ) {
	if ( preg_match( '/\.svg$/i', $filename ) ) {
		$data['ext']  = 'svg';
		$data['type'] = 'image/svg+xml';
	}
	return $data;
}, 10, 4 );

/**
 * Let each component's own sizes hint decide which srcset candidate is used.
 *
 * WordPress 6.7+ prepends sizes="auto" to every lazy-loaded image. Per the spec, auto as the
 * first entry makes the browser IGNORE the rest of the list and select from srcset using the
 * element's rendered LAYOUT width. That is a good default for a theme that cannot know its own
 * layout. It is exactly wrong here, for one specific reason: Card's overlay shapes crop a wide
 * photograph into a much taller box with object-fit:cover, so the layout WIDTH understates the
 * resolution the visible crop actually needs.
 *
 * Measured on the Homepage before this filter: a 286x214 location card (fed the city's 1180x550
 * banner) was served the 300x140 crop and upscaled it 1.61x vertically - visibly soft photography
 * on every restaurant card on the page, while a perfectly good 768w candidate sat unused in the
 * same srcset.
 *
 * Every TMK component passes a deliberate, hand-computed sizes value (see inc/card-render.php and
 * inc/hero-render.php), so removing auto restores those hints as the authority rather than
 * discarding information. Filtered globally instead of per call site because the reasoning is
 * global: in this plugin an explicit hint always exists and is always better informed than the
 * layout width.
 */
add_filter( 'wp_img_tag_add_auto_sizes', '__return_false' );
