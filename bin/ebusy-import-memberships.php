<?php
/**
 * Skript C: Mitgliederbestand nach eBuSy übertragen.
 *
 * Aufruf (Projektroot):
 *   php -c php.ini wp-content/themes/tc-grubweg/bin/ebusy-import-memberships.php --step=<schritt> [--only=nr,nr] [--live]
 *
 * Schritte (in dieser Reihenfolge):
 *   persons      fehlende Personen anlegen (POST /general/person, Login deaktiviert)
 *   memberships  Mitgliedschaft anlegen bzw. vorhandene übernehmen (Nummer, Eintritt, aktiv/passiv, Abteilung)
 *   fees         Beitragsart + Zahlungsart per PATCH (wird von eBuSy ignoriert, nur noch zur Kontrolle)
 *   attributes   Personen-Attribut „Beiträge" setzen – daraus vergibt eBuSy Gruppe + Beitragsart
 *   payers       Hauptzahler an der Person setzen (paidByInfo, nur Modul Mitglieder)
 *   birthdays    Geburtstag aus dem Export übernehmen (nur mit --only)
 *   bank         IBAN aus dem Export, wo eBuSy keine hat (Selbstzahler; --minors auch Minderjährige, ohne Mandat)
 *   masterdata   leere Stammdaten (Adresse, E-Mail, Telefon, Geburtstag, Geschlecht) aus dem Export ergänzen
 *
 * Ohne --live nur Dry-Run (keine Schreibzugriffe). --only begrenzt auf Mitgliedsnummern.
 * Liest import/mitgliedschaften.csv + import/mitglieder-export.csv, merkt sich Erledigtes in
 * import/import-journal.json – ein erneuter Lauf überspringt, was schon übertragen ist.
 *
 * @package tc-grubweg
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$root = dirname( __DIR__, 4 );
require $root . '/wp-load.php';

$import_dir   = $root . '/import';
$journal_file = $import_dir . '/import-journal.json';
$module_id    = 1349;
$type_id      = 338;
$section_ids  = [ 200 ]; // Tennis
$import_note  = 'Übernahme Mitgliederbestand (Vereins-Export) am ' . wp_date( 'd.m.Y' );

$opts = getopt( '', [ 'step:', 'only:', 'live', 'no-payment', 'fee-format:', 'minors' ] );
$step = $opts['step'] ?? '';
$live = isset( $opts['live'] );
$only = isset( $opts['only'] ) ? array_map( 'intval', explode( ',', $opts['only'] ) ) : [];

if ( ! in_array( $step, [ 'persons', 'memberships', 'fees', 'attributes', 'payers', 'birthdays', 'bank', 'masterdata' ], true ) ) {
	fwrite( STDERR, "--step=persons|memberships|fees|attributes|payers|birthdays|bank|masterdata angeben.\n" );
	exit( 1 );
}
if ( 'birthdays' === $step && ! $only ) {
	fwrite( STDERR, "--step=birthdays nur mit --only=nr,nr.\n" );
	exit( 1 );
}

// ── Hilfsfunktionen ────────────────────────────────────────────────────────────

function tcg_imp_read_csv( $file ) {
	$fh = fopen( $file, 'r' );
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

function tcg_imp_journal_save( $file, array $journal ) {
	file_put_contents( $file, wp_json_encode( $journal, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
}

/** Personendeskriptor aus einer Export-Zeile (nur für neu anzulegende Personen). */
function tcg_imp_build_person( array $e, $birthday, $note ) {
	$person = [
		'firstname' => $e['vorname'],
		'lastname'  => $e['nachname'],
		'address'   => [
			'street'      => $e['strasse'],
			'postcode'    => $e['plz'],
			'city'        => $e['ort'],
			'country'     => 'Deutschland',
			'countryCode' => 'DE',
		],
		'contact'   => [],
		// Ohne „user"-Objekt antwortet eBuSy mit HTTP 500; Login bleibt aus, bis der Verein ihn freischaltet.
		'user'      => [
			'enabled' => false,
			'level'   => 'USER',
		],
		'comment'   => $note . ', Mitglieds-Nr. ' . $e['mitglieds_nr'] . '.',
	];
	if ( 'Herrn' === $e['anrede'] ) {
		$person['gender']     = 'MALE';
		$person['salutation'] = 'MALE';
	} elseif ( in_array( $e['anrede'], [ 'Frau', 'Fräulein' ], true ) ) {
		$person['gender']     = 'FEMALE';
		$person['salutation'] = 'FEMALE';
	}
	if ( $birthday ) {
		$person['birthday'] = $birthday;
	}
	if ( '' !== $e['email'] ) {
		$person['contact']['email'] = $e['email'];
	}
	foreach ( [ $e['telefon'], $e['telefon2'] ] as $tel ) {
		if ( '' === $tel ) {
			continue;
		}
		$key = tcg_ebusy_is_mobile_number( $tel ) ? 'mobile' : 'phone';
		if ( empty( $person['contact'][ $key ] ) ) {
			$person['contact'][ $key ] = $tel;
		}
	}
	if ( '' !== $e['iban'] ) {
		$person['bankAccount'] = [
			'holder' => trim( $e['vorname'] . ' ' . $e['nachname'] ),
			'number' => $e['iban'],
			'bank'   => $e['bank'],
		];
	}
	if ( ! $person['contact'] ) {
		unset( $person['contact'] );
	}
	return $person;
}

