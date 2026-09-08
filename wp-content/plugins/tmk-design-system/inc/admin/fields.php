<?php
/**
 * TMK editorial fields — the wp-admin surface the client actually edits.
 *
 * WHY THIS EXISTS, and why it is not ACF
 * -------------------------------------
 * The content model (inc/content-model.php) registers every field with core
 * register_post_meta(), and the renderers read those meta keys directly. What was missing was the
 * *editing* surface: ACF **free** has no Repeater and no Gallery, so `hours` (label/value rows)
 * and `gallery_ids` (an ordered list of attachment IDs) had no UI at all — and the ACF group for
 * `location` actually exposed `hours` as a plain TEXTAREA while the renderer reads it as an array
 * of rows, which means an editor saving that field would have silently emptied the opening hours
 * of a restaurant. Events had no editing UI whatsoever.
 *
 * This module is a small declarative metabox layer over the SAME meta keys. It adds no dependency,
 * installs nothing, and does not rename a single key: every renderer keeps working untouched.
 * The ACF groups that overlapped it are set to ACF's own "inactive" state by _BUILD/200 (backed up
 * first, reversible in one click) so the client sees exactly ONE panel per record instead of two
 * competing ones.
 *
 * WHAT IT DELIBERATELY DOES NOT DO
 *   - it does not touch `city` / `concept` / `menu_category` / `department` / `job_area` term
 *     creation, which stays WordPress's own taxonomy UI (the one place core already does this
 *     better than any custom screen);
 *   - it does not touch the Testimonial or City ACF groups, which work and have no repeaters;
 *   - it never writes a value the editor did not type. Every field degrades to empty, and every
 *     renderer in this codebase already treats empty as "omit the beat".
 *
 * FIELD TYPES
 *   text · textarea · url · email · tel · number · select · bool · checklist · media · gallery
 *   · rows (label/value repeater) · post (post picker) · datetime · date · note (read-only)
 *
 * Storage contract per type — this is the part that has to stay true to the renderers:
 *   rows      -> array of array('label'=>string,'value'=>string)   (tmk_location_hours_rows)
 *   gallery   -> array of int attachment IDs, in order              (gallery_ids / event_gallery_ids)
 *   checklist -> comma-separated string of the chosen keys          (allergens / labels)
 *   media     -> single int attachment ID
 *   post      -> single int post ID
 *   bool      -> '1' or '' (register_post_meta type boolean casts it)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================================================
   THE 14 EU ALLERGENS — regulation, not content.
   Reg. (UE) 1169/2011 Allegato II. The ORDER AND THE NUMBERING ARE FIXED BY LAW: the printed
   legend on the menu refers to these numbers, so an editor must be able to tick which allergens a
   dish contains but must never be able to renumber or reorder them. That is why this list is code
   and the checklist writes the NUMBERS, not the labels.
   ============================================================================================== */
function tmk_eu_allergens() {
	return array(
		1  => 'Glutine',
		2  => 'Crostacei',
		3  => 'Uova',
		4  => 'Pesce',
		5  => 'Arachidi',
		6  => 'Soia',
		7  => 'Latte',
		8  => 'Frutta a guscio',
		9  => 'Sedano',
		10 => 'Senape',
		11 => 'Sesamo',
		12 => 'Anidride solforosa e solfiti',
		13 => 'Lupini',
		14 => 'Molluschi',
	);
}

/** Published dish markers as they exist in the imported data (see content-model.php `labels`). */
function tmk_menu_item_labels() {
	return array(
		'star'          => 'In evidenza (⭐ sul menu pubblicato)',
		'alcohol-free'  => 'Alcohol free',
	);
}

/* ================================================================================================
   THE SCHEMA
   ============================================================================================== */

/**
 * Every editable panel on the site, declared once.
 *
 * @return array post_type => array of panels; each panel = array('id','title','context','fields').
 */
