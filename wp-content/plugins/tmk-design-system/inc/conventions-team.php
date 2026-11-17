<?php
/** Consultazione privata delle richieste raccolte dal modulo convenzioni. */
defined( 'ABSPATH' ) || exit;

const TMK_CONV_TEAM_ROLE = 'tmk_conv_reader';
const TMK_CONV_TEAM_CAP  = 'tmk_read_conventions';

function tmk_conv_team_url() {
	return home_url( '/area-team/convenzioni/' );
}

add_action( 'init', static function () {
	if ( ! get_role( TMK_CONV_TEAM_ROLE ) ) {
		add_role( TMK_CONV_TEAM_ROLE, 'Consultazione convenzioni', array( TMK_CONV_TEAM_CAP => true ) );
	}
} );

function tmk_conv_team_reader( $user = null ) {
	$user = $user ?: wp_get_current_user();
	return $user instanceof WP_User && in_array( TMK_CONV_TEAM_ROLE, $user->roles, true ) && ! user_can( $user, 'manage_options' );
}

add_filter( 'login_redirect', static function ( $url, $requested, $user ) {
	return tmk_conv_team_reader( $user ) ? tmk_conv_team_url() : $url;
}, 20, 3 );

add_action( 'admin_init', static function () {
	if ( tmk_conv_team_reader() ) {
		wp_die( 'Questo account può consultare soltanto il registro convenzioni.', '', array( 'response' => 403 ) );
	}
}, 0 );
add_filter( 'show_admin_bar', static function ( $show ) {
	return tmk_conv_team_reader() ? false : $show;
} );
add_filter( 'rest_authentication_errors', static function ( $error ) {
	return tmk_conv_team_reader() ? new WP_Error( 'tmk_conv_denied', 'Accesso negato.', array( 'status' => 403 ) ) : $error;
}, 101 );
add_filter( 'wp_is_application_passwords_available_for_user', static function ( $allowed, $user ) {
	return tmk_conv_team_reader( $user ) ? false : $allowed;
}, 10, 2 );

add_filter( 'authenticate', static function ( $user ) {
	if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST && $user instanceof WP_User && tmk_conv_team_reader( $user ) ) {
		return new WP_Error( 'tmk_conv_denied', 'Accesso negato.' );
	}
	return $user;
}, 100 );

function tmk_conv_team_fields() {
	return array(
		'source' => 'Origine', 'created' => 'Data di invio / attivazione', 'azienda' => 'Azienda', 'email' => 'Email',
		'citta' => 'Città selezionate', 'dipendenti' => 'Dipendenti', 'settore' => 'Settore',
		'sito_web' => 'Sito web', 'id_richiesta' => 'ID richiesta', 'privacy' => 'Consenso privacy', 'excel_total' => 'Totale (Excel)',
	);
}

function tmk_conv_team_post( $key ) {
	return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
}

/** Historical rows stay outside Elementor: an activation date does not imply a form submission. */
function tmk_conv_team_history_table() {
	global $wpdb;
	return $wpdb->prefix . 'tmk_conv_history';
}

function tmk_conv_team_install_history() {
	if ( '1' === get_option( 'tmk_conv_history_schema' ) ) {
		return;
	}
	global $wpdb;
	$table = tmk_conv_team_history_table();
	$sql = "CREATE TABLE IF NOT EXISTS $table (
		source_row int unsigned NOT NULL PRIMARY KEY,
		activation_gmt datetime DEFAULT NULL,
		azienda text NOT NULL,
		email text NOT NULL,
		citta text NOT NULL,
		dipendenti text NOT NULL,
		excel_total text NOT NULL
	) ENGINE=InnoDB " . $wpdb->get_charset_collate();
	if ( false === $wpdb->query( $sql ) ) {
		wp_die( 'Archivio storico temporaneamente indisponibile.', '', array( 'response' => 503 ) );
	}
	update_option( 'tmk_conv_history_schema', '1', false );
}