/**
 * eBuSy vergibt die Beitragsart nicht per API, sondern über Gruppen „MGV – …“, deren Regeln
 * auf Alter, aktive Mitgliedschaft und das Personen-Attribut „Beiträge" (9309) schauen.
 * Beitragsart-ID => [ Attribut-ID => Attributwert-ID ].
 */
const TCG_IMP_FEE_ATTRIBUTES = [
	3756 => [ 9309 => 92958 ],                 // Einzelpersonen ab 18 Jahren
	3757 => [ 9309 => 92954 ],                 // Ehepaare u. Lebensgemeinschaften mit Kindern bis 13 Jahre
	3758 => [ 9309 => 92959 ],                 // Studenten, Zivildienstleistende, Wehrpflichtige, Auszubildende
	3759 => [ 9309 => 92960 ],                 // Kinder / Jugendliche bis 17 Jahre
	3760 => [ 9309 => 92956, 9310 => 92961 ],  // Kinder bis 13 Jahre + Eltern im Verein / Familienmitgliedschaft
	3761 => [ 9309 => 92956 ],                 // Kinder bis 13 Jahre
	3762 => [ 9309 => 92957 ],                 // Ruhende Mitgliedschaften/passive Mitgliedschaft
	3764 => [ 9309 => 92976 ],                 // Zweiter Erwachsener im Familienbeitrag
	3765 => [ 9309 => 92975 ],                 // Senioren-/Ehrenrabatt
	3766 => [ 9309 => 92977 ],                 // Ehrenmitglied
];

/**
 * Setzt Beitragsart und Zahlungsart einer Mitgliedschaft und prüft das Ergebnis per GET.
 *
 * Beitragsart mit dem Minimal-PATCH aus den Schreibtests vom 13.09.2026 (status + consideredActive +
 * membershipFeeTypes); zusammen mit number/begin übernahm eBuSy sie im Test vom 30.09.2026 nicht.
 *
 * @return true|string true oder Fehlermeldung
 */
function tcg_imp_apply_fee( $module_id, $mid, $fee_id, $active, $payment_type, $fee_format = 'ids' ) {
	$base = [ 'status' => 'ACTIVE', 'consideredActive' => $active ];
	if ( $fee_id ) {
		$fees = 'objects' === $fee_format ? [ [ 'id' => $fee_id ] ] : [ $fee_id ];
		$res  = tcg_ebusy_update_membership( $module_id, $mid, $base + [ 'membershipFeeTypes' => $fees ] );
		if ( ! $res['ok'] ) {
			return 'PATCH Beitragsart: ' . $res['error'];
		}
	}
	if ( $payment_type ) {
		$res = tcg_ebusy_update_membership( $module_id, $mid, $base + [ 'paymentType' => $payment_type ] );
		if ( ! $res['ok'] ) {
			return 'PATCH Zahlungsart: ' . $res['error'];
		}
	}
	$res = tcg_ebusy_request( 'GET', "member/modules/$module_id/membership/by-id/$mid" );
	if ( ! $res['ok'] ) {
		return 'Kontrolle: ' . $res['error'];
	}
	$ms      = $res['data'];
	$fee_ids = array_map( fn( $t ) => is_array( $t ) ? (int) ( $t['id'] ?? 0 ) : (int) $t, (array) ( $ms['membershipFeeTypes'] ?? [] ) );
	$errors  = [];
	if ( $fee_id && $fee_ids !== [ $fee_id ] ) {
		$errors[] = 'Beitragsart ist ' . wp_json_encode( $fee_ids ) . " statt [$fee_id]";
	}
	if ( $payment_type && (int) ( $ms['paymentType']['ordinal'] ?? -1 ) !== (int) $payment_type['ordinal'] ) {
		$errors[] = 'Zahlungsart ist ' . wp_json_encode( $ms['paymentType'] ?? null );
	}
	if ( (bool) ( $ms['consideredActive'] ?? true ) !== (bool) $active ) {
		$errors[] = 'aktiv/passiv falsch';
	}
	return $errors ? implode( '; ', $errors ) : true;
}

