<?php
/**
 * Skript A: Abgleich Mitglieder-Export ↔ vorhandene eBuSy-Personen (nur lesend).
 *
 * Aufruf (Projektroot):
 *   php -c php.ini wp-content/themes/tc-grubweg/bin/ebusy-map-persons.php [--cached]
 *
 * Liest  import/mitglieder-export.csv (UTF-8, Semikolon, Kopfzeile)
 * Holt   GET /general/persons (alle Seiten) und GET /member/modules/{id}/memberships
 *        --cached nutzt stattdessen import/ebusy-persons.json / ebusy-memberships.json
 * Schreibt
 *   import/abgleich-personen.csv   je Mitglied: Status, Methode, eBuSy-Personen-Id, Kandidaten
 *   import/nur-in-ebusy.csv        nicht archivierte eBuSy-Personen ohne Treffer im Export
 *
 * Status: eindeutig | pruefen | mehrdeutig | neu
 *
 * @package tc-grubweg
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$root = dirname( __DIR__, 4 );
require $root . '/wp-load.php';

$import_dir = $root . '/import';
$use_cache  = in_array( '--cached', $argv, true );

// ── Hilfsfunktionen ────────────────────────────────────────────────────────────

function tcg_map_norm( $s ) {
	$s = mb_strtolower( trim( (string) $s ), 'UTF-8' );
	$s = strtr( $s, [ 'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', 'à' => 'a', 'á' => 'a', 'é' => 'e', 'è' => 'e', 'ć' => 'c', 'č' => 'c', 'š' => 's', 'ž' => 'z' ] );
	$s = remove_accents( $s );
	return preg_replace( '/[^a-z]/', '', $s );
}

/** Erster Vorname normalisiert („Hans-Peter" → „hans"). */
function tcg_map_first( $s ) {
	$parts = preg_split( '/[\s\-]+/', trim( (string) $s ) );
	return tcg_map_norm( $parts[0] ?? '' );
}

/** Export-Datum TTMMJJJJ (führende Null evtl. verloren) → JJJJ-MM-TT. */
function tcg_map_csv_date( $d ) {
	$d = preg_replace( '/\D/', '', (string) $d );
	if ( '' === $d ) {
		return '';
	}
	$d = str_pad( $d, 8, '0', STR_PAD_LEFT );
	return substr( $d, 4, 4 ) . '-' . substr( $d, 2, 2 ) . '-' . substr( $d, 0, 2 );
}

/** eBuSy-Datum (JJJJ-MM-TT…, TT.MM.JJJJ, MM/TT/JJJJ) → JJJJ-MM-TT. */
function tcg_map_api_date( $d ) {
	$d = trim( (string) $d );
	if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})/', $d, $m ) ) {
		return "$m[1]-$m[2]-$m[3]";
	}
	if ( preg_match( '/^(\d{1,2})\.(\d{1,2})\.(\d{4})/', $d, $m ) ) {
		return sprintf( '%04d-%02d-%02d', $m[3], $m[2], $m[1] );
	}
	if ( preg_match( '/^(\d{1,2})\/(\d{1,2})\/(\d{4})/', $d, $m ) ) {
		return sprintf( '%04d-%02d-%02d', $m[3], $m[1], $m[2] );
	}
	return '';
}

function tcg_map_fetch_all( $label, callable $fetch ) {
	$all = [];
	for ( $offset = 0; ; $offset += 100 ) {
		$res = $fetch( $offset );
		if ( ! $res['ok'] ) {
			fwrite( STDERR, "Fehler beim Laden ($label, offset $offset): {$res['error']}\n" );
			exit( 1 );
		}
		$data  = $res['data'];
		$items = isset( $data['content'] ) ? $data['content'] : ( array_is_list( (array) $data ) ? (array) $data : [] );
		$all   = array_merge( $all, $items );
		fwrite( STDERR, "\r$label: " . count( $all ) );
		$last = isset( $data['last'] ) ? (bool) $data['last'] : count( $items ) < 100;
		if ( $last || ! $items ) {
			break;
		}
	}
	fwrite( STDERR, "\n" );
	return $all;
}

function tcg_map_write_csv( $file, array $header, array $rows ) {
	$fh = fopen( $file, 'w' );
	fwrite( $fh, "\xEF\xBB\xBF" ); // BOM, damit Excel UTF-8 erkennt.
	fputcsv( $fh, $header, ';', '"', '' );
	foreach ( $rows as $row ) {
		fputcsv( $fh, $row, ';', '"', '' );
	}
	fclose( $fh );
}

// ── Export lesen ───────────────────────────────────────────────────────────────

