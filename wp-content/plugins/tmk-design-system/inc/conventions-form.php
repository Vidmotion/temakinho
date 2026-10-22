<?php
/**
 * Convenzioni aziendali — validazione lato server del modulo Elementor "Convenzioni aziendali".
 *
 * Il modulo (pagina /convenzioni-aziendali/, elemento 9fc0788) aveva nel browser controlli che il
 * server non ripeteva: numero di dipendenti, URL del sito e citta' arrivavano accettati comunque a
 * chi posta direttamente a admin-ajax.php. Qui si rivalidano i VALORI ORIGINALI inviati (il
 * `raw_value` del record, non quello gia' ripulito da Elementor) sull'hook
 * `elementor_pro/forms/validation`: se si aggiunge anche un solo errore Elementor si ferma prima di
 * ogni azione, quindi un invio non valido non viene salvato in Submissions e non genera email.
 *
 * Solo per questo modulo: gli altri moduli Elementor del sito non vengono toccati, e i campi gestiti
 * da Elementor (obbligatorieta', accettazione privacy, honeypot) restano a Elementor.
 *
 * L'elenco delle citta' ammesse e' quello configurato nel campo `citta` del modulo, letto dal
 * server (le impostazioni del widget salvate nella pagina), mai dalla richiesta: chi gestisce il
 * sito lo aggiorna da Elementor quando cambia l'elenco dei ristoranti.
 *
 * @package TMK_Design_System
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** ID (elemento Elementor) dei moduli convenzioni da validare. */
function tmk_conv_form_ids() {
	return (array) apply_filters( 'tmk_conv_form_ids', array( '9fc0788' ) );
}

/** Valore originale inviato, senza le barre aggiunte da WordPress. */
function tmk_conv_raw( $fields, $id ) {
	if ( ! isset( $fields[ $id ] ) ) {
		return null;
	}
	$v = $fields[ $id ]['raw_value'] ?? '';
	return is_array( $v ) ? array_map( 'wp_unslash', $v ) : wp_unslash( (string) $v );
}

/** Opzioni configurate di un campo a scelta (una per riga, eventualmente "etichetta|valore"). */
function tmk_conv_field_options( $record, $id ) {
	foreach ( (array) $record->get_form_settings( 'form_fields' ) as $f ) {
		if ( ( $f['custom_id'] ?? '' ) === $id ) {
			$out = array();
			foreach ( preg_split( '/\r\n|\r|\n/', (string) ( $f['field_options'] ?? '' ) ) as $line ) {
				$line = trim( $line );
				if ( '' === $line ) {
					continue;
				}
				$parts = explode( '|', $line, 2 );
				$out[] = trim( isset( $parts[1] ) ? $parts[1] : $parts[0] );
			}
			return $out;
		}
	}
	return array();
}

/**
 * Regole. Ogni funzione riceve il valore originale e restituisce '' se valido o il messaggio.
 * Esposte come funzioni pure per poterle provare senza Elementor.
 */
function tmk_conv_check_text( $v, $label, $required = true ) {
	$v = is_string( $v ) ? trim( $v ) : '';
	if ( '' === $v ) {
		return $required ? 'Questo campo è obbligatorio.' : '';
	}
	if ( preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $v ) ) {
		return 'Il campo contiene caratteri non ammessi.';
	}
	if ( mb_strlen( $v ) < 2 ) {
		return 'Indica ' . $label . ' per esteso.';
	}
	if ( mb_strlen( $v ) > 200 ) {
		return 'Massimo 200 caratteri.';
	}
	return '';
}

function tmk_conv_check_url( $v ) {
	$v = is_string( $v ) ? trim( $v ) : '';
	if ( '' === $v ) {
		return ''; // facoltativo
	}
	$msg = 'Inserisci l’indirizzo completo del sito, ad esempio https://www.azienda.it, oppure lascia il campo vuoto.';
	if ( strlen( $v ) > 2048 || preg_match( '/\s/', $v ) || false === filter_var( $v, FILTER_VALIDATE_URL ) ) {
		return $msg;
	}
	$p = wp_parse_url( $v );
	if ( empty( $p['scheme'] ) || ! in_array( strtolower( $p['scheme'] ), array( 'http', 'https' ), true ) || empty( $p['host'] ) || false === strpos( $p['host'], '.' ) || isset( $p['user'] ) || isset( $p['pass'] ) ) {
		return $msg;
	}
	return '';
}

function tmk_conv_check_email( $v ) {
	$v = is_string( $v ) ? trim( $v ) : '';
	if ( '' === $v ) {
		return 'Questo campo è obbligatorio.';
	}
	return is_email( $v ) && strlen( $v ) <= 254 ? '' : 'Inserisci un indirizzo email valido, ad esempio nome@azienda.it.';
}