function tmk_admin_field_schema() {
	$schema = array();

	/* ------------------------------------------------------------------ RISTORANTI (location) */
	$schema['location'] = array(
		array(
			'id' => 'tmk_loc_identity', 'title' => 'Ristorante — identità', 'context' => 'normal',
			'fields' => array(
				array( 'key' => 'tagline', 'label' => 'Mini descrizione (tagline)', 'type' => 'text',
					'desc' => 'Una riga sotto il nome, nell’apertura fotografica. Es. “Il primo, quello da cui è partito tutto.”' ),
				array( 'key' => 'short_description', 'label' => 'L’atmosfera', 'type' => 'textarea', 'rows' => 8,
					'desc' => 'Il racconto della sala. Una riga vuota fra i paragrafi crea un paragrafo nuovo.' ),
				array( 'key' => 'tmk_location_logotype_id', 'label' => 'Logotipo della sede', 'type' => 'media',
					'desc' => 'Opzionale. Il lettering che compare accanto al racconto. Se vuoto, resta il nome della sede in maiuscolo.' ),
				array( 'key' => 'tmk_location_has_live_music', 'label' => 'Musica dal vivo', 'type' => 'bool',
					'cb' => 'Questa sede propone musica dal vivo' ),
				array( 'key' => 'delivery_available', 'label' => 'Delivery', 'type' => 'bool',
					'cb' => 'Consegna a domicilio disponibile' ),
			),
		),
		array(
			'id' => 'tmk_loc_gallery', 'title' => 'Ristorante — foto del carosello', 'context' => 'normal',
			'fields' => array(
				array( 'key' => 'gallery_ids', 'label' => 'Galleria', 'type' => 'gallery',
					'desc' => 'Scegli le foto dalla Libreria media, trascinale per cambiare l’ordine, usa la × per togliere una foto. La prima è quella che si vede aprendo la pagina.' ),
			),
		),
		array(
			'id' => 'tmk_loc_practical', 'title' => 'Ristorante — informazioni pratiche', 'context' => 'normal',
			'fields' => array(
				array( 'key' => 'hours', 'label' => 'Orari di apertura', 'type' => 'rows',
					'row_labels' => array( 'Giorno o giorni', 'Periodo' ),
					'desc' => 'Una riga per fascia. A sinistra il giorno (“Lunedì”, “Dal Venerdì alla Domenica”), a destra gli orari (“12.00 - 15.30 | 17.30 - 23.30”). Trascina per riordinare.' ),
				array( 'key' => 'address', 'label' => 'Indirizzo', 'type' => 'textarea', 'rows' => 3,
					'desc' => 'La mappa si aggiorna da sola da questo indirizzo. Gli invii a capo restano come li scrivi.' ),
				array( 'key' => 'tmk_location_neighbourhood_note', 'label' => 'Nota sul quartiere', 'type' => 'textarea', 'rows' => 3,
					'desc' => 'Compare sotto l’indirizzo. Orienta chi arriva (“a due passi da…”), non ripete il racconto della sala.' ),
				array( 'key' => 'phone', 'label' => 'Telefono', 'type' => 'tel',
					'desc' => 'È il numero che viene composto al tocco. Solo cifre e prefisso.' ),
				array( 'key' => 'phone_display', 'label' => 'Telefono — come si legge', 'type' => 'text',
					'desc' => 'Opzionale. Se vuoto si mostra il numero qui sopra così com’è.' ),
				array( 'key' => 'email', 'label' => 'Email', 'type' => 'email' ),
				array( 'key' => 'accessibility_notes', 'label' => 'Note di accessibilità', 'type' => 'textarea', 'rows' => 3 ),
			),
		),
		array(
			'id' => 'tmk_loc_map', 'title' => 'Ristorante — mappa', 'context' => 'normal',
			'fields' => array(
				array( 'key' => 'map_url', 'label' => 'Link “Apri in Google Maps”', 'type' => 'url',
					'desc' => 'Opzionale. Se hai la scheda Google del locale, incollala qui: diventa il pulsante sotto la mappa. Se è vuoto, il pulsante non compare e la mappa resta.' ),
				array( 'key' => 'map_coords', 'label' => 'Coordinate (lat,lng)', 'type' => 'text',
					'desc' => 'Opzionale, solo se le hai verificate. Con le coordinate la mappa punta esattamente sul locale; senza, cerca l’indirizzo scritto sopra. Non inventarle: meglio vuoto.' ),
				array( 'type' => 'note', 'label' => 'Mappa attuale', 'render' => 'tmk_admin_note_location_map' ),
			),
		),
		array(
			'id' => 'tmk_loc_booking', 'title' => 'Ristorante — prenotazione', 'context' => 'side',
			'fields' => array(
				array( 'key' => 'tmk_location_booking_page_id', 'label' => 'Pagina di prenotazione', 'type' => 'post',
					'post_type' => 'page', 'name_like' => 'prenota', 'none' => '— Usa la pagina generale —',
					'desc' => 'La pagina che si apre premendo “Prenota” nella fascia “Il tuo tavolo ti aspetta”. Il collegamento segue la pagina anche se ne cambi lo slug.' ),
				array( 'type' => 'note', 'label' => 'Dove porta “Prenota” adesso', 'render' => 'tmk_admin_note_location_booking' ),
			),
		),
		/*
		 * STATO DELLA SEDE — l'unico campo del sito che decide se un ristorante compare o no.
		 * Aggiunto il 2026-09-07 perche' non esisteva: prima di questo, l'unico modo di togliere
		 * una sede dal sito era metterla in bozza o cancellarla, cioe' far sparire anche la sua
		 * pagina, le sue foto e il suo indirizzo web.
		 */
		array(
			'id' => 'tmk_loc_status', 'title' => 'Ristorante — stato', 'context' => 'side',
			'fields' => array(
				array( 'key' => 'tmk_location_closing_date', 'label' => 'Ultimo giorno di servizio', 'type' => 'date',
					'desc' => 'Lascialo vuoto se il ristorante è aperto. Se lo compili, la sede resta visibile per tutto quel giorno e dal giorno dopo sparisce dagli elenchi — pagina Ristoranti, pagina della città, “Altri ristoranti Temakinho” e bande della home. La scheda, le foto e gli orari restano dove sono. Il riquadro “Stato attuale” qui sotto dice sempre com’è messa la sede in questo momento.' ),
				array( 'type' => 'note', 'label' => 'Stato attuale', 'render' => 'tmk_admin_note_location_status' ),
			),
		),
	);

	/* -------------------------------------------------------------------------- MENU (menu_item) */
	$schema['menu_item'] = array(
		array(
			'id' => 'tmk_dish_it', 'title' => 'Piatto — italiano', 'context' => 'normal',
			'fields' => array(
				array( 'key' => 'price', 'label' => 'Prezzo', 'type' => 'text', 'desc' => 'Come va stampato. Es. “€ 9,50”.' ),
				array( 'key' => 'short_description', 'label' => 'Descrizione', 'type' => 'textarea', 'rows' => 3 ),
				array( 'key' => 'sort_order', 'label' => 'Ordine dentro la categoria', 'type' => 'number',
					'desc' => 'Numero più basso = più in alto. Un menu è una sequenza, non un elenco alfabetico.' ),
				array( 'key' => 'labels', 'label' => 'Etichette', 'type' => 'checklist', 'options' => 'tmk_menu_item_labels' ),
				array( 'key' => 'channel', 'label' => 'Canale', 'type' => 'select',
					'options' => array( '' => '— Tutti —', 'dine-in' => 'Solo in sala', 'delivery' => 'Solo delivery', 'both' => 'Sala e delivery' ) ),
			),
		),
		array(
			'id' => 'tmk_dish_allergens', 'title' => 'Piatto — allergeni', 'context' => 'normal',
			'fields' => array(
				array( 'key' => 'allergens', 'label' => 'Allergeni presenti', 'type' => 'checklist', 'options' => 'tmk_eu_allergens', 'cols' => 2,
					'desc' => 'I 14 allergeni dell’Allegato II del Reg. (UE) 1169/2011. La numerazione e l’ordine sono fissati dalla legge e non si possono cambiare: qui scegli soltanto quali sono presenti in questo piatto.' ),
				array( 'key' => 'allergens_en', 'label' => 'Allergeni — versione inglese del menu', 'type' => 'checklist', 'options' => 'tmk_eu_allergens', 'cols' => 2,
					'desc' => 'Da compilare solo se la carta inglese pubblicata dichiara allergeni diversi da quella italiana. Se lo lasci vuoto, la carta inglese non mostra numeri.' ),
			),
		),
		array(
			'id' => 'tmk_dish_en', 'title' => 'Piatto — English', 'context' => 'normal',
			'fields' => array(
				array( 'key' => 'title_en', 'label' => 'Name (EN)', 'type' => 'text' ),
				array( 'key' => 'short_description_en', 'label' => 'Description (EN)', 'type' => 'textarea', 'rows' => 3 ),
			),
		),
	);

	/* ------------------------------------------------------------------------------ EVENTI (event) */
	$schema['event'] = array(
		array(
			'id' => 'tmk_ev_when', 'title' => 'Evento — quando e dove', 'context' => 'normal',
			'fields' => array(
				array( 'key' => 'event_kind', 'label' => 'Tipologia', 'type' => 'text',
					'desc' => 'Es. “Degustazione”, “Cena a quattro mani”, “Serata speciale”. Compare come occhiello sopra il titolo.' ),
				array( 'key' => 'event_start', 'label' => 'Inizio', 'type' => 'datetime',
					'desc' => 'Data e ora. È anche il criterio con cui il calendario ordina gli eventi.' ),
				array( 'key' => 'tmk_event_end', 'label' => 'Fine', 'type' => 'datetime', 'desc' => 'Opzionale.' ),
				array( 'key' => 'tmk_event_location_id', 'label' => 'Sede Temakinho', 'type' => 'post', 'post_type' => 'location',
					'none' => '— Non è in una nostra sede —',
					'desc' => 'Se scegli una sede, luogo, indirizzo e mappa si compilano da soli con i dati di quel ristorante.' ),
				array( 'key' => 'event_venue', 'label' => 'Luogo (solo se non è in una nostra sede)', 'type' => 'text' ),
				array( 'key' => 'event_address', 'label' => 'Indirizzo (solo se non è in una nostra sede)', 'type' => 'textarea', 'rows' => 3,
					'desc' => 'La mappa si genera da questo indirizzo.' ),
				array( 'key' => 'event_accessibility', 'label' => 'Accessibilità', 'type' => 'textarea', 'rows' => 3 ),
				array( 'type' => 'note', 'label' => 'Cosa vedrà chi apre la pagina', 'render' => 'tmk_admin_note_event_where' ),
			),
		),
		array(
			'id' => 'tmk_ev_info', 'title' => 'Evento — informazioni importanti', 'context' => 'normal',
			'fields' => array(
				array( 'key' => 'event_formula', 'label' => 'Formula / menu', 'type' => 'text', 'desc' => 'Es. “Menu degustazione 6 portate”.' ),
				array( 'key' => 'tmk_event_price', 'label' => 'Prezzo', 'type' => 'text', 'desc' => 'Come va scritto: “€ 65 a persona”, oppure “Su richiesta”.' ),
				array( 'key' => 'tmk_event_capacity', 'label' => 'Disponibilità / posti', 'type' => 'text', 'desc' => 'Es. “Posti limitati”, “30 coperti”, “Un solo turno”.' ),
				array( 'key' => 'tmk_event_notes', 'label' => 'Note importanti', 'type' => 'textarea', 'rows' => 3 ),
			),
		),
		array(
			'id' => 'tmk_ev_gallery', 'title' => 'Evento — galleria', 'context' => 'normal',
			'fields' => array(
				array( 'key' => 'event_gallery_ids', 'label' => 'Immagini', 'type' => 'gallery',
					'desc' => 'Opzionale. Se non ci sono immagini reali, lascia vuoto: la sezione non compare.' ),
			),
		),
		array(
			'id' => 'tmk_ev_join', 'title' => 'Evento — partecipazione', 'context' => 'side',
			'fields' => array(
				array( 'key' => 'tmk_event_booking_page_id', 'label' => 'Pagina di prenotazione', 'type' => 'post',
					'post_type' => 'page', 'name_like' => 'prenota', 'none' => '— Nessuna —' ),
				array( 'key' => 'booking_url', 'label' => 'oppure un indirizzo esterno', 'type' => 'url',
					'desc' => 'Usato solo se non hai scelto una pagina qui sopra.' ),
				array( 'key' => 'tmk_event_cta_label', 'label' => 'Testo del pulsante', 'type' => 'text', 'desc' => 'Se vuoto: “Prenota”.' ),
				array( 'key' => 'tmk_event_contact', 'label' => 'Contatto alternativo', 'type' => 'text',
					'desc' => 'Opzionale. Un numero o una email per chi preferisce scrivere. Compare come riga sotto il pulsante.' ),
				array( 'type' => 'note', 'label' => 'Stato del blocco partecipazione', 'render' => 'tmk_admin_note_event_join' ),
			),
		),
	);

	/* --------------------------------------------------------------------- LAVORA CON NOI (job) */
	$schema['job'] = array(
		array(
			'id' => 'tmk_job_where', 'title' => 'Posizione — dove', 'context' => 'normal',
			'fields' => array(
				array( 'key' => 'location_id', 'label' => 'Ristorante', 'type' => 'post', 'post_type' => 'location',
					'none' => '— Nessuno / Head Quarter —',
					'desc' => 'Se scegli un ristorante, la sede mostrata si compila da sola.' ),
				array( 'key' => 'job_where', 'label' => 'Sede mostrata (testo libero)', 'type' => 'text',
					'desc' => 'Da usare solo quando non c’è un ristorante da collegare. Es. “Milano — Head Quarter”.' ),
				array( 'key' => 'employment_type', 'label' => 'Tipo di contratto', 'type' => 'select',
					'options' => array( '' => '—', 'Full-time' => 'Full-time', 'Part-time' => 'Part-time', 'Stagionale' => 'Stagionale', 'Apprendistato' => 'Apprendistato', 'Tirocinio' => 'Tirocinio' ) ),
				array( 'type' => 'note', 'label' => 'Occhiello che si vedrà', 'render' => 'tmk_admin_note_job_kicker' ),
			),
		),
		array(
			'id' => 'tmk_job_role', 'title' => 'Posizione — il ruolo', 'context' => 'normal',
			'fields' => array(
				array( 'key' => 'tmk_job_responsibilities', 'label' => 'Responsabilità', 'type' => 'textarea', 'rows' => 6,
					'desc' => 'Una per riga. Diventano un elenco puntato. Se vuoto, la sezione non compare.' ),
				array( 'key' => 'requirements', 'label' => 'Requisiti', 'type' => 'textarea', 'rows' => 6,
					'desc' => 'Uno per riga.' ),
				array( 'key' => 'tmk_job_offer', 'label' => 'Cosa offriamo', 'type' => 'textarea', 'rows' => 6,
					'desc' => 'Uno per riga.' ),
			),
		),
		array(
			'id' => 'tmk_job_apply', 'title' => 'Posizione — candidature', 'context' => 'side',
			'fields' => array(
				array( 'key' => 'application_method', 'label' => 'Come si candidano', 'type' => 'select',
					'options' => array( 'native' => 'Con il form del sito', 'external' => 'Su un portale esterno' ) ),
				array( 'key' => 'application_url', 'label' => 'Link al portale esterno', 'type' => 'url',
					'desc' => 'Serve solo se hai scelto “portale esterno”.' ),
				array( 'key' => 'tmk_job_deadline', 'label' => 'Scadenza candidatura', 'type' => 'date', 'desc' => 'Opzionale.' ),
				array( 'type' => 'note', 'label' => 'Candidature ricevute', 'render' => 'tmk_admin_note_job_applications' ),
			),
		),
	);

	/* --------------------------------------------------------------------------- STORIE (post) */
	$schema['post'] = array(
		array(
			'id' => 'tmk_story', 'title' => 'Storia — dati editoriali', 'context' => 'side',
			'fields' => array(
				array( 'key' => 'byline', 'label' => 'Firma', 'type' => 'text',
					'desc' => 'Chi ha scritto il pezzo. Se vuoto si usa l’autore WordPress, e se manca anche quello “Redazione Temakinho”.' ),
				array( 'key' => 'related_city', 'label' => 'Città collegata', 'type' => 'term', 'taxonomy' => 'city', 'none' => '— Nessuna —',
					'desc' => 'Alimenta il blocco “Dove vivere questa storia” in fondo all’articolo.' ),
				array( 'key' => 'related_location_id', 'label' => 'Ristorante collegato', 'type' => 'post', 'post_type' => 'location', 'none' => '— Nessuno —' ),
			),
		),
	);

	/**
	 * Filter so a future field can be added without editing this file.
	 * @param array $schema
	 */
	return apply_filters( 'tmk_admin_field_schema', $schema );
}

