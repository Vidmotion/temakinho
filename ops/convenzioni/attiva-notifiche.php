<?php
/**
 * Convenzioni aziendali — attivazione delle due notifiche del modulo Elementor (pagina 21226,
 * elemento 9fc0788): riepilogo interno + conferma all'azienda.
 *
 *   TMK_CONV_TO=info@temakinho.it TMK_CONV_REPLY_TO=info@temakinho.it \
 *     wp --user=1 eval-file attiva-notifiche.php [dry-run]
 *
 * Valori confermati da Temakinho il 22/10/2026: destinatario interno, mittente e Reply-To della
 * conferma = info@temakinho.it (dominio .it, NON .com); Reply-To del riepilogo interno = email del
 * referente validata dal modulo (campo «email»). Il mittente .it viene applicato solo a queste due
 * email da inc/conventions-form.php (tmk_conv_sender), le altre email del sito non cambiano.
 *
 * Cambia solo: submit_actions (+email, +email2) e le impostazioni email_* / email_*_2 del modulo.
 * Campi, validazione, messaggi a schermo e «salva nel database» (sempre per primo) restano come
 * sono. Idempotente; salva una copia di _elementor_data prima di modificarlo.
 * Annullamento: TMK_CONV_DISABLE=1 (toglie le due azioni email, lascia i testi).
 */
if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }
$post_id = (int) ( getenv( 'TMK_CONV_POST' ) ?: 21226 );
$form_id = (string) ( getenv( 'TMK_CONV_FORM' ) ?: '9fc0788' );
$to      = trim( (string) getenv( 'TMK_CONV_TO' ) );
$reply   = trim( (string) getenv( 'TMK_CONV_REPLY_TO' ) );
$from    = trim( (string) ( getenv( 'TMK_CONV_FROM' ) ?: 'info@temakinho.it' ) );
$disable = (bool) getenv( 'TMK_CONV_DISABLE' );
$dry     = in_array( 'dry-run', (array) ( $args ?? array() ), true );
if ( ! $disable && ( ! is_email( $to ) || ! is_email( $reply ) || ! is_email( $from ) ) ) {
	WP_CLI::error( 'Servono TMK_CONV_TO e TMK_CONV_REPLY_TO validi (indirizzi confermati da Temakinho).' );
}

$internal = array(
	'email_to'             => $to,
	'email_subject'        => 'Nuova richiesta di convenzione n. %%TMK_SUBMISSION_ID%% – [field id="azienda"]',
	'email_content'        => implode( "\n", array(
		'È arrivata una nuova richiesta di convenzione dal sito temakinho.com.',
		'',
		'Submission ID (archivio WordPress): %%TMK_SUBMISSION_ID%%',
		'ID richiesta: [field id="id_richiesta"]',
		'Nome azienda: [field id="azienda"]',
		'Sito web: [field id="sito_web"]',
		'Email di riferimento: [field id="email"]',
		'Numero dipendenti: [field id="dipendenti"]',
		'Settore: [field id="settore"]',
		'Città di interesse: [field id="citta"]',
		'',
		'Stato iniziale: Da valutare. L\'invio del modulo non attiva alcuna convenzione.',
		'',
		'Richiesta nell\'archivio riservato (accesso amministratori):',
		'%%TMK_SUBMISSION_URL%%',
		'',
		'Rispondendo a questa email si scrive all\'indirizzo di riferimento indicato nel modulo.',
	) ),
	'email_from'           => $from,
	'email_from_name'      => 'Temakinho – Sito web',
	'email_reply_to'       => 'email', // campo del modulo: Elementor usa il valore inviato solo se e' un indirizzo valido
	'email_content_type'   => 'plain',
	'email_form_metadata'  => array( 'date', 'time', 'page_url' ),
);
$confirm = array(
	'email_to_2'            => '[field id="email"]',
	'email_subject_2'       => 'Richiesta di convenzione ricevuta – [field id="azienda"]',
	'email_content_2'       => implode( "\n", array(
		'Gentile referente,',
		'',
		'grazie per averci scritto. Abbiamo ricevuto la richiesta di convenzione per [field id="azienda"].',
		'',
		'Riepilogo dei dati inviati:',
		'ID richiesta: [field id="id_richiesta"]',
		'Nome azienda: [field id="azienda"]',
		'Sito web: [field id="sito_web"]',
		'Email di riferimento: [field id="email"]',
		'Numero dipendenti: [field id="dipendenti"]',
		'Settore: [field id="settore"]',
		'Città di interesse: [field id="citta"]',
		'',
		'Questa email conferma soltanto la ricezione: l\'invio del modulo non attiva una convenzione e non riconosce sconti o condizioni. Il team Temakinho valuterà la richiesta e ti ricontatterà a questo indirizzo.',
		'',
		'Per aggiungere informazioni puoi rispondere a questa email.',
		'',
		'Il team Temakinho',
	) ),
	'email_from_2'          => $from,
	'email_from_name_2'     => 'Temakinho',
	'email_reply_to_2'      => $reply,
	'email_content_type_2'  => 'plain',
	'email_form_metadata_2' => array(),
);