function tmk_conv_check_employees( $v ) {
	$v = is_string( $v ) ? trim( $v ) : '';
	if ( '' === $v ) {
		return 'Questo campo è obbligatorio.';
	}
	// Solo cifre: niente segno, decimali, notazione esponenziale o separatori.
	if ( ! preg_match( '/^[0-9]{1,7}$/', $v ) || (int) $v < 1 ) {
		return 'Indica il numero di dipendenti con un numero intero, almeno 1 (senza punti o virgole).';
	}
	return '';
}

function tmk_conv_check_cities( $v, array $allowed ) {
	$list = is_array( $v ) ? $v : ( '' === trim( (string) $v ) ? array() : array( (string) $v ) );
	$list = array_values( array_filter( array_map( 'trim', array_map( 'strval', $list ) ), 'strlen' ) );
	if ( ! $list ) {
		return 'Seleziona almeno una città di interesse.';
	}
	if ( ! $allowed ) {
		return 'Elenco delle città non disponibile. Riprova più tardi.';
	}
	foreach ( $list as $c ) {
		if ( ! in_array( $c, $allowed, true ) ) {
			return 'Scegli le città dall’elenco proposto.';
		}
	}
	if ( count( $list ) !== count( array_unique( $list ) ) ) {
		return 'Ogni città va indicata una sola volta.';
	}
	return '';
}