/* ================================================================================================
   METABOX REGISTRATION + RENDERING
   ============================================================================================== */

add_action( 'add_meta_boxes', 'tmk_admin_register_metaboxes' );
function tmk_admin_register_metaboxes() {
	foreach ( tmk_admin_field_schema() as $post_type => $panels ) {
		foreach ( $panels as $panel ) {
			add_meta_box(
				$panel['id'],
				$panel['title'],
				'tmk_admin_render_metabox',
				$post_type,
				isset( $panel['context'] ) ? $panel['context'] : 'normal',
				'default',
				array( 'panel' => $panel )
			);
		}
	}
}

function tmk_admin_render_metabox( $post, $box ) {
	$panel = $box['args']['panel'];
	wp_nonce_field( 'tmk_admin_fields_' . $post->ID, 'tmk_admin_fields_nonce' );
	echo '<div class="tmk-af">';
	foreach ( $panel['fields'] as $field ) {
		tmk_admin_render_field( $post, $field );
	}
	echo '</div>';
}

/** Resolve an 'options' entry: a literal map, or the name of a function returning one. */
function tmk_admin_options( $field ) {
	$o = isset( $field['options'] ) ? $field['options'] : array();
	if ( is_string( $o ) && function_exists( $o ) ) { $o = call_user_func( $o ); }
	return is_array( $o ) ? $o : array();
}

