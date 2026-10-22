<?php
/**
 * Pulizia dei SOLI dati di prova creati dalle verifiche tecniche del 21-22/10/2026 (produzione).
 *
 *   wp --user=1 eval-file pulizia-dati-prova.php            (elenco, nessuna modifica)
 *   wp --user=1 eval-file pulizia-dati-prova.php esegui     (eliminazione)
 *
 * Elenco chiuso di ID, ognuno con i marcatori che lo identificano come prova: se anche un solo
 * marcatore non corrisponde l'elemento viene saltato e segnalato. Nessuna ricerca per parola
 * («TEST») fuori da questo elenco. Si usano le procedure di WordPress/Elementor:
 * - wp_delete_attachment(): file e anteprime negli upload; per i CV il plugin TMK elimina anche i
 *   file nell'archivio riservato e marca il manifesto come «deleted» (il manifesto resta);
 * - wp_delete_post() per la candidatura, dopo il suo allegato;
 * - Elementor Submissions: Query::delete_submission() (valori, registro azioni, note).
 */
if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }
$run = in_array( 'esegui', (array) ( $args ?? array() ), true );
global $wpdb;
$skip = 0;
$done = array();

$attach_ok = function ( $id, $title_prefix, $file_part, $parent, $author = 1 ) {
	$p = get_post( $id );
	return $p && 'attachment' === $p->post_type && 0 === strpos( $p->post_title, $title_prefix ) && (int) $author === (int) $p->post_author
		&& false !== strpos( (string) get_post_meta( $id, '_wp_attached_file', true ), $file_part ) && (int) $p->post_parent === (int) $parent;
};
$app_ok = function ( $id, $title_prefix, $email ) {
	$p = get_post( $id );
	return $p && 'job_application' === $p->post_type && 0 === strpos( $p->post_title, $title_prefix ) && 1 === (int) $p->post_author
		&& $email === get_post_meta( $id, 'tmk_application_email', true );
};

$items = array(
	array( 'allegato 21236 (CV fittizio pubblico, verifica accesso pubblico)', fn() => $attach_ok( 21236, 'TEST – CV fittizio', 'test-cv-fittizio-', 0 ), fn() => wp_delete_attachment( 21236, true ) ),
	array( 'allegato 21237 (CV della candidatura fittizia 21238, archivio riservato)', fn() => $attach_ok( 21237, 'CV TEST Candidatura fittizia', '/tmk-private/cv/', 21238 ), fn() => wp_delete_attachment( 21237, true ) ),
	array( 'candidatura 21238 (TEST Candidatura fittizia, candidato.test@example.com)', fn() => $app_ok( 21238, 'TEST Candidatura fittizia', 'candidato.test@example.com' ) && false !== strpos( (string) get_post_meta( 21238, 'tmk_application_notes', true ), 'TEST' ), fn() => wp_delete_post( 21238, true ) ),
);
// Candidatura di prova del 22/10 (ID registrato dalla sessione in /root/tmk-ops/test-app-id) e suo CV,
// inviati dal modulo pubblico (autore 0).
$t = get_post( 21251 );
$cv = (int) get_post_meta( 21251, 'tmk_application_cv_id', true );
$items[] = array( "allegato $cv (CV della candidatura di prova 21251)", fn() => 21250 === $cv && $attach_ok( 21250, 'CV TEST TECNICO', '/tmk-private/cv/', 21251, 0 ), fn() => wp_delete_attachment( 21250, true ) );
$items[] = array( 'candidatura 21251 (TEST TECNICO Verifica area riservata CV, nel cestino)', fn() => $t && 'job_application' === $t->post_type && 'trash' === $t->post_status && 0 === strpos( $t->post_title, 'TEST TECNICO' ) && 'verifica-area-riservata@example.invalid' === get_post_meta( 21251, 'tmk_application_email', true ), fn() => wp_delete_post( 21251, true ) );

// Invii Elementor del modulo convenzioni creati dalle prove del 21/10: tutti nel cestino, utente 1,
// azienda che inizia con «TEST», email test@example.com.
$subs = $wpdb->get_results( "SELECT s.id, s.status, s.user_id, s.created_at_gmt,
	MAX(CASE WHEN v.`key`='azienda' THEN v.value END) az, MAX(CASE WHEN v.`key`='email' THEN v.value END) em
	FROM {$wpdb->prefix}e_submissions s LEFT JOIN {$wpdb->prefix}e_submissions_values v ON v.submission_id = s.id
	WHERE s.element_id = '9fc0788' AND s.created_at_gmt < '2026-10-22 00:00:00' GROUP BY s.id ORDER BY s.id" );
foreach ( $subs as $s ) {
	$items[] = array(
		"invio Elementor {$s->id} ({$s->created_at_gmt}, «" . mb_substr( (string) $s->az, 0, 40 ) . '»)',
		fn() => 'trash' === $s->status && 1 === (int) $s->user_id && 0 === strpos( (string) $s->az, 'TEST' ) && 'test@example.com' === $s->em,
		fn() => \ElementorPro\Modules\Forms\Submissions\Database\Query::get_instance()->delete_submission( (int) $s->id ),
	);
}

foreach ( $items as $it ) {
	list( $label, $check, $act ) = $it;
	if ( ! $check() ) {
		WP_CLI::warning( "SALTATO (marcatori non corrispondenti o gia' rimosso): $label" );
		$skip++;
		continue;
	}
	if ( ! $run ) {
		WP_CLI::log( "da eliminare: $label" );
		continue;
	}
	$r = $act();
	WP_CLI::log( ( false === $r || null === $r ? 'ERRORE ' : 'eliminato: ' ) . $label );
	$done[] = $label;
}
WP_CLI::log( ( $run ? 'eliminati ' . count( $done ) : 'elementi individuati ' . ( count( $items ) - $skip ) ) . ", saltati $skip" );
