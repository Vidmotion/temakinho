<?php
/**
 * Convenzioni — verifica (e rimozione) di un invio di PROVA nell'archivio Elementor Submissions.
 *
 *   wp --user=1 eval-file prova-invio.php                          ultimi invii di prova (solo ID e data)
 *   TMK_CONV_SID=<id> wp --user=1 eval-file prova-invio.php        dettaglio: campi e registro azioni
 *   TMK_CONV_SID=<id> wp --user=1 eval-file prova-invio.php elimina
 *
 * Un invio e' «di prova» solo se: modulo 9fc0788, nome azienda che inizia con «TEST» e referente
 * info@temakinho.com (unico indirizzo ammesso per le prove). Altrimenti non viene mostrato ne'
 * eliminato. Eliminazione con la procedura di Elementor (valori, registro azioni, note).
 */
if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }
global $wpdb;
$form = '9fc0788';
$sid  = (int) getenv( 'TMK_CONV_SID' );
$del  = in_array( 'elimina', (array) ( $args ?? array() ), true );
$vals = function ( $id ) use ( $wpdb ) {
	$out = array();
	foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT `key`, value FROM {$wpdb->prefix}e_submissions_values WHERE submission_id = %d", $id ) ) as $r ) {
		$out[ $r->key ] = $r->value;
	}
	return $out;
};
$is_test = function ( $id ) use ( $wpdb, $form, $vals ) {
	$el = $wpdb->get_var( $wpdb->prepare( "SELECT element_id FROM {$wpdb->prefix}e_submissions WHERE id = %d", $id ) );
	$v  = $vals( $id );
	return $form === $el && 0 === strpos( (string) ( $v['azienda'] ?? '' ), 'TEST' ) && 'info@temakinho.com' === strtolower( (string) ( $v['email'] ?? '' ) );
};

if ( ! $sid ) {
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, created_at_gmt FROM {$wpdb->prefix}e_submissions WHERE element_id = %s ORDER BY id DESC LIMIT 20", $form ) );
	$n    = 0;
	foreach ( $rows as $r ) {
		if ( $is_test( (int) $r->id ) ) {
			WP_CLI::log( "invio di prova #{$r->id}  {$r->created_at_gmt} UTC" );
			$n++;
		}
	}
	WP_CLI::log( $n ? "$n invii di prova fra gli ultimi 20." : 'Nessun invio di prova fra gli ultimi 20.' );
	return;
}
if ( ! $is_test( $sid ) ) {
	WP_CLI::error( "L'invio #$sid non esiste o non e' un invio di prova (modulo, «TEST», info@temakinho.com): niente mostrato." );
}
$s = $wpdb->get_row( $wpdb->prepare( "SELECT id, created_at_gmt, status, form_name, post_id FROM {$wpdb->prefix}e_submissions WHERE id = %d", $sid ) );
WP_CLI::log( "invio #{$s->id}  {$s->created_at_gmt} UTC  stato={$s->status}  modulo='{$s->form_name}'  pagina={$s->post_id}" );
foreach ( $vals( $sid ) as $k => $v ) {
	WP_CLI::log( "  $k = $v" );
}
foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT action_name, status, log FROM {$wpdb->prefix}e_submissions_actions_log WHERE submission_id = %d ORDER BY id", $sid ) ) as $a ) {
	WP_CLI::log( "  azione {$a->action_name}: {$a->status}" . ( '' !== (string) $a->log ? ' — ' . $a->log : '' ) );
}
if ( ! $del ) {
	return;
}
\ElementorPro\Modules\Forms\Submissions\Database\Query::get_instance()->delete_submission( $sid );
$left = (int) $wpdb->get_var( $wpdb->prepare( "SELECT (SELECT COUNT(*) FROM {$wpdb->prefix}e_submissions WHERE id = %d) + (SELECT COUNT(*) FROM {$wpdb->prefix}e_submissions_values WHERE submission_id = %d) + (SELECT COUNT(*) FROM {$wpdb->prefix}e_submissions_actions_log WHERE submission_id = %d)", $sid, $sid, $sid ) );
$left ? WP_CLI::error( "Rimozione incompleta: $left righe residue." ) : WP_CLI::success( "Invio di prova #$sid rimosso (righe residue: 0)." );