$raw  = get_post_meta( $post_id, '_elementor_data', true );
$data = json_decode( is_string( $raw ) ? $raw : '', true );
if ( ! is_array( $data ) ) {
	WP_CLI::error( "Pagina $post_id senza dati Elementor leggibili." );
}
$found = false;
$walk  = function ( &$els ) use ( &$walk, &$found, $form_id, $internal, $confirm, $disable ) {
	foreach ( $els as &$e ) {
		if ( ( $e['id'] ?? '' ) === $form_id && 'form' === ( $e['widgetType'] ?? '' ) ) {
			$found   = true;
			$actions = array_values( array_diff( (array) ( $e['settings']['submit_actions'] ?? array() ), array( 'email', 'email2', 'save-to-database' ) ) );
			array_unshift( $actions, 'save-to-database' ); // l'archivio viene sempre prima delle email
			if ( ! $disable ) {
				$actions[] = 'email';
				$actions[] = 'email2';
				foreach ( $internal + $confirm as $k => $v ) {
					$e['settings'][ $k ] = $v;
				}
			}
			$e['settings']['submit_actions'] = $actions;
		}
		if ( ! empty( $e['elements'] ) ) {
			$walk( $e['elements'] );
		}
	}
};
$walk( $data );
if ( ! $found ) {
	WP_CLI::error( "Modulo $form_id non trovato nella pagina $post_id." );
}
$new = wp_json_encode( $data );
if ( $new === $raw ) {
	WP_CLI::success( 'Nessuna modifica necessaria.' );
	return;
}
if ( $dry ) {
	WP_CLI::log( 'dry-run: azioni dopo la modifica → ' . ( $disable ? 'save-to-database' : 'save-to-database, email, email2' ) . "; interno a $to, conferma con Reply-To $reply, mittente $from" );
	return;
}
$backup = trailingslashit( get_temp_dir() ) . 'conv-' . $post_id . '-elementor-' . gmdate( 'Ymd\THis\Z' ) . '.json';
file_put_contents( $backup, $raw );
update_post_meta( $post_id, '_elementor_data', wp_slash( $new ) );
delete_post_meta( $post_id, '_elementor_element_cache' );
delete_post_meta( $post_id, '_elementor_css' );
if ( class_exists( '\Elementor\Plugin' ) ) {
	\Elementor\Plugin::$instance->files_manager->clear_cache();
}
$check = json_decode( get_post_meta( $post_id, '_elementor_data', true ), true );
WP_CLI::success( ( $disable ? 'Notifiche disattivate' : 'Notifiche attivate' ) . '. Copia precedente: ' . $backup . ( is_array( $check ) ? '' : ' — ATTENZIONE: rilettura non riuscita' ) );
