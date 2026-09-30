<?php
/**
 * Prüft übertragene Mitglieder in eBuSy (nur lesend).
 *
 * Aufruf (Projektroot):
 *   php -c php.ini wp-content/themes/tc-grubweg/bin/ebusy-verify-import.php --only=nr,nr
 *
 * Zeigt je Mitglied: Person, Hauptzahler, Mitgliedschaft (Nummer, Eintritt, Status, aktiv,
 * Beitragsart, Abteilungen, Zahlungsart) und vergleicht mit import/mitgliedschaften.csv.
 *
 * @package tc-grubweg
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$root = dirname( __DIR__, 4 );
require $root . '/wp-load.php';

$import_dir = $root . '/import';
$module_id  = 1349;
$opts       = getopt( '', [ 'only:' ] );
$only       = isset( $opts['only'] ) ? array_map( 'intval', explode( ',', $opts['only'] ) ) : [];
if ( ! $only ) {
	fwrite( STDERR, "--only=nr,nr angeben.\n" );
	exit( 1 );
}

$fh = fopen( $import_dir . '/mitgliedschaften.csv', 'r' );
if ( fread( $fh, 3 ) !== "\xEF\xBB\xBF" ) {
	rewind( $fh );
}
$header = fgetcsv( $fh, null, ';', '"', '' );
$rows   = [];
while ( ( $row = fgetcsv( $fh, null, ';', '"', '' ) ) !== false ) {
	$r                                  = array_combine( $header, $row );
	$rows[ (int) $r['mitglieds_nr'] ] = $r;
}
fclose( $fh );
$journal = file_exists( $import_dir . '/import-journal.json' ) ? json_decode( file_get_contents( $import_dir . '/import-journal.json' ), true ) : [];

$ok = fn( $cond ) => $cond ? '✓' : '✗';

foreach ( $only as $nr ) {
	$r   = $rows[ $nr ] ?? null;
	$pid = (int) ( $r['ebusy_person_id'] ?? 0 ) ?: (int) ( $journal[ $nr ]['person_id'] ?? 0 );
	echo "── $nr {$r['vorname']} {$r['nachname']} (Person #$pid)\n";
	if ( ! $pid ) {
		echo "   keine Personen-Id\n";
		continue;
	}

	$p = tcg_ebusy_request( 'GET', "general/person/by-id/$pid" );
	if ( ! $p['ok'] ) {
		echo "   FEHLER Person: {$p['error']}\n";
		continue;
	}
	$p      = $p['data'];
	$payer  = (int) ( $p['paidByInfo']['id'] ?? 0 );
	$expect = '' !== $r['hauptzahler_person_id'] ? (int) $r['hauptzahler_person_id'] : (int) ( $journal[ (int) $r['hauptzahler_nr'] ]['person_id'] ?? 0 );
	printf( "   Person:      %s %s, geb. %s, IBAN %s, Login %s\n", $p['firstname'] ?? '', $p['lastname'] ?? '', $p['birthday'] ?? '–', empty( $p['bankAccount']['number'] ) ? 'nein' : 'ja', ! empty( $p['user']['enabled'] ) ? 'aktiv' : 'aus' );
	printf( "   Hauptzahler: %s  %s\n", $payer ? "#$payer (Module " . wp_json_encode( $p['paidByInfo']['modules'] ?? [] ) . ')' : '–', $ok( $payer === $expect ) );

	$m = tcg_ebusy_request( 'GET', "member/modules/$module_id/memberships/by-person-id/$pid" );
	if ( ! $m['ok'] ) {
		echo "   FEHLER Mitgliedschaft: {$m['error']}\n";
		continue;
	}
	$list = $m['data']['content'] ?? ( is_array( $m['data'] ) && array_is_list( $m['data'] ) ? $m['data'] : [] );
	if ( ! $list ) {
		echo "   Mitgliedschaft: keine\n";
	}
	foreach ( $list as $ms ) {
		$fees = array_map( fn( $t ) => is_array( $t ) ? ( $t['id'] . ' ' . ( $t['name'] ?? '' ) ) : $t, (array) $ms['membershipFeeTypes'] );
		$fee_ids = array_map( fn( $t ) => is_array( $t ) ? (int) $t['id'] : (int) $t, (array) $ms['membershipFeeTypes'] );
		printf( "   Mitgliedschaft #%d: Nr %s %s | Eintritt %s %s | %s | aktiv %s %s | archiviert %s\n", $ms['id'], $ms['number'] ?? '–', $ok( (string) ( $ms['number'] ?? '' ) === (string) $nr ), $ms['begin'] ?? '–', $ok( ( $ms['begin'] ?? '' ) === $r['eintritt'] ), $ms['status'] ?? '?', ! empty( $ms['consideredActive'] ) ? 'ja' : 'nein', $ok( ( ! empty( $ms['consideredActive'] ) ? 'ja' : 'nein' ) === $r['aktiv'] ), ! empty( $ms['archived'] ) ? 'ja' : 'nein' );
		printf( "      Beitragsart: %s %s | Abteilungen %s | Zahlungsart %s\n", implode( ', ', $fees ) ?: '–', $ok( '' === $r['beitragsart_id'] ? ! array_filter( $fee_ids ) : in_array( (int) $r['beitragsart_id'], $fee_ids, true ) ), wp_json_encode( $ms['sections'] ?? [] ), wp_json_encode( $ms['paymentType'] ?? null, JSON_UNESCAPED_UNICODE ) );
	}
}