/** A single row per submission. Apply filters to the pivoted fields so all columns stay available. */
function tmk_conv_team_query( $filters, $page, $export = false ) {
	global $wpdb;
	$forms = array_values( array_filter( tmk_conv_form_ids() ) );
	if ( ! $forms ) {
		return array( array(), 0, 1 );
	}
	$fields = tmk_conv_team_fields();
	unset( $fields['source'], $fields['created'], $fields['excel_total'] );
	$columns = array();
	foreach ( array_keys( $fields ) as $key ) {
		$columns[] = $wpdb->prepare( "MAX(CASE WHEN v.`key`=%s THEN v.value END) AS `$key`", $key );
	}
	$placeholders = implode( ',', array_fill( 0, count( $forms ), '%s' ) );
	$where = $wpdb->prepare( "s.element_id IN ($placeholders) AND s.status <> 'trash'", $forms );
	$live = "SELECT s.id, 'Modulo' AS source, s.created_at_gmt AS created," . implode( ',', $columns ) . ", '' AS excel_total FROM {$wpdb->prefix}e_submissions s LEFT JOIN {$wpdb->prefix}e_submissions_values v ON v.submission_id=s.id WHERE $where GROUP BY s.id,s.created_at_gmt";
	tmk_conv_team_install_history();
	$history = "SELECT source_row AS id, 'Storico Excel' AS source, activation_gmt AS created, azienda, email, citta, dipendenti, '' AS settore, '' AS sito_web, '' AS id_richiesta, '' AS privacy, excel_total FROM " . tmk_conv_team_history_table();
	$select = " FROM ($live UNION ALL $history) entries WHERE 1=1";
	foreach ( array( 'dal', 'al' ) as $key ) {
		if ( '' === $filters[ $key ] ) {
			continue;
		}
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $filters[ $key ], wp_timezone() );
		if ( ! $date || $date->format( 'Y-m-d' ) !== $filters[ $key ] ) {
			wp_die( 'Data non valida.', '', array( 'response' => 400 ) );
		}
		$op = '>=';
		if ( 'al' === $key ) {
			$date = $date->modify( '+1 day' );
			$op = '<';
		}
		$select .= $wpdb->prepare( " AND created $op %s", $date->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ) );
	}
	if ( '' !== $filters['q'] ) {
		$like = '%' . $wpdb->esc_like( $filters['q'] ) . '%';
		$select .= $wpdb->prepare( ' AND (azienda LIKE %s OR email LIKE %s OR id_richiesta LIKE %s)', $like, $like, $like );
	}
	if ( '' !== $filters['citta'] ) {
		$select .= $wpdb->prepare( ' AND citta LIKE %s', '%' . $wpdb->esc_like( $filters['citta'] ) . '%' );
	}
	$total = $export ? 0 : (int) $wpdb->get_var( 'SELECT COUNT(*)' . $select );
	$page  = min( max( 1, $page ), max( 1, (int) ceil( $total / 50 ) ) );
	$limit = $export ? '' : $wpdb->prepare( ' LIMIT 50 OFFSET %d', ( $page - 1 ) * 50 );
	$rows  = $wpdb->get_results( 'SELECT *' . $select . " ORDER BY created DESC, source ASC, CASE WHEN source = 'Storico Excel' THEN id END ASC, id DESC" . $limit, ARRAY_A );
	if ( $wpdb->last_error ) {
		wp_die( 'Registro temporaneamente indisponibile.', '', array( 'response' => 503 ) );
	}
	foreach ( $rows as &$row ) {
		if ( $row['created'] ) {
			$format = 'Storico Excel' === $row['source'] ? 'd/m/Y' : 'd/m/Y H:i';
			$row['created'] = get_date_from_gmt( $row['created'], $format );
		}
	}
	return array( $rows, $total, $page );
}

function tmk_conv_team_table( $rows, $total, $page ) {
	$pages = max( 1, (int) ceil( $total / 50 ) );
	?>
	<p><strong><?php echo esc_html( $total ); ?></strong> voci · Dalla più recente, seguite dalle voci senza data</p>
	<?php if ( $rows ) : ?>
	<div class="scroll" tabindex="0" aria-label="Tabella richieste"><table><thead><tr>
	<?php foreach ( tmk_conv_team_fields() as $label ) : ?><th scope="col"><?php echo esc_html( $label ); ?></th><?php endforeach; ?>
	</tr></thead><tbody>
	<?php foreach ( $rows as $row ) : ?><tr><?php foreach ( tmk_conv_team_fields() as $key => $label ) : ?><td><?php echo esc_html( $row[ $key ] ?? '' ); ?></td><?php endforeach; ?></tr><?php endforeach; ?>
	</tbody></table></div>
	<?php else : ?><p>Nessuna richiesta corrisponde ai filtri selezionati.</p><?php endif; ?>
	<?php if ( $pages > 1 ) : ?><nav aria-label="Pagine"><?php if ( $page > 1 ) : ?><button form="filters" name="pagina" value="<?php echo (int) $page - 1; ?>">Precedente</button><?php endif; ?>
	Pagina <?php echo (int) $page; ?> di <?php echo (int) $pages; ?>
	<?php if ( $page < $pages ) : ?><button form="filters" name="pagina" value="<?php echo (int) $page + 1; ?>">Successiva</button><?php endif; ?></nav><?php endif; ?>
	<?php
}

add_action( 'template_redirect', static function () {
	$path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
	if ( untrailingslashit( (string) $path ) !== untrailingslashit( wp_parse_url( tmk_conv_team_url(), PHP_URL_PATH ) ) ) {
		return;
	}
	nocache_headers();
	header( 'Cache-Control: private, no-store, max-age=0, must-revalidate' );
	header( 'X-Robots-Tag: noindex, nofollow, noarchive' );
	header( 'Referrer-Policy: no-referrer' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Frame-Options: DENY' );
	if ( ! is_user_logged_in() ) {
		auth_redirect();
		exit;
	}
	if ( ! current_user_can( TMK_CONV_TEAM_CAP ) && ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Accesso riservato al team convenzioni.', '', array( 'response' => 403 ) );
	}
	$action = tmk_conv_team_post( 'azione' );
	if ( 'POST' === $_SERVER['REQUEST_METHOD'] && ! wp_verify_nonce( tmk_conv_team_post( '_wpnonce' ), 'tmk_team_read' ) ) {
		wp_die( 'Sessione scaduta. Riapri il registro.', '', array( 'response' => 403 ) );
	}
	status_header( 200 );
	$filters = array();
	foreach ( array( 'q', 'citta', 'dal', 'al' ) as $key ) {
		$filters[ $key ] = tmk_conv_team_post( $key );
	}
	$page = max( 1, (int) tmk_conv_team_post( 'pagina' ) );
	$is_export = 'xlsx' === $action;
	list( $rows, $total, $page ) = tmk_conv_team_query( $filters, $page, $is_export );
	if ( $is_export ) {
		require __DIR__ . '/conventions-team-xlsx.php';
		tmk_conv_team_xlsx( tmk_conv_team_fields(), $rows );
		exit;
	}
	header( 'Content-Type: text/html; charset=UTF-8' );
	if ( 'lista' === $action ) {
		tmk_conv_team_table( $rows, $total, $page );
		exit;
	}
	require __DIR__ . '/conventions-team-view.php';
	exit;
}, 0 );
