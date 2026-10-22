<?php
/**
 * Accesso riservato alle candidature e ai CV — ruolo "Consultazione CV".
 *
 * Richiesta (Bea, ottobre 2026): un accesso per info@temakinho.com che serva SOLO a vedere le
 * candidature e scaricare i CV. Questo file fa esattamente questo:
 *
 *  1. ruolo `tmk_cv_viewer` con le sole capability `read` e `tmk_view_applications`;
 *     l'amministratore riceve `tmk_view_applications`. Nessun altro ruolo viene toccato.
 *  2. pagina "Candidature" (elenco + dettaglio) in sola lettura: niente modifica, cestino,
 *     esportazione o azioni di gruppo;
 *  3. download del CV solo tramite admin-post.php, con login, capability, nonce legato alla
 *     candidatura e all'utente, appartenenza dell'allegato e percorso reale nell'archivio
 *     riservato. Per chi non ha fatto login l'handler fa soltanto il reindirizzamento al login;
 *  4. per un utente che ha SOLO il ruolo di consultazione: ogni altra schermata di wp-admin,
 *     ogni admin-post e ogni chiamata AJAX non pubblica viene negata (403), la REST API
 *     autenticata e le password per applicazioni sono disattivate. Elenco bianco, non nero: un
 *     plugin che domani aggiunge una pagina con capability `read` resta comunque chiuso.
 *
 * L'archivio dei file e la migrazione dei CV gia' pubblici stanno in inc/cv-storage.php.
 *
 * @package TMK_Design_System
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/cv-storage.php';

define( 'TMK_CV_ROLE', 'tmk_cv_viewer' );
define( 'TMK_CV_CAP', 'tmk_view_applications' );
define( 'TMK_CV_ROLES_VER', '1' );
define( 'TMK_CV_PAGE', 'tmk-candidature' );
define( 'TMK_CV_TOOLS_PAGE', 'tmk-cv-migrazione' );

/* =============================================================================================
   1. RUOLO E CAPABILITY — idempotente
   ============================================================================================= */

function tmk_cv_role_caps() {
	return array(
		'read'     => true,
		TMK_CV_CAP => true,
	);
}

/**
 * Crea o riallinea il ruolo all'elenco esatto (una capability aggiunta a mano viene tolta) e da'
 * la capability all'amministratore. Esce subito se la versione e' gia' registrata.
 */
function tmk_cv_sync_roles( $force = false ) {
	if ( ! $force && TMK_CV_ROLES_VER === get_option( 'tmk_cv_roles_version' ) ) {
		return;
	}
	$caps = tmk_cv_role_caps();
	$role = get_role( TMK_CV_ROLE );
	if ( ! $role ) {
		add_role( TMK_CV_ROLE, 'Consultazione CV', $caps );
	} else {
		foreach ( array_keys( (array) $role->capabilities ) as $cap ) {
			if ( ! array_key_exists( $cap, $caps ) ) {
				$role->remove_cap( $cap );
			}
		}
		foreach ( $caps as $cap => $grant ) {
			if ( empty( $role->capabilities[ $cap ] ) ) {
				$role->add_cap( $cap, $grant );
			}
		}
	}
	$admin = get_role( 'administrator' );
	if ( $admin && empty( $admin->capabilities[ TMK_CV_CAP ] ) ) {
		$admin->add_cap( TMK_CV_CAP );
	}
	update_option( 'tmk_cv_roles_version', TMK_CV_ROLES_VER, true );
}
add_action( 'init', 'tmk_cv_sync_roles', 5 );

/** True se l'utente ha SOLO il ruolo di consultazione. */
function tmk_cv_is_viewer_only( $user = null ) {
	$user = $user ? $user : wp_get_current_user();
	return $user instanceof WP_User && $user->exists() && array( TMK_CV_ROLE ) === array_values( (array) $user->roles );
}

