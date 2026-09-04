<?php
/**
 * Heritage — the emotional heart (Terminal/Story family, Blueprint §5.7). Direct translation of
 * pages/Heritage.jsx: cinematic hero -> manifesto -> origins diptych (illustration-led) -> Timeline
 * (the meeting) -> cinematic full-bleed -> closing manifesto (garland) -> soft discovery CTA.
 * Illustration-dominant, slow editorial pacing; connects Japan & Brazil. Mounted on the /heritage
 * Page via the generic full-width template. Not an About page — the brand's reason to exist.
 *
 * Illustrations resolve by slug via tmk_asset_id(); any motif that is not present in the media
 * library is simply omitted (the macaw art is not yet uploaded), so the page degrades gracefully
 * rather than showing a broken image.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function tmk_render_heritage() {
	/*
	 * FOTOGRAFIA AL POSTO DELLE ILLUSTRAZIONI (rework 2026-09-04).
	 *
	 * Il dittico delle origini era koi + ara: due disegni alti quasi mezzo schermo accanto a un
	 * paragrafo di quattro righe. Guardata a 1440 la sezione non raccontava niente - l'immagine
	 * dominava, il testo spariva, e l'illustrazione non aggiungeva significato al testo che le
	 * stava accanto. Sostituiti da due fotografie REALI, gia' in libreria, scelte guardandole:
	 *   - il bancone curvo illuminato d'ambra (#227) per IL RIGORE: e' letteralmente il gesto,
	 *     la postazione, la precisione di cui parla il testo;
	 *   - le persone che ridono con la caipirinha (#377) per LA GIOIA: e' l'unica fotografia del
	 *     progetto con delle PERSONE, ed e' esattamente cio' che il testo chiama "alegria".
	 * Nessuna immagine nuova e' stata creata: entrambe erano gia' caricate e inutilizzate.
	 * Le illustrazioni non spariscono dal sito - restano nel sistema decorativo di gutter
	 * (css/components/decor.css), dove sono ambiente e non riempitivo.
	 */
	$garland = function_exists( 'tmk_asset_id' ) ? tmk_asset_id( 'garland-horizontal' ) : 0;
	$hero    = function_exists( 'tmk_asset_id' ) ? tmk_asset_id( 'interior-milano' ) : 0;
	$cine    = function_exists( 'tmk_asset_id' ) ? tmk_asset_id( 'interior-rimini-wide' ) : 0;
	$ph_rigore = function_exists( 'tmk_asset_id' ) ? tmk_asset_id( 'interior-rimini-bar' ) : 0;
	$ph_gioia  = function_exists( 'tmk_asset_id' ) ? tmk_asset_id( 'caipirinha' ) : 0;

	$origins = array(
		array( 'side' => 'Giappone', 'title' => 'Il rigore', 'motif' => $ph_rigore, 'alt' => 'Il bancone curvo illuminato d’ambra, il posto dove nasce il gesto',
			'body' => 'Dal Giappone prendiamo la disciplina del gesto: il taglio netto, il riso alla temperatura esatta, il rispetto assoluto per la materia prima. La bellezza che nasce dal togliere, non dall’aggiungere.' ),
		array( 'side' => 'Brasile', 'title' => 'La gioia', 'motif' => $ph_gioia, 'alt' => 'Due ospiti ridono al tavolo con una caipirinha in mano',
			'body' => 'Dal Brasile prendiamo l’alegria: il calore che riempie una sala, il ritmo che fa restare un’ora in più, la voglia di far festa anche in un martedì qualunque. Il cuore che scalda la tecnica.' ),
	);
	$timeline = array(
		array( 'marker' => '01', 'title' => 'L’incontro', 'text' => 'Un sushi chef e un’anima carioca si ritrovano attorno a un bancone. Nasce l’idea di una cucina che non scelga tra rigore e gioia, ma li tenga insieme.' ),
		array( 'marker' => '02', 'title' => 'Il primo temaki', 'text' => 'A Milano apre la prima sala. Il temaki diventa il nostro simbolo: la precisione giapponese nel palmo di una mano, con il colore e il sapore del Brasile.' ),
		array( 'marker' => '03', 'title' => 'Le città', 'text' => 'Da Milano a Roma, da Bologna a Rimini, fino a Lione: ogni sala parla la lingua del suo quartiere, senza mai perdere l’accento.' ),
		array( 'marker' => '04', 'title' => 'Oggi', 'text' => 'Restiamo fedeli alla domanda di partenza. Ogni servizio è un nuovo modo di rispondere: due mondi, una sola tavola.' ),
	);

	ob_start();

	echo tmk_render_hero( array(
		'variant'     => 'full-bleed',
		'media_id'    => $hero,
		'media_alt'   => 'La sala di Temakinho: verde giungla, turchese e luce d’ambra',
		'eyebrow'     => 'La nostra storia',
		'headline'    => 'Due mondi, una sola tavola',
		'subheadline' => 'Dove il silenzio di Tokyo incontra il sole di Rio. Questa è la ragione per cui esistiamo.',
		'cta_label'   => '',
		'height'      => 'min(72vh, 680px)',
	) );
	?>

	<div class="tmk-heritage">

		<div class="tmk-heritage__col">
			<?php echo tmk_render_breadcrumb( array(
				array( 'label' => 'Home', 'href' => home_url( '/' ) ),
				array( 'label' => 'Storie', 'href' => home_url( '/storie/' ) ),
				array( 'label' => 'La nostra storia' ),
			) ); ?>
		</div>

		<div class="tmk-heritage__col tmk-heritage__manifesto">
			<p>Temakinho nasce da una domanda semplice: cosa succede se la precisione del Giappone incontra la gioia del Brasile? La risposta non si spiega. Si apparecchia, si versa, si condivide.</p>
		</div>

		<div class="tmk-heritage__origins tmk-decor-l-vine">
			<?php foreach ( $origins as $i => $o ) : ?>
				<section class="tmk-heritage__origin<?php echo ( $i % 2 ) ? ' is-flip' : ''; ?>" aria-label="Origine: <?php echo esc_attr( $o['side'] ); ?>">
					<?php if ( $o['motif'] ) : ?>
						<div class="tmk-heritage__origin-art"><?php echo wp_get_attachment_image( $o['motif'], 'medium', false, array( 'alt' => $o['alt'], 'loading' => 'lazy', 'decoding' => 'async' ) ); ?></div>
					<?php endif; ?>
					<div class="tmk-heritage__origin-text">
						<p class="tmk-heritage__side"><?php echo esc_html( $o['side'] ); ?></p>
						<h2 class="tmk-heritage__origin-title"><?php echo esc_html( $o['title'] ); ?></h2>
						<p class="tmk-heritage__origin-body"><?php echo esc_html( $o['body'] ); ?></p>
					</div>
				</section>
			<?php endforeach; ?>
		</div>

		<?php /* La rail perde i due motivi che ci stavano: erano lo stesso koi e la stessa ara del
		         dittico sopra, ridotti a francobollo, messi li' per non lasciare vuota la colonna.
		         Al loro posto la colonna fa il suo mestiere - regge il titolo della sezione - e il
		         titolo diventa sticky, cosi' accompagna la lettura dei quattro passaggi invece di
		         scorrere via al primo. */ ?>
		<section class="tmk-heritage__meeting" aria-label="Come i due mondi si sono incontrati">
			<div class="tmk-heritage__meeting-rail">
				<p class="tmk-heritage__side">La storia</p>
				<h2 class="tmk-heritage__meeting-title">Come i due mondi si sono incontrati</h2>
			</div>
			<?php echo tmk_render_timeline( $timeline ); ?>
		</section>

		<?php if ( $cine ) : ?>
			<figure class="tmk-heritage__cinematic"><?php echo wp_get_attachment_image( $cine, 'full', false, array( 'alt' => 'La sala di Rimini al calare della sera, tra velluti corallo e verde', 'loading' => 'lazy', 'decoding' => 'async', 'sizes' => '100vw' ) ); ?></figure>
		<?php endif; ?>

		<?php /* La chiusura diventa una BANDA SCURA, come quella della Home: prima era testo
		         centrato su crema con la ghirlanda stesa dietro, e la ghirlanda passava sotto il
		         titolo e sotto la CTA rovinandone la leggibilita' (misurato: sovrapposizione
		         geometrica reale). Su fondo ink la ghirlanda non serve piu' come riempitivo e la
		         CTA finale acquista finalmente il peso di una chiusura. */ ?>
		<section class="tmk-heritage__closing" aria-label="Alegria">
			<?php if ( $garland ) echo wp_get_attachment_image( $garland, 'full', false, array( 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async', 'class' => 'tmk-heritage__garland', 'sizes' => '(min-width: 640px) 720px, 90vw' ) ); ?>
			<h2 class="tmk-heritage__closing-title">Alegria é a melhor coisa que existe</h2>
			<p class="tmk-heritage__closing-body">Non è un motto. È il modo in cui apparecchiamo ogni tavola, ogni sera, in ogni città. Vieni a scoprire cosa significa.</p>
			<a class="tmk-cta tmk-cta--primary" href="<?php echo esc_url( home_url( '/ristoranti/' ) ); ?>">Esplora i ristoranti</a>
		</section>

	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'tmk_heritage', 'tmk_render_heritage' );
