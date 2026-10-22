<?php
/**
 * Convenzioni aziendali — sincronizzazione automatica del registro condiviso.
 *
 * Fonte persistente: l'archivio WordPress (Elementor › Submissions, modulo 9fc0788). Il registro
 * (tabella `tblRichieste` del file Registro_richieste_convenzioni_OPERATIVO_rev2.xlsx su Excel
 * Online / OneDrive / SharePoint) ne riceve una copia, una riga per invio:
 *
 * - chiave autorevole = Submission ID (colonna «ID archivio WordPress»);
 * - prima di aggiungere una riga si rilegge la tabella: se la chiave c'e' gia' non si scrive nulla,
 *   quindi tentativi ripetuti non duplicano e «Stato (manuale)» / «Note interne» non vengono mai
 *   toccati (si scrivono solo le colonne che vengono dal modulo; quelle calcolate restano formule);
 * - coda persistente {prefix}tmk_conv_sync: ogni invio salvato entra «pending» e viene spedito in
 *   modo asincrono (wp-cron), con tentativi a intervalli crescenti; un passaggio orario recupera
 *   anche gli invii salvati che non fossero entrati in coda;
 * - senza configurazione non parte nessuna richiesta: gli invii restano in attesa e l'amministratore
 *   vede un avviso. Comandi: `wp tmk-conv-registry status|configure|test|run|retry`.
 *
 * Configurazione (nessun segreto nel codice): opzione `tmk_conv_registry` scritta con
 * `wp tmk-conv-registry configure`, oppure variabili d'ambiente TMK_CONV_REGISTRY_* che prevalgono.
 * Microsoft Graph con credenziali applicative (client credentials) limitate al solo sito/file:
 * permesso consigliato Sites.Selected (o Files.ReadWrite.All se l'amministratore del tenant non usa
 * Sites.Selected).
 *
 * @package TMK_Design_System
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const TMK_CONV_SYNC_DB_VER = '1';
const TMK_CONV_SYNC_MAX_ATTEMPTS = 12;

function tmk_conv_sync_table() {
	global $wpdb;
	return $wpdb->prefix . 'tmk_conv_sync';
}

add_action( 'init', 'tmk_conv_sync_install' );
function tmk_conv_sync_install() {
	if ( TMK_CONV_SYNC_DB_VER === get_option( 'tmk_conv_sync_db' ) ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( 'CREATE TABLE ' . tmk_conv_sync_table() . " (
		submission_id bigint(20) unsigned NOT NULL,
		status varchar(16) NOT NULL DEFAULT 'pending',
		attempts smallint(5) unsigned NOT NULL DEFAULT 0,
		next_try datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
		last_error varchar(500) NOT NULL DEFAULT '',
		synced_at datetime NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (submission_id),
		KEY status_next (status,next_try)
	) " . $wpdb->get_charset_collate() . ';' );
	update_option( 'tmk_conv_sync_db', TMK_CONV_SYNC_DB_VER );
}

/** Configurazione: opzione + variabili d'ambiente (che prevalgono). Il segreto non viene mai stampato. */
function tmk_conv_sync_config() {
	$o = (array) get_option( 'tmk_conv_registry', array() );
	$c = array(
		'driver'        => 'msgraph' === ( $o['driver'] ?? '' ) ? 'msgraph' : '',
		'tenant'        => (string) ( $o['tenant'] ?? '' ),
		'client_id'     => (string) ( $o['client_id'] ?? '' ),
		'client_secret' => (string) ( $o['client_secret'] ?? '' ),
		'drive_id'      => (string) ( $o['drive_id'] ?? '' ),
		'item_id'       => (string) ( $o['item_id'] ?? '' ),
		'table'         => (string) ( $o['table'] ?? 'tblRichieste' ),
		'graph_base'    => (string) ( $o['graph_base'] ?? 'https://graph.microsoft.com/v1.0' ),
		'login_base'    => (string) ( $o['login_base'] ?? 'https://login.microsoftonline.com' ),
	);
	foreach ( array_keys( $c ) as $k ) {
		$env = getenv( 'TMK_CONV_REGISTRY_' . strtoupper( $k ) );
		if ( false !== $env && '' !== $env ) {
			$c[ $k ] = (string) $env;
		}
	}
	return (array) apply_filters( 'tmk_conv_sync_config', $c );
}

