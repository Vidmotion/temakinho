<?php
/**
 * Archivio riservato dei CV — percorso, salvataggio, inventario, migrazione, rollback.
 *
 * SOLO FUNZIONI, NESSUN HOOK. Il file e' caricato dal plugin (vedi inc/cv-access.php) ma puo'
 * essere caricato anche da solo, con il plugin disattivato, per eseguire il rollback:
 *
 *   wp --skip-plugins eval 'require "<percorso>/cv-storage.php"; tmk_cv_store_cli( array( "rollback-prepare" ), array( "yes" => 1 ) );'
 *
 * per questo non dipende da nessun'altra parte del plugin e usa un prefisso proprio
 * (tmk_cv_store_*) che non collide con le funzioni di careers-render.php di nessuna versione.
 *
 * DOVE STANNO I CV
 *   TMK_CV_PRIVATE_DIR (wp-config / docker-compose), altrimenti <genitore di ABSPATH>/tmk-private/cv.
 *   In produzione: /var/www/tmk-private/cv, volume Docker dedicato, FUORI dalla webroot: Apache non
 *   ha alcun URL che vi arrivi, quindi la protezione non dipende da un .htaccess.
 *   L'archivio e' considerato utilizzabile solo se nella cartella madre esiste `.tmk-persistent`,
 *   che l'entrypoint del container scrive soltanto quando quella cartella e' davvero un volume
 *   montato. Senza volume i CV finirebbero nello strato effimero del container e sparirebbero al
 *   deploy successivo: in quel caso la candidatura viene rifiutata, mai salvata altrove.
 *
 * MIGRAZIONE DEI CV GIA' PUBBLICI — una macchina a stati per allegato, registrata in un manifesto
 * JSON nell'archivio stesso (manifest/att-<ID>.json), scritto in modo atomico e riletto prima di
 * procedere:
 *   planned  -> elenco dei file da copiare con SHA-256 e dimensione, riferimento originale salvato
 *   copied   -> tutte le copie private presenti e verificate
 *   referenced -> _wp_attached_file punta alla copia privata (riletto dal database)
 *   public_removed -> copie pubbliche rimosse, ciascuna solo dopo una verifica completa
 *   rolled_back -> riferimento riportato a una copia pubblica (procedura di rollback)
 * Ogni passo si puo' interrompere in qualunque punto e riprendere rieseguendo lo stesso comando.
 *
 * @package TMK_Design_System
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( defined( 'TMK_CV_STORE_LOADED' ) ) {
	return;
}
define( 'TMK_CV_STORE_LOADED', '1' );
define( 'TMK_CV_STORE_SCHEMA', 1 );
define( 'TMK_CV_STORE_SENTINEL', '.tmk-persistent' );
// Nome dei file CV scritti da careers-render.php fin dalla prima versione: 32 caratteri casuali.
define( 'TMK_CV_STORE_NAME_RE', '/^[A-Za-z0-9]{32}(\.(pdf|docx?|odt)|-pdf(-\d+x\d+)?\.jpg)$/' );

/* =============================================================================================
   PERCORSI E STATO DELL'ARCHIVIO
   ============================================================================================= */

/** Cartella dei CV, senza slash finale. */
function tmk_cv_store_dir() {
	if ( defined( 'TMK_CV_PRIVATE_DIR' ) && TMK_CV_PRIVATE_DIR ) {
		return untrailingslashit( wp_normalize_path( TMK_CV_PRIVATE_DIR ) );
	}
	return untrailingslashit( wp_normalize_path( dirname( ABSPATH ) ) ) . '/tmk-private/cv';
}

/** Cartella madre (volume): contiene cv/, manifest/, il sentinella e il lock. */
function tmk_cv_store_root() {
	return dirname( tmk_cv_store_dir() );
}

function tmk_cv_store_manifest_dir() {
	return tmk_cv_store_root() . '/manifest';
}

function tmk_cv_store_uploads_basedir() {
	$up = wp_upload_dir( null, false );
	return untrailingslashit( wp_normalize_path( $up['basedir'] ) );
}

/** True se $path (esistente) sta dentro $base, dopo aver risolto link e "..". */
function tmk_cv_store_path_inside( $path, $base ) {
	$p = $path ? realpath( $path ) : false;
	$b = $base ? realpath( $base ) : false;
	if ( ! $p || ! $b ) {
		return false;
	}
	return 0 === strpos( wp_normalize_path( $p ), trailingslashit( wp_normalize_path( $b ) ) );
}

/** True se il file esiste ed e' dentro l'archivio riservato. */
function tmk_cv_store_is_private_path( $path ) {
	return tmk_cv_store_path_inside( $path, tmk_cv_store_dir() );
}

/**
 * Stato dell'archivio. 'ready' => false significa: nessun CV puo' essere salvato.
 *
 * @return array{ready:bool,reason:string,dir:string}
 */
function tmk_cv_store_status() {
	$dir  = tmk_cv_store_dir();
	$root = tmk_cv_store_root();
	$out  = array( 'ready' => false, 'reason' => '', 'dir' => $dir );
	if ( ! is_file( $root . '/' . TMK_CV_STORE_SENTINEL ) ) {
		$out['reason'] = 'volume persistente non confermato: manca ' . $root . '/' . TMK_CV_STORE_SENTINEL;
		return $out;
	}
	if ( ! wp_mkdir_p( $dir ) || ! wp_mkdir_p( tmk_cv_store_manifest_dir() ) ) {
		$out['reason'] = 'impossibile creare le cartelle dell\'archivio';
		return $out;
	}
	if ( ! is_writable( $dir ) || ! is_writable( tmk_cv_store_manifest_dir() ) ) {
		$out['reason'] = 'archivio non scrivibile dal web server';
		return $out;
	}
	// Mai dentro la webroot o negli upload: li' un file e' raggiungibile da URL.
	$web = realpath( ABSPATH );
	$up  = realpath( tmk_cv_store_uploads_basedir() );
	$rd  = realpath( $dir );
	foreach ( array( $web, $up ) as $public ) {
		if ( $public && $rd && ( wp_normalize_path( $rd ) === wp_normalize_path( $public ) || 0 === strpos( wp_normalize_path( $rd ), trailingslashit( wp_normalize_path( $public ) ) ) ) ) {
			$out['reason'] = 'l\'archivio e\' dentro una cartella pubblica (' . $public . ')';
			return $out;
		}
	}
	$out['ready'] = true;
	return $out;
}

/* =============================================================================================
   UTILITA'
   ============================================================================================= */

/** Valore grezzo di _wp_attached_file letto dal database, senza cache e senza filtri. */
function tmk_cv_store_raw_attached_file( $att_id ) {
	global $wpdb;
	$v = $wpdb->get_var( $wpdb->prepare(
		"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_wp_attached_file' ORDER BY meta_id DESC LIMIT 1",
		(int) $att_id
	) );
	return null === $v ? '' : (string) $v;
}

