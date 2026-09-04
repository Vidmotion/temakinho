<?php
/**
 * TMK Legal Documents — griglia di card documentali per la pagina /allergeni/.
 * PATCH 10 (2026-09-04). Vedi _BUILD/PHP-PATCH-NOTE.md.
 *
 * PERCHE' ESISTE. Il brief chiede che gli URL dei PDF siano "risolti dinamicamente da ID quando
 * possibile": un widget HTML con URL scritti a mano si romperebbe al primo cambio di percorso
 * uploads o al reimport di un documento. Questo shortcode legge la mappa persistente scritta da
 * _BUILD/201-legal-documents.php (option `tmk_legal_docs_map`) e risolve ogni URL con
 * wp_get_attachment_url( $id ) a ogni render.
 *
 * NON contiene testi legali, titoli inventati o date: titolo, tipo e data "in vigore dal" sono
 * derivati meccanicamente dal nome del file sorgente dallo script 201 e salvati nella mappa.
 *
 * Markup e stile usano SOLO componenti e token esistenti: .tmk-cta/.tmk-cta--primary (components/
 * cta.css), .tmk-legal__eyebrow (pages/legal.css), .tmk-sr-only (utilities/accessibility.css) e
 * il blocco .tmk-legal-docs aggiunto in coda a pages/legal.css. Nessun colore, font, raggio o
 * bottone nuovo.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Documenti pubblicabili di un gruppo, con URL risolto dall'ID allegato.
 *
 * @param string $group Gruppo nella mappa ('allergens' | 'legal').
 * @return array<int,array<string,string>>
 */
function tmk_legal_docs_get( $group = 'allergens' ) {
	$map  = get_option( 'tmk_legal_docs_map', array() );
	$docs = array();

	if ( ! is_array( $map ) ) {
		return $docs;
	}

	foreach ( $map as $rel => $row ) {
		if ( ! is_array( $row ) || empty( $row['id'] ) ) {
			continue;
		}
		if ( $group && ( $row['group'] ?? '' ) !== $group ) {
			continue;
		}

		$id  = (int) $row['id'];
		$url = wp_get_attachment_url( $id );

		// Un allegato cancellato a mano non deve produrre una card rotta.
		if ( ! $url || 'attachment' !== get_post_type( $id ) ) {
			continue;
		}

		$docs[] = array(
			'id'       => $id,
			'url'      => $url,
			'title'    => (string) ( $row['title'] ?? get_the_title( $id ) ),
			'kind'     => (string) ( $row['kind'] ?? '' ),
			'since'    => (string) ( $row['since'] ?? '' ),
			'size'     => (string) ( $row['size_human'] ?? '' ),
			'sort'     => (string) ( $row['sort'] ?? $rel ),
		);
	}

	usort(
		$docs,
		static function ( $a, $b ) {
			return strcmp( $a['sort'], $b['sort'] );
		}
	);

	return $docs;
}

/**
 * [tmk_legal_docs group="allergens"] — griglia di card documentali.
 */
function tmk_render_legal_docs( $atts = array() ) {
	$atts = shortcode_atts(
		array(
			'group' => 'allergens',
		),
		$atts,
		'tmk_legal_docs'
	);

	$docs = tmk_legal_docs_get( $atts['group'] );

	// Nessun documento: messaggio minimo, nessuna promessa, nessuna card vuota.
	if ( empty( $docs ) ) {
		return '<p class="tmk-legal-docs__empty">' .
			esc_html__( 'Al momento non ci sono documenti disponibili in questa sezione.', 'tmk' ) .
			'</p>';
	}

	$out  = '<ul class="tmk-legal-docs__grid">';
	foreach ( $docs as $d ) {
		$meta = array_filter( array( 'PDF', $d['since'] ? 'in vigore dal ' . $d['since'] : '', $d['size'] ) );

		$out .= '<li class="tmk-legal-doc">';
		$out .= '<p class="tmk-legal-doc__kind">' . esc_html( implode( ' · ', $meta ) ) . '</p>';
		$out .= '<h3 class="tmk-legal-doc__title">' . esc_html( $d['title'] ) . '</h3>';
		$out .= '<a class="tmk-cta tmk-cta--primary tmk-legal-doc__cta" href="' . esc_url( $d['url'] ) . '"'
			. ' target="_blank" rel="noopener noreferrer">Apri il documento'
			. '<span class="tmk-sr-only"> (' . esc_html( $d['title'] ) . ', PDF, si apre in una nuova scheda)</span>'
			. '</a>';
		$out .= '</li>';
	}
	$out .= '</ul>';

	return $out;
}
add_shortcode( 'tmk_legal_docs', 'tmk_render_legal_docs' );

/**
 * URL dell'allegato di un documento legale, risolto dall'ID.
 *
 * @param string $slug privacy | cookie | etico | whistleblowing
 * @return string URL, oppure '' se il documento non e' stato importato.
 */
function tmk_legal_doc_url( $slug ) {
	$files = array(
		'privacy'        => 'Informativa_privacy_website.pdf',
		'cookie'         => 'Cookie_Policy.pdf',
		'etico'          => 'Temakinho_Codice_Etico.pdf',
		'whistleblowing' => 'Informativa_privacy_whistleblowing.pdf',
	);
	if ( ! isset( $files[ $slug ] ) ) {
		return '';
	}
	$map = get_option( 'tmk_legal_docs_map', array() );
	$row = is_array( $map ) && isset( $map[ $files[ $slug ] ] ) ? $map[ $files[ $slug ] ] : null;
	if ( ! $row || empty( $row['id'] ) || 'attachment' !== get_post_type( (int) $row['id'] ) ) {
		return '';
	}
	$url = wp_get_attachment_url( (int) $row['id'] );
	return $url ? $url : '';
}

/**
 * Il consenso privacy dei form punta all'informativa interna.
 *
 * careers-render.php aveva già previsto questo punto di estensione: il suo commento dice
 * "swapped for an on-site privacy page the day one exists". Quel giorno è oggi, e il filtro
 * evita di toccare il renderer. Vale per il form Candidatura e per chiunque altro usi
 * tmk_privacy_consent_text().
 */
add_filter( 'tmk_privacy_policy_url', function ( $url ) {
	$internal = tmk_legal_doc_url( 'privacy' );
	return $internal ? $internal : $url;
} );

/**
 * Il link del consenso apre un PDF in una nuova scheda: serve il rel completo.
 * tmk_privacy_consent_text() stampa rel="noopener"; il brief chiede
 * rel="noopener noreferrer". Anche qui si usa il filtro che il renderer espone, invece di
 * modificarne il markup.
 */
add_filter( 'tmk_privacy_consent_text', function ( $text ) {
	return str_replace( 'rel="noopener"', 'rel="noopener noreferrer"', (string) $text );
}, 10, 1 );