function tmk_conv_sync_configured( $c = null ) {
	$c = $c ?: tmk_conv_sync_config();
	foreach ( array( 'tenant', 'client_id', 'client_secret', 'drive_id', 'item_id', 'table' ) as $k ) {
		if ( '' === $c[ $k ] ) {
			return false;
		}
	}
	return 'msgraph' === $c['driver'];
}

/* ---------------------------------------------------------------------------------------------
 * Coda
 * ------------------------------------------------------------------------------------------- */

add_action( 'tmk_conv_saved', 'tmk_conv_sync_enqueue', 10, 1 );
function tmk_conv_sync_enqueue( $submission_id ) {
	global $wpdb;
	$submission_id = (int) $submission_id;
	if ( $submission_id <= 0 ) {
		return;
	}
	tmk_conv_sync_install();
	$wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO ' . tmk_conv_sync_table() . " (submission_id, status, created_at) VALUES (%d, 'pending', %s)", $submission_id, current_time( 'mysql', true ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	if ( tmk_conv_sync_configured() && ! wp_next_scheduled( 'tmk_conv_sync_run' ) ) {
		wp_schedule_single_event( time(), 'tmk_conv_sync_run' );
		spawn_cron();
	}
}

add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'tmk_conv_sync_sweep' ) ) {
		wp_schedule_event( time() + 300, 'hourly', 'tmk_conv_sync_sweep' );
	}
} );

