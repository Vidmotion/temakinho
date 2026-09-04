<?php
/**
 * System State — direct translation of the DS SystemState component (pages/SystemStates.jsx,
 * pages/Search.jsx). The reusable loading / empty / success / error partial every template shares,
 * so a "no results", "sent", or "something went wrong" moment looks the same everywhere and always
 * offers a way onward. Calm and specific, never blaming (the states catalog's own brief).
 *
 * Exposed as [tmk_system_state kind="empty" title="…" copy="…" action_label="…" action_href="…"].
 * The title is a <p>, not a heading: these render inside other pages (search results, form
 * responses) and must not inject an out-of-order heading into the document outline.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function tmk_system_state_defaults( $kind ) {
	$d = array(
		'loading' => array( 'title' => 'Un attimo…', 'copy' => 'Stiamo apparecchiando i contenuti.' ),
		'empty'   => array( 'title' => 'Nessun risultato', 'copy' => 'Non abbiamo trovato nulla — prova un’altra strada, oppure esplora da qui.' ),
		'success' => array( 'title' => 'Fatto', 'copy' => 'Tutto a posto. Ti risponderemo al più presto.' ),
		'error'   => array( 'title' => 'Qualcosa è andato storto', 'copy' => 'Riprova tra un attimo — oppure scrivici, rispondiamo in fretta.' ),
	);
	return $d[ $kind ] ?? $d['empty'];
}

function tmk_render_system_state( $atts = array() ) {
	$atts = shortcode_atts( array(
		'kind'         => 'empty', // loading | empty | success | error
		'title'        => '',
		'copy'         => '',
		'action_label' => '',
		'action_href'  => '',
	), $atts );

	$kind = in_array( $atts['kind'], array( 'loading', 'empty', 'success', 'error' ), true ) ? $atts['kind'] : 'empty';
	$def  = tmk_system_state_defaults( $kind );
	$title = $atts['title'] ?: $def['title'];
	$copy  = $atts['copy'] ?: $def['copy'];

	// loading announces politely; error assertively; the rest are neutral status regions.
	$role = ( 'error' === $kind ) ? 'alert' : 'status';
	$live = ( 'loading' === $kind ) ? ' aria-live="polite"' : '';

	ob_start();
	?>
	<div class="tmk-state tmk-state--<?php echo esc_attr( $kind ); ?>" role="<?php echo esc_attr( $role ); ?>"<?php echo $live; ?>>
		<?php if ( 'loading' === $kind ) : ?>
			<span class="tmk-state__spinner" aria-hidden="true"></span>
		<?php else : ?>
			<span class="tmk-state__mark tmk-state__mark--<?php echo esc_attr( $kind ); ?>" aria-hidden="true"></span>
		<?php endif; ?>
		<?php if ( $title ) : ?><p class="tmk-state__title"><?php echo esc_html( $title ); ?></p><?php endif; ?>
		<?php if ( $copy ) : ?><p class="tmk-state__copy"><?php echo esc_html( $copy ); ?></p><?php endif; ?>
		<?php if ( $atts['action_label'] && $atts['action_href'] ) : ?>
			<a class="tmk-cta tmk-cta--secondary" href="<?php echo esc_url( $atts['action_href'] ); ?>"><?php echo esc_html( $atts['action_label'] ); ?></a>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'tmk_system_state', function ( $atts ) {
	return tmk_render_system_state( $atts );
} );