function tmk_admin_render_field( $post, $f ) {
	$type = isset( $f['type'] ) ? $f['type'] : 'text';

	// A note is read-only feedback: it renders whatever its callback returns and stores nothing.
	if ( 'note' === $type ) {
		$body = ( isset( $f['render'] ) && function_exists( $f['render'] ) ) ? call_user_func( $f['render'], $post ) : '';
		if ( '' === trim( (string) $body ) ) { return; }
		echo '<div class="tmk-af__row tmk-af__row--note"><p class="tmk-af__label">' . esc_html( $f['label'] ) . '</p><div class="tmk-af__note">' . $body . '</div></div>';
		return;
	}

	$key   = $f['key'];
	$name  = 'tmk_af[' . $key . ']';
	$id    = 'tmk-af-' . $key;
	$value = get_post_meta( $post->ID, $key, true );
	$desc  = isset( $f['desc'] ) ? '<p class="tmk-af__desc">' . wp_kses_post( $f['desc'] ) . '</p>' : '';

	echo '<div class="tmk-af__row tmk-af__row--' . esc_attr( $type ) . '">';
	if ( 'bool' !== $type ) {
		echo '<label class="tmk-af__label" for="' . esc_attr( $id ) . '">' . esc_html( $f['label'] ) . '</label>';
	}

	switch ( $type ) {

		case 'textarea':
			printf( '<textarea class="tmk-af__input" id="%s" name="%s" rows="%d">%s</textarea>',
				esc_attr( $id ), esc_attr( $name ), (int) ( $f['rows'] ?? 4 ), esc_textarea( (string) $value ) );
			break;

		case 'number':
			printf( '<input class="tmk-af__input tmk-af__input--sm" type="number" id="%s" name="%s" value="%s" />',
				esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ) );
			break;

		case 'select':
			echo '<select class="tmk-af__input" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
			foreach ( tmk_admin_options( $f ) as $k => $lab ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( (string) $value, (string) $k, false ), esc_html( $lab ) );
			}
			echo '</select>';
			break;

		case 'bool':
			printf( '<label class="tmk-af__check"><input type="checkbox" id="%s" name="%s" value="1"%s /> <span>%s</span></label>',
				esc_attr( $id ), esc_attr( $name ), checked( (bool) $value, true, false ),
				esc_html( $f['cb'] ?? $f['label'] ) );
			break;

		case 'checklist':
			// Stored as a comma-separated string of keys, which is the shape the renderers parse.
			$chosen = array_filter( array_map( 'trim', explode( ',', (string) $value ) ), 'strlen' );
			$cols   = (int) ( $f['cols'] ?? 1 );
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="" />';
			echo '<ul class="tmk-af__checklist" style="--tmk-af-cols:' . max( 1, $cols ) . '">';
			foreach ( tmk_admin_options( $f ) as $k => $lab ) {
				printf( '<li><label><input type="checkbox" name="%s[]" value="%s"%s /> <span>%s%s</span></label></li>',
					esc_attr( $name ), esc_attr( $k ),
					checked( in_array( (string) $k, $chosen, true ), true, false ),
					is_int( $k ) ? '<b>' . (int) $k . '</b> ' : '', esc_html( $lab ) );
			}
			echo '</ul>';
			break;

		case 'media':
			$att = (int) $value;
			$src = $att ? wp_get_attachment_image_url( $att, 'thumbnail' ) : '';
			echo '<div class="tmk-af__media" data-tmk-media>';
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $att ) . '" data-tmk-media-value />';
			echo '<div class="tmk-af__media-preview" data-tmk-media-preview>' . ( $src ? '<img src="' . esc_url( $src ) . '" alt="" />' : '' ) . '</div>';
			echo '<p><button type="button" class="button" data-tmk-media-pick>Scegli immagine</button> ';
			echo '<button type="button" class="button-link tmk-af__danger" data-tmk-media-clear' . ( $att ? '' : ' hidden' ) . '>Rimuovi</button></p>';
			echo '</div>';
			break;

		case 'gallery':
			$ids = array_values( array_filter( array_map( 'intval', (array) $value ) ) );
			echo '<div class="tmk-af__gallery" data-tmk-gallery>';
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( implode( ',', $ids ) ) . '" data-tmk-gallery-value />';
			echo '<ul class="tmk-af__gallery-list" data-tmk-gallery-list>';
			foreach ( $ids as $aid ) {
				$src = wp_get_attachment_image_url( $aid, 'thumbnail' );
				if ( ! $src ) { continue; }
				printf( '<li data-id="%d"><img src="%s" alt="" /><button type="button" class="tmk-af__x" data-tmk-gallery-remove aria-label="Togli questa foto">&times;</button></li>', $aid, esc_url( $src ) );
			}
			echo '</ul>';
			echo '<p><button type="button" class="button" data-tmk-gallery-add>Aggiungi foto</button> <span class="tmk-af__count" data-tmk-gallery-count>' . count( $ids ) . ' foto</span></p>';
			echo '</div>';
			break;

		case 'rows':
			$rows = tmk_location_hours_rows( $value ); // same normaliser the renderer uses
			$lab  = $f['row_labels'] ?? array( 'Etichetta', 'Valore' );
			echo '<div class="tmk-af__rows" data-tmk-rows data-name="' . esc_attr( $name ) . '">';
			echo '<div class="tmk-af__rows-head"><span>' . esc_html( $lab[0] ) . '</span><span>' . esc_html( $lab[1] ) . '</span><span></span></div>';
			echo '<ul class="tmk-af__rows-list" data-tmk-rows-list>';
			foreach ( $rows as $r ) {
				printf( '<li class="tmk-af__rowitem"><span class="tmk-af__grip" aria-hidden="true">⋮⋮</span><input type="text" name="%s[label][]" value="%s" /><input type="text" name="%s[value][]" value="%s" /><button type="button" class="tmk-af__x" data-tmk-rows-remove aria-label="Togli questa riga">&times;</button></li>',
					esc_attr( $name ), esc_attr( $r['label'] ), esc_attr( $name ), esc_attr( $r['value'] ) );
			}
			echo '</ul>';
			echo '<p><button type="button" class="button" data-tmk-rows-add>Aggiungi riga</button></p>';
			// An always-present empty pair guarantees the key is submitted even when the editor
			// deletes every row, so "no rows" can actually be saved.
			echo '<input type="hidden" name="' . esc_attr( $name ) . '[submitted]" value="1" />';
			echo '</div>';
			break;

		case 'post':
			$args = array(
				'post_type'      => $f['post_type'] ?? 'page',
				'posts_per_page' => -1,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'orderby'        => 'title',
				'order'          => 'ASC',
			);
			$items = get_posts( $args );
			if ( ! empty( $f['name_like'] ) ) {
				$like  = $f['name_like'];
				$items = array_values( array_filter( $items, function ( $p ) use ( $like ) { return 0 === strpos( $p->post_name, $like ); } ) );
			}
			echo '<select class="tmk-af__input" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
			printf( '<option value="">%s</option>', esc_html( $f['none'] ?? '— Nessuno —' ) );
			foreach ( $items as $p ) {
				printf( '<option value="%d"%s>%s%s</option>', $p->ID, selected( (int) $value, $p->ID, false ),
					esc_html( $p->post_title ), 'publish' === $p->post_status ? '' : ' (' . esc_html( $p->post_status ) . ')' );
			}
			echo '</select>';
			break;

		case 'term':
			$terms = get_terms( array( 'taxonomy' => $f['taxonomy'], 'hide_empty' => false ) );
			echo '<select class="tmk-af__input" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
			printf( '<option value="">%s</option>', esc_html( $f['none'] ?? '— Nessuna —' ) );
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $t ) {
					printf( '<option value="%s"%s>%s</option>', esc_attr( $t->slug ), selected( (string) $value, $t->slug, false ), esc_html( $t->name ) );
				}
			}
			echo '</select>';
			break;

		case 'datetime':
		case 'date':
			// Stored as 'Y-m-d H:i:s' (event_start's existing shape) but edited as the browser's
			// own native picker, so there is no format for the client to remember.
			$ts   = $value ? strtotime( (string) $value ) : 0;
			$html = 'datetime' === $type
				? ( $ts ? gmdate( 'Y-m-d\TH:i', $ts ) : '' )
				: ( $ts ? gmdate( 'Y-m-d', $ts ) : '' );
			printf( '<input class="tmk-af__input tmk-af__input--sm" type="%s" id="%s" name="%s" value="%s" />',
				'datetime' === $type ? 'datetime-local' : 'date', esc_attr( $id ), esc_attr( $name ), esc_attr( $html ) );
			break;

		default: // text | url | email | tel
			$html_type = in_array( $type, array( 'url', 'email', 'tel' ), true ) ? $type : 'text';
			printf( '<input class="tmk-af__input" type="%s" id="%s" name="%s" value="%s" />',
				esc_attr( $html_type ), esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ) );
	}

	echo $desc . '</div>';
}