/** Percorso assoluto a partire dal valore grezzo (relativo agli upload oppure assoluto). */
function tmk_cv_store_abs_from_raw( $raw ) {
	$raw = (string) $raw;
	if ( '' === $raw ) {
		return '';
	}
	if ( 0 === strpos( $raw, '/' ) || preg_match( '|^[A-Za-z]:[\\\\/]|', $raw ) ) {
		return wp_normalize_path( $raw );
	}
	return tmk_cv_store_uploads_basedir() . '/' . ltrim( wp_normalize_path( $raw ), '/' );
}

/** Scrive _wp_attached_file e lo rilegge dal database: true solo se il valore salvato e' esatto. */
function tmk_cv_store_set_reference( $att_id, $value ) {
	update_post_meta( (int) $att_id, '_wp_attached_file', $value );
	wp_cache_delete( (int) $att_id, 'post_meta' );
	clean_post_cache( (int) $att_id );
	return tmk_cv_store_raw_attached_file( $att_id ) === (string) $value;
}

/** Tutti i file di un allegato: originale + anteprime registrate. Chiave = ruolo. */
function tmk_cv_store_attachment_files( $att_id, $main_abs ) {
	$files = array( 'main' => $main_abs );
	if ( '' === $main_abs ) {
		return $files;
	}
	$dir  = dirname( $main_abs );
	$meta = get_post_meta( (int) $att_id, '_wp_attachment_metadata', true );
	if ( is_array( $meta ) ) {
		if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $name => $s ) {
				if ( ! empty( $s['file'] ) && is_string( $s['file'] ) ) {
					$files[ 'size:' . $name ] = $dir . '/' . wp_basename( $s['file'] );
				}
			}
		}
		if ( ! empty( $meta['original_image'] ) && is_string( $meta['original_image'] ) ) {
			$files['original'] = $dir . '/' . wp_basename( $meta['original_image'] );
		}
	}
	// Due ruoli possono puntare allo stesso file: si tiene il primo.
	$seen = array();
	foreach ( $files as $role => $f ) {
		if ( isset( $seen[ $f ] ) ) {
			unset( $files[ $role ] );
		}
		$seen[ $f ] = true;
	}
	return $files;
}

function tmk_cv_store_sha( $file ) {
	clearstatcache( true, $file );
	return is_file( $file ) ? hash_file( 'sha256', $file ) : '';
}

/**
 * Copia $src in $dst passando da un file temporaneo nella stessa cartella e verificando
 * dimensione e SHA-256 prima e dopo la rinomina. Se $dst esiste gia' identico non fa nulla.
 *
 * @return true|string true oppure il motivo dell'errore.
 */
