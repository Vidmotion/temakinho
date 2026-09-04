<?php
/**
 * TMK logo — one artwork, recolored by context via CSS, matching the design system's own
 * guideline card (guidelines/brand-logos.html: "Black on the stage, white on interiors —
 * never redrawn") and the source SVGs themselves: both tmk_logo_black.svg and
 * tmk_logo_white.svg ship with an unstyled `.st0` path class and no embedded fill, i.e. the
 * artwork is meant to inherit color from its context rather than exist as two separate
 * drawings. Referencing it via <img src="*.svg"> can never pick up that context (external SVG
 * documents don't inherit page color), so the logo is inlined and colored with currentColor.
 * Shared here rather than duplicated between Header and Footer render files.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function tmk_get_logo_svg_markup( $extra_class = '' ) {
	static $cache = null;
	if ( $cache === null ) {
		$path = get_attached_file( 8 ); // TMK Logo Black — canonical artwork, see docblock above.
		$cache = ( $path && file_exists( $path ) ) ? file_get_contents( $path ) : '';
	}
	if ( ! $cache ) return '';

	$svg = preg_replace( '/<\?xml.*?\?>/', '', $cache );
	/*
	 * Strip the root id (Illustrator writes id="Livello_1" into every export). The logo is inlined
	 * twice on every page — header and footer — so that id was a duplicate in every document,
	 * which is invalid HTML and makes getElementById ambiguous. Nothing references it: the artwork
	 * has no url(#...) references and takes its colour from currentColor. Session 6.
	 */
	$svg = preg_replace( '/(<svg\b[^>]*?)\s+id="[^"]*"/', '$1', $svg, 1 );
	$svg = preg_replace( '/<svg /', '<svg class="tmk-logo' . ( $extra_class ? ' ' . esc_attr( $extra_class ) : '' ) . '" role="img" aria-label="Temakinho" ', $svg, 1 );
	return $svg;
}
