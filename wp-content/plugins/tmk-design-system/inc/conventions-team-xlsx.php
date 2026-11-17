<?php
/** Minimal Office Open XML workbook. User input is always an inline string, never a formula. */
defined( 'ABSPATH' ) || exit;
function tmk_conv_team_xlsx( $fields, $rows ) {
	$tmp = tempnam( sys_get_temp_dir(), 'tmk-xlsx-' );
	if ( false === $tmp ) {
		wp_die( 'Esportazione non disponibile.', '', array( 'response' => 503 ) );
	}
	try {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
			throw new RuntimeException();
		}
		$ox = 'http://schemas.openxmlformats.org/';
		$sheet_ns = $ox . 'spreadsheetml/2006/main';
		$rel_ns = $ox . 'officeDocument/2006/relationships';
		$rels = '<Relationships xmlns="' . $ox . 'package/2006/relationships"><Relationship Id="rId1" ';
		$parts = array(
			'[Content_Types].xml' => '<Types xmlns="' . $ox . 'package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>',
			'_rels/.rels' => $rels . 'Type="' . $rel_ns . '/officeDocument" Target="xl/workbook.xml"/></Relationships>',
			'xl/_rels/workbook.xml.rels' => $rels . 'Type="' . $rel_ns . '/worksheet" Target="worksheets/sheet1.xml"/></Relationships>',
			'xl/workbook.xml' => '<workbook xmlns="' . $sheet_ns . '" xmlns:r="' . $rel_ns . '"><sheets><sheet name="Convenzioni" sheetId="1" r:id="rId1"/></sheets></workbook>',
		);
		$xml = '<worksheet xmlns="' . $sheet_ns . '"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="9" width="26" customWidth="1"/></cols><sheetData>';
		array_unshift( $rows, $fields );
		foreach ( $rows as $i => $row ) {
			$n = $i + 1;
			$xml .= '<row r="' . $n . '">';
			$j = 0;
			foreach ( $fields as $key => $label ) {
				$value = preg_replace( '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', (string) ( $row[ $key ] ?? '' ) );
				$xml .= '<c r="' . chr( 65 + $j++ ) . $n . '" t="inlineStr"><is><t xml:space="preserve">' . htmlspecialchars( $value, ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8' ) . '</t></is></c>';
			}
			$xml .= '</row>';
		}
		$parts['xl/worksheets/sheet1.xml'] = $xml . '</sheetData><autoFilter ref="A1:I' . count( $rows ) . '"/></worksheet>';
		foreach ( $parts as $name => $content ) {
			if ( ! $zip->addFromString( $name, $content ) ) {
				throw new RuntimeException();
			}
		}
		if ( ! $zip->close() ) {
			throw new RuntimeException();
		}
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="convenzioni-' . wp_date( 'Y-m-d' ) . '.xlsx"' );
		readfile( $tmp );
	} finally {
		unlink( $tmp );
	}
}