function tmk_cv_store_copy_verified( $src, $dst, $sha, $size ) {
	clearstatcache( true, $dst );
	if ( file_exists( $dst ) ) {
		return ( (int) filesize( $dst ) === (int) $size && tmk_cv_store_sha( $dst ) === $sha ) ? true : 'nella destinazione esiste gia\' un file diverso: ' . $dst;
	}
	if ( ! wp_mkdir_p( dirname( $dst ) ) ) {
		return 'impossibile creare ' . dirname( $dst );
	}
	$tmp = dirname( $dst ) . '/.tmp-' . wp_generate_password( 10, false, false ) . '-' . wp_basename( $dst );
	if ( ! @copy( $src, $tmp ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return 'copia non riuscita verso ' . $dst;
	}
	@chmod( $tmp, 0640 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	clearstatcache( true, $tmp );
	if ( (int) filesize( $tmp ) !== (int) $size || hash_file( 'sha256', $tmp ) !== $sha ) {
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return 'la copia non coincide con l\'originale: ' . $dst;
	}
	if ( ! @rename( $tmp, $dst ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return 'rinomina non riuscita: ' . $dst;
	}
	return tmk_cv_store_sha( $dst ) === $sha ? true : 'verifica dopo la rinomina fallita: ' . $dst;
}

/** Punto di controllo per le prove (interruzioni simulate). Non fa nulla senza un listener. */
function tmk_cv_store_checkpoint( $stage, $id ) {
	if ( function_exists( 'do_action' ) ) {
		do_action( 'tmk_cv_store_checkpoint', (string) $stage, $id );
	}
}

/** Lock esclusivo sulle operazioni di migrazione/rollback (CLI e pannello non si sovrappongono). */
function tmk_cv_store_lock() {
	if ( ! wp_mkdir_p( tmk_cv_store_root() ) ) {
		return false;
	}
	$fh = @fopen( tmk_cv_store_root() . '/.migration.lock', 'c' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	if ( ! $fh ) {
		return false;
	}
	if ( ! flock( $fh, LOCK_EX | LOCK_NB ) ) {
		fclose( $fh );
		return false;
	}
	return $fh;
}

function tmk_cv_store_unlock( $fh ) {
	if ( $fh ) {
		flock( $fh, LOCK_UN );
		fclose( $fh );
	}
}

/* =============================================================================================
   MANIFESTO — persistente, atomico, riletto dopo ogni scrittura
   ============================================================================================= */

function tmk_cv_store_manifest_path( $key ) {
	return tmk_cv_store_manifest_dir() . '/' . $key . '.json';
}

function tmk_cv_store_att_key( $att_id ) {
	return 'att-' . (int) $att_id;
}

/**
 * @return array|null|false array = manifesto; null = non esiste; false = illeggibile/incoerente.
 */
function tmk_cv_store_manifest_read( $key ) {
	$p = tmk_cv_store_manifest_path( $key );
	clearstatcache( true, $p );
	if ( ! is_file( $p ) ) {
		return null;
	}
	$m = json_decode( (string) file_get_contents( $p ), true );
	if ( ! is_array( $m ) || ( $m['key'] ?? '' ) !== $key || empty( $m['state'] ) || ! isset( $m['files'] ) || ! is_array( $m['files'] ) ) {
		return false;
	}
	return $m;
}

/**
 * Scrive il manifesto (file temporaneo + fsync + rinomina) e lo rilegge.
 *
 * @return array|false il manifesto come e' stato riletto, oppure false.
 */
function tmk_cv_store_manifest_write( $key, array $m, $event = '' ) {
	$m['key']        = $key;
	$m['schema']     = TMK_CV_STORE_SCHEMA;
	$m['updated_at'] = gmdate( 'c' );
	if ( '' !== $event ) {
		$m['history']   = isset( $m['history'] ) && is_array( $m['history'] ) ? $m['history'] : array();
		$m['history'][] = gmdate( 'c' ) . ' ' . $event;
	}
	$json = wp_json_encode( $m, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	if ( false === $json || ! wp_mkdir_p( tmk_cv_store_manifest_dir() ) ) {
		return false;
	}
	$p   = tmk_cv_store_manifest_path( $key );
	$tmp = $p . '.tmp-' . wp_generate_password( 8, false, false );
	$fh  = @fopen( $tmp, 'xb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	if ( ! $fh ) {
		return false;
	}
	$ok = false !== fwrite( $fh, $json ) && fflush( $fh );
	if ( $ok && function_exists( 'fsync' ) ) {
		$ok = fsync( $fh );
	}
	fclose( $fh );
	if ( ! $ok || ! @rename( $tmp, $p ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return false;
	}
	$back = tmk_cv_store_manifest_read( $key );
	if ( ! is_array( $back ) || wp_json_encode( $back ) !== wp_json_encode( json_decode( $json, true ) ) ) {
		return false;
	}
	return $back;
}

/** Elenco delle chiavi di manifesto esistenti (att-<ID>, orphan-<hash>). */
function tmk_cv_store_manifest_keys() {
	$keys = array();
	foreach ( (array) glob( tmk_cv_store_manifest_dir() . '/*.json' ) as $f ) {
		$keys[] = wp_basename( $f, '.json' );
	}
	sort( $keys );
	return $keys;
}

/* =============================================================================================
   NUOVI CARICAMENTI — direttamente nell'archivio, nessuna anteprima, nessun ripiego pubblico
   ============================================================================================= */

/** Firma iniziale del file coerente con l'estensione dichiarata. */
function tmk_cv_store_magic_ok( $file, $ext ) {
	$fh = @fopen( $file, 'rb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	if ( ! $fh ) {
		return false;
	}
	$head = (string) fread( $fh, 8 );
	fclose( $fh );
	switch ( $ext ) {
		case 'pdf':
			return 0 === strpos( $head, '%PDF-' );
		case 'docx':
		case 'odt':
			return 0 === strpos( $head, "PK\x03\x04" );
		case 'doc':
			return 0 === strpos( $head, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" );
	}
	return false;
}

/**
 * Salva un file caricato ($_FILES['cv']) nell'archivio riservato.
 *
 * @param array $file  elemento di $_FILES.
 * @param array $mimes estensione => MIME ammessi.
 * @return array|WP_Error array( 'file' => percorso assoluto, 'type' => MIME, 'ext' => estensione )
 */
function tmk_cv_store_save_upload( array $file, array $mimes ) {
	$status = tmk_cv_store_status();
	if ( ! $status['ready'] ) {
		return new WP_Error( 'tmk_cv_store_unavailable', $status['reason'] );
	}
	$tmp = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
	if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) {
		return new WP_Error( 'tmk_cv_not_uploaded', 'file non ricevuto tramite caricamento HTTP' );
	}
	// Estensione E tipo reale (finfo) devono essere fra quelli ammessi; poi la firma del file.
	$check = wp_check_filetype_and_ext( $tmp, (string) $file['name'], $mimes );
	$ext   = strtolower( (string) $check['ext'] );
	$type  = (string) $check['type'];
	if ( '' === $ext || '' === $type || ! isset( $mimes[ $ext ] ) || $mimes[ $ext ] !== $type || ! tmk_cv_store_magic_ok( $tmp, $ext ) ) {
		return new WP_Error( 'tmk_cv_bad_type', 'formato non ammesso' );
	}
	$sub = gmdate( 'Y' ) . '/' . gmdate( 'm' );
	$dir = tmk_cv_store_dir() . '/' . $sub;
	if ( ! wp_mkdir_p( $dir ) ) {
		return new WP_Error( 'tmk_cv_store_mkdir', 'impossibile creare ' . $dir );
	}
	do {
		$dest = $dir . '/' . wp_generate_password( 32, false, false ) . '.' . $ext;
	} while ( file_exists( $dest ) );
	if ( ! @move_uploaded_file( $tmp, $dest ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return new WP_Error( 'tmk_cv_store_move', 'spostamento del file non riuscito' );
	}
	@chmod( $dest, 0640 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	clearstatcache( true, $dest );
	if ( ! is_file( $dest ) || (int) filesize( $dest ) !== (int) $file['size'] || ! tmk_cv_store_is_private_path( $dest ) ) {
		@unlink( $dest ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return new WP_Error( 'tmk_cv_store_verify', 'verifica del file salvato non riuscita' );
	}
	return array( 'file' => $dest, 'type' => $type, 'ext' => $ext );
}

/** Elimina i file privati di un allegato CV (quando l'amministratore elimina l'allegato). */
function tmk_cv_store_delete_private_files( $att_id ) {
	$main = tmk_cv_store_abs_from_raw( tmk_cv_store_raw_attached_file( $att_id ) );
	if ( '' === $main || ! tmk_cv_store_is_private_path( $main ) ) {
		return 0;
	}
	$n = 0;
	foreach ( tmk_cv_store_attachment_files( $att_id, $main ) as $f ) {
		if ( is_file( $f ) && tmk_cv_store_is_private_path( $f ) && wp_delete_file_from_directory( $f, tmk_cv_store_dir() ) ) {
			$n++;
		}
	}
	$m = tmk_cv_store_manifest_read( tmk_cv_store_att_key( $att_id ) );
	if ( is_array( $m ) ) {
		$m['state'] = 'deleted';
		tmk_cv_store_manifest_write( tmk_cv_store_att_key( $att_id ), $m, 'allegato eliminato da WordPress: ' . $n . ' file privati rimossi' );
	}
	return $n;
}

/* =============================================================================================
   INVENTARIO — ambito di protezione completo: ogni stato, cestino compreso, e gli orfani
   ============================================================================================= */

/** Tipi MIME dei documenti CV. */
function tmk_cv_store_doc_mimes() {
	return array( 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.oasis.opendocument.text' );
}

/**
 * Tutti gli ID di allegato che sono (o sono stati) CV.
 *
 * Volutamente con SQL diretto e SENZA filtro di stato: post_status=any di WP_Query esclude il
 * cestino, e un CV di una candidatura cestinata resta un dato personale da proteggere.
 */
function tmk_cv_store_cv_attachment_ids() {
	global $wpdb;
	$ids  = array();
	$apps = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'job_application'" );
	// 1. referenziati da una candidatura (qualunque stato)
	foreach ( $wpdb->get_col( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'tmk_application_cv_id'" ) as $v ) {
		if ( (int) $v ) {
			$ids[ (int) $v ] = true;
		}
	}
	// 2. figli di una candidatura
	if ( $apps ) {
		$in = implode( ',', array_map( 'intval', $apps ) );
		foreach ( $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_parent IN ($in)" ) as $v ) { // phpcs:ignore WordPress.DB.PreparedSQL
			$ids[ (int) $v ] = true;
		}
	}
	// 3. marcati come CV da questo modulo
	foreach ( $wpdb->get_col( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_tmk_cv'" ) as $v ) {
		$ids[ (int) $v ] = true;
	}
	// 4. il cui file sta nell'archivio riservato
	$like = $wpdb->esc_like( tmk_cv_store_dir() . '/' ) . '%';
	foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s", $like ) ) as $v ) {
		$ids[ (int) $v ] = true;
	}
	// 5. orfani: documento intitolato "CV ..." con il nome casuale dei CV, padre assente o non candidatura
	$mimes = "'" . implode( "','", array_map( 'esc_sql', tmk_cv_store_doc_mimes() ) ) . "'";
	$rows  = $wpdb->get_results( "SELECT p.ID, m.meta_value FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_wp_attached_file' WHERE p.post_type = 'attachment' AND p.post_title LIKE 'CV %' AND p.post_mime_type IN ($mimes)" ); // phpcs:ignore WordPress.DB.PreparedSQL
	foreach ( $rows as $r ) {
		if ( preg_match( TMK_CV_STORE_NAME_RE, wp_basename( (string) $r->meta_value ) ) ) {
			$ids[ (int) $r->ID ] = true;
		}
	}
	// 6. allegati che hanno un manifesto
	foreach ( tmk_cv_store_manifest_keys() as $k ) {
		if ( preg_match( '/^att-(\d+)$/', $k, $mm ) ) {
			$ids[ (int) $mm[1] ] = true;
		}
	}
	$ids = array_keys( $ids );
	sort( $ids );
	return $ids;
}

/** Candidatura collegata a un allegato CV (0 se nessuna) e suo stato. */
function tmk_cv_store_app_of( $att_id ) {
	global $wpdb;
	$app = (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'tmk_application_cv_id' AND meta_value = %s ORDER BY post_id DESC LIMIT 1", (string) (int) $att_id ) );
	if ( ! $app ) {
		$parent = (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_parent FROM {$wpdb->posts} WHERE ID = %d", $att_id ) );
		if ( $parent && 'job_application' === $wpdb->get_var( $wpdb->prepare( "SELECT post_type FROM {$wpdb->posts} WHERE ID = %d", $parent ) ) ) {
			$app = $parent;
		}
	}
	$status = $app ? (string) $wpdb->get_var( $wpdb->prepare( "SELECT post_status FROM {$wpdb->posts} WHERE ID = %d AND post_type = 'job_application'", $app ) ) : '';
	return array( $app, $app ? ( '' !== $status ? $status : 'mancante' ) : 'nessuna' );
}

/**
 * Inventario in sola lettura. Nessun dato personale: ID, stati, conteggi e nomi casuali.
 *
 * @return array{attachments:array,orphan_files:array,summary:array}
 */
function tmk_cv_store_inventory() {
	global $wpdb;
	$up      = tmk_cv_store_uploads_basedir();
	$known   = array();
	$rows    = array();
	$summary = array( 'allegati' => 0, 'privati' => 0, 'pubblici' => 0, 'mancanti' => 0, 'file_pubblici' => 0, 'file_privati' => 0, 'cestino' => 0, 'senza_candidatura' => 0, 'orfani_su_disco' => 0, 'manifesti_illeggibili' => 0 );
	foreach ( tmk_cv_store_cv_attachment_ids() as $att ) {
		$exists = 'attachment' === $wpdb->get_var( $wpdb->prepare( "SELECT post_type FROM {$wpdb->posts} WHERE ID = %d", $att ) );
		list( $app, $app_status ) = tmk_cv_store_app_of( $att );
		$raw   = $exists ? tmk_cv_store_raw_attached_file( $att ) : '';
		$main  = tmk_cv_store_abs_from_raw( $raw );
		$files = $exists ? tmk_cv_store_attachment_files( $att, $main ) : array();
		$m     = tmk_cv_store_manifest_read( tmk_cv_store_att_key( $att ) );
		$pub   = 0;
		$priv  = 0;
		$cur   = array();
		foreach ( $files as $f ) {
			$f                = wp_normalize_path( $f );
			$cur[ $f ]        = true;
			$known[ $f ]      = true;
			if ( is_file( $f ) ) {
				tmk_cv_store_is_private_path( $f ) ? $priv++ : $pub++;
			}
		}
		if ( is_array( $m ) ) {
			// Ogni percorso registrato nel manifesto e' "conosciuto" (non e' un orfano)...
			$listed = $m['files'];
			if ( ! empty( $m['rollback']['files'] ) && is_array( $m['rollback']['files'] ) ) {
				$listed = array_merge( $listed, array_values( $m['rollback']['files'] ) );
			}
			foreach ( $listed as $f ) {
				foreach ( array( 'src', 'dst', 'public', 'private' ) as $k ) {
					if ( ! empty( $f[ $k ] ) ) {
						$known[ wp_normalize_path( $f[ $k ] ) ] = true;
					}
				}
			}
			// ...e una copia pubblica registrata, ancora su disco e non piu' referenziata, e' un residuo.
			foreach ( $listed as $f ) {
				foreach ( array( 'src', 'public' ) as $k ) {
					if ( ! empty( $f[ $k ] ) && ! isset( $cur[ wp_normalize_path( $f[ $k ] ) ] ) && is_file( $f[ $k ] ) && ! tmk_cv_store_is_private_path( $f[ $k ] ) ) {
						$cur[ wp_normalize_path( $f[ $k ] ) ] = true; // contato una volta sola
						$pub++;
					}
				}
			}
		}
		if ( ! $exists ) {
			$where = 'allegato eliminato';
		} elseif ( '' === $main || ! is_file( $main ) ) {
			$where = 'file mancante';
		} elseif ( tmk_cv_store_is_private_path( $main ) ) {
			$where = 'privato';
		} else {
			$where = 'pubblico';
		}
		$summary['allegati']++;
		if ( 'privato' === $where ) {
			$summary['privati']++;
		} elseif ( 'pubblico' === $where ) {
			$summary['pubblici']++;
		} else {
			$summary['mancanti']++;
		}
		$summary['file_pubblici'] += $pub;
		$summary['file_privati']  += $priv;
		if ( 'trash' === $app_status ) {
			$summary['cestino']++;
		}
		if ( ! $app || 'mancante' === $app_status ) {
			$summary['senza_candidatura']++;
		}
		if ( false === $m ) {
			$summary['manifesti_illeggibili']++;
		}
		$rows[] = array(
			'att'        => (int) $att,
			'app'        => (int) $app,
			'app_status' => $app_status,
			'location'   => $where,
			'files'      => count( $files ),
			'public'     => $pub,
			'private'    => $priv,
			'manifest'   => false === $m ? 'ILLEGGIBILE' : ( is_array( $m ) ? $m['state'] : '-' ),
		);
	}
	// File CV su disco che nessun allegato o manifesto conosce: negli upload (pubblici) e nell'archivio.
	$orphans = array();
	foreach ( array( $up => 'pubblico', tmk_cv_store_dir() => 'privato' ) as $base => $kind ) {
		if ( ! is_dir( $base ) ) {
			continue;
		}
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $f ) {
			if ( ! $f->isFile() || ! preg_match( TMK_CV_STORE_NAME_RE, $f->getFilename() ) ) {
				continue;
			}
			$path = wp_normalize_path( $f->getPathname() );
			if ( 'pubblico' === $kind && ! preg_match( '#^' . preg_quote( $up, '#' ) . '/\d{4}/\d{2}/[^/]+$#', $path ) ) {
				continue; // solo le cartelle anno/mese di WordPress, dove i CV sono sempre stati scritti
			}
			if ( isset( $known[ $path ] ) ) {
				continue;
			}
			$m = tmk_cv_store_manifest_read( tmk_cv_store_orphan_key( $path ) );
			$orphans[] = array(
				'path'     => $path,
				'kind'     => $kind,
				'size'     => (int) $f->getSize(),
				'manifest' => is_array( $m ) ? $m['state'] : '-',
			);
			if ( 'pubblico' === $kind ) {
				$summary['orfani_su_disco']++;
				$summary['file_pubblici']++;
			}
		}
	}
	// Orfani gia' messi al riparo: hanno un manifesto, la copia pubblica puo' non esserci piu'.
	foreach ( tmk_cv_store_manifest_keys() as $k ) {
		if ( 0 !== strpos( $k, 'orphan-' ) ) {
			continue;
		}
		$m = tmk_cv_store_manifest_read( $k );
		if ( ! is_array( $m ) || empty( $m['files'][0]['src'] ) ) {
			continue;
		}
		$src = $m['files'][0]['src'];
		$dup = false;
		foreach ( $orphans as $o ) {
			if ( $o['path'] === $src ) {
				$dup = true;
			}
		}
		if ( ! $dup ) {
			$orphans[] = array( 'path' => $src, 'kind' => 'pubblico (gestito)', 'size' => (int) ( $m['files'][0]['size'] ?? 0 ), 'manifest' => $m['state'] );
		}
	}
	return array( 'attachments' => $rows, 'orphan_files' => $orphans, 'summary' => $summary );
}

/* =============================================================================================
   MIGRAZIONE — passo 1: copia verificata + aggiornamento del riferimento (nessuna rimozione)
   ============================================================================================= */

/** Destinazione privata di un file pubblico: stessa sottocartella anno/mese dentro cv/. */
function tmk_cv_store_private_target( $src, $att_id ) {
	$up = tmk_cv_store_uploads_basedir();
	$s  = wp_normalize_path( $src );
	if ( 0 === strpos( $s, $up . '/' ) ) {
		$rel = substr( $s, strlen( $up ) + 1 );
		return tmk_cv_store_dir() . '/' . ( '.' === dirname( $rel ) ? '' : dirname( $rel ) . '/' ) . wp_basename( $s );
	}
	return tmk_cv_store_dir() . '/legacy/att-' . (int) $att_id . '/' . wp_basename( $s );
}

/**
 * Migra un allegato. Riprendibile: legge il manifesto e prosegue dallo stato registrato.
 *
 * @return array{att:int,ok:bool,result:string}
 */
function tmk_cv_store_migrate_one( $att_id, $dry_run = false ) {
	$att_id = (int) $att_id;
	$key    = tmk_cv_store_att_key( $att_id );
	$res    = function ( $ok, $msg ) use ( $att_id ) {
		return array( 'att' => $att_id, 'ok' => (bool) $ok, 'result' => $msg );
	};
	if ( 'attachment' !== get_post_type( $att_id ) ) {
		return $res( true, 'allegato non esistente: nulla da migrare' );
	}
	$raw  = tmk_cv_store_raw_attached_file( $att_id );
	$main = tmk_cv_store_abs_from_raw( $raw );
	$m    = tmk_cv_store_manifest_read( $key );
	if ( false === $m ) {
		return $res( false, 'ERRORE: manifesto illeggibile, serve una verifica manuale (' . tmk_cv_store_manifest_path( $key ) . ')' );
	}
	if ( '' === $raw ) {
		return $res( false, 'ERRORE: l\'allegato non ha un riferimento al file' );
	}

	// A. Il riferimento punta gia' all'archivio riservato.
	if ( tmk_cv_store_is_private_path( $main ) ) {
		if ( is_array( $m ) && in_array( $m['state'], array( 'planned', 'copied' ), true ) && ( $m['files']['main']['dst'] ?? '' ) === $main ) {
			// Interruzione fra l'aggiornamento del database e quello del manifesto: si completa.
			foreach ( $m['files'] as $f ) {
				if ( tmk_cv_store_sha( $f['dst'] ) !== $f['sha256'] ) {
					return $res( false, 'ERRORE: copia privata non integra (' . $f['dst'] . ')' );
				}
			}
			if ( $dry_run ) {
				return $res( true, 'da completare: riferimento gia\' aggiornato, manifesto da chiudere' );
			}
			$m['state']         = 'referenced';
			$m['referenced_at'] = gmdate( 'c' );
			return tmk_cv_store_manifest_write( $key, $m, 'riferimento trovato gia\' aggiornato: manifesto completato' )
				? $res( true, 'completato dopo interruzione: riferimento privato verificato' )
				: $res( false, 'ERRORE: manifesto non aggiornabile' );
		}
		return $res( true, is_array( $m ) ? 'gia\' privato (' . $m['state'] . ')' : 'privato: caricato direttamente nell\'area riservata' );
	}

	// B. File pubblico da mettere al riparo.
	if ( ! is_file( $main ) ) {
		return $res( false, 'ERRORE: file pubblico mancante (' . $main . ')' );
	}
	$files = tmk_cv_store_attachment_files( $att_id, $main );
	$plan  = array();
	$miss  = array();
	foreach ( $files as $role => $src ) {
		if ( ! is_file( $src ) ) {
			$miss[] = $role; // anteprima registrata ma assente su disco: nulla da copiare
			continue;
		}
		$plan[ $role ] = array(
			'role'   => $role,
			'src'    => wp_normalize_path( $src ),
			'dst'    => tmk_cv_store_private_target( $src, $att_id ),
			'sha256' => hash_file( 'sha256', $src ),
			'size'   => (int) filesize( $src ),
		);
	}
	// Ripresa: un piano gia' registrato per questi stessi file sorgente si riusa cosi' com'e'.
	$resume = is_array( $m ) && in_array( $m['state'], array( 'planned', 'copied' ), true ) && array_keys( $m['files'] ) === array_keys( $plan );
	if ( $resume ) {
		foreach ( $plan as $role => $p ) {
			if ( $m['files'][ $role ]['src'] !== $p['src'] || $m['files'][ $role ]['sha256'] !== $p['sha256'] ) {
				$resume = false;
			}
		}
	}
	if ( $dry_run ) {
		return $res( true, ( $resume ? 'da riprendere (' . $m['state'] . ')' : 'da copiare' ) . ': ' . count( $plan ) . ' file' . ( $miss ? ', anteprime registrate ma assenti: ' . count( $miss ) : '' ) );
	}
	if ( ! $resume ) {
		$prev = is_array( $m ) ? $m : array();
		$m    = array(
			'state'       => 'planned',
			'att_id'      => $att_id,
			'app_id'      => tmk_cv_store_app_of( $att_id )[0],
			'orig_raw'    => $raw,
			'created_at'  => gmdate( 'c' ),
			'files'       => $plan,
			'missing'     => $miss,
			'removed'     => array(),
			'history'     => isset( $prev['history'] ) ? $prev['history'] : array(),
		);
		foreach ( $plan as $p ) {
			clearstatcache( true, $p['dst'] );
			if ( file_exists( $p['dst'] ) && tmk_cv_store_sha( $p['dst'] ) !== $p['sha256'] ) {
				return $res( false, 'ERRORE: nell\'area riservata esiste gia\' un file diverso con lo stesso nome (' . $p['dst'] . ')' );
			}
		}
		// Il manifesto si registra e si rilegge PRIMA di toccare qualunque file o riferimento.
		$m = tmk_cv_store_manifest_write( $key, $m, 'piano registrato: ' . count( $plan ) . ' file' . ( $prev ? ' (stato precedente: ' . $prev['state'] . ')' : '' ) );
		if ( ! $m ) {
			return $res( false, 'ERRORE: manifesto non scrivibile: nessun file copiato, nessun riferimento cambiato' );
		}
	}
	tmk_cv_store_checkpoint( 'planned', $att_id );

	if ( 'planned' === $m['state'] ) {
		foreach ( $m['files'] as $p ) {
			$r = tmk_cv_store_copy_verified( $p['src'], $p['dst'], $p['sha256'], $p['size'] );
			if ( true !== $r ) {
				tmk_cv_store_manifest_write( $key, $m, 'copia interrotta: ' . $r );
				return $res( false, 'ERRORE: ' . $r );
			}
			tmk_cv_store_checkpoint( 'copied-file', $att_id );
		}
		$m['state']     = 'copied';
		$m['copied_at'] = gmdate( 'c' );
		$m              = tmk_cv_store_manifest_write( $key, $m, 'copie private verificate' );
		if ( ! $m ) {
			return $res( false, 'ERRORE: manifesto non aggiornabile dopo la copia (le copie restano, il riferimento no)' );
		}
	}
	tmk_cv_store_checkpoint( 'copied', $att_id );

	// Prima di cambiare il riferimento si riverificano tutte le copie.
	foreach ( $m['files'] as $p ) {
		if ( tmk_cv_store_sha( $p['dst'] ) !== $p['sha256'] ) {
			return $res( false, 'ERRORE: copia privata non integra prima dell\'aggiornamento (' . $p['dst'] . ')' );
		}
	}
	$dst_main = $m['files']['main']['dst'];
	if ( ! tmk_cv_store_set_reference( $att_id, $dst_main ) ) {
		// Salvataggio fallito o diverso da quello atteso: si ripristina l'originale e lo si verifica.
		$back = tmk_cv_store_set_reference( $att_id, $m['orig_raw'] );
		tmk_cv_store_manifest_write( $key, $m, 'aggiornamento del riferimento NON riuscito; ripristino originale ' . ( $back ? 'verificato' : 'NON verificato' ) );
		return $res( false, 'ERRORE: aggiornamento del riferimento non riuscito; riferimento originale ' . ( $back ? 'ripristinato e verificato' : 'DA VERIFICARE A MANO' ) );
	}
	update_post_meta( $att_id, '_tmk_cv', 1 );
	tmk_cv_store_checkpoint( 'referenced-db', $att_id );

	$m['state']         = 'referenced';
	$m['referenced_at'] = gmdate( 'c' );
	if ( ! tmk_cv_store_manifest_write( $key, $m, 'riferimento aggiornato e riletto dal database' ) ) {
		return $res( false, 'ATTENZIONE: riferimento privato salvato e verificato, ma manifesto non aggiornato: rieseguire la migrazione' );
	}
	return $res( true, 'copiato e verificato: ' . count( $m['files'] ) . ' file, riferimento privato' );
}

/* ---------- file orfani (nessun allegato li conosce) ---------- */

function tmk_cv_store_orphan_key( $path ) {
	return 'orphan-' . substr( hash( 'sha256', wp_normalize_path( $path ) ), 0, 24 );
}

/** Mette al riparo un file CV pubblico che nessun allegato referenzia (copia + manifesto). */
function tmk_cv_store_migrate_orphan( $path, $dry_run = false ) {
	$path = wp_normalize_path( $path );
	$key  = tmk_cv_store_orphan_key( $path );
	$m    = tmk_cv_store_manifest_read( $key );
	$res  = function ( $ok, $msg ) use ( $path ) {
		return array( 'att' => 0, 'ok' => (bool) $ok, 'result' => wp_basename( $path ) . ': ' . $msg );
	};
	if ( false === $m ) {
		return $res( false, 'ERRORE: manifesto illeggibile' );
	}
	if ( is_array( $m ) && in_array( $m['state'], array( 'copied', 'public_removed' ), true ) ) {
		return $res( true, 'gia\' al riparo (' . $m['state'] . ')' );
	}
	if ( ! is_file( $path ) ) {
		return $res( false, 'ERRORE: file non trovato' );
	}
	$up  = tmk_cv_store_uploads_basedir();
	$rel = 0 === strpos( $path, $up . '/' ) ? substr( $path, strlen( $up ) + 1 ) : wp_basename( $path );
	$p   = array(
		'role'   => 'orphan',
		'src'    => $path,
		'dst'    => tmk_cv_store_dir() . '/orfani/' . $rel,
		'sha256' => hash_file( 'sha256', $path ),
		'size'   => (int) filesize( $path ),
	);
	if ( $dry_run ) {
		return $res( true, 'orfano da mettere al riparo' );
	}
	$m = tmk_cv_store_manifest_write( $key, array( 'state' => 'planned', 'att_id' => 0, 'files' => array( $p ), 'removed' => array(), 'created_at' => gmdate( 'c' ) ), 'file orfano: piano registrato' );
	if ( ! $m ) {
		return $res( false, 'ERRORE: manifesto non scrivibile' );
	}
	tmk_cv_store_checkpoint( 'orphan-planned', $key );
	$r = tmk_cv_store_copy_verified( $p['src'], $p['dst'], $p['sha256'], $p['size'] );
	if ( true !== $r ) {
		return $res( false, 'ERRORE: ' . $r );
	}
	$m['state'] = 'copied';
	return tmk_cv_store_manifest_write( $key, $m, 'copia privata verificata' ) ? $res( true, 'copiato e verificato nell\'area riservata' ) : $res( false, 'ERRORE: manifesto non aggiornabile' );
}

/** Passo 1 per tutto l'inventario. */
function tmk_cv_store_migrate_all( $dry_run = false ) {
	$lock = $dry_run ? true : tmk_cv_store_lock();
	if ( ! $lock ) {
		return array( array( 'att' => 0, 'ok' => false, 'result' => 'ERRORE: un\'altra operazione sull\'archivio e\' in corso' ) );
	}
	$status = tmk_cv_store_status();
	if ( ! $status['ready'] ) {
		if ( ! $dry_run ) {
			tmk_cv_store_unlock( $lock );
		}
		return array( array( 'att' => 0, 'ok' => false, 'result' => 'ERRORE: archivio non disponibile: ' . $status['reason'] ) );
	}
	$out = array();
	$inv = tmk_cv_store_inventory();
	foreach ( $inv['attachments'] as $r ) {
		if ( 'allegato eliminato' === $r['location'] ) {
			continue;
		}
		$out[] = tmk_cv_store_migrate_one( $r['att'], $dry_run );
	}
	foreach ( $inv['orphan_files'] as $o ) {
		if ( 'pubblico' === $o['kind'] ) {
			$out[] = tmk_cv_store_migrate_orphan( $o['path'], $dry_run );
		}
	}
	if ( ! $dry_run ) {
		tmk_cv_store_unlock( $lock );
	}
	return $out;
}

/* =============================================================================================
   MIGRAZIONE — passo 2: rimozione delle copie pubbliche, solo con tutte le condizioni verificate
   ============================================================================================= */

/** Rimuove le copie pubbliche di un manifesto (allegato o orfano). */
function tmk_cv_store_remove_public_one( $key ) {
	$m   = tmk_cv_store_manifest_read( $key );
	$res = function ( $ok, $msg ) use ( $key ) {
		return array( 'att' => preg_match( '/^att-(\d+)$/', $key, $mm ) ? (int) $mm[1] : 0, 'ok' => (bool) $ok, 'result' => $key . ': ' . $msg );
	};
	if ( ! is_array( $m ) ) {
		return $res( false, 'BLOCCATO: manifesto assente o illeggibile' );
	}
	$is_att = 0 === strpos( $key, 'att-' );
	$need   = $is_att ? array( 'referenced', 'public_removed' ) : array( 'copied', 'public_removed' );
	if ( ! in_array( $m['state'], $need, true ) ) {
		return $res( false, 'BLOCCATO: stato "' . $m['state'] . '", la rimozione richiede ' . implode( '/', $need ) );
	}
	if ( $is_att ) {
		$att = (int) $m['att_id'];
		if ( 'attachment' !== get_post_type( $att ) || tmk_cv_store_att_key( $att ) !== $key ) {
			return $res( false, 'BLOCCATO: manifesto incoerente con l\'allegato' );
		}
		// Il riferimento ATTUALE, riletto dal database, deve essere esattamente la copia privata.
		$raw = tmk_cv_store_raw_attached_file( $att );
		if ( $raw !== $m['files']['main']['dst'] || ! tmk_cv_store_is_private_path( $raw ) ) {
			return $res( false, 'BLOCCATO: il riferimento attuale non punta alla copia privata registrata' );
		}
	}
	// Ogni copia privata deve esistere, stare nell'archivio ed essere integra.
	foreach ( $m['files'] as $p ) {
		if ( ! tmk_cv_store_is_private_path( $p['dst'] ) || tmk_cv_store_sha( $p['dst'] ) !== $p['sha256'] ) {
			return $res( false, 'BLOCCATO: copia privata mancante o non integra (' . $p['dst'] . ')' );
		}
	}
	$removed = isset( $m['removed'] ) && is_array( $m['removed'] ) ? $m['removed'] : array();
	$notes   = array();
	$done    = 0;
	foreach ( $m['files'] as $p ) {
		$src = $p['src'];
		if ( in_array( $src, $removed, true ) ) {
			continue;
		}
		clearstatcache( true, $src );
		if ( ! file_exists( $src ) ) {
			$removed[] = $src;
			$notes[]   = wp_basename( $src ) . ' gia\' assente';
			continue;
		}
		if ( tmk_cv_store_is_private_path( $src ) ) {
			$notes[] = wp_basename( $src ) . ' NON rimosso: e\' dentro l\'archivio';
			continue;
		}
		if ( tmk_cv_store_sha( $src ) !== $p['sha256'] ) {
			$notes[] = wp_basename( $src ) . ' NON rimosso: diverso da quello copiato';
			continue;
		}
		tmk_cv_store_checkpoint( 'before-unlink', $key );
		if ( @unlink( $src ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			clearstatcache( true, $src );
			if ( ! file_exists( $src ) ) {
				$removed[] = $src;
				$done++;
				continue;
			}
		}
		$notes[] = wp_basename( $src ) . ' NON rimosso: cancellazione non riuscita';
	}
	$m['removed'] = array_values( array_unique( $removed ) );
	$all          = count( $m['removed'] ) >= count( $m['files'] );
	if ( $all ) {
		$m['state']             = 'public_removed';
		$m['public_removed_at'] = gmdate( 'c' );
	}
	if ( ! tmk_cv_store_manifest_write( $key, $m, 'rimozione copie pubbliche: ' . $done . ' rimosse' . ( $notes ? '; ' . implode( '; ', $notes ) : '' ) ) ) {
		return $res( false, 'ATTENZIONE: ' . $done . ' copie rimosse ma manifesto non aggiornato: rieseguire' );
	}
	return $res( $all, ( $all ? 'copie pubbliche rimosse: ' : 'rimozione INCOMPLETA: ' ) . $done . ( $notes ? ' (' . implode( '; ', $notes ) . ')' : '' ) );
}

function tmk_cv_store_remove_public_all( $confirmed ) {
	if ( ! $confirmed ) {
		return array( array( 'att' => 0, 'ok' => false, 'result' => 'Non eseguito: manca la conferma esplicita' ) );
	}
	$lock = tmk_cv_store_lock();
	if ( ! $lock ) {
		return array( array( 'att' => 0, 'ok' => false, 'result' => 'ERRORE: un\'altra operazione sull\'archivio e\' in corso' ) );
	}
	$out = array();
	foreach ( tmk_cv_store_manifest_keys() as $key ) {
		$m = tmk_cv_store_manifest_read( $key );
		if ( is_array( $m ) && in_array( $m['state'], array( 'public_removed', 'deleted', 'rolled_back' ), true ) ) {
			continue;
		}
		$out[] = tmk_cv_store_remove_public_one( $key );
	}
	tmk_cv_store_unlock( $lock );
	return $out;
}

/* =============================================================================================
   ROLLBACK — riporta OGNI CV dell'archivio (anche quelli arrivati dopo il rilascio) a un
   riferimento pubblico leggibile dal codice 1.21.3. Non cancella mai la copia privata e non tocca
   nessun altro dato: le candidature arrivate nel frattempo restano, con il loro CV scaricabile.
   ============================================================================================= */

function tmk_cv_store_rollback_one( $att_id ) {
	$att_id = (int) $att_id;
	$key    = tmk_cv_store_att_key( $att_id );
	$res    = function ( $ok, $msg ) use ( $att_id ) {
		return array( 'att' => $att_id, 'ok' => (bool) $ok, 'result' => $msg );
	};
	if ( 'attachment' !== get_post_type( $att_id ) ) {
		return $res( true, 'allegato non esistente' );
	}
	$raw  = tmk_cv_store_raw_attached_file( $att_id );
	$main = tmk_cv_store_abs_from_raw( $raw );
	if ( ! tmk_cv_store_is_private_path( $main ) ) {
		return $res( true, 'gia\' con riferimento pubblico' );
	}
	$m = tmk_cv_store_manifest_read( $key );
	if ( false === $m ) {
		return $res( false, 'ERRORE: manifesto illeggibile' );
	}
	$up    = tmk_cv_store_uploads_basedir();
	$base  = tmk_cv_store_dir();
	$plan  = array();
	foreach ( tmk_cv_store_attachment_files( $att_id, $main ) as $role => $priv ) {
		if ( ! is_file( $priv ) ) {
			continue;
		}
		if ( is_array( $m ) && ! empty( $m['files'][ $role ]['src'] ) ) {
			$pub = $m['files'][ $role ]['src']; // posizione pubblica originale
		} else {
			// Caricato direttamente nell'area riservata: stessa sottocartella anno/mese negli upload.
			$rel = 0 === strpos( wp_normalize_path( $priv ), $base . '/' ) ? substr( wp_normalize_path( $priv ), strlen( $base ) + 1 ) : wp_basename( $priv );
			$pub = $up . '/' . ( preg_match( '#^\d{4}/\d{2}/#', $rel ) ? $rel : gmdate( 'Y/m', (int) get_post_time( 'U', true, $att_id ) ) . '/' . wp_basename( $priv ) );
		}
		$plan[ $role ] = array( 'role' => $role, 'private' => wp_normalize_path( $priv ), 'public' => wp_normalize_path( $pub ), 'sha256' => hash_file( 'sha256', $priv ), 'size' => (int) filesize( $priv ) );
	}
	if ( empty( $plan['main'] ) ) {
		return $res( false, 'ERRORE: copia privata principale mancante' );
	}
	foreach ( $plan as $p ) {
		$r = tmk_cv_store_copy_verified( $p['private'], $p['public'], $p['sha256'], $p['size'] );
		if ( true !== $r ) {
			return $res( false, 'ERRORE: ' . $r );
		}
	}
	$rel_main = substr( $plan['main']['public'], strlen( $up ) + 1 );
	$mm       = is_array( $m ) ? $m : array( 'att_id' => $att_id, 'files' => array(), 'removed' => array(), 'created_at' => gmdate( 'c' ) );
	$mm['state']          = 'rolled_back';
	$mm['rolled_back_at'] = gmdate( 'c' );
	$mm['rollback']       = array( 'private_raw' => $raw, 'files' => $plan );
	if ( ! tmk_cv_store_manifest_write( $key, $mm, 'rollback: copia pubblica ricreata e verificata' ) ) {
		return $res( false, 'ERRORE: manifesto non scrivibile, riferimento NON cambiato' );
	}
	if ( ! tmk_cv_store_set_reference( $att_id, $rel_main ) ) {
		$back = tmk_cv_store_set_reference( $att_id, $raw );
		return $res( false, 'ERRORE: riferimento pubblico non salvato; riferimento privato ' . ( $back ? 'ripristinato' : 'DA VERIFICARE' ) );
	}
	return $res( true, 'riferimento pubblico ripristinato (' . count( $plan ) . ' file); copia privata conservata' );
}

function tmk_cv_store_rollback_all( $confirmed ) {
	if ( ! $confirmed ) {
		return array( array( 'att' => 0, 'ok' => false, 'result' => 'Non eseguito: manca la conferma esplicita' ) );
	}
	$lock = tmk_cv_store_lock();
	if ( ! $lock ) {
		return array( array( 'att' => 0, 'ok' => false, 'result' => 'ERRORE: un\'altra operazione sull\'archivio e\' in corso' ) );
	}
	$out = array();
	foreach ( tmk_cv_store_cv_attachment_ids() as $att ) {
		$out[] = tmk_cv_store_rollback_one( $att );
	}
	tmk_cv_store_unlock( $lock );
	return $out;
}

/* =============================================================================================
   VERIFICA — integrita' di ogni copia privata e assenza di copie pubbliche residue
   ============================================================================================= */

function tmk_cv_store_verify() {
	$out = array();
	$inv = tmk_cv_store_inventory();
	foreach ( $inv['attachments'] as $r ) {
		if ( 'allegato eliminato' === $r['location'] ) {
			continue;
		}
		$problems = array();
		if ( 'privato' !== $r['location'] ) {
			$problems[] = 'riferimento ' . $r['location'];
		}
		if ( $r['public'] ) {
			$problems[] = $r['public'] . ' copie pubbliche presenti';
		}
		$m = tmk_cv_store_manifest_read( tmk_cv_store_att_key( $r['att'] ) );
		if ( is_array( $m ) && ! in_array( $m['state'], array( 'rolled_back', 'deleted' ), true ) ) {
			foreach ( $m['files'] as $p ) {
				if ( ! empty( $p['dst'] ) && tmk_cv_store_sha( $p['dst'] ) !== $p['sha256'] ) {
					$problems[] = 'copia privata non integra: ' . wp_basename( $p['dst'] );
				}
			}
		}
		$out[] = array( 'att' => $r['att'], 'ok' => ! $problems, 'result' => $problems ? implode( '; ', $problems ) : 'ok' );
	}
	foreach ( $inv['orphan_files'] as $o ) {
		if ( 'pubblico' === $o['kind'] ) {
			$out[] = array( 'att' => 0, 'ok' => false, 'result' => 'file CV pubblico non gestito: ' . wp_basename( $o['path'] ) );
		}
	}
	return $out;
}

/* =============================================================================================
   WP-CLI — anche senza il plugin caricato (vedi intestazione)
   ============================================================================================= */

function tmk_cv_store_cli( $args, $assoc = array() ) {
	$cmd = isset( $args[0] ) ? $args[0] : 'inventory';
	$print = function ( $rows ) {
		if ( class_exists( 'WP_CLI' ) ) {
			WP_CLI\Utils\format_items( 'table', $rows, $rows ? array_keys( $rows[0] ) : array( 'att', 'ok', 'result' ) );
		} else {
			echo wp_json_encode( $rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
		}
	};
	$fail = function ( $rows ) {
		foreach ( $rows as $r ) {
			if ( empty( $r['ok'] ) ) {
				return true;
			}
		}
		return false;
	};
	switch ( $cmd ) {
		case 'status':
			$s = tmk_cv_store_status();
			$print( array( array( 'ready' => $s['ready'] ? 'si' : 'NO', 'dir' => $s['dir'], 'reason' => $s['reason'] ) ) );
			return $s['ready'] ? 0 : 1;
		case 'inventory':
			$inv = tmk_cv_store_inventory();
			if ( ! empty( $assoc['format'] ) && 'json' === $assoc['format'] ) {
				echo wp_json_encode( $inv, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
				return 0;
			}
			$print( $inv['attachments'] );
			if ( $inv['orphan_files'] ) {
				$print( $inv['orphan_files'] );
			}
			$print( array( $inv['summary'] ) );
			return 0;
		case 'migrate':
			$rows = tmk_cv_store_migrate_all( ! empty( $assoc['dry-run'] ) );
			break;
		case 'remove-public':
			$rows = tmk_cv_store_remove_public_all( ! empty( $assoc['yes'] ) );
			break;
		case 'rollback-prepare':
			$rows = tmk_cv_store_rollback_all( ! empty( $assoc['yes'] ) );
			break;
		case 'verify':
			$rows = tmk_cv_store_verify();
			break;
		default:
			echo "Comandi: status | inventory [--format=json] | migrate [--dry-run] | remove-public --yes | rollback-prepare --yes | verify\n";
			return 1;
	}
	$print( $rows );
	return $fail( $rows ) ? 1 : 0;
}
