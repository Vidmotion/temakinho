<?php
/**
 * SOLO PROVE — imitazione minima di Microsoft Graph (token + tabella Excel) per provare
 * inc/conventions-registry.php nello staging isolato, senza account reali.
 *
 *   php -S 127.0.0.1:18999 mock-graph.php        (dentro il container WordPress di staging)
 *
 * Stato in /tmp/mockgraph/state.json: intestazioni e righe della tabella tblRichieste come nel file
 * rev. 2 (21 colonne, colonne calcolate J:O e T, una riga dati vuota iniziale). Riproduce SOLO il
 * comportamento su cui il connettore fa affidamento, e lo dichiara:
 *  - una cella null in values/formulasR1C1 non viene scritta;
 *  - aggiungendo una riga, le colonne calcolate restano vuote (salvo /tmp/mockgraph/autofill),
 *    cosi' si prova anche il ripristino delle formule;
 *  - /tmp/mockgraph/fail-N: le prossime N richieste rispondono 503; /tmp/mockgraph/expire: il
 *    token corrente diventa non valido (401).
 * Ogni richiesta viene annotata in /tmp/mockgraph/requests.log (metodo e percorso).
 * Il comportamento reale di Excel Online va confermato sul file reale (wp tmk-conv-registry test).
 */
$dir = '/tmp/mockgraph';
@mkdir( $dir, 0700, true );
$sf   = $dir . '/state.json';
$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$qs   = (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_QUERY );
$m    = $_SERVER['REQUEST_METHOD'];
file_put_contents( $dir . '/requests.log', $m . ' ' . rawurldecode( $path ) . ( $qs ? '?' . rawurldecode( $qs ) : '' ) . "\n", FILE_APPEND );

function out( $code, $j ) {
	http_response_code( $code );
	header( 'Content-Type: application/json' );
	echo json_encode( $j, JSON_UNESCAPED_UNICODE );
	exit;
}

$head = array( 'ID archivio WordPress', 'ID richiesta', 'Data e ora', 'Nome azienda', 'Sito web', 'Email di riferimento', 'Numero dipendenti', 'Settore', 'Città selezionate', 'Milano', 'Roma', 'Bologna', 'Lione', 'Rimini', 'Reggio Calabria', 'Newsletter: adesione', 'Newsletter: data consenso', 'Newsletter: versione testo', 'Stato (manuale)', 'Stato', 'Note interne' );
$city = '=IF(RC9="","",IF(ISNUMBER(SEARCH(","&SUBSTITUTE(R1C," ","")&",",","&SUBSTITUTE(RC9," ","")&",")),"Sì",""))';
$calc = array( 9 => $city, 10 => $city, 11 => $city, 12 => $city, 13 => $city, 14 => $city, 19 => '=IF(RC1="","",IF(RC19="","Da valutare",RC19))' );
$st = is_file( $sf ) ? json_decode( file_get_contents( $sf ), true ) : null;
if ( ! $st ) {
	$row = array_fill( 0, 21, '' );
	foreach ( $calc as $i => $f ) { $row[ $i ] = $f; }
	$st = array( 'rows' => array( $row ), 'token' => '', 'n' => 0 );
}
$save = function () use ( &$st, $sf ) { file_put_contents( $sf, json_encode( $st, JSON_UNESCAPED_UNICODE ) ); };

foreach ( glob( $dir . '/fail-*' ) as $f ) {
	$n = (int) substr( basename( $f ), 5 );
	unlink( $f );
	if ( $n > 1 ) { touch( $dir . '/fail-' . ( $n - 1 ) ); }
	out( 503, array( 'error' => array( 'code' => 'serviceNotAvailable', 'message' => 'prova: servizio non disponibile' ) ) );
}

// Token
if ( 'POST' === $m && preg_match( '#^/login/[^/]+/oauth2/v2\.0/token$#', $path ) ) {
	if ( ( $_POST['client_id'] ?? '' ) !== 'mock-client' || ( $_POST['client_secret'] ?? '' ) !== 'mock-secret' || ( $_POST['grant_type'] ?? '' ) !== 'client_credentials' ) {
		out( 401, array( 'error' => 'invalid_client', 'error_description' => 'credenziali non valide' ) );
	}
	$st['token'] = 'tok-' . bin2hex( random_bytes( 6 ) );
	$save();
	out( 200, array( 'access_token' => $st['token'], 'expires_in' => 3600, 'token_type' => 'Bearer' ) );
}
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if ( is_file( $dir . '/expire' ) ) { unlink( $dir . '/expire' ); $st['token'] = 'scaduto'; $save(); }
if ( 'Bearer ' . $st['token'] !== $auth || '' === $st['token'] ) {
	out( 401, array( 'error' => array( 'code' => 'InvalidAuthenticationToken', 'message' => 'token non valido' ) ) );
}
if ( ! preg_match( '#^/v1\.0/drives/mock-drive/items/mock-item/workbook/tables/tblRichieste(/.*)?$#', $path, $mm ) ) {
	out( 404, array( 'error' => array( 'code' => 'itemNotFound', 'message' => 'percorso sconosciuto ' . $path ) ) );
}
$sub  = rawurldecode( $mm[1] ?? '' );
$body = json_decode( (string) file_get_contents( 'php://input' ), true );
$val  = function ( $cell ) { return is_string( $cell ) && '' !== $cell && '=' === $cell[0] ? '' : $cell; }; // le formule non vengono calcolate
$apply = function ( $i, $vals, $isFormula ) use ( &$st ) {
	foreach ( $vals as $c => $v ) {
		if ( null === $v ) { continue; } // null: cella non scritta
		$st['rows'][ $i ][ $c ] = $v;
	}
};

if ( 'GET' === $m && '/range' === $sub ) {
	$vals = array( $head );
	foreach ( $st['rows'] as $r ) { $vals[] = array_map( $val, $r ); }
	out( 200, array( 'values' => $vals ) );
}
if ( 'POST' === $m && '/rows' === $sub ) {
	foreach ( (array) ( $body['values'] ?? array() ) as $vals ) {
		$row = array_fill( 0, 21, '' );
		if ( is_file( $dir . '/autofill' ) ) { foreach ( $calc as $i => $f ) { $row[ $i ] = $f; } }
		$st['rows'][] = $row;
		$apply( count( $st['rows'] ) - 1, $vals, false );
	}
	$save();
	out( 201, array( 'index' => count( $st['rows'] ) - 1, 'values' => array() ) );
}
if ( preg_match( '#^/rows/itemAt\(index=(\d+)\)/range$#', $sub, $r ) ) {
	$i = (int) $r[1];
	if ( ! isset( $st['rows'][ $i ] ) ) { out( 400, array( 'error' => array( 'code' => 'InvalidArgument', 'message' => 'indice fuori tabella' ) ) ); }
	if ( 'GET' === $m ) { out( 200, array( 'formulasR1C1' => array( $st['rows'][ $i ] ), 'values' => array( array_map( $val, $st['rows'][ $i ] ) ) ) ); }
	if ( 'PATCH' === $m ) {
		if ( isset( $body['values'] ) ) { $apply( $i, $body['values'][0], false ); }
		if ( isset( $body['formulasR1C1'] ) ) { $apply( $i, $body['formulasR1C1'][0], true ); }
		$save();
		out( 200, array( 'address' => 'Richieste!A' . ( $i + 2 ) ) );
	}
}
out( 400, array( 'error' => array( 'code' => 'BadRequest', 'message' => 'operazione non prevista ' . $m . ' ' . $sub ) ) );