// ── Daten laden ────────────────────────────────────────────────────────────────

$export = [];
foreach ( tcg_imp_read_csv( $import_dir . '/mitglieder-export.csv' ) as $e ) {
	$export[ (int) $e['mitglieds_nr'] ] = $e;
}
$rows = tcg_imp_read_csv( $import_dir . '/mitgliedschaften.csv' );
if ( $only ) {
	$rows = array_values( array_filter( $rows, fn( $r ) => in_array( (int) $r['mitglieds_nr'], $only, true ) ) );
}
$journal = file_exists( $journal_file ) ? json_decode( file_get_contents( $journal_file ), true ) : [];

$settings     = tcg_ebusy_settings();
$payment_type = [ 'ordinal' => 2 ];
if ( '' !== (string) ( $settings['payment_type_name'] ?? '' ) ) {
	$payment_type['name'] = $settings['payment_type_name'];
}

/** Personen-Id: aus der Tabelle oder, für neu angelegte, aus dem Journal. */
$person_id_of = function ( $nr ) use ( &$journal, $import_dir ) {
	static $table = null;
	if ( null === $table ) {
		$table = [];
		foreach ( tcg_imp_read_csv( $import_dir . '/mitgliedschaften.csv' ) as $r ) {
			$table[ (int) $r['mitglieds_nr'] ] = (int) $r['ebusy_person_id'];
		}
	}
	return $table[ $nr ] ?: (int) ( $journal[ $nr ]['person_id'] ?? 0 );
};

echo ( $live ? 'LIVE' : 'DRY-RUN' ) . " – Schritt $step – " . count( $rows ) . " Zeilen\n";
$count = [];
$log   = function ( $nr, $name, $action, $detail = '' ) use ( &$count ) {
	$count[ $action ] = ( $count[ $action ] ?? 0 ) + 1;
	printf( "  %5d %-32s %-26s %s\n", $nr, mb_substr( $name, 0, 32 ), $action, $detail );
};

// ── Schritte ───────────────────────────────────────────────────────────────────