/* ================================================================================================
   SAVE
   ============================================================================================== */

add_action( 'save_post', 'tmk_admin_save_fields', 10, 2 );
function tmk_admin_save_fields( $post_id, $post ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
	if ( wp_is_post_revision( $post_id ) ) { return; }
	$schema = tmk_admin_field_schema();
	if ( ! isset( $schema[ $post->post_type ] ) ) { return; }
	if ( ! isset( $_POST['tmk_admin_fields_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tmk_admin_fields_nonce'] ) ), 'tmk_admin_fields_' . $post_id ) ) { return; }
	if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }

	$in = isset( $_POST['tmk_af'] ) && is_array( $_POST['tmk_af'] ) ? wp_unslash( $_POST['tmk_af'] ) : array();

	foreach ( $schema[ $post->post_type ] as $panel ) {
		foreach ( $panel['fields'] as $f ) {
			$type = $f['type'] ?? 'text';
			if ( 'note' === $type ) { continue; }
			$key = $f['key'];
			$raw = $in[ $key ] ?? null;

			switch ( $type ) {

				case 'bool':
					// An unchecked checkbox submits nothing, so absence means false — never "leave
					// as it was", which would make the box impossible to untick.
					update_post_meta( $post_id, $key, $raw ? '1' : '' );
					break;

				case 'checklist':
					$vals = is_array( $raw ) ? $raw : array();
					$ok   = array_keys( tmk_admin_options( $f ) );
					$vals = array_values( array_filter( array_map( 'strval', $vals ), function ( $v ) use ( $ok ) {
						return in_array( $v, array_map( 'strval', $ok ), true );
					} ) );
					// Preserve the declared (legal) order, not the browser's submission order.
					$sorted = array();
					foreach ( $ok as $k ) { if ( in_array( (string) $k, $vals, true ) ) { $sorted[] = (string) $k; } }
					update_post_meta( $post_id, $key, implode( ',', $sorted ) );
					break;

				case 'gallery':
					$ids = array_values( array_filter( array_map( 'intval', explode( ',', (string) $raw ) ) ) );
					// Only real attachments, so a stale or mistyped ID can never reach a renderer.
					$ids = array_values( array_filter( $ids, function ( $i ) { return 'attachment' === get_post_type( $i ); } ) );
					update_post_meta( $post_id, $key, $ids );
					break;

				case 'media':
					$att = (int) $raw;
					update_post_meta( $post_id, $key, ( $att && 'attachment' === get_post_type( $att ) ) ? $att : '' );
					break;

				case 'rows':
					if ( ! is_array( $raw ) || empty( $raw['submitted'] ) ) { break; }
					$labels = (array) ( $raw['label'] ?? array() );
					$values = (array) ( $raw['value'] ?? array() );
					$rows   = array();
					foreach ( $labels as $i => $l ) {
						$l = sanitize_text_field( (string) $l );
						$v = sanitize_text_field( (string) ( $values[ $i ] ?? '' ) );
						if ( '' === $l && '' === $v ) { continue; }
						$rows[] = array( 'label' => $l, 'value' => $v );
					}
					update_post_meta( $post_id, $key, $rows );
					break;

				case 'post':
					$pid = (int) $raw;
					update_post_meta( $post_id, $key, $pid > 0 ? $pid : '' );
					break;

				case 'number':
					update_post_meta( $post_id, $key, '' === trim( (string) $raw ) ? '' : (int) $raw );
					break;

				case 'datetime':
					$ts = $raw ? strtotime( (string) $raw ) : 0;
					update_post_meta( $post_id, $key, $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : '' );
					break;

				case 'date':
					$ts = $raw ? strtotime( (string) $raw ) : 0;
					update_post_meta( $post_id, $key, $ts ? gmdate( 'Y-m-d', $ts ) : '' );
					break;

				case 'url':
					$u = trim( (string) $raw );
					update_post_meta( $post_id, $key, '' === $u ? '' : esc_url_raw( $u ) );
					break;

				case 'email':
					$e = trim( (string) $raw );
					update_post_meta( $post_id, $key, is_email( $e ) ? sanitize_email( $e ) : '' );
					break;

				case 'select':
					$ok = array_map( 'strval', array_keys( tmk_admin_options( $f ) ) );
					$v  = (string) $raw;
					update_post_meta( $post_id, $key, in_array( $v, $ok, true ) ? $v : '' );
					break;

				case 'term':
					$slug = sanitize_title( (string) $raw );
					$ok   = $slug ? term_exists( $slug, $f['taxonomy'] ) : false;
					update_post_meta( $post_id, $key, $ok ? $slug : '' );
					break;

				case 'textarea':
					// Line breaks are content here (one requirement per line, paragraphs in the
					// atmosphere copy), so newlines survive and only tags are stripped.
					update_post_meta( $post_id, $key, sanitize_textarea_field( (string) $raw ) );
					break;

				default:
					update_post_meta( $post_id, $key, sanitize_text_field( (string) $raw ) );
			}
		}
	}
}