add_action( 'elementor_pro/forms/validation', 'tmk_conv_validate', 10, 2 );
function tmk_conv_validate( $record, $ajax_handler ) {
	if ( ! in_array( (string) $record->get_form_settings( 'id' ), tmk_conv_form_ids(), true ) ) {
		return;
	}
	$fields = (array) $record->get( 'fields' );
	// Se un campo atteso manca dalla configurazione del modulo non si puo' validare: si rifiuta
	// l'invio (e lo si registra) invece di salvare dati non controllati.
	foreach ( array( 'azienda', 'sito_web', 'email', 'dipendenti', 'settore', 'citta' ) as $id ) {
		if ( ! array_key_exists( $id, $fields ) ) {
			error_log( 'tmk_conv_validate: campo "' . $id . '" assente nel modulo convenzioni' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			$ajax_handler->add_error_message( 'Il modulo non è al momento disponibile. Riprova più tardi.' );
			$ajax_handler->add_error( '__form', 'configurazione' );
			return;
		}
	}
	$checks = array(
		'azienda'    => tmk_conv_check_text( tmk_conv_raw( $fields, 'azienda' ), 'il nome dell’azienda' ),
		'sito_web'   => tmk_conv_check_url( tmk_conv_raw( $fields, 'sito_web' ) ),
		'email'      => tmk_conv_check_email( tmk_conv_raw( $fields, 'email' ) ),
		'dipendenti' => tmk_conv_check_employees( tmk_conv_raw( $fields, 'dipendenti' ) ),
		'settore'    => tmk_conv_check_text( tmk_conv_raw( $fields, 'settore' ), 'il settore' ),
		'citta'      => tmk_conv_check_cities( tmk_conv_raw( $fields, 'citta' ), tmk_conv_field_options( $record, 'citta' ) ),
	);
	foreach ( $checks as $id => $msg ) {
		if ( '' !== $msg ) {
			$ajax_handler->add_error( $id, $msg );
		}
	}
}

/* ------------------------------------------------------------------------------------------------
 * 1.23.0 — identificativi, notifiche e limiti dell'invio
 *
 * - L'ID richiesta (CONV-AAAAMMGG-XXXXXXXX) lo genera il server: quello calcolato dal browser era
 *   prevedibile e ripetibile. Resta solo un riferimento leggibile: la chiave e' il Submission ID.
 * - Dopo il salvataggio in Submissions (prima azione del modulo, priorita' 0 in Elementor) se ne
 *   ricava il Submission ID, che le email ricevono al posto di %%TMK_SUBMISSION_ID%% e
 *   %%TMK_SUBMISSION_URL%% (link diretto all'invio nell'archivio riservato).
 * - Mittente: WP Mail SMTP forza info@temakinho.com su ogni email del sito. Solo le due email di
 *   questo modulo partono da tmk_conv_sender() (info@temakinho.it): il modulo le marca con
 *   un'intestazione interna che viene tolta all'ultimo momento, quando il From viene sostituito.
 *   Candidature, recupero password e tutte le altre email restano invariate.
 * - La conferma va all'indirizzo scritto nel modulo, quindi limita quante email un estraneo puo'
 *   far partire verso uno stesso indirizzo (o in tutto) prima di qualunque salvataggio.
 * ---------------------------------------------------------------------------------------------- */

/** Mittente delle email del modulo convenzioni (dominio autenticato in Brevo). */
function tmk_conv_sender() {
	$s = (string) apply_filters( 'tmk_conv_sender_email', 'info@temakinho.it' );
	return is_email( $s ) ? $s : '';
}

function tmk_conv_is_form( $record ) {
	return is_object( $record ) && in_array( (string) $record->get_form_settings( 'id' ), tmk_conv_form_ids(), true );
}

/** Stato dell'invio in corso (una richiesta HTTP = un invio). */
function tmk_conv_state( $set = null ) {
	static $state = array();
	if ( is_array( $set ) ) {
		$state = $set;
	}
	return $state;
}

/** Limiti: per indirizzo in 24 ore, per IP in un'ora, in tutto in un'ora. */
function tmk_conv_limits() {
	return (array) apply_filters( 'tmk_conv_limits', array( 'email_day' => 3, 'ip_hour' => 10, 'all_hour' => 50 ) );
}

function tmk_conv_limit_keys( $email ) {
	$ip = class_exists( '\ElementorPro\Core\Utils' ) ? (string) \ElementorPro\Core\Utils::get_client_ip() : (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$h  = function ( $v ) {
		return substr( hash_hmac( 'sha256', strtolower( trim( (string) $v ) ), wp_salt( 'nonce' ) ), 0, 24 );
	};
	return array(
		'email_day' => array( 'tmk_conv_rl_e_' . $h( $email ), DAY_IN_SECONDS ),
		'ip_hour'   => array( 'tmk_conv_rl_i_' . $h( $ip ), HOUR_IN_SECONDS ),
		'all_hour'  => array( 'tmk_conv_rl_all', HOUR_IN_SECONDS ),
	);
}

/** '' se l'invio puo' procedere, altrimenti il messaggio per chi compila. */
function tmk_conv_rate_check( $email ) {
	$limits = tmk_conv_limits();
	foreach ( tmk_conv_limit_keys( $email ) as $k => $spec ) {
		$hit = get_transient( $spec[0] );
		if ( is_array( $hit ) && isset( $limits[ $k ] ) && (int) $hit['n'] >= (int) $limits[ $k ] ) {
			return 'email_day' === $k
				? 'Abbiamo già ricevuto più richieste da questo indirizzo nelle ultime 24 ore: le valuteremo e ti ricontatteremo. Per aggiungere informazioni scrivi a ' . tmk_conv_sender() . '.'
				: 'In questo momento non riusciamo a ricevere altre richieste. Riprova tra un’ora.';
		}
	}
	return '';
}

function tmk_conv_rate_hit( $email ) {
	foreach ( tmk_conv_limit_keys( $email ) as $spec ) {
		$hit = get_transient( $spec[0] );
		$hit = is_array( $hit ) ? $hit : array( 'n' => 0, 'until' => time() + $spec[1] );
		$hit['n']++;
		set_transient( $spec[0], $hit, max( 1, (int) $hit['until'] - time() ) ); // finestra fissa dal primo invio
	}
}

add_action( 'elementor_pro/forms/validation', 'tmk_conv_validate_rate', 20, 2 );
function tmk_conv_validate_rate( $record, $ajax_handler ) {
	if ( ! tmk_conv_is_form( $record ) || ! empty( $ajax_handler->errors ) ) {
		return; // prima gli errori dei campi
	}
	$msg = tmk_conv_rate_check( tmk_conv_raw( (array) $record->get( 'fields' ), 'email' ) );
	if ( '' !== $msg ) {
		$ajax_handler->add_error_message( $msg );
		$ajax_handler->add_error( '__form', 'limite' );
	}
}

add_action( 'elementor_pro/forms/process', 'tmk_conv_process', 10, 2 );
function tmk_conv_process( $record, $ajax_handler ) {
	if ( ! tmk_conv_is_form( $record ) ) {
		return;
	}
	$req = 'CONV-' . wp_date( 'Ymd' ) . '-' . strtoupper( bin2hex( random_bytes( 4 ) ) );
	$record->update_field( 'id_richiesta', 'value', $req );
	$record->update_field( 'id_richiesta', 'raw_value', $req );
	tmk_conv_state( array( 'request_id' => $req, 'submission_id' => 0 ) );
}

/** Submission ID dell'invio appena salvato (Elementor non lo espone), cercato per ID richiesta. */
add_action( 'elementor_pro/forms/actions/after_run', 'tmk_conv_after_action', 20, 2 );
function tmk_conv_after_action( $action, $exception = null ) {
	$st = tmk_conv_state();
	if ( empty( $st['request_id'] ) || $exception || ! is_object( $action ) || 'save-to-database' !== $action->get_name() ) {
		return;
	}
	global $wpdb;
	$ids = "'" . implode( "','", array_map( 'esc_sql', tmk_conv_form_ids() ) ) . "'";
	$st['submission_id'] = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT s.id FROM {$wpdb->prefix}e_submissions s JOIN {$wpdb->prefix}e_submissions_values v ON v.submission_id = s.id AND v.`key` = 'id_richiesta' WHERE s.element_id IN ($ids) AND v.value = %s ORDER BY s.id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$st['request_id']
	) );
	tmk_conv_state( $st );
}