foreach ( $rows as $r ) {
	$nr   = (int) $r['mitglieds_nr'];
	$name = $r['vorname'] . ' ' . $r['nachname'];
	$j    = $journal[ $nr ] ?? [];

	if ( 'persons' === $step ) {
		if ( '' !== $r['ebusy_person_id'] ) {
			continue;
		}
		if ( ! empty( $j['person_id'] ) ) {
			$log( $nr, $name, 'bereits angelegt', '#' . $j['person_id'] );
			continue;
		}
		$person = tcg_imp_build_person( $export[ $nr ], $r['geburtstag'], $import_note );
		if ( ! $live ) {
			$log( $nr, $name, 'Person anlegen', isset( $person['bankAccount'] ) ? 'mit IBAN' : 'ohne IBAN' );
			continue;
		}
		$res = tcg_ebusy_create_person( $person );
		$pid = $res['ok'] ? (int) ( $res['data']['id'] ?? 0 ) : 0;
		if ( ! $pid ) {
			$log( $nr, $name, 'FEHLER Person', $res['error'] ?: 'keine Id' );
			continue;
		}
		$journal[ $nr ]['person_id'] = $pid;
		tcg_imp_journal_save( $journal_file, $journal );
		$log( $nr, $name, 'Person angelegt', "#$pid" );
	}

	// Mitgliedschaft anlegen bzw. übernehmen – nur, solange im Journal noch keine steht.
	if ( 'memberships' === $step && empty( $j['membership_id'] ) ) {
		$pid = $person_id_of( $nr );
		if ( ! $pid ) {
			$log( $nr, $name, 'übersprungen', 'keine Personen-Id (erst --step=persons)' );
			continue;
		}
		if ( '' === $r['eintritt'] ) {
			$log( $nr, $name, 'übersprungen', 'Eintrittsdatum fehlt' );
			continue;
		}
		$active   = 'ja' === $r['aktiv'];
		$fee_id   = (int) $r['beitragsart_id'];
		$has_bank = '' !== $export[ $nr ]['iban'] || '' !== $r['hauptzahler_nr'];

		// Vorhandene Mitgliedschaft (z. B. aus einem Online-Antrag) übernehmen statt doppelt anzulegen.
		$mid = (int) ( $j['membership_id'] ?? 0 );
		if ( ! $mid ) {
			$res = tcg_ebusy_request( 'GET', "member/modules/$module_id/memberships/by-person-id/$pid" );
			if ( ! $res['ok'] ) {
				$log( $nr, $name, 'FEHLER Abfrage', $res['error'] );
				continue;
			}
			$existing = $res['data']['content'] ?? ( is_array( $res['data'] ) && array_is_list( $res['data'] ) ? $res['data'] : [] );
			foreach ( $existing as $ms ) {
				if ( empty( $ms['archived'] ) && in_array( $ms['status'] ?? '', [ 'ACTIVE', 'REQUESTED' ], true ) ) {
					$mid = (int) $ms['id'];
					break;
				}
			}
		}

		$fee_label = $fee_id ? "Beitragsart $fee_id" : 'ohne Beitragsart';
		if ( ! $live ) {
			$log( $nr, $name, $mid ? 'vorhandene übernehmen' : 'Mitgliedschaft anlegen', "$fee_label, Eintritt {$r['eintritt']}" . ( $active ? '' : ', passiv' ) . ( $mid ? " (#$mid)" : '' ) );
			continue;
		}

		if ( ! $mid ) {
			$membership = [
				'personId'         => $pid,
				'membershipTypeId' => $type_id,
				'number'           => (string) $nr,
				'begin'            => $r['eintritt'],
				'status'           => 'ACTIVE',
				'consideredActive' => $active,
				'sections'         => $section_ids,
				'comment'          => $import_note . ( $r['bemerkung'] ? ' – ' . $r['bemerkung'] : '' ),
			];
			// Keine paymentType: Standard „wie in Beitragsart hinterlegt" (Lastschrift steht an der Beitragsart).
			$res = tcg_ebusy_create_membership( $module_id, $membership );
			$mid = $res['ok'] ? (int) ( $res['data']['id'] ?? 0 ) : 0;
			if ( ! $mid ) {
				$log( $nr, $name, 'FEHLER Mitgliedschaft', $res['error'] ?: 'keine Id' );
				continue;
			}
			$journal[ $nr ]['membership_id'] = $mid;
			$journal[ $nr ]['created']       = true;
			tcg_imp_journal_save( $journal_file, $journal );
		} else {
			$journal[ $nr ]['membership_id'] = $mid;
			$journal[ $nr ]['created']       = false;
			tcg_imp_journal_save( $journal_file, $journal );
		}

		// Übernommene Mitgliedschaft: Nummer und Eintritt aus dem Export setzen (eigener PATCH, getrennt von der Beitragsart).
		if ( ! $journal[ $nr ]['created'] ) {
			$res = tcg_ebusy_update_membership( $module_id, $mid, [ 'status' => 'ACTIVE', 'consideredActive' => $active, 'number' => (string) $nr, 'begin' => $r['eintritt'] ] );
			if ( ! $res['ok'] ) {
				$log( $nr, $name, 'FEHLER Nummer/Eintritt', "#$mid: " . $res['error'] );
				continue;
			}
		}
		$log( $nr, $name, $journal[ $nr ]['created'] ? 'Mitgliedschaft angelegt' : 'vorhandene übernommen', "#$mid" );
	}

	// Nur noch zur Diagnose: eBuSy ignoriert Beitragsart und Zahlungsart per PATCH (Beitragsart → --step=attributes,
	// Zahlungsart = „wie in Beitragsart hinterlegt", in der API null).
	if ( 'fees' === $step ) {
		$j   = $journal[ $nr ] ?? [];
		$mid = (int) ( $j['membership_id'] ?? 0 );
		if ( ! $mid || ! empty( $j['fee_ok'] ) ) {
			continue;
		}
		$fee_id   = (int) $r['beitragsart_id'];
		$active   = 'ja' === $r['aktiv'];
		$has_bank = '' !== $export[ $nr ]['iban'] || '' !== $r['hauptzahler_nr'];
		if ( ! $live ) {
			$log( $nr, $name, 'Beitragsart setzen', ( $fee_id ?: 'keine' ) . ( $has_bank ? ', Lastschrift' : '' ) . " (#$mid)" );
			continue;
		}
		$result = tcg_imp_apply_fee( $module_id, $mid, $fee_id, $active, ( $has_bank && ! isset( $opts['no-payment'] ) ) ? $payment_type : null, $opts['fee-format'] ?? 'ids' );
		if ( true !== $result ) {
			$log( $nr, $name, 'FEHLER Beitragsart', "#$mid: $result" );
			continue;
		}
		$journal[ $nr ]['fee_ok'] = true;
		tcg_imp_journal_save( $journal_file, $journal );
		$log( $nr, $name, 'Beitragsart gesetzt', ( $fee_id ?: 'keine' ) . ( $has_bank ? ', Lastschrift' : '' ) . " (#$mid)" );
	}

	// Geburtstag aus dem Export übernehmen (nur für ausdrücklich per --only genannte Mitglieder):
	// die Gruppenregeln hängen am Alter, fehlende/falsche Geburtstage in eBuSy ergeben falsche Beitragsarten.
	if ( 'birthdays' === $step ) {
		$pid = $person_id_of( $nr );
		if ( ! $pid || '' === $r['geburtstag'] ) {
			$log( $nr, $name, 'übersprungen', ! $pid ? 'keine Personen-Id' : 'kein Geburtstag im Export' );
			continue;
		}
		$before = tcg_ebusy_request( 'GET', "general/person/by-id/$pid" );
		$old    = $before['ok'] ? ( $before['data']['birthday'] ?? '' ) : '?';
		if ( ! $live ) {
			$log( $nr, $name, 'Geburtstag setzen', ( $old ?: 'leer' ) . " → {$r['geburtstag']}" );
			continue;
		}
		$res = tcg_ebusy_request( 'PATCH', "general/person/$pid", [ 'birthday' => $r['geburtstag'] ] );
		$now = $res['ok'] ? tcg_ebusy_request( 'GET', "general/person/by-id/$pid" ) : null;
		if ( ! $res['ok'] || ( $now['data']['birthday'] ?? '' ) !== $r['geburtstag'] ) {
			$log( $nr, $name, 'FEHLER Geburtstag', $res['ok'] ? 'ist jetzt ' . ( $now['data']['birthday'] ?? 'leer' ) : $res['error'] );
			continue;
		}
		$journal[ $nr ]['birthday_fixed'] = ( $old ?: 'leer' ) . ' → ' . $r['geburtstag'];
		tcg_imp_journal_save( $journal_file, $journal );
		$log( $nr, $name, 'Geburtstag gesetzt', $journal[ $nr ]['birthday_fixed'] );
	}

	// IBAN aus dem Export übertragen – nur wo eindeutig: Mitglied zahlt selbst, eBuSy hat (live geprüft)
	// keine IBAN, Export hat eine. Kein SEPA-Mandat. Kontoinhaber = Mitglied; Minderjährige nur mit
	// --minors: Inhaber aus import/kontoinhaber-minderjaehrige.csv (IBAN-Abgleich), sonst Name des Kindes als Platzhalter.
	if ( 'bank' === $step ) {
		$iban = strtoupper( str_replace( ' ', '', $export[ $nr ]['iban'] ) );
		if ( '' !== $r['hauptzahler_nr'] || 0 === (int) $r['beitrag_eur'] || '' === $iban ) {
			continue;
		}
		if ( ! empty( $j['bank_set'] ) ) {
			$log( $nr, $name, 'bereits übertragen', '' );
			continue;
		}
		$holder        = trim( $export[ $nr ]['vorname'] . ' ' . $export[ $nr ]['nachname'] );
		$holder_source = 'Mitglied';
		if ( (int) $r['alter_2026'] < 18 ) {
			if ( ! isset( $opts['minors'] ) ) {
				$log( $nr, $name, 'übersprungen', 'minderjährig – Kontoinhaber klären (--minors)' );
				continue;
			}
			static $minor_holders = null;
			if ( null === $minor_holders ) {
				$minor_holders = [];
				foreach ( tcg_imp_read_csv( $import_dir . '/kontoinhaber-minderjaehrige.csv' ) as $k ) {
					$minor_holders[ (int) $k['mitglieds_nr'] ] = $k['kontoinhaber_vorschlag'];
				}
			}
			if ( '' !== ( $minor_holders[ $nr ] ?? '' ) ) {
				$holder        = $minor_holders[ $nr ];
				$holder_source = 'Elternteil (IBAN-Abgleich)';
			} else {
				$holder_source = 'Platzhalter (Name des Kindes)';
			}
		}
		$pid = $person_id_of( $nr );
		$cur = $pid ? tcg_ebusy_request( 'GET', "general/person/by-id/$pid" ) : null;
		if ( ! $cur || ! $cur['ok'] ) {
			$log( $nr, $name, 'FEHLER Abfrage', $cur['error'] ?? 'keine Personen-Id' );
			continue;
		}
		if ( '' !== ( $cur['data']['bankAccount']['number'] ?? '' ) ) {
			continue; // eBuSy hat bereits eine IBAN – nicht anfassen.
		}
		$account = [
			'holder' => $holder,
			'number' => $iban,
			'bank'   => $export[ $nr ]['bank'],
		];
		if ( ! $live ) {
			$log( $nr, $name, 'IBAN übertragen', "Inhaber $holder – $holder_source" );
			continue;
		}
		$res = tcg_ebusy_request( 'PATCH', "general/person/$pid", [ 'bankAccount' => $account ] );
		$now = $res['ok'] ? tcg_ebusy_request( 'GET', "general/person/by-id/$pid" ) : null;
		if ( ! $res['ok'] || strtoupper( str_replace( ' ', '', $now['data']['bankAccount']['number'] ?? '' ) ) !== $iban ) {
			$log( $nr, $name, 'FEHLER IBAN', $res['ok'] ? 'nicht übernommen' : $res['error'] );
			continue;
		}
		$journal[ $nr ]['bank_set']      = true;
		$journal[ $nr ]['holder_source'] = $holder_source;
		tcg_imp_journal_save( $journal_file, $journal );
		$log( $nr, $name, 'IBAN übertragen', "Inhaber $holder – $holder_source" );
	}

	// Stammdaten ergänzen: nur leere Felder in eBuSy aus dem Export füllen, nichts überschreiben.
	// Adresse und Kontakt werden als vollständiges Objekt (bestehend + neu) gesendet, damit der PATCH
	// keine vorhandenen Unterfelder leert; danach Kontrolle per GET.
	if ( 'masterdata' === $step ) {
		if ( ! empty( $j['masterdata'] ) ) {
			continue;
		}
		$pid = $person_id_of( $nr );
		$cur = $pid ? tcg_ebusy_request( 'GET', "general/person/by-id/$pid" ) : null;
		if ( ! $cur || ! $cur['ok'] ) {
			$log( $nr, $name, 'FEHLER Abfrage', $cur['error'] ?? 'keine Personen-Id' );
			continue;
		}
		$p       = $cur['data'];
		$e       = $export[ $nr ];
		$patch   = [];
		$changes = [];
		$notes   = [];

		// Adresse: nur wenn Straße fehlt und vorhandene PLZ/Ort nicht widersprechen.
		$addr = array_merge( [ 'street' => '', 'postcode' => '', 'city' => '', 'country' => '', 'countryCode' => '' ], array_filter( (array) ( $p['address'] ?? [] ), 'is_string' ) );
		if ( '' === trim( $addr['street'] ) && '' !== $e['strasse'] ) {
			$conflict = ( '' !== $addr['postcode'] && $addr['postcode'] !== $e['plz'] ) || ( '' !== $addr['city'] && 0 !== strcasecmp( $addr['city'], $e['ort'] ) );
			if ( $conflict ) {
				$notes[] = 'Adresse: PLZ/Ort weichen ab, nicht ergänzt';
			} else {
				$addr['street']   = $e['strasse'];
				$addr['postcode'] = $addr['postcode'] ?: $e['plz'];
				$addr['city']     = $addr['city'] ?: $e['ort'];
				if ( '' === $addr['country'] && '' === $addr['countryCode'] ) {
					$addr['country']     = 'Deutschland';
					$addr['countryCode'] = 'DE';
				}
				$patch['address'] = $addr;
				$changes[]        = 'Adresse';
			}
		}

		// Kontakt: E-Mail, Festnetz, Mobil einzeln, nur wenn leer; Nummer nicht doppelt eintragen.
		$contact     = array_merge( [ 'email' => '', 'phone' => '', 'mobile' => '', 'phoneBusiness' => '' ], array_filter( (array) ( $p['contact'] ?? [] ), 'is_string' ) );
		$contact_new = $contact;
		if ( '' === trim( $contact['email'] ) && '' !== $e['email'] ) {
			$contact_new['email'] = $e['email'];
			$changes[]            = 'E-Mail';
		}
		$digits = fn( $n ) => preg_replace( '/^(\+49|0049)/', '0', preg_replace( '/[^\d+]/', '', (string) $n ) );
		$known  = array_filter( [ $digits( $contact['phone'] ), $digits( $contact['mobile'] ), $digits( $contact['phoneBusiness'] ) ] );
		foreach ( [ $e['telefon'], $e['telefon2'] ] as $tel ) {
			if ( '' === $tel || in_array( $digits( $tel ), $known, true ) ) {
				continue;
			}
			$key = tcg_ebusy_is_mobile_number( $tel ) ? 'mobile' : 'phone';
			if ( '' === trim( $contact_new[ $key ] ) ) {
				$contact_new[ $key ] = $tel;
				$known[]             = $digits( $tel );
				$changes[]           = 'mobile' === $key ? 'Mobil' : 'Telefon';
			}
		}
		if ( $contact_new !== $contact ) {
			$patch['contact'] = $contact_new;
		}

		if ( empty( $p['birthday'] ) && '' !== $r['geburtstag'] ) {
			$patch['birthday'] = $r['geburtstag'];
			$changes[]         = 'Geburtstag';
		}
		if ( empty( $p['gender'] ) && in_array( $e['anrede'], [ 'Herrn', 'Frau', 'Fräulein' ], true ) ) {
			$patch['gender'] = 'Herrn' === $e['anrede'] ? 'MALE' : 'FEMALE';
			if ( empty( $p['salutation'] ) || 'NONE' === $p['salutation'] ) {
				$patch['salutation'] = $patch['gender'];
			}
			$changes[] = 'Geschlecht';
		}

		if ( ! $patch ) {
			if ( $notes ) {
				$log( $nr, $name, 'nichts ergänzt', implode( '; ', $notes ) );
			}
			continue;
		}
		if ( ! $live ) {
			$log( $nr, $name, 'ergänzen', implode( ', ', $changes ) . ( $notes ? ' | ' . implode( '; ', $notes ) : '' ) );
			continue;
		}
		$res = tcg_ebusy_request( 'PATCH', "general/person/$pid", $patch );
		$now = $res['ok'] ? tcg_ebusy_request( 'GET', "general/person/by-id/$pid" ) : null;
		if ( ! $res['ok'] || ! $now || ! $now['ok'] ) {
			$log( $nr, $name, 'FEHLER Stammdaten', $res['error'] ?: ( $now['error'] ?? '' ) );
			continue;
		}
		// Kontrolle: vorher belegte Felder unverändert, neue Felder gesetzt.
		$q      = $now['data'];
		$broken = [];
		foreach ( [ 'street', 'postcode', 'city' ] as $f ) {
			if ( '' !== (string) ( $p['address'][ $f ] ?? '' ) && ( $p['address'][ $f ] ?? '' ) !== ( $q['address'][ $f ] ?? '' ) ) {
				$broken[] = "address.$f";
			}
		}
		foreach ( [ 'email', 'phone', 'mobile', 'phoneBusiness' ] as $f ) {
			if ( '' !== (string) ( $p['contact'][ $f ] ?? '' ) && ( $p['contact'][ $f ] ?? '' ) !== ( $q['contact'][ $f ] ?? '' ) ) {
				$broken[] = "contact.$f";
			}
		}
		foreach ( [ 'firstname', 'lastname', 'birthday' ] as $f ) {
			if ( ! empty( $p[ $f ] ) && ( $p[ $f ] ?? '' ) !== ( $q[ $f ] ?? '' ) ) {
				$broken[] = $f;
			}
		}
		if ( ( $p['bankAccount']['number'] ?? '' ) !== ( $q['bankAccount']['number'] ?? '' ) ) {
			$broken[] = 'IBAN';
		}
		if ( $broken ) {
			$log( $nr, $name, 'FEHLER Kontrolle', 'verändert: ' . implode( ', ', $broken ) );
			continue;
		}
		$journal[ $nr ]['masterdata'] = implode( ', ', $changes );
		tcg_imp_journal_save( $journal_file, $journal );
		$log( $nr, $name, 'ergänzt', implode( ', ', $changes ) . ( $notes ? ' | ' . implode( '; ', $notes ) : '' ) );
	}

	// Personen-Attribut „Beiträge" setzen – daraus leitet eBuSy Gruppe und Beitragsart ab.
	if ( 'attributes' === $step ) {
		if ( ! empty( $j['attr_ok'] ) ) {
			$log( $nr, $name, 'bereits gesetzt', '' );
			continue;
		}
		$fee_id = (int) $r['beitragsart_id'];
		$attrs  = TCG_IMP_FEE_ATTRIBUTES[ $fee_id ] ?? null;
		$pid    = $person_id_of( $nr );
		if ( ! $attrs || ! $pid ) {
			$log( $nr, $name, 'übersprungen', ! $pid ? 'keine Personen-Id' : "kein Attributwert für Beitragsart $fee_id" );
			continue;
		}
		$detail = implode( ', ', array_map( fn( $a, $v ) => "$a=$v", array_keys( $attrs ), $attrs ) );
		if ( ! $live ) {
			$log( $nr, $name, 'Attribut setzen', "$detail (Beitragsart $fee_id)" );
			continue;
		}
		$res = tcg_ebusy_request( 'POST', "general/person/$pid/set-attributes", [ 'attributes' => array_map( 'strval', $attrs ) ] );
		if ( ! $res['ok'] ) {
			$log( $nr, $name, 'FEHLER Attribut', $res['error'] );
			continue;
		}
		$journal[ $nr ]['attr_ok'] = $detail;
		tcg_imp_journal_save( $journal_file, $journal );
		$log( $nr, $name, 'Attribut gesetzt', "$detail (Beitragsart $fee_id)" );
	}

	if ( 'payers' === $step ) {
		if ( '' === $r['hauptzahler_nr'] ) {
			continue;
		}
		if ( ! empty( $j['payer_set'] ) ) {
			$log( $nr, $name, 'bereits gesetzt', 'Zahler #' . $j['payer_set'] );
			continue;
		}
		$pid       = $person_id_of( $nr );
		$payer_pid = $person_id_of( (int) $r['hauptzahler_nr'] );
		if ( ! $pid || ! $payer_pid ) {
			$log( $nr, $name, 'übersprungen', 'Personen-Id fehlt (Mitglied oder Zahler)' );
			continue;
		}
		$payer_name = $export[ (int) $r['hauptzahler_nr'] ]['vorname'] . ' ' . $export[ (int) $r['hauptzahler_nr'] ]['nachname'];
		if ( ! $live ) {
			$log( $nr, $name, 'Hauptzahler setzen', "$payer_name (#$payer_pid)" );
			continue;
		}
		$res = tcg_ebusy_request(
			'PATCH',
			"general/person/$pid",
			[
				'paidByInfo' => [
					'id'                        => $payer_pid,
					'modules'                   => [ $module_id ],
					'paysForVouchersAndCoupons' => false,
					'paysForCustomPurchases'    => false,
				],
			]
		);
		if ( ! $res['ok'] ) {
			$log( $nr, $name, 'FEHLER Hauptzahler', $res['error'] );
			continue;
		}
		$journal[ $nr ]['payer_set'] = $payer_pid;
		tcg_imp_journal_save( $journal_file, $journal );
		$log( $nr, $name, 'Hauptzahler gesetzt', "$payer_name (#$payer_pid)" );
	}
}

echo "Zusammenfassung:\n";
foreach ( $count as $action => $n ) {
	printf( "  %-26s %d\n", $action, $n );
}
