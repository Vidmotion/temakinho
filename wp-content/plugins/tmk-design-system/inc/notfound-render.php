<?php
/**
 * 404 — direct translation of pages/NotFound.jsx: editorial voice, illustration-softened, two clear
 * ways back. Rendered by inc/templates/404.php inside the shell. Content from util-data.notFound.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function tmk_render_404() {
	ob_start();
	?>
	<section class="tmk-404" aria-labelledby="tmk-404-title">
		<p class="tmk-404__code" aria-hidden="true">404</p>
		<p class="tmk-404__eyebrow">Pagina non trovata</p>
		<h1 id="tmk-404-title" class="tmk-404__title">Questo tavolo non esiste</h1>
		<p class="tmk-404__copy">Forse è stato spostato, forse non è mai stato apparecchiato. Torniamo dove si mangia davvero.</p>
		<div class="tmk-404__actions">
			<a class="tmk-cta tmk-cta--primary" href="<?php echo esc_url( home_url( '/' ) ); ?>">Torna alla home</a>
			<a class="tmk-cta tmk-cta--secondary" href="<?php echo esc_url( home_url( '/ristoranti/' ) ); ?>">Esplora i ristoranti</a>
		</div>
	</section>
	<?php
	return ob_get_clean();
}
add_shortcode( 'tmk_404', 'tmk_render_404' );