add_action( 'tmk_conv_sync_sweep', 'tmk_conv_sync_sweep' );
/** Recupero: ogni invio del modulo salvato in WordPress (escluso il cestino) deve essere in coda. */
function tmk_conv_sync_sweep() {
	global $wpdb;
	tmk_conv_sync_install();
	$ids = "'" . implode( "','", array_map( 'esc_sql', tmk_conv_form_ids() ) ) . "'";
	$missing = $wpdb->get_col( "SELECT s.id FROM {$wpdb->prefix}e_submissions s LEFT JOIN " . tmk_conv_sync_table() . " q ON q.submission_id = s.id WHERE s.element_id IN ($ids) AND s.status <> 'trash' AND q.submission_id IS NULL ORDER BY s.id" ); // phpcs:ignore WordPress.DB.PreparedSQL
	foreach ( $missing as $sid ) {
		$wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO ' . tmk_conv_sync_table() . " (submission_id, status, created_at) VALUES (%d, 'pending', %s)", (int) $sid, current_time( 'mysql', true ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
	return count( $missing ) + tmk_conv_sync_process();
}

add_action( 'tmk_conv_sync_run', 'tmk_conv_sync_process' );
/** Spedisce gli invii dovuti. Un solo processo per volta. Restituisce quanti ne ha sincronizzati. */
function tmk_conv_sync_process( $limit = 25 ) {
	global $wpdb;
	$c = tmk_conv_sync_config();
	if ( ! tmk_conv_sync_configured( $c ) ) {
		return 0;
	}
	if ( ! tmk_conv_sync_lock() ) {
		return 0;
	}
	$done = 0;
	try {
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . tmk_conv_sync_table() . " WHERE status IN ('pending','error') AND next_try <= %s ORDER BY submission_id LIMIT %d", current_time( 'mysql', true ), (int) $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		foreach ( $rows as $r ) {
			$row = tmk_conv_sync_row_data( (int) $r->submission_id );
			if ( is_wp_error( $row ) ) {
				tmk_conv_sync_mark( $r, 'skipped', $row->get_error_message() );
				continue;
			}
			$res = tmk_conv_graph_push( $c, $row );
			if ( is_wp_error( $res ) ) {
				tmk_conv_sync_mark( $r, 'error', $res->get_error_message() );
				continue;
			}
			tmk_conv_sync_mark( $r, 'done', $res );
			$done++;
		}
	} finally {
		tmk_conv_sync_unlock();
	}
	return $done;
}

function tmk_conv_sync_mark( $r, $status, $note ) {
	global $wpdb;
	$attempts = (int) $r->attempts + ( 'done' === $status ? 0 : 1 );
	if ( 'error' === $status && $attempts >= TMK_CONV_SYNC_MAX_ATTEMPTS ) {
		$status = 'failed';
	}
	$wait = min( DAY_IN_SECONDS, 300 * ( 2 ** max( 0, $attempts - 1 ) ) ); // 5 min, 10, 20, ... max 24 h
	$wpdb->update(
		tmk_conv_sync_table(),
		array(
			'status'     => $status,
			'attempts'   => $attempts,
			'next_try'   => gmdate( 'Y-m-d H:i:s', time() + $wait ),
			'last_error' => 'done' === $status ? (string) $note : mb_substr( (string) $note, 0, 500 ),
			'synced_at'  => 'done' === $status ? current_time( 'mysql', true ) : null,
		),
		array( 'submission_id' => (int) $r->submission_id )
	);
}

function tmk_conv_sync_lock() {
	$until = (int) get_option( 'tmk_conv_sync_lock', 0 );
	if ( $until && $until > time() ) {
		return false;
	}
	if ( $until ) {
		delete_option( 'tmk_conv_sync_lock' ); // blocco scaduto (processo interrotto)
	}
	return add_option( 'tmk_conv_sync_lock', time() + 300, '', false ); // INSERT: riesce a un solo processo
}

function tmk_conv_sync_unlock() {
	delete_option( 'tmk_conv_sync_lock' );
}

/* ---------------------------------------------------------------------------------------------
 * Dati della riga (dall'archivio WordPress, mai dalla richiesta o dalle email)
 * ------------------------------------------------------------------------------------------- */

function tmk_conv_sync_row_data( $sid ) {
	global $wpdb;
	$s = $wpdb->get_row( $wpdb->prepare( "SELECT id, element_id, status, created_at_gmt FROM {$wpdb->prefix}e_submissions WHERE id = %d", $sid ) );
	if ( ! $s || ! in_array( (string) $s->element_id, tmk_conv_form_ids(), true ) ) {
		return new WP_Error( 'tmk_conv_missing', 'invio non trovato nell\'archivio o di un altro modulo' );
	}
	if ( 'trash' === $s->status ) {
		return new WP_Error( 'tmk_conv_trash', 'invio nel cestino' );
	}
	$v = array();
	foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT `key`, value FROM {$wpdb->prefix}e_submissions_values WHERE submission_id = %d", $sid ) ) as $kv ) {
		$v[ $kv->key ] = (string) $kv->value;
	}
	$tz  = new DateTimeZone( (string) apply_filters( 'tmk_conv_sync_timezone', 'Europe/Rome' ) );
	$ts  = strtotime( $s->created_at_gmt . ' UTC' );
	$loc = ( new DateTime( '@' . $ts ) )->setTimezone( $tz );
	$dip = trim( $v['dipendenti'] ?? '' );
	return array(
		'ID archivio WordPress' => (int) $s->id,
		'ID richiesta'          => tmk_conv_sync_text( $v['id_richiesta'] ?? '' ),
		'Data e ora'            => round( 25569 + ( $ts + $loc->getOffset() ) / 86400, 8 ), // numero di serie Excel, ora italiana
		'Nome azienda'          => tmk_conv_sync_text( $v['azienda'] ?? '' ),
		'Sito web'              => tmk_conv_sync_text( $v['sito_web'] ?? '' ),
		'Email di riferimento'  => tmk_conv_sync_text( $v['email'] ?? '' ),
		'Numero dipendenti'     => preg_match( '/^[0-9]{1,7}$/', $dip ) ? (int) $dip : tmk_conv_sync_text( $dip ),
		'Settore'               => tmk_conv_sync_text( $v['settore'] ?? '' ),
		'Città selezionate'     => tmk_conv_sync_text( $v['citta'] ?? '' ),
	);
}

/** Testo sempre come testo: un valore che inizia con = + - @ non deve diventare una formula. */
function tmk_conv_sync_text( $s ) {
	$s = (string) $s;
	return ( '' !== $s && false !== strpos( "=+-@\t\r", $s[0] ) ) ? "'" . $s : $s;
}

/* ---------------------------------------------------------------------------------------------
 * Microsoft Graph — Excel (tabella in un file su OneDrive / SharePoint)
 * ------------------------------------------------------------------------------------------- */