add_action( 'elementor_pro/forms/new_record', 'tmk_conv_new_record', 10, 2 );
function tmk_conv_new_record( $record, $ajax_handler ) {
	if ( ! tmk_conv_is_form( $record ) ) {
		return;
	}
	tmk_conv_rate_hit( tmk_conv_raw( (array) $record->get( 'fields' ), 'email' ) );
	$st = tmk_conv_state();
	if ( ! empty( $st['submission_id'] ) ) {
		do_action( 'tmk_conv_saved', (int) $st['submission_id'], $record );
	}
	tmk_conv_state( array() );
}

/** Segnaposto del Submission ID nelle email + mittente .it marcato per wp_mail. */
add_filter( 'elementor_pro/forms/wp_mail_fields', 'tmk_conv_mail_fields', 10, 2 );
function tmk_conv_mail_fields( $fields, $record ) {
	if ( ! tmk_conv_is_form( $record ) ) {
		return $fields;
	}
	$sid = (int) ( tmk_conv_state()['submission_id'] ?? 0 );
	$rep = array(
		'%%TMK_SUBMISSION_ID%%'  => $sid ? (string) $sid : 'non disponibile',
		'%%TMK_SUBMISSION_URL%%' => admin_url( 'admin.php?page=e-form-submissions' . ( $sid ? '#/' . $sid : '' ) ),
	);
	foreach ( array( 'email_subject', 'email_content' ) as $k ) {
		if ( isset( $fields[ $k ] ) ) {
			$fields[ $k ] = strtr( (string) $fields[ $k ], $rep );
		}
	}
	$sender = tmk_conv_sender();
	if ( '' !== $sender ) {
		$fields['email_from'] = $sender;
		tmk_conv_sender_token( true );
	}
	return $fields;
}

/**
 * Marcatura delle email del modulo: un valore casuale emesso in questa richiesta. $issue = true ne
 * emette uno nuovo, una stringa lo verifica e lo consuma; senza argomenti restituisce quello in
 * attesa di essere messo nelle intestazioni (e lo azzera).
 */
function tmk_conv_sender_token( $arg = null ) {
	static $pending = '', $issued = array();
	if ( true === $arg ) {
		$pending            = bin2hex( random_bytes( 12 ) );
		$issued[ $pending ] = true;
		return $pending;
	}
	if ( is_string( $arg ) ) {
		$ok = isset( $issued[ $arg ] );
		unset( $issued[ $arg ] );
		return $ok;
	}
	$t       = $pending;
	$pending = '';
	return $t;
}

/* Il filtro delle intestazioni viene subito dopo quello dei campi, nella stessa email. */
add_filter( 'elementor_pro/forms/wp_mail_headers', 'tmk_conv_mail_headers', 99 );
function tmk_conv_mail_headers( $headers ) {
	$token = tmk_conv_sender_token();
	if ( '' === $token ) {
		return $headers;
	}
	if ( is_array( $headers ) ) {
		$headers[] = 'X-TMK-Sender: ' . $token;
		return $headers;
	}
	return rtrim( (string) $headers, "\r\n" ) . "\r\nX-TMK-Sender: " . $token . "\r\n";
}

/*
 * Ultimo passaggio prima dell'invio (dopo WP Mail SMTP, che forza il suo mittente): toglie sempre
 * la marcatura e applica il mittente solo se il valore e' uno di quelli emessi dal modulo in questa
 * stessa richiesta. Un'intestazione aggiunta da altro codice non sceglie il From.
 */
add_action( 'phpmailer_init', 'tmk_conv_apply_sender', PHP_INT_MAX );
function tmk_conv_apply_sender( $phpmailer ) {
	$want = '';
	$keep = array();
	foreach ( (array) $phpmailer->getCustomHeaders() as $h ) {
		if ( 0 === strcasecmp( trim( (string) $h[0] ), 'X-TMK-Sender' ) ) {
			$want = trim( (string) $h[1] );
		} else {
			$keep[] = $h;
		}
	}
	if ( '' === $want ) {
		return;
	}
	$phpmailer->clearCustomHeaders();
	foreach ( $keep as $h ) {
		$phpmailer->addCustomHeader( $h[0], $h[1] );
	}
	$sender = tmk_conv_sender();
	if ( '' === $sender || ! tmk_conv_sender_token( $want ) ) {
		return;
	}
	$old = $phpmailer->From;
	$phpmailer->setFrom( $sender, $phpmailer->FromName, false );
	if ( $phpmailer->Sender === $old ) {
		$phpmailer->Sender = $sender; // return-path allineato, come fa WP Mail SMTP con il proprio mittente
	}
}
