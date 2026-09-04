<?php
/**
 * Legal / utility documents — direct translation of pages/Legal.jsx (Terminal, illustration-bare):
 * a narrow reading column with an eyebrow + title + "last updated" header, an auto-built table of
 * contents from the page's own <h2> sections, and the prose.
 *
 * On the live temakinho.com the legal register is mostly EXTERNAL — Privacy, Cookie and the Codice
 * Etico are PDFs and Whistleblowing is an external portal — so the footer links point straight at
 * those real destinations (inc/footer-render.php) rather than fabricating on-site copies. The one
 * genuinely on-site legal page is "Lista allergeni e decongelati"; this renderer + inc/templates/
 * page-legal.php serve it (and any future on-site legal page flagged _tmk_template=legal).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function tmk_render_legal_page( $post = null ) {
	$post = $post ? get_post( $post ) : get_queried_object();
	if ( ! $post instanceof WP_Post ) {
		return '';
	}

	$updated = (string) get_post_meta( $post->ID, 'legal_updated', true );
	if ( '' === $updated ) {
		$updated = 'Ultimo aggiornamento: ' . tmk_date_it( strtotime( $post->post_modified ) );
	}

	// Render the body, then inject ids into its <h2>s and collect a table of contents from them.
	$content = apply_filters( 'the_content', $post->post_content );
	$toc      = array();
	$content  = preg_replace_callback( '/<h2([^>]*)>(.*?)<\/h2>/is', function ( $m ) use ( &$toc ) {
		$text = trim( wp_strip_all_tags( $m[2] ) );
		$slug = sanitize_title( $text );
		$toc[] = array( 'slug' => $slug, 'text' => $text );
		// Preserve any existing attributes but ensure an id is present.
		$attrs = $m[1];
		if ( false === strpos( $attrs, 'id=' ) ) {
			$attrs .= ' id="' . esc_attr( $slug ) . '"';
		}
		return '<h2' . $attrs . '>' . $m[2] . '</h2>';
	}, $content );

	ob_start();
	?>
	<div class="tmk-legal tmk-decor-r-koi">
		<?php echo tmk_render_breadcrumb( array(
			array( 'label' => 'Home', 'href' => home_url( '/' ) ),
			array( 'label' => 'Note legali' ),
		) ); ?>

		<header class="tmk-legal__header">
			<p class="tmk-legal__eyebrow">Note legali</p>
			<h1 class="tmk-legal__title"><?php echo esc_html( get_the_title( $post ) ); ?></h1>
			<?php if ( $updated ) : ?><p class="tmk-legal__updated"><?php echo esc_html( $updated ); ?></p><?php endif; ?>
		</header>

		<?php if ( count( $toc ) > 1 ) : ?>
			<nav class="tmk-legal__toc" aria-label="Indice">
				<ol>
					<?php foreach ( $toc as $t ) : ?>
						<li><a href="#<?php echo esc_attr( $t['slug'] ); ?>"><?php echo esc_html( $t['text'] ); ?></a></li>
					<?php endforeach; ?>
				</ol>
			</nav>
		<?php endif; ?>

		<div class="tmk-legal__body">
			<?php echo $content; // already passed through the_content + id injection ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