/** Dopo il login l'utente di consultazione arriva direttamente all'elenco. */
add_filter( 'login_redirect', function ( $redirect_to, $requested, $user ) {
	if ( $user instanceof WP_User && tmk_cv_is_viewer_only( $user ) ) {
		$req = (string) $requested;
		// Si rispetta solo un rimando a una pagina che l'utente puo' davvero aprire (es. un download).
		if ( '' !== $req && ( false !== strpos( $req, 'page=' . TMK_CV_PAGE ) || false !== strpos( $req, 'action=tmk_cv_download' ) ) ) {
			return $req;
		}
		return admin_url( 'admin.php?page=' . TMK_CV_PAGE );
	}
	return $redirect_to;
}, 10, 3 );

/* =============================================================================================
   2. CHIUSURA DI TUTTO IL RESTO PER L'UTENTE DI CONSULTAZIONE
   ============================================================================================= */

add_action( 'admin_init', 'tmk_cv_viewer_lockdown', 0 );
function tmk_cv_viewer_lockdown() {
	if ( ! tmk_cv_is_viewer_only() ) {
		return;
	}
	global $pagenow;
	$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( wp_doing_ajax() ) {
		// Lo stesso potere di un visitatore anonimo: solo le azioni AJAX che un plugin espone anche
		// senza login (moduli pubblici), piu' le due del core usate dalle schermate aperte
		// (heartbeat e generazione password del profilo), che admin-ajax.php registra DOPO admin_init.
		if ( in_array( $action, array( 'heartbeat', 'generate-password' ), true ) || ( '' !== $action && has_action( 'wp_ajax_nopriv_' . $action ) ) ) {
			return;
		}
		wp_die( '0', '', array( 'response' => 403 ) );
	}
	if ( 'admin-post.php' === $pagenow ) {
		if ( 'tmk_cv_download' === $action ) {
			return;
		}
		wp_die( 'Accesso negato', '', array( 'response' => 403 ) );
	}
	if ( 'admin.php' === $pagenow && isset( $_GET['page'] ) && TMK_CV_PAGE === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	if ( 'profile.php' === $pagenow ) {
		return;
	}
	if ( 'index.php' === $pagenow ) {
		wp_safe_redirect( admin_url( 'admin.php?page=' . TMK_CV_PAGE ) );
		exit;
	}
	wp_die( 'Accesso negato: questo account puo\' consultare soltanto le candidature.', 'Accesso negato', array( 'response' => 403 ) );
}

/** Menu ridotto a Candidature + Profilo. */
add_action( 'admin_menu', function () {
	if ( ! tmk_cv_is_viewer_only() ) {
		return;
	}
	global $menu;
	foreach ( (array) $menu as $item ) {
		if ( isset( $item[2] ) && ! in_array( $item[2], array( TMK_CV_PAGE, 'profile.php' ), true ) ) {
			remove_menu_page( $item[2] );
		}
	}
}, PHP_INT_MAX );

/** Barra di amministrazione senza scorciatoie verso schermate chiuse. */
add_action( 'admin_bar_menu', function ( $bar ) {
	if ( ! tmk_cv_is_viewer_only() ) {
		return;
	}
	foreach ( array( 'dashboard', 'appearance', 'new-content', 'comments', 'updates', 'edit', 'elementor_edit_page', 'customize' ) as $node ) {
		$bar->remove_node( $node );
	}
	$bar->add_node( array( 'parent' => 'site-name', 'id' => 'tmk-candidature', 'title' => 'Candidature', 'href' => admin_url( 'admin.php?page=' . TMK_CV_PAGE ) ) );
}, PHP_INT_MAX );

/**
 * REST API autenticata chiusa: l'account non ne ha bisogno. Priorita' 101, cioe' DOPO il controllo
 * del nonce del core (rest_cookie_check_errors, 100): una richiesta senza nonce e' gia' stata
 * declassata ad anonima e resta tale; si nega solo quella davvero autenticata come consultazione.
 */
add_filter( 'rest_authentication_errors', function ( $result ) {
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	if ( is_user_logged_in() && tmk_cv_is_viewer_only() ) {
		return new WP_Error( 'tmk_cv_rest_forbidden', 'Accesso negato.', array( 'status' => 403 ) );
	}
	return $result;
}, 101 );

add_filter( 'wp_is_application_passwords_available_for_user', function ( $available, $user ) {
	return tmk_cv_is_viewer_only( $user ) ? false : $available;
}, 10, 2 );

/* =============================================================================================
   3. LETTURA DEI DATI
   ============================================================================================= */

/** Candidatura consultabile o null: esiste, e' un job_application, non e' nel cestino. */
function tmk_cv_get_application( $id ) {
	$id   = absint( $id );
	$post = $id ? get_post( $id ) : null;
	if ( ! $post || 'job_application' !== $post->post_type || ! in_array( $post->post_status, array( 'private', 'publish' ), true ) ) {
		return null;
	}
	return $post;
}

/** ID dell'allegato CV, solo se appartiene davvero a questa candidatura. */
function tmk_cv_attachment_id( $app_id ) {
	$att = (int) get_post_meta( $app_id, 'tmk_application_cv_id', true );
	if ( ! $att || 'attachment' !== get_post_type( $att ) || (int) wp_get_post_parent_id( $att ) !== (int) $app_id ) {
		return 0;
	}
	return $att;
}

function tmk_cv_detail_url( $app_id ) {
	return admin_url( 'admin.php?page=' . TMK_CV_PAGE . '&application=' . absint( $app_id ) );
}

/** Link di download: il nonce vale per questa candidatura, per questo utente e questa sessione. */
function tmk_cv_download_url( $app_id ) {
	$app_id = absint( $app_id );
	return wp_nonce_url( admin_url( 'admin-post.php?action=tmk_cv_download&application=' . $app_id ), 'tmk_cv_download_' . $app_id );
}

/* =============================================================================================
   4. PAGINA "CANDIDATURE" — sola lettura
   ============================================================================================= */

add_action( 'admin_menu', function () {
	$hook = add_menu_page( 'Candidature', 'Candidature', TMK_CV_CAP, TMK_CV_PAGE, 'tmk_cv_render_page', 'dashicons-id-alt', 26 );
	add_action( 'load-' . $hook, 'tmk_cv_page_guard' );
} );

/** Controlli eseguiti prima dell'output, cosi' 403/404 arrivano davvero al browser. */
function tmk_cv_page_guard() {
	if ( ! current_user_can( TMK_CV_CAP ) ) {
		wp_die( 'Accesso negato', '', array( 'response' => 403 ) );
	}
	if ( isset( $_GET['application'] ) && ! tmk_cv_get_application( absint( $_GET['application'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		wp_die( 'Candidatura non trovata', '', array( 'response' => 404, 'back_link' => true ) );
	}
}

function tmk_cv_render_page() {
	if ( ! current_user_can( TMK_CV_CAP ) ) {
		wp_die( 'Accesso negato', '', array( 'response' => 403 ) );
	}
	if ( isset( $_GET['application'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		tmk_cv_render_detail( absint( $_GET['application'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	tmk_cv_render_list();
}

function tmk_cv_render_list() {
	// phpcs:disable WordPress.Security.NonceVerification -- filtri GET in sola lettura
	$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	$job    = isset( $_GET['job'] ) ? absint( $_GET['job'] ) : 0;
	$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
	// phpcs:enable
	$args = array(
		'post_type'      => 'job_application',
		'post_status'    => array( 'private', 'publish' ),
		'posts_per_page' => 30,
		'paged'          => $paged,
		'orderby'        => 'date',
		'order'          => 'DESC',
	);
	if ( '' !== $search ) {
		$args['s'] = $search;
	}
	if ( $job ) {
		$args['meta_query'] = array( array( 'key' => 'tmk_application_job_id', 'value' => $job, 'type' => 'NUMERIC' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	}
	$q    = new WP_Query( $args );
	$jobs = get_posts( array( 'post_type' => 'job', 'post_status' => array( 'publish', 'draft', 'private', 'pending', 'future' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
	?>
	<div class="wrap">
		<h1>Candidature</h1>
		<p>Consultazione in sola lettura. I CV si scaricano solo da qui: i collegamenti valgono solo per chi li apre e scadono.</p>
		<form method="get">
			<input type="hidden" name="page" value="<?php echo esc_attr( TMK_CV_PAGE ); ?>" />
			<label class="screen-reader-text" for="tmk-cv-s">Cerca</label>
			<input type="search" id="tmk-cv-s" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Nome o posizione" />
			<label class="screen-reader-text" for="tmk-cv-job">Posizione</label>
			<select id="tmk-cv-job" name="job">
				<option value="0">Tutte le posizioni</option>
				<?php foreach ( $jobs as $j ) : ?>
					<option value="<?php echo (int) $j->ID; ?>" <?php selected( $job, (int) $j->ID ); ?>><?php echo esc_html( get_the_title( $j ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( 'Filtra', 'secondary', '', false ); ?>
		</form>
		<table class="widefat striped" style="margin-top:12px">
			<thead><tr><th scope="col">Data</th><th scope="col">Candidato</th><th scope="col">Posizione</th><th scope="col">Contatti</th><th scope="col">CV</th><th scope="col"><span class="screen-reader-text">Dettaglio</span></th></tr></thead>
			<tbody>
			<?php if ( ! $q->have_posts() ) : ?>
				<tr><td colspan="6">Nessuna candidatura.</td></tr>
			<?php endif; ?>
			<?php foreach ( $q->posts as $p ) :
				$job_id = (int) get_post_meta( $p->ID, 'tmk_application_job_id', true );
				$name   = trim( get_post_meta( $p->ID, 'tmk_application_first_name', true ) . ' ' . get_post_meta( $p->ID, 'tmk_application_last_name', true ) );
				$mail   = (string) get_post_meta( $p->ID, 'tmk_application_email', true );
				$phone  = (string) get_post_meta( $p->ID, 'tmk_application_phone', true );
				$has_cv = (bool) tmk_cv_attachment_id( $p->ID );
				?>
				<tr>
					<td><?php echo esc_html( get_the_date( 'd/m/Y H:i', $p ) ); ?></td>
					<td><?php echo esc_html( '' !== $name ? $name : get_the_title( $p ) ); ?></td>
					<td><?php echo $job_id ? esc_html( get_the_title( $job_id ) ) : '&mdash;'; ?></td>
					<td><?php echo is_email( $mail ) ? '<a href="' . esc_url( 'mailto:' . $mail ) . '">' . esc_html( $mail ) . '</a>' : '&mdash;'; ?><?php echo '' !== $phone ? '<br>' . esc_html( $phone ) : ''; ?></td>
					<td><?php echo $has_cv ? '<a href="' . esc_url( tmk_cv_download_url( $p->ID ) ) . '">Scarica CV</a>' : '&mdash;'; ?></td>
					<td><a href="<?php echo esc_url( tmk_cv_detail_url( $p->ID ) ); ?>">Dettaglio</a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		if ( $q->max_num_pages > 1 ) {
			echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( paginate_links( array(
				'base'    => esc_url_raw( add_query_arg( 'paged', '%#%' ) ),
				'format'  => '',
				'current' => $paged,
				'total'   => (int) $q->max_num_pages,
			) ) ) . '</div></div>';
		}
		?>
	</div>
	<?php
}

function tmk_cv_render_detail( $id ) {
	$p = tmk_cv_get_application( $id );
	if ( ! $p ) {
		wp_die( 'Candidatura non trovata', '', array( 'response' => 404, 'back_link' => true ) );
	}
	$m = function ( $k ) use ( $p ) {
		return (string) get_post_meta( $p->ID, 'tmk_application_' . $k, true );
	};
	$job_id = (int) $m( 'job_id' );
	$cover  = $m( 'cover_letter' );
	if ( '' === $cover ) {
		$cover = (string) $p->post_content;
	}
	$email = $m( 'email' );
	// Ogni valore e' sottoposto a escaping QUI; la tabella sotto stampa solo queste stringhe.
	$rows = array(
		'Ricevuta il'              => esc_html( get_the_date( 'd/m/Y H:i', $p ) ),
		'Posizione'                => $job_id ? esc_html( get_the_title( $job_id ) ) : '&mdash;',
		'Nome'                     => esc_html( $m( 'first_name' ) ),
		'Cognome'                  => esc_html( $m( 'last_name' ) ),
		'Email'                    => is_email( $email ) ? '<a href="' . esc_url( 'mailto:' . $email ) . '">' . esc_html( $email ) . '</a>' : '&mdash;',
		'Telefono'                 => '' !== $m( 'phone' ) ? esc_html( $m( 'phone' ) ) : '&mdash;',
		'Lettera di presentazione' => nl2br( esc_html( $cover ) ),
		'Altre informazioni'       => '' !== $m( 'notes' ) ? nl2br( esc_html( $m( 'notes' ) ) ) : '&mdash;',
		'Consenso privacy'         => '' !== $m( 'privacy_at' ) ? esc_html( $m( 'privacy_at' ) ) : '&mdash;',
		'CV'                       => tmk_cv_attachment_id( $p->ID ) ? '<a class="button button-primary" href="' . esc_url( tmk_cv_download_url( $p->ID ) ) . '">Scarica CV</a>' : 'Nessun CV',
	);
	?>
	<div class="wrap">
		<h1>Candidatura</h1>
		<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . TMK_CV_PAGE ) ); ?>">&larr; Tutte le candidature</a></p>
		<table class="form-table" role="presentation"><tbody>
		<?php foreach ( $rows as $label => $html ) : ?>
			<tr><th scope="row"><?php echo esc_html( $label ); ?></th><td><?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- sottoposto a escaping sopra ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>
	</div>
	<?php
}

/* =============================================================================================
   5. DOWNLOAD
   ============================================================================================= */

/** Senza login: SOLO il reindirizzamento al login. Non legge parametri, dati o file. */
function tmk_cv_handle_download_anon() {
	auth_redirect();
	exit;
}
add_action( 'admin_post_nopriv_tmk_cv_download', 'tmk_cv_handle_download_anon' );

function tmk_cv_handle_download() {
	if ( ! current_user_can( TMK_CV_CAP ) ) {
		wp_die( 'Accesso negato', '', array( 'response' => 403 ) );
	}
	$id = isset( $_GET['application'] ) ? absint( $_GET['application'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification -- verificato sotto
	if ( ! $id ) {
		wp_die( 'Richiesta non valida', '', array( 'response' => 400 ) );
	}
	$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'tmk_cv_download_' . $id ) ) {
		wp_die( 'Collegamento scaduto o non valido. Torna all\'elenco delle candidature e riprova.', '', array( 'response' => 403, 'back_link' => true ) );
	}
	if ( ! tmk_cv_get_application( $id ) ) {
		wp_die( 'Candidatura non trovata', '', array( 'response' => 404 ) );
	}
	$att = tmk_cv_attachment_id( $id );
	if ( ! $att ) {
		wp_die( 'CV non trovato', '', array( 'response' => 404 ) );
	}
	$file = tmk_cv_store_abs_from_raw( tmk_cv_store_raw_attached_file( $att ) );
	$real = '' !== $file ? realpath( $file ) : false;
	if ( ! $real || ! is_file( $real ) || ! is_readable( $real ) ) {
		wp_die( 'CV non trovato', '', array( 'response' => 404 ) );
	}
	if ( ! tmk_cv_store_is_private_path( $real ) ) {
		// Mai servire un file fuori dall'archivio riservato (CV non ancora migrato o percorso anomalo).
		wp_die( 'CV non ancora spostato nell\'area riservata. Avvisa l\'amministratore.', '', array( 'response' => 403 ) );
	}
	$rules   = function_exists( 'tmk_cv_upload_rules' ) ? tmk_cv_upload_rules() : array( 'mimes' => array() );
	$mime    = (string) get_post_mime_type( $att );
	$ext     = strtolower( pathinfo( $real, PATHINFO_EXTENSION ) );
	if ( ! in_array( $mime, array_values( (array) $rules['mimes'] ), true ) ) {
		$mime = 'application/octet-stream';
	}
	$last  = (string) get_post_meta( $id, 'tmk_application_last_name', true );
	$utf8  = sanitize_file_name( 'CV-' . ( '' !== $last ? $last : 'candidato' ) . '-' . $id . '.' . $ext );
	$ascii = preg_replace( '/[^A-Za-z0-9._-]/', '', remove_accents( $utf8 ) );
	if ( '' === $ascii || '.' . $ext === $ascii ) {
		$ascii = 'CV-' . $id . '.' . preg_replace( '/[^a-z0-9]/', '', $ext );
	}
	while ( ob_get_level() ) {
		ob_end_clean();
	}
	nocache_headers();
	header( 'Content-Type: ' . $mime );
	header( 'Content-Length: ' . (string) filesize( $real ) );
	header( 'Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode( $utf8 ) );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
	header( 'X-Robots-Tag: noindex, nofollow' );
	readfile( $real ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
}
add_action( 'admin_post_tmk_cv_download', 'tmk_cv_handle_download' );

/* =============================================================================================
   6. ALLEGATI CV NEL RESTO DI WORDPRESS
   ============================================================================================= */

/** Nessun URL per un file dell'archivio riservato (non esiste: e' fuori dalla webroot). */
add_filter( 'wp_get_attachment_url', function ( $url, $att_id ) {
	$raw = get_post_meta( $att_id, '_wp_attached_file', true );
	if ( is_string( $raw ) && '' !== $raw && 0 === strpos( wp_normalize_path( $raw ), tmk_cv_store_dir() . '/' ) ) {
		return '';
	}
	return $url;
}, 99, 2 );

/** I CV non compaiono nella Libreria media (niente "copia URL", niente inserimento in una pagina). */
function tmk_cv_hidden_attachment_ids() {
	global $wpdb;
	return array_map( 'intval', $wpdb->get_col( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_tmk_cv'" ) );
}
add_filter( 'ajax_query_attachments_args', function ( $q ) {
	$hide = tmk_cv_hidden_attachment_ids();
	if ( $hide ) {
		$q['post__not_in'] = array_merge( isset( $q['post__not_in'] ) ? (array) $q['post__not_in'] : array(), $hide );
	}
	return $q;
} );
add_action( 'pre_get_posts', function ( $q ) {
	global $pagenow;
	if ( is_admin() && $q->is_main_query() && 'upload.php' === $pagenow ) {
		$hide = tmk_cv_hidden_attachment_ids();
		if ( $hide ) {
			$q->set( 'post__not_in', array_merge( (array) $q->get( 'post__not_in' ), $hide ) );
		}
	}
} );

/** Eliminare l'allegato elimina anche i file nell'archivio (WordPress cancella solo negli upload). */
add_action( 'delete_attachment', function ( $att_id ) {
	tmk_cv_store_delete_private_files( (int) $att_id );
}, 5 );

/** Avviso all'amministratore se l'archivio non e' utilizzabile (le candidature vengono rifiutate). */
add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s = tmk_cv_store_status();
	if ( ! $s['ready'] ) {
		echo '<div class="notice notice-error"><p><strong>Archivio CV non disponibile:</strong> ' . esc_html( $s['reason'] ) . '. Le nuove candidature vengono rifiutate finche\' il problema non e\' risolto.</p></div>';
	}
} );

/* =============================================================================================
   7. STRUMENTI › MIGRAZIONE CV (solo amministratori) + WP-CLI
   ============================================================================================= */

add_action( 'admin_menu', function () {
	$hook = add_management_page( 'Migrazione CV', 'Migrazione CV', 'manage_options', TMK_CV_TOOLS_PAGE, 'tmk_cv_render_tools' );
	add_action( 'load-' . $hook, 'tmk_cv_tools_handle_post' );
} );

function tmk_cv_tools_handle_post() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Accesso negato', '', array( 'response' => 403 ) );
	}
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! isset( $_POST['tmk_cv_op'] ) ) {
		return;
	}
	check_admin_referer( 'tmk_cv_tools' );
	$op      = sanitize_key( wp_unslash( $_POST['tmk_cv_op'] ) );
	$confirm = ! empty( $_POST['tmk_cv_confirm'] );
	$map     = array(
		'dry'      => array( 'Simulazione', function () { return tmk_cv_store_migrate_all( true ); } ),
		'copy'     => array( 'Copia nell\'area riservata', function () { return tmk_cv_store_migrate_all( false ); } ),
		'verify'   => array( 'Verifica', 'tmk_cv_store_verify' ),
		'remove'   => array( 'Rimozione copie pubbliche', function () use ( $confirm ) { return tmk_cv_store_remove_public_all( $confirm ); } ),
		'rollback' => array( 'Rollback: riferimenti pubblici', function () use ( $confirm ) { return tmk_cv_store_rollback_all( $confirm ); } ),
	);
	if ( isset( $map[ $op ] ) ) {
		$GLOBALS['tmk_cv_tools_result'] = array( 'title' => $map[ $op ][0], 'report' => call_user_func( $map[ $op ][1] ) );
	}
}

function tmk_cv_render_tools() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Accesso negato', '', array( 'response' => 403 ) );
	}
	$res    = $GLOBALS['tmk_cv_tools_result'] ?? null;
	$status = tmk_cv_store_status();
	$inv    = tmk_cv_store_inventory();
	?>
	<div class="wrap">
		<h1>Migrazione CV</h1>
		<p>Archivio riservato: <code><?php echo esc_html( $status['dir'] ); ?></code> &mdash;
			<?php echo $status['ready'] ? '<strong>disponibile</strong>' : '<strong style="color:#b32d2e">NON disponibile</strong>: ' . esc_html( $status['reason'] ); ?></p>
		<?php if ( $res ) : ?>
			<h2><?php echo esc_html( $res['title'] ); ?></h2>
			<table class="widefat striped"><thead><tr><th>Allegato</th><th>Esito</th><th>Dettaglio</th></tr></thead><tbody>
			<?php if ( ! $res['report'] ) : ?><tr><td colspan="3">Nessuna operazione necessaria.</td></tr><?php endif; ?>
			<?php foreach ( $res['report'] as $r ) : ?>
				<tr><td><?php echo (int) $r['att']; ?></td><td><?php echo ! empty( $r['ok'] ) ? 'ok' : '<strong>ERRORE</strong>'; ?></td><td><?php echo esc_html( $r['result'] ); ?></td></tr>
			<?php endforeach; ?>
			</tbody></table>
		<?php endif; ?>
		<h2>Inventario</h2>
		<p><?php foreach ( $inv['summary'] as $k => $v ) { echo esc_html( str_replace( '_', ' ', $k ) . ': ' . $v ) . ' &middot; '; } ?></p>
		<table class="widefat striped"><thead><tr><th>Allegato</th><th>Candidatura</th><th>Stato candidatura</th><th>Posizione file</th><th>File</th><th>Copie pubbliche</th><th>Manifesto</th></tr></thead><tbody>
		<?php foreach ( $inv['attachments'] as $r ) : ?>
			<tr><td><?php echo (int) $r['att']; ?></td><td><?php echo (int) $r['app']; ?></td><td><?php echo esc_html( $r['app_status'] ); ?></td><td><?php echo esc_html( $r['location'] ); ?></td><td><?php echo (int) $r['files']; ?></td><td><?php echo (int) $r['public']; ?></td><td><?php echo esc_html( $r['manifest'] ); ?></td></tr>
		<?php endforeach; ?>
		<?php foreach ( $inv['orphan_files'] as $o ) : ?>
			<tr><td>&mdash;</td><td>&mdash;</td><td>file orfano</td><td><?php echo esc_html( $o['kind'] . ' ' . wp_basename( $o['path'] ) ); ?></td><td>1</td><td><?php echo 'pubblico' === $o['kind'] ? 1 : 0; ?></td><td><?php echo esc_html( $o['manifest'] ); ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>
		<form method="post" style="margin-top:16px">
			<?php wp_nonce_field( 'tmk_cv_tools' ); ?>
			<p><button class="button" name="tmk_cv_op" value="dry">1. Simulazione (non cambia nulla)</button>
			<button class="button button-primary" name="tmk_cv_op" value="copy">2. Copia nell'area riservata e aggiorna i riferimenti</button>
			<button class="button" name="tmk_cv_op" value="verify">3. Verifica</button></p>
			<p><label><input type="checkbox" name="tmk_cv_confirm" value="1" /> Backup verificato e download di prova riusciti</label><br>
			<button class="button" name="tmk_cv_op" value="remove">4. Rimuovi le copie pubbliche verificate</button>
			<button class="button" name="tmk_cv_op" value="rollback">Rollback: ricrea copie pubbliche e riferimenti (prima di tornare alla versione 1.21.3)</button></p>
		</form>
	</div>
	<?php
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'tmk-cv', function ( $args, $assoc ) {
		$code = tmk_cv_store_cli( $args, $assoc );
		if ( $code ) {
			WP_CLI::halt( $code );
		}
	} );
}