function tmk_conv_graph_token( $c, $fresh = false ) {
	$key = 'tmk_conv_graph_tok_' . md5( $c['tenant'] . '|' . $c['client_id'] . '|' . $c['login_base'] );
	$tok = $fresh ? false : get_transient( $key );
	if ( is_string( $tok ) && '' !== $tok ) {
		return $tok;
	}
	$r = wp_remote_post( rtrim( $c['login_base'], '/' ) . '/' . rawurlencode( $c['tenant'] ) . '/oauth2/v2.0/token', array(
		'timeout' => 20,
		'body'    => array(
			'grant_type'    => 'client_credentials',
			'client_id'     => $c['client_id'],
			'client_secret' => $c['client_secret'],
			'scope'         => 'https://graph.microsoft.com/.default',
		),
	) );
	if ( is_wp_error( $r ) ) {
		return new WP_Error( 'tmk_graph_token', 'autenticazione: ' . $r->get_error_message() );
	}
	$j = json_decode( (string) wp_remote_retrieve_body( $r ), true );
	if ( 200 !== (int) wp_remote_retrieve_response_code( $r ) || empty( $j['access_token'] ) ) {
		return new WP_Error( 'tmk_graph_token', 'autenticazione HTTP ' . wp_remote_retrieve_response_code( $r ) . ': ' . mb_substr( (string) ( $j['error_description'] ?? $j['error'] ?? '' ), 0, 200 ) );
	}
	set_transient( $key, $j['access_token'], max( 60, (int) ( $j['expires_in'] ?? 3600 ) - 300 ) );
	return $j['access_token'];
}

/** Chiamata al file di lavoro. Restituisce l'array JSON o WP_Error (con codice HTTP nel messaggio). */
function tmk_conv_graph( $c, $method, $path, $body = null ) {
	$url = rtrim( $c['graph_base'], '/' ) . '/drives/' . rawurlencode( $c['drive_id'] ) . '/items/' . rawurlencode( $c['item_id'] ) . '/workbook' . $path;
	for ( $try = 0; $try < 2; $try++ ) {
		$tok = tmk_conv_graph_token( $c, $try > 0 );
		if ( is_wp_error( $tok ) ) {
			return $tok;
		}
		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array( 'Authorization' => 'Bearer ' . $tok, 'Content-Type' => 'application/json' ),
		);
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}
		$r = wp_remote_request( $url, $args );
		if ( is_wp_error( $r ) ) {
			return new WP_Error( 'tmk_graph', $method . ' ' . $path . ': ' . $r->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $r );
		if ( 401 === $code && 0 === $try ) {
			continue; // token scaduto o revocato: uno nuovo e si riprova una volta
		}
		$j = json_decode( (string) wp_remote_retrieve_body( $r ), true );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'tmk_graph', $method . ' ' . $path . ' HTTP ' . $code . ': ' . mb_substr( (string) ( $j['error']['code'] ?? '' ) . ' ' . (string) ( $j['error']['message'] ?? '' ), 0, 200 ) );
		}
		return is_array( $j ) ? $j : array();
	}
	return new WP_Error( 'tmk_graph', 'autenticazione rifiutata' );
}

function tmk_conv_graph_table_path( $c ) {
	return '/tables/' . rawurlencode( $c['table'] );
}

/**
 * Legge intestazioni e chiavi della tabella. Restituisce array(
 *   'head' => [nome => indice], 'keys' => [submission_id => true], 'rows' => n righe dati,
 *   'blank_first' => la prima riga dati e' vuota (tabella appena creata) ).
 */
function tmk_conv_graph_read( $c ) {
	$t = tmk_conv_graph( $c, 'GET', tmk_conv_graph_table_path( $c ) . '/range?$select=values' );
	if ( is_wp_error( $t ) ) {
		return $t;
	}
	$vals = (array) ( $t['values'] ?? array() );
	if ( ! $vals ) {
		return new WP_Error( 'tmk_graph', 'tabella vuota o non leggibile' );
	}
	$head = array();
	foreach ( (array) $vals[0] as $i => $h ) {
		$head[ trim( (string) $h ) ] = $i;
	}
	foreach ( array_keys( tmk_conv_sync_row_data_template() ) as $need ) {
		if ( ! isset( $head[ $need ] ) ) {
			return new WP_Error( 'tmk_graph', 'colonna mancante nel registro: «' . $need . '»' );
		}
	}
	$ki   = $head['ID archivio WordPress'];
	$keys = array();
	$rows = array_slice( $vals, 1 );
	foreach ( $rows as $row ) {
		$k = trim( (string) ( $row[ $ki ] ?? '' ) );
		if ( '' !== $k && is_numeric( $k ) ) {
			$keys[ (int) $k ] = true;
		}
	}
	$blank = false;
	if ( 1 === count( $rows ) ) {
		$blank = true;
		foreach ( array_keys( tmk_conv_sync_row_data_template() ) as $col ) {
			if ( '' !== trim( (string) ( $rows[0][ $head[ $col ] ] ?? '' ) ) ) {
				$blank = false;
			}
		}
	}
	return array( 'head' => $head, 'keys' => $keys, 'rows' => count( $rows ), 'blank_first' => $blank );
}