$fh = fopen( $import_dir . '/mitglieder-export.csv', 'r' );
if ( ! $fh ) {
	fwrite( STDERR, "import/mitglieder-export.csv nicht gefunden.\n" );
	exit( 1 );
}
$header  = fgetcsv( $fh, null, ';', '"', '' );
$members = [];
while ( ( $row = fgetcsv( $fh, null, ';', '"', '' ) ) !== false ) {
	if ( count( $row ) === count( $header ) ) {
		$members[] = array_combine( $header, $row );
	}
}
fclose( $fh );

// ── eBuSy laden ────────────────────────────────────────────────────────────────

$module_id    = 1349;
$persons_file = $import_dir . '/ebusy-persons.json';
$ms_file      = $import_dir . '/ebusy-memberships.json';

if ( $use_cache ) {
	$persons     = json_decode( file_get_contents( $persons_file ), true );
	$memberships = json_decode( file_get_contents( $ms_file ), true );
} else {
	$persons = tcg_map_fetch_all( 'Personen', fn( $o ) => tcg_ebusy_request( 'GET', 'general/persons?offset=' . $o . '&limit=100' ) );
	$memberships = tcg_map_fetch_all( 'Mitgliedschaften', fn( $o ) => tcg_ebusy_get_memberships( $module_id, $o, 100 ) );
	file_put_contents( $persons_file, wp_json_encode( $persons, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
	file_put_contents( $ms_file, wp_json_encode( $memberships, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
}

// Personen-Id → Mitgliedschaftsstatus.
$ms_by_person = [];
foreach ( $memberships as $ms ) {
	$pid = $ms['person']['id'] ?? $ms['personId'] ?? null;
	if ( $pid ) {
		$ms_by_person[ $pid ][] = ( $ms['status'] ?? '?' ) . '#' . ( $ms['id'] ?? '' );
	}
}

// Indizes über die eBuSy-Personen.
$idx = [ 'cust' => [], 'name_birth' => [], 'name_mail' => [], 'birth_mail' => [], 'last_birth' => [], 'name' => [] ];
$by_id = [];
foreach ( $persons as $p ) {
	$id           = $p['id'];
	$by_id[ $id ] = $p;
	$last         = tcg_map_norm( $p['lastname'] ?? '' );
	$first        = tcg_map_first( $p['firstname'] ?? '' );
	$birth        = tcg_map_api_date( $p['birthday'] ?? '' );
	$mail         = strtolower( trim( $p['contact']['email'] ?? '' ) );
	$cust         = trim( (string) ( $p['customerId'] ?? '' ) );

	if ( '' !== $cust ) {
		$idx['cust'][ $cust ][] = $id;
	}
	if ( '' === $last ) {
		continue;
	}
	$idx['name'][ "$last|$first" ][] = $id;
	if ( $birth ) {
		$idx['name_birth'][ "$last|$first|$birth" ][] = $id;
		$idx['last_birth'][ "$last|$birth" ][]        = $id;
	}
	if ( $mail ) {
		$idx['name_mail'][ "$last|$first|$mail" ][] = $id;
		if ( $birth ) {
			$idx['birth_mail'][ "$birth|$mail" ][] = $id;
		}
	}
}

// ── Abgleich ───────────────────────────────────────────────────────────────────

// Stufe => [ Index, Status bei genau einem Treffer ].
$stages = [
	'kundennummer'      => [ 'cust', 'eindeutig' ],
	'name+geburtstag'   => [ 'name_birth', 'eindeutig' ],
	'name+email'        => [ 'name_mail', 'eindeutig' ],
	'geburtstag+email'  => [ 'birth_mail', 'pruefen' ],
	'nachname+geburtst' => [ 'last_birth', 'pruefen' ],
	'nur name'          => [ 'name', 'pruefen' ],
];

$rows    = [];
$matched = [];
$counts  = [];

foreach ( $members as $m ) {
	$last  = tcg_map_norm( $m['nachname'] );
	$first = tcg_map_first( $m['vorname'] );
	$birth = tcg_map_csv_date( $m['geburtstag'] );
	$mail  = strtolower( trim( $m['email'] ) );
	$keys  = [
		'kundennummer'      => trim( $m['mitglieds_nr'] ),
		'name+geburtstag'   => $birth ? "$last|$first|$birth" : '',
		'name+email'        => $mail ? "$last|$first|$mail" : '',
		'geburtstag+email'  => ( $birth && $mail ) ? "$birth|$mail" : '',
		'nachname+geburtst' => $birth ? "$last|$birth" : '',
		'nur name'          => "$last|$first",
	];

	$status = 'neu';
	$method = '';
	$ids    = [];
	foreach ( $stages as $stage => [ $index, $single_status ] ) {
		$key = $keys[ $stage ];
		if ( '' === $key || empty( $idx[ $index ][ $key ] ) ) {
			continue;
		}
		$ids = array_values( array_unique( $idx[ $index ][ $key ] ) );
		// Bei Mehrfachtreffern archivierte Personen zurückstellen.
		if ( count( $ids ) > 1 ) {
			$active = array_values( array_filter( $ids, fn( $id ) => empty( $by_id[ $id ]['archived'] ) ) );
			if ( 1 === count( $active ) ) {
				$ids = $active;
			}
		}
		$method = $stage;
		$status = 1 === count( $ids ) ? $single_status : 'mehrdeutig';
		break;
	}

	$p = 1 === count( $ids ) ? $by_id[ $ids[0] ] : null;
	if ( $p && 'eindeutig' === $status ) {
		$matched[ $p['id'] ] = true;
	}
	$counts[ $status ] = ( $counts[ $status ] ?? 0 ) + 1;

	$rows[] = [
		$m['mitglieds_nr'],
		$m['nachname'],
		$m['vorname'],
		$birth,
		$m['email'],
		$status,
		$method,
		$p ? $p['id'] : '',
		$p ? trim( ( $p['firstname'] ?? '' ) . ' ' . ( $p['lastname'] ?? '' ) ) : '',
		$p ? tcg_map_api_date( $p['birthday'] ?? '' ) : '',
		$p ? ( $p['contact']['email'] ?? '' ) : '',
		$p ? ( $p['customerId'] ?? '' ) : '',
		$p ? ( empty( $p['archived'] ) ? 'nein' : 'ja' ) : '',
		$p ? implode( ',', $ms_by_person[ $p['id'] ] ?? [] ) : '',
		count( $ids ) > 1 ? implode( ',', array_map( fn( $id ) => $id . ' ' . trim( ( $by_id[ $id ]['firstname'] ?? '' ) . ' ' . ( $by_id[ $id ]['lastname'] ?? '' ) ) . ' ' . tcg_map_api_date( $by_id[ $id ]['birthday'] ?? '' ), $ids ) ) : '',
	];
}

tcg_map_write_csv(
	$import_dir . '/abgleich-personen.csv',
	[ 'mitglieds_nr', 'nachname', 'vorname', 'geburtstag', 'email', 'status', 'methode', 'ebusy_person_id', 'ebusy_name', 'ebusy_geburtstag', 'ebusy_email', 'ebusy_kundennr', 'ebusy_archiviert', 'ebusy_mitgliedschaft', 'kandidaten' ],
	$rows
);

// eBuSy-Personen ohne Treffer (nicht archiviert).
$only = [];
foreach ( $persons as $p ) {
	if ( empty( $p['archived'] ) && empty( $matched[ $p['id'] ] ) ) {
		$only[] = [
			$p['id'],
			$p['lastname'] ?? '',
			$p['firstname'] ?? '',
			tcg_map_api_date( $p['birthday'] ?? '' ),
			$p['contact']['email'] ?? '',
			$p['customerId'] ?? '',
			implode( ',', $ms_by_person[ $p['id'] ] ?? [] ),
		];
	}
}
tcg_map_write_csv( $import_dir . '/nur-in-ebusy.csv', [ 'ebusy_person_id', 'nachname', 'vorname', 'geburtstag', 'email', 'kundennr', 'mitgliedschaft' ], $only );

// ── Zusammenfassung (ohne personenbezogene Daten) ──────────────────────────────

$with_birth = count( array_filter( $persons, fn( $p ) => '' !== tcg_map_api_date( $p['birthday'] ?? '' ) ) );
$archived   = count( array_filter( $persons, fn( $p ) => ! empty( $p['archived'] ) ) );
$methods    = array_count_values( array_filter( array_column( $rows, 6 ) ) );

echo "eBuSy-Personen: " . count( $persons ) . " (archiviert $archived, mit Geburtstag $with_birth)\n";
echo "eBuSy-Mitgliedschaften Modul $module_id: " . count( $memberships ) . "\n";
echo "Export-Mitglieder: " . count( $members ) . "\n";
foreach ( [ 'eindeutig', 'pruefen', 'mehrdeutig', 'neu' ] as $s ) {
	printf( "  %-11s %d\n", $s, $counts[ $s ] ?? 0 );
}
echo "Treffer nach Methode: " . json_encode( $methods, JSON_UNESCAPED_UNICODE ) . "\n";
echo "Nur in eBuSy (nicht archiviert): " . count( $only ) . "\n";
