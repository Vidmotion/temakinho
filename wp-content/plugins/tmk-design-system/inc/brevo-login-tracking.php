<?php
/**
 * Plugin Brevo (mailin) — niente identificazione degli account WordPress verso Brevo.
 *
 * Il plugin, con il tracciamento "marketing automation" attivo, invia a Brevo email e username di
 * chiunque entri in WordPress (azione wp_login → SIB_Manager::sib_wp_login_identify →
 * in-automate.brevo.com, con un cookie email_id) e, alle pagine pubbliche viste da un utente
 * collegato, aggiunge la sua email allo script di tracciamento (email_id / userId).
 *
 * Qui si tolgono solo questi due comportamenti, senza modificare i file del plugin (che gli
 * aggiornamenti sovrascriverebbero): restano invariati l'invio delle email transazionali (WP Mail
 * SMTP), i moduli, le liste e il tracciamento anonimo dei visitatori non collegati.
 *
 * @package TMK_Design_System
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Rimuove dal hook i callback del plugin Brevo con quel nome di metodo. */
function tmk_brevo_unhook( $hook, $method ) {
	global $wp_filter;
	if ( empty( $wp_filter[ $hook ] ) ) {
		return 0;
	}
	$n = 0;
	foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
		foreach ( $callbacks as $cb ) {
			$f = $cb['function'];
			if ( is_array( $f ) && is_object( $f[0] ) && 'SIB_Manager' === get_class( $f[0] ) && $method === $f[1] ) {
				remove_action( $hook, $f, $priority );
				$n++;
			}
		}
	}
	return $n;
}

// Prima di qualunque callback del login (il plugin si aggancia con priorita' 10).
add_action( 'wp_login', function () {
	tmk_brevo_unhook( 'wp_login', 'sib_wp_login_identify' );
}, 0 );

// Pagine pubbliche viste da un utente collegato: nessuno script che porti la sua email a Brevo.
add_action( 'wp_head', function () {
	if ( is_user_logged_in() ) {
		tmk_brevo_unhook( 'wp_head', 'install_ma_script' );
	}
}, 0 );
