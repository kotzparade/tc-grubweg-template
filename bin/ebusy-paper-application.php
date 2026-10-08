<?php
/**
 * Papierantrag nach eBuSy übertragen – gleiche Pipeline wie der Online-Antrag
 * (Person, Attribut „Beiträge", Mitgliedschaft laut Einstellung, SEPA-Mandat, Protokoll).
 *
 * Aufruf (Projektroot):
 *   php -c php.ini wp-content/themes/tc-grubweg/bin/ebusy-paper-application.php --file=import/papierantraege/x.json [--person-id=123] [--live]
 *
 * Die JSON wird aus dem Foto des Antrags erstellt und vor dem Lauf von einem Menschen geprüft.
 * Felder: vorname, nachname, geschlecht, geburtsdatum, nationalitaet_code, telefon, email,
 * strasse, plz, ort, eltern, beruf, beitragsmodell, schluessel, interessen[], antragsdatum,
 * eintritt (optional, sonst Antragsdatum), kontoinhaber, iban, bank, unterschrift_kontoinhaber,
 * hinweise[]. Datumsangaben als TT.MM.JJJJ oder JJJJ-MM-TT.
 *
 * Ohne --live nur Dry-Run. Findet die Dublettenprüfung die Person schon in eBuSy, läuft --live
 * nur mit --person-id (Mitgliedschaft + Attribute werden dann an diese Person gehängt).
 *
 * @package tc-grubweg
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$root = dirname( __DIR__, 4 );
require $root . '/wp-load.php';

// Weitere Personen-Attribute des Papierformulars (IDs aus „Verbindung testen").
const TCG_PAPER_ATTR_BERUF       = 9312;  // Freitext
const TCG_PAPER_ATTR_ELTERN      = 9313;  // Freitext
const TCG_PAPER_ATTR_SCHLUESSEL  = 9311;  // Wert 92962
const TCG_PAPER_SCHLUESSEL_WERT  = 92962;

$opts      = getopt( '', [ 'file:', 'person-id:', 'live' ] );
$live      = isset( $opts['live'] );
$person_id = isset( $opts['person-id'] ) ? (int) $opts['person-id'] : 0;
$file      = $opts['file'] ?? '';
if ( '' === $file ) {
	fwrite( STDERR, "--file=import/papierantraege/<datei>.json angeben.\n" );
	exit( 1 );
}
if ( ! is_file( $file ) ) {
	$file = $root . '/' . ltrim( $file, '/' );
}
$in = is_file( $file ) ? json_decode( file_get_contents( $file ), true ) : null;
if ( ! is_array( $in ) ) {
	fwrite( STDERR, "JSON nicht lesbar: $file\n" );
	exit( 1 );
}

// ── Hilfsfunktionen ────────────────────────────────────────────────────────────

function tcg_paper_date( $value ) {
	$value = trim( (string) $value );
	if ( preg_match( '/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $value, $m ) ) {
		$value = sprintf( '%04d-%02d-%02d', $m[3], $m[2], $m[1] );
	}
	return tcg_ebusy_sanitize_date( $value );
}

function tcg_paper_norm( $s ) {
	$s = remove_accents( strtr( mb_strtolower( trim( (string) $s ), 'UTF-8' ), [ 'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss' ] ) );
	return preg_replace( '/[^a-z]/', '', $s );
}

function tcg_paper_mask_iban( $iban ) {
	return '' === $iban ? '' : substr( $iban, 0, 4 ) . '…' . substr( $iban, -4 );
}

// ── Daten aufbereiten und prüfen ───────────────────────────────────────────────

$settings = tcg_ebusy_settings();
$errors   = [];
$str      = fn( $k ) => trim( (string) ( $in[ $k ] ?? '' ) );

$anrede = match ( tcg_paper_norm( $str( 'geschlecht' ) ) ) {
	'weiblich', 'w', 'female', 'frau' => 'Frau',
	'maennlich', 'm', 'male', 'herr' => 'Herr',
	'divers', 'd' => 'Divers',
	default => '',
};

// Beitragsmodell: exaktes Formular-Label oder eindeutiger Teiltext („Jugendliche bis 17").
$tiers  = tcg_ebusy_form_tier_options( tcg_ebusy_target_form_id() );
$wanted = $str( 'beitragsmodell' );
$tier   = in_array( $wanted, $tiers, true ) ? $wanted : '';
if ( '' === $tier && '' !== $wanted ) {
	$hits = array_values( array_filter( $tiers, fn( $t ) => false !== mb_stripos( $t, $wanted ) ) );
	$tier = 1 === count( $hits ) ? $hits[0] : '';
}

$antragsdatum = tcg_paper_date( $str( 'antragsdatum' ) );
$iban         = tcg_ebusy_normalize_iban( $str( 'iban' ) );
$signed       = ! empty( $in['unterschrift_kontoinhaber'] );

// Eltern/Beruf zusätzlich in den Kommentar: set-attributes nimmt Freitext laut Doku an, übernimmt ihn
// aber nicht (Test 08.10.2026, Antwort ok, Attribut fehlt danach).
$notes = [];
if ( '' !== trim( (string) ( $in['eltern'] ?? '' ) ) ) {
	$notes[] = 'Namen der Eltern: ' . trim( $in['eltern'] );
}
if ( '' !== trim( (string) ( $in['beruf'] ?? '' ) ) ) {
	$notes[] = 'Beruf: ' . trim( $in['beruf'] );
}
if ( ! empty( $in['interessen'] ) ) {
	$notes[] = 'Interesse: ' . implode( ', ', (array) $in['interessen'] );
}
foreach ( (array) ( $in['hinweise'] ?? [] ) as $hint ) {
	$notes[] = 'Hinweis Erfassung: ' . $hint;
}

$data = [
	'beitragsmodell'     => $tier,
	'anrede'             => $anrede,
	'geburtsdatum'       => tcg_paper_date( $str( 'geburtsdatum' ) ),
	'vorname'            => $str( 'vorname' ),
	'nachname'           => $str( 'nachname' ),
	'strasse'            => $str( 'strasse' ),
	'plz'                => $str( 'plz' ),
	'ort'                => $str( 'ort' ),
	'email'              => $str( 'email' ),
	'telefon'            => $str( 'telefon' ),
	'eintritt'           => tcg_paper_date( $str( 'eintritt' ) ) ?: $antragsdatum,
	'bemerkungen'        => implode( '; ', $notes ),
	'kontoinhaber'       => $str( 'kontoinhaber' ),
	'iban'               => $iban,
	'satzung'            => true,
	'datenschutz'        => false,
	'sepa'               => '' !== $iban && $signed,
	'foto'               => false,
	// Zusatzschlüssel für Papieranträge (siehe tcg_ebusy_build_person()).
	'quelle'             => 'papier',
	'antragsdatum'       => $antragsdatum,
	'mandatsdatum'       => $antragsdatum,
	'nationalitaet_code' => strtoupper( $str( 'nationalitaet_code' ) ),
	'bank'               => $str( 'bank' ),
];

foreach ( [ 'vorname', 'nachname', 'geburtsdatum', 'strasse', 'plz', 'ort' ] as $k ) {
	if ( '' === $data[ $k ] ) {
		$errors[] = "Pflichtfeld fehlt: $k";
	}
}
if ( '' === $antragsdatum ) {
	$errors[] = 'Antragsdatum fehlt oder ungültig';
}
if ( '' === $anrede ) {
	$errors[] = 'Geschlecht nicht erkannt: „' . $str( 'geschlecht' ) . '"';
}
if ( '' === $tier ) {
	$errors[] = 'Beitragsmodell nicht eindeutig: „' . $wanted . '" (Optionen: ' . implode( ' | ', $tiers ) . ')';
} elseif ( empty( $settings['attr_map'][ tcg_ebusy_tier_key( $tier ) ] ) || empty( $settings['fee_attribute_id'] ) ) {
	$errors[] = "Für „$tier\" ist kein Attributwert „Beiträge\" eingestellt";
}
if ( '' !== $iban && ! tcg_ebusy_iban_is_valid( $iban ) ) {
	$errors[] = 'IBAN-Prüfsumme ungültig: ' . $str( 'iban' );
}
if ( '' !== $iban && ! $signed ) {
	$errors[] = 'Einzugsvollmacht nicht unterschrieben – IBAN ohne Mandat wird nicht übertragen';
}
if ( '' !== $data['email'] && ! is_email( $data['email'] ) ) {
	$errors[] = 'E-Mail ungültig: ' . $data['email'];
}
if ( ! tcg_ebusy_is_configured() ) {
	$errors[] = 'eBuSy-Zugangsdaten fehlen (Einstellungen → eBuSy-Schnittstelle)';
}

$extra_attrs = [];
if ( '' !== $str( 'beruf' ) ) {
	$extra_attrs[ TCG_PAPER_ATTR_BERUF ] = $str( 'beruf' );
}
if ( '' !== $str( 'eltern' ) ) {
	$extra_attrs[ TCG_PAPER_ATTR_ELTERN ] = $str( 'eltern' );
}
if ( ! empty( $in['schluessel'] ) ) {
	$extra_attrs[ TCG_PAPER_ATTR_SCHLUESSEL ] = TCG_PAPER_SCHLUESSEL_WERT;
}

// ── Dublettenprüfung gegen eBuSy ───────────────────────────────────────────────

$dupes = [];
if ( tcg_ebusy_is_configured() ) {
	$first = tcg_paper_norm( $data['vorname'] );
	$last  = tcg_paper_norm( $data['nachname'] );
	$mail  = strtolower( $data['email'] );
	for ( $offset = 0; $offset < 5000; $offset += 100 ) {
		$res = tcg_ebusy_request( 'GET', "general/persons?offset=$offset&limit=100" );
		if ( ! $res['ok'] ) {
			$errors[] = 'Dublettenprüfung fehlgeschlagen: ' . $res['error'];
			break;
		}
		$page = $res['data']['content'] ?? ( array_is_list( (array) $res['data'] ) ? (array) $res['data'] : [] );
		foreach ( $page as $p ) {
			$pf  = tcg_paper_norm( $p['firstname'] ?? '' );
			$pl  = tcg_paper_norm( $p['lastname'] ?? '' );
			$why = [];
			if ( $pl === $last && $pf === $first ) {
				$why[] = 'Name';
			}
			if ( $pl === $first && $pf === $last ) {
				$why[] = 'Name vertauscht';
			}
			if ( $why && substr( (string) ( $p['birthday'] ?? '' ), 0, 10 ) === $data['geburtsdatum'] ) {
				$why[] = 'Geburtstag';
			}
			if ( '' !== $mail && strtolower( $p['contact']['email'] ?? '' ) === $mail ) {
				$why[] = 'E-Mail';
			}
			if ( $why ) {
				$dupes[] = sprintf( '#%d %s %s, geb. %s (%s)%s', $p['id'], $p['firstname'] ?? '', $p['lastname'] ?? '', $p['birthday'] ?? '–', implode( '+', $why ), ! empty( $p['archived'] ) ? ' [archiviert]' : '' );
			}
		}
		if ( ! empty( $res['data']['last'] ) || count( $page ) < 100 ) {
			break;
		}
	}
}

// ── Ausgabe Dry-Run ────────────────────────────────────────────────────────────

$name = trim( $data['vorname'] . ' ' . $data['nachname'] );
echo ( $live ? 'LIVE' : 'DRY-RUN' ) . " – Papierantrag $name\n";
printf( "  Beitragsmodell: %s → Attribut %s=%s\n", $tier ?: '–', $settings['fee_attribute_id'], $settings['attr_map'][ tcg_ebusy_tier_key( $tier ) ] ?? '–' );
printf( "  Anrede/Geburtstag: %s, %s | Eintritt: %s | Antrag: %s\n", $anrede ?: '–', $data['geburtsdatum'] ?: '–', $data['eintritt'] ?: '–', $antragsdatum ?: '–' );
printf( "  Adresse: %s, %s %s | E-Mail: %s | Telefon: %s\n", $data['strasse'], $data['plz'], $data['ort'], $data['email'] ?: '–', $data['telefon'] ?: '–' );
printf( "  Bank: %s, Inhaber %s, %s | SEPA-Mandat: %s\n", tcg_paper_mask_iban( $iban ) ?: '–', $data['kontoinhaber'] ?: '–', $data['bank'] ?: '–', $data['sepa'] ? 'ja, Datum ' . $antragsdatum : 'nein' );
printf( "  Status Mitgliedschaft: %s | Zusatz-Attribute: %s\n", $settings['membership_status'], $extra_attrs ? wp_json_encode( $extra_attrs, JSON_UNESCAPED_UNICODE ) : '–' );
printf( "  Bemerkungen: %s\n", $data['bemerkungen'] ?: '–' );
echo '  Dubletten in eBuSy: ' . ( $dupes ? "\n    " . implode( "\n    ", $dupes ) : 'keine' ) . "\n";
if ( $person_id ) {
	echo "  → an vorhandene Person #$person_id anhängen\n";
}

if ( $errors ) {
	echo "FEHLER:\n  - " . implode( "\n  - ", $errors ) . "\n";
	exit( 1 );
}
if ( ! $live ) {
	echo "OK – mit --live übertragen" . ( $dupes && ! $person_id ? ' (vorher Dublette klären, ggf. --person-id=…)' : '' ) . ".\n";
	exit( 0 );
}
if ( $dupes && ! $person_id ) {
	echo "ABBRUCH: mögliche Dublette – erst klären, dann mit --person-id=… oder nach Bereinigung erneut starten.\n";
	exit( 1 );
}

// ── Übertragen ─────────────────────────────────────────────────────────────────

$result = [
	'ok'                => false,
	'transferred'       => true,
	'person_id'         => 0,
	'membership_id'     => 0,
	'mandate_reference' => $data['sepa'] ? tcg_ebusy_generate_mandate_reference() : '',
	'name'              => $name . ' (Papierantrag)',
	'message'           => '',
];

if ( $person_id ) {
	$check = tcg_ebusy_request( 'GET', "general/person/by-id/$person_id" );
	if ( ! $check['ok'] ) {
		echo "FEHLER: Person #$person_id nicht abrufbar: {$check['error']}\n";
		exit( 1 );
	}
	$result = tcg_ebusy_submit_membership_for_person( $person_id, $data, $settings, $result );
} else {
	$result = tcg_ebusy_submit_application( $data, $settings, $result );
}

if ( $result['person_id'] && $extra_attrs ) {
	$attr_res = tcg_ebusy_set_attributes( $result['person_id'], $extra_attrs );
	// Kontrolle, welche Zusatz-Attribute eBuSy tatsächlich übernommen hat (Freitext wird ignoriert).
	$now     = tcg_ebusy_request( 'GET', 'general/person/by-id/' . (int) $result['person_id'] );
	$present = $now['ok'] ? array_map( 'intval', array_column( (array) ( $now['data']['attributes'] ?? [] ), 'id' ) ) : [];
	$missing = array_diff( array_keys( $extra_attrs ), $present );
	if ( ! $attr_res['ok'] && isset( $extra_attrs[ TCG_PAPER_ATTR_SCHLUESSEL ] ) ) {
		$result['ok']       = false;
		$result['message'] .= ' Attribut Schlüssel NICHT gesetzt: ' . $attr_res['error'];
	} elseif ( $missing ) {
		echo '  Hinweis: Attribute ' . implode( ', ', $missing ) . " nicht übernommen – Eltern/Beruf stehen im Kommentar.\n";
	}
}

tcg_ebusy_log( [
	'name'          => $result['name'],
	'ok'            => $result['ok'],
	'person_id'     => $result['person_id'],
	'membership_id' => $result['membership_id'],
	'message'       => $result['message'],
] );
if ( ! $result['ok'] ) {
	tcg_ebusy_notify_failure( $result, $settings );
}

echo ( $result['ok'] ? 'OK' : 'FEHLER' ) . ": {$result['message']}\n";
printf( "  Person #%d, Mitgliedschaft #%d, Mandat %s\n", $result['person_id'], $result['membership_id'], $result['mandate_reference'] ?: '–' );
exit( $result['ok'] ? 0 : 1 );