/* ================================================================================================
   READ-ONLY NOTES — so the editor can see the CONSEQUENCE of what they typed without publishing.
   Every one of these reads only stored data; none of them writes anything.
   ============================================================================================== */

function tmk_admin_note_location_map( $post ) {
	$coords  = trim( (string) get_post_meta( $post->ID, 'map_coords', true ) );
	$address = trim( (string) get_post_meta( $post->ID, 'address', true ) );
	$map_url = trim( (string) get_post_meta( $post->ID, 'map_url', true ) );
	if ( $coords && preg_match( '/^\s*-?\d{1,3}(\.\d+)?\s*,\s*-?\d{1,3}(\.\d+)?\s*$/', $coords ) ) {
		$out = 'Punta sulle <b>coordinate</b> verificate.';
	} elseif ( $address ) {
		$out = 'Cerca su Google l’<b>indirizzo</b> scritto sopra. Cambiando l’indirizzo, cambia la mappa.';
	} else {
		$out = '<b>Nessuna mappa.</b> Aggiungi un indirizzo (o delle coordinate) e comparirà.';
	}
	$out .= $map_url ? ' Il pulsante “Apri in Google Maps” è attivo.' : ' Il pulsante “Apri in Google Maps” non compare (manca il link).';
	return $out;
}

function tmk_admin_note_location_booking( $post ) {
	$page = (int) get_post_meta( $post->ID, 'tmk_location_booking_page_id', true );
	if ( $page && 'publish' === get_post_status( $page ) ) {
		return 'Alla pagina <b>' . esc_html( get_the_title( $page ) ) . '</b> — <code>' . esc_html( get_permalink( $page ) ) . '</code>';
	}
	if ( $page ) {
		return 'La pagina scelta (<b>' . esc_html( get_the_title( $page ) ) . '</b>) <b>non è pubblicata</b>: fino a quel momento “Prenota” usa la destinazione generale.';
	}
	$legacy = trim( (string) get_post_meta( $post->ID, 'booking_url', true ) );
	if ( $legacy ) {
		return 'All’indirizzo salvato in precedenza: <code>' . esc_html( $legacy ) . '</code>';
	}
	return 'Alla destinazione generale del sito: <code>' . esc_html( defined( 'TMK_BOOKING_URL' ) ? TMK_BOOKING_URL : home_url( '/' ) ) . '</code>';
}

/**
 * Stato pubblico della sede, detto in chiaro nella scheda: e' l'unico posto dove un redattore puo'
 * accorgersi che un ristorante non compare piu' negli elenchi, e perche'.
 *
 * Distingue le due origini possibili della data — il campo qui sopra, oppure la mappa delle
 * chiusure gia' decise nel plugin (tmk_location_closing_dates) — perche' si annullano in due modi
 * diversi, e non dirlo lascerebbe qualcuno a svuotare un campo gia' vuoto.
 */
function tmk_admin_note_location_status( $post ) {
	$closing = tmk_location_closing_date( $post->ID );
	if ( '' === $closing ) {
		return 'La sede <b>compare</b> in tutti gli elenchi del sito.';
	}

	$stored   = '' !== trim( (string) get_post_meta( $post->ID, 'tmk_location_closing_date', true ) );
	$ts       = strtotime( $closing );
	$readable = $ts ? wp_date( 'j F Y', $ts ) : $closing;

	$out = tmk_location_is_active( $post->ID )
		? 'Ultimo giorno di servizio: <b>' . esc_html( $readable ) . '</b>. Fino a tutto quel giorno la sede compare normalmente; dal giorno dopo <b>sparisce dagli elenchi</b>.'
		: 'Servizio concluso il <b>' . esc_html( $readable ) . '</b>: la sede <b>non compare più negli elenchi</b>. La scheda, le foto e il suo indirizzo web restano attivi.';

	$out .= $stored
		? '<br />Data impostata in questo campo: per rimettere la sede in elenco, svuotalo.'
		: '<br />Questa data <b>non</b> arriva dal campo qui sopra, ma da una chiusura già concordata e scritta nel plugin (<code>tmk_location_closing_dates()</code> in <code>inc/content-model.php</code>). Scrivere una data nel campo la sostituisce; per annullare del tutto la chiusura serve invece un intervento sul plugin.';

	return $out;
}

