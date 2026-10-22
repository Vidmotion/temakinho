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