function tmk_conv_sync_row_data_template() {
	return array_fill_keys( array( 'ID archivio WordPress', 'ID richiesta', 'Data e ora', 'Nome azienda', 'Sito web', 'Email di riferimento', 'Numero dipendenti', 'Settore', 'Città selezionate' ), null );
}

/** Aggiunge la riga se la chiave non c'e'. Restituisce la nota di esito o WP_Error. */
function tmk_conv_graph_push( $c, $row ) {
	$info = tmk_conv_graph_read( $c );
	if ( is_wp_error( $info ) ) {
		return $info;
	}
	$sid = (int) $row['ID archivio WordPress'];
	if ( isset( $info['keys'][ $sid ] ) ) {
		return 'gia\' presente nel registro: nessuna modifica';
	}
	// Una cella null non viene scritta: colonne calcolate, Stato (manuale), Note e newsletter restano come sono.
	$values = array_fill( 0, count( $info['head'] ), null );
	foreach ( $row as $col => $val ) {
		$values[ $info['head'][ $col ] ] = $val;
	}
	$base = tmk_conv_graph_table_path( $c );
	if ( $info['blank_first'] ) {
		$res   = tmk_conv_graph( $c, 'PATCH', $base . '/rows/itemAt(index=0)/range', array( 'values' => array( $values ) ) );
		$index = 0;
	} else {
		$res   = tmk_conv_graph( $c, 'POST', $base . '/rows', array( 'values' => array( $values ) ) );
		$index = is_array( $res ) && isset( $res['index'] ) ? (int) $res['index'] : $info['rows'];
	}
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	// Le colonne calcolate della tabella devono avere la formula anche nella nuova riga: se Excel non
	// l'ha estesa, la si copia (in notazione R1C1) dalla prima riga della tabella.
	if ( $index > 0 ) {
		$ref = tmk_conv_graph( $c, 'GET', $base . '/rows/itemAt(index=0)/range?$select=formulasR1C1' );
		$new = tmk_conv_graph( $c, 'GET', $base . '/rows/itemAt(index=' . $index . ')/range?$select=formulasR1C1' );
		if ( ! is_wp_error( $ref ) && ! is_wp_error( $new ) ) {
			$fix  = array_fill( 0, count( $info['head'] ), null );
			$need = false;
			foreach ( (array) ( $ref['formulasR1C1'][0] ?? array() ) as $i => $f ) {
				$cur = (string) ( $new['formulasR1C1'][0][ $i ] ?? '' );
				if ( is_string( $f ) && 0 === strpos( $f, '=' ) && '' === $cur ) {
					$fix[ $i ] = $f;
					$need      = true;
				}
			}
			if ( $need ) {
				tmk_conv_graph( $c, 'PATCH', $base . '/rows/itemAt(index=' . $index . ')/range', array( 'formulasR1C1' => array( $fix ) ) );
			}
		}
	}
	// Rilettura: la chiave deve risultare presente.
	$check = tmk_conv_graph_read( $c );
	if ( is_wp_error( $check ) ) {
		return new WP_Error( 'tmk_graph', 'riga scritta ma rilettura non riuscita: ' . $check->get_error_message() );
	}
	if ( ! isset( $check['keys'][ $sid ] ) ) {
		return new WP_Error( 'tmk_graph', 'riga non trovata alla rilettura' );
	}
	return 'aggiunta (riga ' . ( $index + 1 ) . ' della tabella)';
}

/* ---------------------------------------------------------------------------------------------
 * Avviso agli amministratori e comandi
 * ------------------------------------------------------------------------------------------- */