function tmk_admin_note_event_where( $post ) {
	$f = tmk_event_place( $post->ID );
	if ( ! $f['venue'] && ! $f['address'] ) {
		return 'Nessun luogo indicato: la pagina non mostrerà né sede né mappa.';
	}
	$out = array();
	if ( $f['venue'] )   { $out[] = 'Luogo: <b>' . esc_html( $f['venue'] ) . '</b>' . ( $f['from_location'] ? ' <i>(dalla sede collegata)</i>' : '' ); }
	if ( $f['address'] ) { $out[] = 'Indirizzo: ' . esc_html( $f['address'] ) . ( $f['from_location'] ? ' <i>(dalla sede collegata)</i>' : '' ); }
	$out[] = $f['address'] ? 'La mappa si genera da questo indirizzo.' : 'Senza indirizzo la mappa non compare.';
	return implode( '<br />', $out );
}

function tmk_admin_note_event_join( $post ) {
	$href = tmk_event_join_href( $post->ID );
	if ( ! $href ) {
		$c = trim( (string) get_post_meta( $post->ID, 'tmk_event_contact', true ) );
		return $c ? 'Nessun pulsante: comparirà solo il contatto alternativo.' : 'Nessun pulsante e nessun contatto: il blocco non comparirà.';
	}
	$label = trim( (string) get_post_meta( $post->ID, 'tmk_event_cta_label', true ) );
	return 'Pulsante “<b>' . esc_html( $label ?: 'Prenota' ) . '</b>” verso <code>' . esc_html( $href ) . '</code>';
}

function tmk_admin_note_job_kicker( $post ) {
	$k = function_exists( 'tmk_job_kicker' ) ? tmk_job_kicker( $post ) : '';
	return $k ? '<code>' . esc_html( $k ) . '</code>' : 'Vuoto: aggiungi categoria, reparto, sede o contratto.';
}

function tmk_admin_note_job_applications( $post ) {
	$n = get_posts( array(
		'post_type'      => 'job_application',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_key'       => 'tmk_application_job_id',
		'meta_value'     => $post->ID,
	) );
	$c = count( $n );
	if ( ! $c ) { return 'Ancora nessuna candidatura per questa posizione.'; }
	$url = admin_url( 'edit.php?post_type=job_application' );
	return '<b>' . (int) $c . '</b> ' . ( 1 === $c ? 'candidatura' : 'candidature' ) . ' — <a href="' . esc_url( $url ) . '">apri l’elenco</a>. Mettere la posizione in bozza non le cancella.';
}

/* ================================================================================================
   CITY — one city per restaurant, chosen from a list, with a controlled way to add a new one.
   -----------------------------------------------------------------------------------------------
   `city` is registered non-hierarchical, so WordPress renders the free-text "tag" box for it: an
   editor could type "milano", "Milano " or "MIlano" and silently create a second city, and the
   /ristoranti/{citta}/ URL of every restaurant in it would change. Making the taxonomy
   hierarchical would give a checkbox list for free but also changes how WordPress builds term
   links for it, which is load-bearing for those URLs. So the metabox is replaced instead: a radio
   list of the cities that exist plus one explicit "nuova citta'" field.
   ============================================================================================== */

add_action( 'add_meta_boxes', 'tmk_admin_city_metabox', 20 );
function tmk_admin_city_metabox() {
	remove_meta_box( 'tagsdiv-city', 'location', 'side' );
	remove_meta_box( 'citydiv', 'location', 'side' );
	add_meta_box( 'tmk_city_box', 'Città', 'tmk_admin_render_city_box', 'location', 'side', 'high' );
}

function tmk_admin_render_city_box( $post ) {
	wp_nonce_field( 'tmk_city_' . $post->ID, 'tmk_city_nonce' );
	$terms   = get_terms( array( 'taxonomy' => 'city', 'hide_empty' => false, 'orderby' => 'name' ) );
	$current = wp_get_post_terms( $post->ID, 'city', array( 'fields' => 'ids' ) );
	$current = ( ! is_wp_error( $current ) && $current ) ? (int) $current[0] : 0;
	echo '<div class="tmk-af tmk-af--city"><ul class="tmk-af__radios">';
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $t ) {
			printf( '<li><label><input type="radio" name="tmk_city_term" value="%d"%s /> <span>%s</span></label></li>',
				$t->term_id, checked( $current, $t->term_id, false ), esc_html( $t->name ) );
		}
	}
	printf( '<li><label><input type="radio" name="tmk_city_term" value="0"%s /> <span><i>Nessuna città</i></span></label></li>', checked( $current, 0, false ) );
	echo '</ul>';
	if ( current_user_can( 'manage_categories' ) ) {
		echo '<p class="tmk-af__label" style="margin-top:10px">Nuova città</p>';
		echo '<input class="tmk-af__input" type="text" name="tmk_city_new" value="" placeholder="Es. Firenze" />';
		echo '<p class="tmk-af__desc">Scrivi il nome solo se la città non è nell’elenco. Verrà creata e assegnata a questo ristorante al salvataggio.</p>';
	}
	echo '</div>';
}

add_action( 'save_post_location', 'tmk_admin_save_city', 10, 2 );
function tmk_admin_save_city( $post_id, $post ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
	if ( ! isset( $_POST['tmk_city_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tmk_city_nonce'] ) ), 'tmk_city_' . $post_id ) ) { return; }
	if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }

	$new = isset( $_POST['tmk_city_new'] ) ? sanitize_text_field( wp_unslash( $_POST['tmk_city_new'] ) ) : '';
	if ( '' !== $new && current_user_can( 'manage_categories' ) ) {
		$existing = term_exists( $new, 'city' );
		if ( $existing ) {
			$term_id = (int) ( is_array( $existing ) ? $existing['term_id'] : $existing );
		} else {
			$made    = wp_insert_term( $new, 'city' );
			$term_id = is_wp_error( $made ) ? 0 : (int) $made['term_id'];
		}
		if ( $term_id ) { wp_set_post_terms( $post_id, array( $term_id ), 'city', false ); return; }
	}
	$term_id = isset( $_POST['tmk_city_term'] ) ? (int) $_POST['tmk_city_term'] : 0;
	wp_set_post_terms( $post_id, $term_id ? array( $term_id ) : array(), 'city', false );
}

