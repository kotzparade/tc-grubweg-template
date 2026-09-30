<?php
/**
 * Baut die Import-Tabelle der Mitgliedschaften (nur lokal, kein API-Zugriff).
 *
 * Aufruf (Projektroot):
 *   php wp-content/themes/tc-grubweg/bin/ebusy-build-memberships.php
 *
 * Liest  import/mitglieder-export.csv, import/abgleich-personen.csv (Skript A),
 *        import/zuordnung-manuell.csv (manuell geprüfte Zuordnungen)
 * Schreibt import/mitgliedschaften.csv
 *
 * Regeln:
 *   Personen-Id    eindeutiger Treffer aus Skript A, sonst manuelle Zuordnung, sonst leer (neu anlegen)
 *   Beitragsart    aus Jahresbeitrag + Alter am 01.01.2026 + Rolle (x0 = Hauptmitglied, x1–x9 = Familie)
 *   Hauptzahler    wer in der Zehnergruppe den Familienbeitrag zahlt, sonst die x0-Nummer
 *
 * @package tc-grubweg
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$import_dir = dirname( __DIR__, 4 ) . '/import';

const TCG_FEE_TYPES = [
	3756 => 'Einzelpersonen ab 18',
	3757 => 'Familienbeitrag',
	3758 => 'Studenten/Azubis',
	3759 => 'Kinder/Jugendliche bis 17',
	3760 => 'Kinder bis 13 (Familienbeitrag)',
	3761 => 'Kinder bis 13 (einzeln)',
	3762 => 'Ruhende Mitgliedschaft',
	3764 => 'Zweiter Erwachsener (Familie)',
	3765 => 'Senioren-/Ehrenrabatt',
	3766 => 'Ehrenmitglied',
];

function tcg_build_read_csv( $file ) {
	$fh = fopen( $file, 'r' );
	if ( ! $fh ) {
		fwrite( STDERR, "Datei fehlt: $file\n" );
		exit( 1 );
	}
	if ( fread( $fh, 3 ) !== "\xEF\xBB\xBF" ) {
		rewind( $fh );
	}
	$header = fgetcsv( $fh, null, ';', '"', '' );
	$rows   = [];
	while ( ( $row = fgetcsv( $fh, null, ';', '"', '' ) ) !== false ) {
		if ( count( $row ) === count( $header ) ) {
			$rows[] = array_combine( $header, $row );
		}
	}
	fclose( $fh );
	return $rows;
}

/** Export-Datum TTMMJJJJ (führende Null evtl. verloren) → JJJJ-MM-TT. */
function tcg_build_date( $d ) {
	$d = preg_replace( '/\D/', '', (string) $d );
	if ( '' === $d ) {
		return '';
	}
	$d = str_pad( $d, 8, '0', STR_PAD_LEFT );
	return substr( $d, 4, 4 ) . '-' . substr( $d, 2, 2 ) . '-' . substr( $d, 0, 2 );
}

/** Alter in vollen Jahren zum Stichtag. */
function tcg_build_age( $birth, $at = '2026-01-01' ) {
	if ( '' === $birth ) {
		return null;
	}
	return ( new DateTime( $birth ) )->diff( new DateTime( $at ) )->y;
}

/**
 * Beitragsart je Mitglied.
 *
 * @return array [ fee_type_id|null, aktiv (bool), bemerkung ]
 */
function tcg_build_fee_type( $beitrag, $age, $is_main ) {
	switch ( (int) $beitrag ) {
		case 280:
			return [ 3757, true, '' ];
		case 170:
			return [ 3756, true, '' ];
		case 100:
			return [ 3758, true, '' ];
		case 80:
			return [ 3759, true, '' ];
		case 60:
			return $age < 18 ? [ 3761, true, '' ] : [ 3762, false, '' ];
		case 30:
			return [ 3765, true, '' ];
		case 0:
			// Beitragsfreie Erwachsene ohne Familienzahler = Ehrenmitglieder.
			if ( $is_main && $age >= 18 ) {
				return [ 3766, true, '' ];
			}
			if ( $age < 14 ) {
				return [ 3760, true, '' ];
			}
			if ( $age >= 18 ) {
				return [ 3764, true, '' ];
			}
			return [ null, true, "0 € mit $age Jahren in Familie – Beitragsart klären" ];
	}
	return [ null, true, "unbekannter Beitrag $beitrag €" ];
}

// ── Daten laden ────────────────────────────────────────────────────────────────

$members = tcg_build_read_csv( $import_dir . '/mitglieder-export.csv' );

$person_ids = [];
foreach ( tcg_build_read_csv( $import_dir . '/abgleich-personen.csv' ) as $a ) {
	if ( 'eindeutig' === $a['status'] ) {
		$person_ids[ $a['mitglieds_nr'] ] = [ $a['ebusy_person_id'], 'automatisch' ];
	}
}
foreach ( tcg_build_read_csv( $import_dir . '/zuordnung-manuell.csv' ) as $z ) {
	if ( str_starts_with( $z['entscheidung'], 'zuordnen' ) ) {
		$person_ids[ $z['mitglieds_nr'] ] = [ $z['ebusy_person_id'], 'manuell (' . $z['sicherheit'] . ')' ];
	}
}

