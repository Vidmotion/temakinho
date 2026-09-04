<?php
/**
 * Search results — direct translation of pages/Search.jsx over the REAL WordPress search. The
 * header's search overlay (inc/refinements.php) posts a GET `s` to home; this renders those results
 * as type-tagged cards, with a refine field and a SystemState empty state. Rendered by
 * inc/templates/search.php.
 *
 * Faithful deviation: the prototype filters a static in-page index client-side; here the query is a
 * real WP_Query across the site's content types, so the results are the site's actual content.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** Human label for a result's post type (the card eyebrow). */
function tmk_search_type_label( $post_type ) {
	$map = array(
		'post'      => 'Storia',
		'location'  => 'Ristorante',
		'event'     => 'Evento',
		'job'       => 'Posizione',
		'menu_item' => 'Piatto',
		'page'      => 'Pagina',
	);
	if ( isset( $map[ $post_type ] ) ) {
		return $map[ $post_type ];
	}
	$obj = get_post_type_object( $post_type );
	return $obj ? $obj->labels->singular_name : ucfirst( $post_type );
}

function tmk_render_search() {
	$q = get_search_query();

	ob_start();
	?>
	<div class="tmk-search">
		<?php echo tmk_render_breadcrumb( array(
			array( 'label' => 'Home', 'href' => home_url( '/' ) ),
			array( 'label' => 'Cerca' ),
		) ); ?>

		<header class="tmk-search__header">
			<p class="tmk-search__eyebrow">Cerca</p>
			<h1 class="tmk-search__title"><?php echo $q ? esc_html( sprintf( 'Risultati per “%s”', $q ) ) : 'Cosa stai cercando?'; ?></h1>
			<form role="search" method="get" class="tmk-search__form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				<label class="tmk-sr-only" for="tmk-search-page-field">Cerca su Temakinho</label>
				<input type="search" id="tmk-search-page-field" name="s" class="tmk-form__input" value="<?php echo esc_attr( $q ); ?>" placeholder="Ristoranti, piatti, storie, eventi…" autofocus />
				<button type="submit" class="tmk-cta tmk-cta--primary">Cerca</button>
			</form>
		</header>

		<?php
		if ( '' !== $q ) :
			$results = new WP_Query( array(
				's'              => $q,
				'post_type'      => array( 'post', 'location', 'event', 'job', 'page' ),
				'post_status'    => 'publish',
				'posts_per_page' => 24,
			) );
			if ( $results->have_posts() ) :
				$cards = '';
				while ( $results->have_posts() ) : $results->the_post();
					$cards .= tmk_render_card( array(
						'variant'    => 'story',
						'media_id'   => (int) get_post_thumbnail_id( get_the_ID() ),
						'eyebrow'    => tmk_search_type_label( get_post_type() ),
						'title'      => get_the_title(),
						'supporting' => wp_trim_words( get_the_excerpt(), 22 ),
						'action'     => 'Apri',
						'href'       => get_permalink(),
						'sizes'      => '(min-width:1024px) 320px, calc(100vw - 48px)',
					) );
				endwhile;
				wp_reset_postdata();
				echo tmk_render_card_grid( $cards, 3 );
			else :
				echo tmk_render_system_state( array(
					'kind'         => 'empty',
					'title'        => sprintf( 'Nessun risultato per “%s”', $q ),
					'copy'         => 'Prova con il nome di una città, un piatto o una storia — oppure esplora da qui.',
					'action_label' => 'Esplora i ristoranti',
					'action_href'  => home_url( '/ristoranti/' ),
				) );
			endif;
		endif;
		?>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'tmk_search', 'tmk_render_search' );
