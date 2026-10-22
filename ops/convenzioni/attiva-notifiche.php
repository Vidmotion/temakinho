<?php
/**
 * Convenzioni aziendali — attivazione delle due notifiche gia' predisposte nel modulo Elementor
 * (riepilogo interno + conferma all'azienda). Da eseguire SOLO quando destinatario interno e
 * Reply-To sono stati confermati da Temakinho:
 *
 *   TMK_CONV_TO=<destinatario interno> TMK_CONV_REPLY_TO=<reply-to della conferma> \
 *     wp --user=1 eval-file attiva-notifiche.php [dry-run]
 *
 * Cambia solo: submit_actions (+email, +email2), email_to, email_reply_to_2 del modulo 9fc0788 della
 * pagina 21226. Testi, mittente, campi e azione "salva nel database" restano come sono. Idempotente;
 * salva una copia di _elementor_data prima di modificarlo. Annullamento: TMK_CONV_DISABLE=1.
 */
if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }
$post_id = (int) ( getenv( 'TMK_CONV_POST' ) ?: 21226 );
$form_id = (string) ( getenv( 'TMK_CONV_FORM' ) ?: '9fc0788' );
$to      = trim( (string) getenv( 'TMK_CONV_TO' ) );
$reply   = trim( (string) getenv( 'TMK_CONV_REPLY_TO' ) );
$disable = (bool) getenv( 'TMK_CONV_DISABLE' );
$dry     = in_array( 'dry-run', (array) ( $args ?? array() ), true );
if ( ! $disable && ( ! is_email( $to ) || ! is_email( $reply ) ) ) {
	WP_CLI::error( 'Servono TMK_CONV_TO e TMK_CONV_REPLY_TO validi (indirizzi confermati da Temakinho).' );
}
$raw  = get_post_meta( $post_id, '_elementor_data', true );
$data = json_decode( is_string( $raw ) ? $raw : '', true );
if ( ! is_array( $data ) ) {
	WP_CLI::error( "Pagina $post_id senza dati Elementor leggibili." );
}
$found = false;
$walk  = function ( &$els ) use ( &$walk, &$found, $form_id, $to, $reply, $disable ) {
	foreach ( $els as &$e ) {
		if ( ( $e['id'] ?? '' ) === $form_id && 'form' === ( $e['widgetType'] ?? '' ) ) {
			$found   = true;
			$actions = array_values( array_diff( (array) ( $e['settings']['submit_actions'] ?? array() ), array( 'email', 'email2' ) ) );
			if ( ! in_array( 'save-to-database', $actions, true ) ) {
				array_unshift( $actions, 'save-to-database' ); // l'archivio viene sempre prima delle email
			}
			if ( ! $disable ) {
				$actions[] = 'email';
				$actions[] = 'email2';
				$e['settings']['email_to']         = $to;
				$e['settings']['email_reply_to_2'] = $reply;
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
	WP_CLI::log( 'dry-run: azioni dopo la modifica → ' . ( $disable ? 'save-to-database' : 'save-to-database, email, email2' ) );
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
