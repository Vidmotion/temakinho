<?php
/**
 * Plugin Name: TMK Staging guard (SOLO STAGING)
 * Description: Posta verso Mailpit, nessuna richiesta HTTP esterna, niente cron, noindex, punti di interruzione per le prove dell'archivio CV. Non va MAI installato in produzione.
 *
 * Montato in sola lettura da ops/cv-access/staging/override.yml. Seconda rete di sicurezza: la
 * prima e' che il container WordPress di staging e' collegato solo a reti `internal: true`.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TMK_STAGING', true );
if ( ! defined( 'DISABLE_WP_CRON' ) ) {
	define( 'DISABLE_WP_CRON', true );
}

/*
 * WP Mail SMTP: le costanti prevalgono sulle impostazioni salvate nel database (in produzione il
 * mailer e' Brevo via API). Qui tutto va a Mailpit, senza autenticazione.
 */
foreach ( array(
	'WPMS_ON'            => true,
	'WPMS_MAILER'        => 'smtp',
	'WPMS_SMTP_HOST'     => 'mailpit',
	'WPMS_SMTP_PORT'     => 1025,
	'WPMS_SSL'           => '',
	'WPMS_SMTP_AUTH'     => false,
	'WPMS_SMTP_AUTOTLS'  => false,
	'WPMS_DO_NOT_SEND'   => false,
) as $k => $v ) {
	if ( ! defined( $k ) ) {
		define( $k, $v );
	}
}
add_action( 'phpmailer_init', function ( $m ) {
	$m->isSMTP();
	$m->Host        = 'mailpit';
	$m->Port        = 1025;
	$m->SMTPAuth    = false;
	$m->SMTPSecure  = '';
	$m->SMTPAutoTLS = false;
}, PHP_INT_MAX );

/* Nessuna richiesta HTTP verso l'esterno (Brevo, licenze, aggiornamenti): solo rete locale. */
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	$host = (string) wp_parse_url( $url, PHP_URL_HOST );
	if ( in_array( $host, array( 'localhost', '127.0.0.1', 'wordpress', 'mailpit' ), true ) ) {
		return $pre;
	}
	return new WP_Error( 'tmk_staging_offline', 'Staging: richieste esterne disattivate (' . $host . ')' );
}, 1, 3 );

add_filter( 'wp_robots', 'wp_robots_no_robots' );

add_action( 'admin_notices', function () {
	echo '<div class="notice notice-warning"><p><strong>STAGING</strong> — ambiente di prova isolato: posta intercettata, nessuna connessione esterna.</p></div>';
} );

/*
 * Punti di interruzione per le prove (inc/cv-storage.php -> tmk_cv_store_checkpoint()).
 * File /tmp/tmk-test-checkpoint nel container: "<fase> <azione>", con azione = exit | sleep:<s>.
 * Esempio: "copied exit" interrompe il processo subito dopo la copia, come un crash.
 */
add_action( 'tmk_cv_store_checkpoint', function ( $stage, $id ) {
	$f = '/tmp/tmk-test-checkpoint';
	if ( ! is_file( $f ) ) {
		return;
	}
	$spec = preg_split( '/\s+/', trim( (string) file_get_contents( $f ) ) );
	if ( ( $spec[0] ?? '' ) !== $stage ) {
		return;
	}
	$action = $spec[1] ?? 'exit';
	if ( 'exit' === $action ) {
		@unlink( $f ); // una sola interruzione per prova
		fwrite( STDERR, "TEST: interruzione simulata a $stage\n" );
		exit( 3 );
	}
	if ( 0 === strpos( $action, 'sleep:' ) ) {
		sleep( (int) substr( $action, 6 ) );
	}
}, 10, 2 );

/*
 * Salvataggio del riferimento fallito, per le prove: con /tmp/tmk-test-fail-ref presente ogni
 * scrittura di _wp_attached_file verso l'archivio riservato viene rifiutata dal database simulato.
 */
add_filter( 'update_post_metadata', function ( $check, $object_id, $meta_key, $meta_value ) {
	if ( '_wp_attached_file' === $meta_key && is_file( '/tmp/tmk-test-fail-ref' ) && is_string( $meta_value ) && false !== strpos( $meta_value, '/tmk-private/' ) ) {
		return false;
	}
	return $check;
}, 10, 4 );
