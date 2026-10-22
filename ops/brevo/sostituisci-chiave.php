<?php
/**
 * Sostituzione della chiave API Brevo usata dal sito (WP Mail SMTP + plugin Brevo «mailin»).
 *
 *   TMK_BREVO_NEW_KEY_FILE=/percorso/riservato wp --user=1 eval-file sostituisci-chiave.php [verifica|applica]
 *
 * La nuova chiave si legge da un file (permessi 600, fuori da Git e dai log) e non viene mai
 * stampata: a schermo solo impronte sha256 (10 caratteri). «verifica» (predefinito) controlla la
 * nuova chiave sull'API Brevo senza cambiare nulla; «applica» la scrive dove la vecchia era in uso,
 * elimina i transient il cui nome contiene la vecchia chiave (o la sua impronta md5) e rilegge.
 * Copia di sicurezza delle opzioni toccate nello stesso file riservato + «.bak.json» (600).
 */
if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }
$mode = in_array( 'applica', (array) ( $args ?? array() ), true ) ? 'applica' : 'verifica';
$file = (string) getenv( 'TMK_BREVO_NEW_KEY_FILE' );
$new  = is_readable( $file ) ? trim( (string) file_get_contents( $file ) ) : '';
$fp   = function ( $s ) { return '' === (string) $s ? '(vuota)' : substr( hash( 'sha256', (string) $s ), 0, 10 ); };
if ( ! preg_match( '/^xkeysib-[A-Za-z0-9]{64}-[A-Za-z0-9]{16}$/', $new ) ) {
	WP_CLI::error( 'File della nuova chiave assente o non nel formato di una chiave API v3 Brevo.' );
}
$smtp = get_option( 'wp_mail_smtp', array() );
$old  = (string) ( $smtp['sendinblue']['api_key'] ?? '' );
$v3   = (string) get_option( 'sib_api_key_v3', '' );
WP_CLI::log( 'chiave attuale WP Mail SMTP: ' . $fp( $old ) . ' | plugin Brevo: ' . $fp( $v3 ) . ' | nuova: ' . $fp( $new ) );
if ( hash_equals( $new, $old ) ) {
	WP_CLI::error( 'La nuova chiave coincide con quella attuale.' );
}
$call = function ( $key, $path ) {
	$r = wp_remote_get( 'https://api.brevo.com' . $path, array( 'timeout' => 20, 'headers' => array( 'api-key' => $key, 'accept' => 'application/json' ) ) );
	return is_wp_error( $r ) ? array( 0, $r->get_error_message() ) : array( (int) wp_remote_retrieve_response_code( $r ), json_decode( (string) wp_remote_retrieve_body( $r ), true ) );
};
list( $code, $acc ) = $call( $new, '/v3/account' );
WP_CLI::log( 'nuova chiave su /v3/account: HTTP ' . $code . ( 200 === $code ? ' (' . ( $acc['companyName'] ?? '' ) . ')' : '' ) );
if ( 200 !== $code ) {
	WP_CLI::error( 'La nuova chiave non viene accettata da Brevo: niente modificato.' );
}
list( $c2 ) = $call( $old, '/v3/account' );
WP_CLI::log( 'vecchia chiave su /v3/account: HTTP ' . $c2 . ' (deve restare valida fino alla revoca)' );
if ( 'verifica' === $mode ) {
	WP_CLI::success( 'Verifica completata, nessuna modifica. Eseguire con «applica».' );
	return;
}

global $wpdb;
$names = array();
foreach ( array_unique( array_filter( array( $old, $v3 ) ) ) as $k ) {
	foreach ( array( $k, md5( $k ) ) as $needle ) {
		foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", '%' . $wpdb->esc_like( $needle ) . '%' ) ) as $n ) {
			$names[ $n ] = true;
		}
	}
}
$backup = array( 'wp_mail_smtp' => $smtp, 'sib_api_key_v3' => $v3 );
foreach ( array_keys( $names ) as $n ) {
	$backup[ $n ] = get_option( $n );
}
file_put_contents( $file . '.bak.json', wp_json_encode( $backup ) );
chmod( $file . '.bak.json', 0600 );

$changed = array();
if ( '' !== $old ) {
	$smtp['sendinblue']['api_key'] = $new;
	update_option( 'wp_mail_smtp', $smtp );
	$changed[] = 'WP Mail SMTP';
}
if ( '' !== $v3 ) {
	update_option( 'sib_api_key_v3', $new );
	$changed[] = 'plugin Brevo';
}
foreach ( array_keys( $names ) as $n ) {
	delete_option( $n ); // transient e relativi timeout che portano la vecchia chiave (o il suo md5) nel nome
}
wp_cache_flush();
$chk_smtp = (string) ( get_option( 'wp_mail_smtp', array() )['sendinblue']['api_key'] ?? '' );
$chk_v3   = (string) get_option( 'sib_api_key_v3', '' );
$left     = 0;
foreach ( array_unique( array_filter( array( $old, $v3 ) ) ) as $k ) {
	$like  = '%' . $wpdb->esc_like( $k ) . '%';
	$left += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s OR option_value LIKE %s", $like, $like ) );
}
WP_CLI::log( 'aggiornati: ' . implode( ', ', $changed ) . ' | transient rimossi: ' . count( $names ) );
WP_CLI::log( 'rilettura: WP Mail SMTP ' . $fp( $chk_smtp ) . ' | plugin Brevo ' . $fp( $chk_v3 ) . ' | occorrenze residue della vecchia chiave nel database: ' . $left );
if ( ! hash_equals( $new, $chk_smtp ) || ( '' !== $v3 && ! hash_equals( $new, $chk_v3 ) ) || $left ) {
	WP_CLI::error( 'Rilettura non coerente: controllare (copia delle opzioni in ' . $file . '.bak.json).' );
}
WP_CLI::success( 'Nuova chiave in uso. La vecchia va revocata in Brevo.' );