function tmk_conv_sync_counts() {
	global $wpdb;
	$out = array( 'pending' => 0, 'error' => 0, 'failed' => 0, 'done' => 0, 'skipped' => 0 );
	if ( TMK_CONV_SYNC_DB_VER !== get_option( 'tmk_conv_sync_db' ) ) {
		return $out;
	}
	foreach ( $wpdb->get_results( 'SELECT status, COUNT(*) n FROM ' . tmk_conv_sync_table() . ' GROUP BY status' ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$out[ $r->status ] = (int) $r->n;
	}
	return $out;
}

add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$n   = tmk_conv_sync_counts();
	$msg = '';
	if ( $n['failed'] ) {
		$msg = sprintf( 'Registro convenzioni: %d richieste non sono state copiate nel registro dopo %d tentativi. Controllare con «wp tmk-conv-registry status» e riprovare con «wp tmk-conv-registry retry».', $n['failed'], TMK_CONV_SYNC_MAX_ATTEMPTS );
	} elseif ( ( $n['pending'] + $n['error'] ) && ! tmk_conv_sync_configured() ) {
		$msg = sprintf( 'Registro convenzioni: sincronizzazione non configurata, %d richieste in attesa (restano nell\'archivio Submissions).', $n['pending'] + $n['error'] );
	}
	if ( $msg ) {
		echo '<div class="notice notice-warning"><p>' . esc_html( $msg ) . '</p></div>';
	}
} );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'tmk-conv-registry', function ( $args, $assoc ) {
		global $wpdb;
		$cmd = $args[0] ?? 'status';
		$c   = tmk_conv_sync_config();
		tmk_conv_sync_install();
		switch ( $cmd ) {
			case 'status':
				WP_CLI::log( 'configurazione: ' . ( tmk_conv_sync_configured( $c ) ? 'completa (' . $c['driver'] . ', tabella ' . $c['table'] . ')' : 'assente o incompleta' ) );
				foreach ( tmk_conv_sync_counts() as $k => $v ) {
					WP_CLI::log( sprintf( '  %-8s %d', $k, $v ) );
				}
				foreach ( $wpdb->get_results( 'SELECT submission_id, status, attempts, next_try, last_error, synced_at FROM ' . tmk_conv_sync_table() . ' ORDER BY submission_id DESC LIMIT 20' ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					WP_CLI::log( sprintf( '  #%d %s tentativi=%d prossimo=%s %s %s', $r->submission_id, $r->status, $r->attempts, $r->next_try, $r->synced_at ? 'sincronizzato=' . $r->synced_at : '', $r->last_error ) );
				}
				return;
			case 'configure':
				$o = (array) get_option( 'tmk_conv_registry', array() );
				foreach ( array( 'driver', 'tenant', 'client-id', 'drive-id', 'item-id', 'table', 'graph-base', 'login-base' ) as $k ) {
					if ( isset( $assoc[ $k ] ) ) {
						$o[ str_replace( '-', '_', $k ) ] = trim( (string) $assoc[ $k ] );
					}
				}
				if ( isset( $assoc['secret-stdin'] ) ) {
					$o['client_secret'] = trim( (string) stream_get_contents( STDIN ) );
				}
				update_option( 'tmk_conv_registry', $o, false );
				WP_CLI::success( 'Configurazione salvata (' . ( tmk_conv_sync_configured() ? 'completa' : 'incompleta' ) . ').' );
				return;
			case 'test':
				if ( ! tmk_conv_sync_configured( $c ) ) {
					WP_CLI::error( 'Configurazione incompleta.' );
				}
				$info = tmk_conv_graph_read( $c );
				if ( is_wp_error( $info ) ) {
					WP_CLI::error( $info->get_error_message() );
				}
				WP_CLI::success( sprintf( 'Registro raggiungibile: %d colonne, %d righe dati, %d chiavi; nessuna scrittura eseguita.', count( $info['head'] ), $info['rows'], count( $info['keys'] ) ) );
				return;
			case 'run':
				$n = tmk_conv_sync_sweep();
				WP_CLI::success( 'Passaggio eseguito (' . (int) $n . ' tra accodati e sincronizzati).' );
				return;
			case 'retry':
				$where = isset( $assoc['id'] ) ? $wpdb->prepare( 'submission_id = %d', (int) $assoc['id'] ) : "status IN ('error','failed')";
				$n     = $wpdb->query( 'UPDATE ' . tmk_conv_sync_table() . " SET status='pending', attempts=0, next_try='1970-01-01 00:00:00' WHERE $where AND status <> 'done'" ); // phpcs:ignore WordPress.DB.PreparedSQL
				WP_CLI::success( (int) $n . ' invii rimessi in coda.' );
				return;
		}
		WP_CLI::error( 'Comandi: status | configure | test | run | retry [--id=N]' );
	} );
}
