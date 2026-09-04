<?php
/**
 * Shop — direct translation of pages/Shop.jsx (editorial commerce, Collection family): photographic
 * hero -> featured product (cinematic diptych, price prominent) -> photo-led collections (price
 * always visible) -> conversion (bespoke gifting). Temakinho identity, no cart/Shopify chrome.
 *
 * Mounted on the /shop Page via the generic full-width template. This is an on-brand SHOWCASE: the
 * real storefront lives on temakinho.com (Shopify), so every product action routes to TMK_SHOP_URL
 * rather than faking a basket, and the conversion CTA routes to Contact. The showcase items are the
 * approved design selection (shop-data.js); swap for the live catalogue when the store is integrated.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function tmk_render_shop() {
	$shop = defined( 'TMK_SHOP_URL' ) ? TMK_SHOP_URL : home_url( '/shop/' );
	$aid  = function_exists( 'tmk_asset_id' ) ? 'tmk_asset_id' : null;
	$img  = function ( $slug ) use ( $aid ) { return $aid ? call_user_func( $aid, $slug ) : 0; };

	$featured = array( 'eyebrow' => 'La selezione del mese', 'title' => 'Il kit del temaki perfetto', 'price' => '€48,00',
		'excerpt' => 'Alga, riso, tappetino e il nostro ricettario illustrato: tutto per arrotolare il temaki come al bancone. In edizione limitata.',
		'media' => $img( 'interior-milano' ) );
	$collections = array(
		array( 'name' => 'In cucina', 'items' => array(
			array( 'title' => 'Salsa di anacardi', 'supporting' => 'La nostra ricetta carioca, in barattolo', 'price' => '€9,00', 'media' => $img( 'interior-bologna' ) ),
			array( 'title' => 'Leche de tigre', 'supporting' => 'Base per ceviche, pronta all’uso', 'price' => '€11,00', 'media' => $img( 'interior-roma' ) ),
			array( 'title' => 'Ricettario illustrato', 'supporting' => '40 ricette e la storia della casa', 'price' => '€24,00', 'media' => $img( 'interior-rimini-wide' ) ),
		) ),
		array( 'name' => 'A tavola', 'items' => array(
			array( 'title' => 'Ciotola in ceramica', 'supporting' => 'Fatta a mano, verde giungla', 'price' => '€28,00', 'media' => $img( 'interior-milano' ) ),
			array( 'title' => 'Bacchette in bambù', 'supporting' => 'Set da due paia, incise', 'price' => '€14,00', 'media' => $img( 'interior-lione' ) ),
			array( 'title' => 'Grembiule della casa', 'supporting' => 'Tela grezza, ricamo ara', 'price' => '€32,00', 'media' => $img( 'interior-rimini-bar' ) ),
		) ),
		array( 'name' => 'Buono regalo', 'items' => array(
			array( 'title' => 'Gift card cena', 'supporting' => 'Per due, in tutte le sale', 'price' => '€90,00', 'media' => $img( 'interior-roma' ) ),
			array( 'title' => 'Gift card libera', 'supporting' => 'Scegli tu l’importo', 'price' => 'da €25,00', 'media' => $img( 'interior-bologna' ) ),
		) ),
	);

	ob_start();

	echo tmk_render_hero( array(
		'variant'     => 'full-bleed',
		'media_id'    => $img( 'interior-rimini-table' ),
		'media_alt'   => 'Tavolo Temakinho con ceramiche, fiori e oggetti della casa',
		'eyebrow'     => 'Lo shop',
		'headline'    => 'Portati Temakinho a casa',
		'subheadline' => 'Il gusto, il colore e l’alegria delle nostre sale — da vivere anche fuori dal tavolo.',
		'cta_label'   => '',
		'height'      => 'min(58vh, 520px)',
	) );
	?>

	<div class="tmk-stack">

		<?php /* FEATURED — cinematic diptych, price prominent (reuses the .tmk-cover diptych). */ ?>
		<section aria-label="In evidenza">
			<h2 class="tmk-section-eyebrow"><?php echo esc_html( $featured['eyebrow'] ); ?></h2>
			<a class="tmk-cover tmk-shop-featured tmk-reveal" href="<?php echo esc_url( $shop ); ?>" rel="noopener" aria-label="<?php echo esc_attr( $featured['title'] ); ?>">
				<span class="tmk-cover__media"><?php echo $featured['media'] ? wp_get_attachment_image( $featured['media'], 'full', false, array( 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async', 'sizes' => '(min-width:1024px) 540px, 100vw' ) ) : ''; ?></span>
				<span class="tmk-cover__text">
					<span class="tmk-cover__title"><?php echo esc_html( $featured['title'] ); ?></span>
					<span class="tmk-standfirst"><?php echo esc_html( $featured['excerpt'] ); ?></span>
					<span class="tmk-shop-featured__buy">
						<span class="tmk-shop-featured__price"><?php echo esc_html( $featured['price'] ); ?></span>
						<span class="tmk-cover__readmore">Scopri il kit &rarr;</span>
					</span>
				</span>
			</a>
		</section>

		<?php foreach ( $collections as $col ) : ?>
			<section aria-label="<?php echo esc_attr( $col['name'] ); ?>">
				<h2 class="tmk-section-eyebrow"><?php echo esc_html( $col['name'] ); ?></h2>
				<?php
				$cols  = count( $col['items'] ) <= 2 ? 2 : 3;
				$cards = '';
				foreach ( $col['items'] as $it ) {
					$cards .= tmk_render_card( array(
						'variant'    => 'story',
						'media_id'   => (int) $it['media'],
						'title'      => $it['title'],
						'supporting' => $it['supporting'],
						'price'      => $it['price'],
						'action'     => 'Scopri',
						'href'       => $shop,
						'sizes'      => '(min-width:1024px) 320px, calc(100vw - 48px)',
					) );
				}
				echo tmk_render_card_grid( $cards, $cols );
				?>
			</section>
		<?php endforeach; ?>

		<?php /* CONVERSION — bespoke gifting, one primary action. */ ?>
		<section aria-label="Regali su misura">
			<?php echo tmk_render_cta_banner( array(
				'variant'   => 'booking',
				'title'     => 'Cerchi un regalo su misura?',
				'copy'      => 'Confezioni per aziende ed eventi: te le prepariamo noi.',
				'cta_label' => 'Scrivici',
				'cta_href'  => home_url( '/contattaci/' ),
			) ); ?>
		</section>

	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'tmk_shop', 'tmk_render_shop' );