/* ================================================================================================
   MENU CATEGORY term fields — the published sequence and the English name.
   Categories themselves (create / rename / nest) stay on WordPress's own taxonomy screen; these
   two fields are what the front end additionally needs and had no UI at all.
   ============================================================================================== */

add_action( 'menu_category_add_form_fields', 'tmk_admin_menu_cat_add' );
function tmk_admin_menu_cat_add() {
	echo '<div class="form-field"><label for="tmk_mc_sort">Ordine</label><input type="number" name="tmk_mc_sort" id="tmk_mc_sort" value="" /><p>Numero più basso = più in alto nel menu.</p></div>';
	echo '<div class="form-field"><label for="tmk_mc_name_en">Nome (EN)</label><input type="text" name="tmk_mc_name_en" id="tmk_mc_name_en" value="" /></div>';
}

add_action( 'menu_category_edit_form_fields', 'tmk_admin_menu_cat_edit' );
function tmk_admin_menu_cat_edit( $term ) {
	$sort = get_term_meta( $term->term_id, 'sort_order', true );
	$en   = get_term_meta( $term->term_id, 'name_en', true );
	echo '<tr class="form-field"><th><label for="tmk_mc_sort">Ordine</label></th><td><input type="number" name="tmk_mc_sort" id="tmk_mc_sort" value="' . esc_attr( (string) $sort ) . '" /><p class="description">Numero più basso = più in alto nel menu.</p></td></tr>';
	echo '<tr class="form-field"><th><label for="tmk_mc_name_en">Nome (EN)</label></th><td><input type="text" name="tmk_mc_name_en" id="tmk_mc_name_en" value="' . esc_attr( (string) $en ) . '" /></td></tr>';
}

add_action( 'created_menu_category', 'tmk_admin_menu_cat_save' );
add_action( 'edited_menu_category', 'tmk_admin_menu_cat_save' );
function tmk_admin_menu_cat_save( $term_id ) {
	if ( ! current_user_can( 'manage_categories' ) ) { return; }
	if ( isset( $_POST['tmk_mc_sort'] ) ) {
		$v = trim( (string) wp_unslash( $_POST['tmk_mc_sort'] ) );
		update_term_meta( $term_id, 'sort_order', '' === $v ? '' : (int) $v );
	}
	if ( isset( $_POST['tmk_mc_name_en'] ) ) {
		update_term_meta( $term_id, 'name_en', sanitize_text_field( wp_unslash( $_POST['tmk_mc_name_en'] ) ) );
	}
}

/* ================================================================================================
   ADMIN COLUMNS — so the client can see and sort the lists that matter.
   ============================================================================================== */

add_filter( 'manage_menu_item_posts_columns', function ( $c ) {
	$new = array();
	foreach ( $c as $k => $v ) {
		$new[ $k ] = $v;
		if ( 'title' === $k ) { $new['tmk_price'] = 'Prezzo'; $new['tmk_sort'] = 'Ordine'; $new['tmk_allerg'] = 'Allergeni'; }
	}
	return $new;
} );
add_action( 'manage_menu_item_posts_custom_column', function ( $col, $id ) {
	if ( 'tmk_price' === $col )  { echo esc_html( (string) get_post_meta( $id, 'price', true ) ); }
	if ( 'tmk_sort' === $col )   { echo esc_html( (string) get_post_meta( $id, 'sort_order', true ) ); }
	if ( 'tmk_allerg' === $col ) { echo esc_html( (string) get_post_meta( $id, 'allergens', true ) ); }
}, 10, 2 );
add_filter( 'manage_edit-menu_item_sortable_columns', function ( $c ) { $c['tmk_sort'] = 'tmk_sort'; return $c; } );

add_filter( 'manage_event_posts_columns', function ( $c ) {
	$new = array();
	foreach ( $c as $k => $v ) {
		$new[ $k ] = $v;
		if ( 'title' === $k ) { $new['tmk_when'] = 'Quando'; $new['tmk_where'] = 'Dove'; }
	}
	return $new;
} );
add_action( 'manage_event_posts_custom_column', function ( $col, $id ) {
	if ( 'tmk_when' === $col ) {
		$s = (string) get_post_meta( $id, 'event_start', true );
		echo $s ? esc_html( function_exists( 'tmk_event_when_short' ) ? tmk_event_when_short( $s ) : $s ) : '—';
	}
	if ( 'tmk_where' === $col ) {
		$p = tmk_event_place( $id );
		echo esc_html( $p['venue'] ?: '—' );
	}
}, 10, 2 );

add_filter( 'manage_location_posts_columns', function ( $c ) {
	$new = array();
	foreach ( $c as $k => $v ) {
		$new[ $k ] = $v;
		if ( 'title' === $k ) { $new['tmk_gal'] = 'Foto'; $new['tmk_hours'] = 'Orari'; }
	}
	return $new;
} );
add_action( 'manage_location_posts_custom_column', function ( $col, $id ) {
	if ( 'tmk_gal' === $col )   { echo (int) count( array_filter( (array) get_post_meta( $id, 'gallery_ids', true ) ) ); }
	if ( 'tmk_hours' === $col ) { echo (int) count( tmk_location_hours_rows( get_post_meta( $id, 'hours', true ) ) ); }
}, 10, 2 );

/* ================================================================================================
   ASSETS — loaded only on the screens that have one of these panels.
   ============================================================================================== */

add_action( 'admin_enqueue_scripts', 'tmk_admin_fields_assets' );
function tmk_admin_fields_assets( $hook ) {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen ) { return; }
	$schema = tmk_admin_field_schema();
	$is_post_screen = in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && isset( $schema[ $screen->post_type ] );
	$is_term_screen = ( 'edit-tags.php' === $hook || 'term.php' === $hook ) && in_array( $screen->taxonomy, array( 'menu_category', 'city' ), true );
	if ( ! $is_post_screen && ! $is_term_screen ) { return; }

	$css = TMK_DS_PATH . 'css/admin/fields.css';
	$js  = TMK_DS_PATH . 'js/admin/fields.js';
	wp_enqueue_style( 'tmk-admin-fields', TMK_DS_URL . 'css/admin/fields.css', array(), file_exists( $css ) ? filemtime( $css ) : TMK_DS_VERSION );
	if ( $is_post_screen ) {
		wp_enqueue_media();
		wp_enqueue_script( 'tmk-admin-fields', TMK_DS_URL . 'js/admin/fields.js', array( 'jquery', 'jquery-ui-sortable' ), file_exists( $js ) ? filemtime( $js ) : TMK_DS_VERSION, true );
	}
}