// Zahler je Zehnergruppe: wer den Familienbeitrag (280 €) zahlt, sonst das x0-Mitglied.
$groups = [];
$by_nr  = [];
foreach ( $members as $m ) {
	$nr                                   = (int) $m['mitglieds_nr'];
	$by_nr[ $nr ]                         = $m;
	$groups[ intdiv( $nr, 10 ) * 10 ][ $nr ] = (int) $m['beitrag'];
}
$payers = [];
foreach ( $groups as $main_nr => $fees ) {
	$family = array_keys( $fees, 280, true );
	if ( 1 === count( $family ) ) {
		$payers[ $main_nr ] = $family[0];
	} elseif ( isset( $fees[ $main_nr ] ) ) {
		$payers[ $main_nr ] = $main_nr;
	}
}

// ── Tabelle bauen ──────────────────────────────────────────────────────────────

$out    = [];
$stats  = [ 'fee' => [], 'ohne_person' => 0, 'ohne_beitragsart' => 0, 'ohne_eintritt' => 0, 'mit_hauptzahler' => 0, 'passiv' => 0 ];
foreach ( $members as $m ) {
	$nr      = (int) $m['mitglieds_nr'];
	$main_nr = intdiv( $nr, 10 ) * 10;
	$is_main = $nr === $main_nr;
	$birth   = tcg_build_date( $m['geburtstag'] );
	$begin   = tcg_build_date( $m['eintritt'] );
	$age     = tcg_build_age( $birth );

	[ $fee_id, $active, $note ] = tcg_build_fee_type( $m['beitrag'], $age, $is_main );
	[ $pid, $source ]           = $person_ids[ $nr ] ?? [ '', 'neu anlegen' ];

	$payer_nr  = '';
	$payer_pid = '';
	$payer   = $payers[ $main_nr ] ?? null;
	// Wer eine eigene, abweichende IBAN hat, zahlt selbst.
	$own_iban   = $m['iban'];
	$payer_iban = null !== $payer ? $by_nr[ $payer ]['iban'] : '';
	$pays_self  = (int) $m['beitrag'] > 0 && '' !== $own_iban && $own_iban !== $payer_iban;
	if ( $pays_self && null !== $payer && $payer !== $nr ) {
		$note = trim( $note . ' eigene IBAN – zahlt selbst, kein Hauptzahler' );
	}
	if ( null !== $payer && $payer !== $nr && count( $groups[ $main_nr ] ) > 1 && ! $pays_self ) {
		$payer_nr  = $payer;
		$payer_pid = $person_ids[ $payer ][0] ?? '';
		++$stats['mit_hauptzahler'];
		if ( $payer !== $main_nr ) {
			$note = trim( $note . " Zahler ist Nr. $payer (Familienbeitrag), nicht x0" );
		}
	}
	if ( $is_main && null !== $age && $age < 18 && 280 === (int) $m['beitrag'] ) {
		$note = trim( $note . ' Familienbeitrag bei Minderjährigem (' . $age . ' J.) – Geburtstag/Zahler prüfen' );
	}
	if ( '' === $begin ) {
		$note = trim( $note . ' Eintrittsdatum fehlt' );
		++$stats['ohne_eintritt'];
	}

	$stats['fee'][ $fee_id ?? 'keine' ] = ( $stats['fee'][ $fee_id ?? 'keine' ] ?? 0 ) + 1;
	$stats['ohne_person']      += '' === $pid ? 1 : 0;
	$stats['ohne_beitragsart'] += null === $fee_id ? 1 : 0;
	$stats['passiv']           += $active ? 0 : 1;

	$out[] = [
		$m['mitglieds_nr'],
		$m['nachname'],
		$m['vorname'],
		$birth,
		$age,
		$pid,
		$source,
		$begin,
		$m['beitrag'],
		$fee_id ?? '',
		null !== $fee_id ? TCG_FEE_TYPES[ $fee_id ] : '',
		$active ? 'ja' : 'nein',
		$payer_nr,
		$payer_pid,
		$note,
	];
}

$fh = fopen( $import_dir . '/mitgliedschaften.csv', 'w' );
fwrite( $fh, "\xEF\xBB\xBF" );
fputcsv( $fh, [ 'mitglieds_nr', 'nachname', 'vorname', 'geburtstag', 'alter_2026', 'ebusy_person_id', 'zuordnung', 'eintritt', 'beitrag_eur', 'beitragsart_id', 'beitragsart', 'aktiv', 'hauptzahler_nr', 'hauptzahler_person_id', 'bemerkung' ], ';', '"', '' );
foreach ( $out as $row ) {
	fputcsv( $fh, $row, ';', '"', '' );
}
fclose( $fh );

// ── Zusammenfassung ────────────────────────────────────────────────────────────

echo 'Mitgliedschaften: ' . count( $out ) . "\n";
ksort( $stats['fee'] );
foreach ( $stats['fee'] as $id => $n ) {
	printf( "  %-6s %-34s %d\n", $id, TCG_FEE_TYPES[ $id ] ?? '(keine)', $n );
}
echo "Ohne Personen-Id (neu anlegen): {$stats['ohne_person']}\n";
echo "Ohne Beitragsart: {$stats['ohne_beitragsart']}\n";
echo "Passiv: {$stats['passiv']}\n";
echo "Mit Hauptzahler: {$stats['mit_hauptzahler']}\n";
echo "Ohne Eintrittsdatum: {$stats['ohne_eintritt']}\n";
echo 'Mit Bemerkung: ' . count( array_filter( $out, fn( $r ) => '' !== $r[14] ) ) . "\n";
