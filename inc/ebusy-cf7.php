<?php
/**
 * eBuSy-Schnittstelle: Anbindung des Contact-Form-7-Formulars „Mitgliederantrag".
 *
 * Ablauf bei einem Antrag (Hook wpcf7_before_send_mail, nach Validierung + Spamprüfung):
 *   1. IBAN normalisieren/prüfen (bereits bei der Validierung)
 *   2. SEPA-Mandatsreferenz erzeugen (immer, auch ohne API)
 *   3. Person in eBuSy anlegen (POST /general/person)
 *   4. Mitgliedschaft mit Status REQUESTED anlegen (POST /member/modules/{id}/membership)
 *   5. Ergebnis für Mail-Tags [_ebusy_status] / [_ebusy_mandatsreferenz] und Flamingo bereithalten
 *
 * Ein Fehler bei eBuSy bricht den Antrag NICHT ab – Mail und Flamingo sichern die Daten,
 * der Vorstand wird per Mail informiert und legt das Mitglied manuell an.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── IBAN ───────────────────────────────────────────────────────────────────────

/**
 * Entfernt Leerzeichen/Sonderzeichen und wandelt in Großbuchstaben um.
 */
function tcg_ebusy_normalize_iban( $iban ) {
    return strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $iban ) );
}

/**
 * Prüft Format, Länge (für bekannte Länder) und MOD-97-Prüfziffer einer IBAN.
 */
function tcg_ebusy_iban_is_valid( $iban ) {
    $iban = tcg_ebusy_normalize_iban( $iban );

    if ( ! preg_match( '/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $iban ) ) {
        return false;
    }

    $lengths = [ 'DE' => 22, 'AT' => 20, 'CH' => 21, 'NL' => 18, 'FR' => 27, 'IT' => 27, 'BE' => 16, 'LU' => 20, 'ES' => 24, 'PL' => 28, 'CZ' => 24 ];
    $country = substr( $iban, 0, 2 );
    if ( isset( $lengths[ $country ] ) && strlen( $iban ) !== $lengths[ $country ] ) {
        return false;
    }

    // Prüfziffer: Ländercode + Prüfziffer ans Ende, Buchstaben → Zahlen (A=10 … Z=35), mod 97 === 1
    $rearranged = substr( $iban, 4 ) . substr( $iban, 0, 4 );
    $numeric    = '';
    foreach ( str_split( $rearranged ) as $char ) {
        $numeric .= ctype_alpha( $char ) ? (string) ( ord( $char ) - 55 ) : $char;
    }

    $remainder = 0;
    foreach ( str_split( $numeric ) as $digit ) {
        $remainder = ( $remainder * 10 + (int) $digit ) % 97;
    }

    return 1 === $remainder;
}

/**
 * Normalisiert das IBAN-Feld in den gesendeten Daten (gilt für Mail, Flamingo und API).
 */
function tcg_ebusy_cf7_posted_data( $posted_data ) {
    if ( isset( $posted_data['iban'] ) && is_string( $posted_data['iban'] ) ) {
        $posted_data['iban'] = tcg_ebusy_normalize_iban( $posted_data['iban'] );
    }
    return $posted_data;
}
add_filter( 'wpcf7_posted_data', 'tcg_ebusy_cf7_posted_data' );

/**
 * Serverseitige IBAN-Validierung für das Feld „iban" (Pflichtprüfung übernimmt CF7).
 */
function tcg_ebusy_cf7_validate_iban( $result, $tag ) {
    if ( 'iban' !== $tag->name ) {
        return $result;
    }

    $submission = WPCF7_Submission::get_instance();
    $value      = $submission ? tcg_ebusy_normalize_iban( $submission->get_posted_string( 'iban' ) ) : '';

    if ( '' !== $value && ! tcg_ebusy_iban_is_valid( $value ) ) {
        $result->invalidate( $tag, __( 'Bitte eine gültige IBAN eingeben.', 'tc-grubweg' ) );
    }

    return $result;
}
add_filter( 'wpcf7_validate_text', 'tcg_ebusy_cf7_validate_iban', 20, 2 );
add_filter( 'wpcf7_validate_text*', 'tcg_ebusy_cf7_validate_iban', 20, 2 );

// ── Ergebnis der aktuellen Übertragung ─────────────────────────────────────────

/**
 * Hält das Ergebnis der Übertragung für die Dauer des Requests
 * (für Mail-Tags und Flamingo; bewusst nicht in der AJAX-Antwort an den Browser).
 */
function tcg_ebusy_last_result( $set = null ) {
    static $result = null;
    if ( null !== $set ) {
        $result = $set;
    }
    return $result;
}

/**
 * Erzeugt eine eindeutige SEPA-Mandatsreferenz, z. B. TCG-20260826-K3P9Q.
 */
function tcg_ebusy_generate_mandate_reference() {
    return 'TCG-' . wp_date( 'Ymd' ) . '-' . strtoupper( wp_generate_password( 5, false, false ) );
}

// ── Hilfsfunktionen für das Mapping ────────────────────────────────────────────

/**
 * Liefert ein Datum im Format Y-m-d oder '' (CF7-Datumsfelder senden bereits Y-m-d).
 */
function tcg_ebusy_sanitize_date( $value ) {
    $value = trim( (string) $value );
    if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) && false !== strtotime( $value ) ) {
        return $value;
    }
    return '';
}

/**
 * Y-m-d → d.m.Y für Kommentartexte.
 */
function tcg_ebusy_format_date_de( $ymd ) {
    return $ymd ? wp_date( 'd.m.Y', strtotime( $ymd ) ) : '';
}

/**
 * Deutsche Mobilnummer? (015x/016x/017x, auch mit +49 / 0049)
 */
function tcg_ebusy_is_mobile_number( $phone ) {
    $n = preg_replace( '/[^\d+]/', '', (string) $phone );
    $n = preg_replace( '/^(\+49|0049)/', '0', $n );
    return (bool) preg_match( '/^01[5-7]/', $n );
}

/**
 * Baut den Personendeskriptor aus den Formulardaten.
 */
function tcg_ebusy_build_person( array $data, array $settings, $mandate_reference ) {
    $person = [
        'firstname' => $data['vorname'],
        'lastname'  => $data['nachname'],
        'address'   => [
            'street'      => $data['strasse'],
            'postcode'    => $data['plz'],
            'city'        => $data['ort'],
            'country'     => 'Deutschland',
            'countryCode' => 'DE',
        ],
        'contact'   => [
            'email' => $data['email'],
        ],
        // eBuSy antwortet ohne „user"-Objekt mit HTTP 500 (Live-Test 06.09.2026), obwohl die Doku
        // es nicht als Pflicht nennt. Konto bewusst deaktiviert: Zugang erst nach Bestätigung durch den Vorstand.
        'user'      => [
            'enabled' => false,
            'level'   => 'USER',
        ],
    ];

    switch ( $data['anrede'] ) {
        case 'Frau':
            $person['gender']     = 'FEMALE';
            $person['salutation'] = 'FEMALE';
            break;
        case 'Herr':
            $person['gender']     = 'MALE';
            $person['salutation'] = 'MALE';
            break;
        case 'Divers':
            $person['gender']     = 'DIVERSE';
            $person['salutation'] = 'NONE';
            break;
    }

    if ( $data['geburtsdatum'] ) {
        $person['birthday'] = $data['geburtsdatum'];
    }

    if ( '' !== $data['telefon'] ) {
        $key                       = tcg_ebusy_is_mobile_number( $data['telefon'] ) ? 'mobile' : 'phone';
        $person['contact'][ $key ] = $data['telefon'];
    }

    if ( '' !== $data['iban'] ) {
        $person['bankAccount'] = [
            'holder' => '' !== $data['kontoinhaber'] ? $data['kontoinhaber'] : trim( $data['vorname'] . ' ' . $data['nachname'] ),
            'number' => $data['iban'],
        ];
    }

    if ( $data['sepa'] ) {
        $person['sepaMandate'] = [
            'date'      => wp_date( 'Y-m-d' ),
            'reference' => $mandate_reference,
        ];
    }

    if ( ! empty( $settings['send_user_info'] ) ) {
        $person['sendUserInfo'] = true;
    }

    $ja_nein = static function ( $flag ) {
        return $flag ? 'ja' : 'nein';
    };

    $lines   = [];
    $lines[] = sprintf( 'Online-Antrag vom %s über die Vereins-Website (Formular „Mitgliederantrag").', wp_date( 'd.m.Y H:i' ) );
    $lines[] = 'Beitragsmodell: ' . ( $data['beitragsmodell'] ?: '–' );
    $lines[] = 'Gewünschter Eintritt: ' . ( $data['eintritt'] ? tcg_ebusy_format_date_de( $data['eintritt'] ) : 'schnellstmöglich' );
    if ( '' !== $data['bemerkungen'] ) {
        $lines[] = 'Bemerkungen: ' . $data['bemerkungen'];
    }
    $lines[] = sprintf(
        'Einwilligungen: Satzung/Beitragsordnung %s, Datenschutz %s, SEPA-Lastschrift %s%s, Fotoveröffentlichung %s',
        $ja_nein( $data['satzung'] ),
        $ja_nein( $data['datenschutz'] ),
        $ja_nein( $data['sepa'] ),
        $data['sepa'] ? ' (Mandat ' . $mandate_reference . ', erteilt am ' . wp_date( 'd.m.Y' ) . ')' : '',
        $ja_nein( $data['foto'] )
    );

    $person['comment'] = implode( "\n", $lines );

    return $person;
}

/**
 * Baut den Mitgliedschaftsdeskriptor.
 */
function tcg_ebusy_build_membership( array $data, array $settings, $person_id, $type_id ) {
    $membership = [
        'personId'         => (int) $person_id,
        'membershipTypeId' => (int) $type_id,
        'begin'            => $data['eintritt'] ? $data['eintritt'] : wp_date( 'Y-m-d' ),
        'status'           => 'REQUESTED',
        'comment'          => trim( 'Online-Antrag: ' . $data['beitragsmodell'] . ( '' !== $data['bemerkungen'] ? "\n" . $data['bemerkungen'] : '' ) ),
    ];

    if ( '' !== (string) $settings['payment_type_ordinal'] ) {
        $membership['paymentType'] = [ 'ordinal' => (int) $settings['payment_type_ordinal'] ];
        if ( '' !== $settings['payment_type_name'] ) {
            $membership['paymentType']['name'] = $settings['payment_type_name'];
        }
    }

    return $membership;
}

// ── Übertragung ────────────────────────────────────────────────────────────────

/**
 * Liest alle benötigten Felder aus der Submission (Strings getrimmt, Akzeptanzfelder als bool).
 */
function tcg_ebusy_collect_form_data( WPCF7_Submission $submission ) {
    $str = static function ( $name ) use ( $submission ) {
        return trim( (string) $submission->get_posted_string( $name ) );
    };
    $flag = static function ( $name ) use ( $str ) {
        return '' !== $str( $name );
    };

    return [
        'beitragsmodell' => $str( 'beitragsmodell' ),
        'anrede'         => $str( 'anrede' ),
        'geburtsdatum'   => tcg_ebusy_sanitize_date( $str( 'geburtsdatum' ) ),
        'vorname'        => $str( 'vorname' ),
        'nachname'       => $str( 'nachname' ),
        'strasse'        => $str( 'strasse' ),
        'plz'            => $str( 'plz' ),
        'ort'            => $str( 'ort' ),
        'email'          => $str( 'email' ),
        'telefon'        => $str( 'telefon' ),
        'eintritt'       => tcg_ebusy_sanitize_date( $str( 'eintritt' ) ),
        'bemerkungen'    => $str( 'bemerkungen' ),
        'kontoinhaber'   => $str( 'kontoinhaber' ),
        'iban'           => tcg_ebusy_normalize_iban( $str( 'iban' ) ),
        'satzung'        => $flag( 'satzung' ),
        'datenschutz'    => $flag( 'datenschutz' ),
        'sepa'           => $flag( 'sepa' ),
        'foto'           => $flag( 'foto' ),
    ];
}

/**
 * Legt die Person in eBuSy an, bei eingetragener Modul-ID zusätzlich die Mitgliedschaft, und ergänzt $result.
 */
function tcg_ebusy_submit_application( array $data, array $settings, array $result ) {
    $person_response = tcg_ebusy_create_person( tcg_ebusy_build_person( $data, $settings, $result['mandate_reference'] ) );

    if ( ! $person_response['ok'] ) {
        $result['message'] = sprintf(
            /* translators: %s = Fehlermeldung */
            __( 'FEHLER beim Anlegen der Person in eBuSy: %s – bitte manuell anlegen.', 'tc-grubweg' ),
            $person_response['error']
        );
        return $result;
    }

    $person_id           = isset( $person_response['data']['id'] ) ? (int) $person_response['data']['id'] : 0;
    $result['person_id'] = $person_id;

    if ( ! $person_id ) {
        $result['message'] = __( 'FEHLER: eBuSy hat die Person angenommen, aber keine Personen-ID zurückgegeben – bitte in eBuSy prüfen.', 'tc-grubweg' );
        return $result;
    }

    $module_id = (int) $settings['module_id'];

    // Ohne Modul-ID hat eBuSy keine Mitgliederverwaltung: Nur die Person anlegen, das zählt als Erfolg.
    if ( ! $module_id ) {
        $result['ok']      = true;
        $result['message'] = sprintf(
            /* translators: 1: Personen-ID, 2: Zusatz mit Mandatsreferenz, 3: Beitragsmodell */
            __( 'Person #%1$d in eBuSy angelegt%2$s. Beitragsmodell „%3$s" und Einwilligungen stehen im Kommentar der Person; eine Mitgliedschaft wird ohne Mitgliedermodul nicht angelegt.', 'tc-grubweg' ),
            $person_id,
            /* translators: %s = Mandatsreferenz */
            $result['mandate_reference'] ? sprintf( __( ' (SEPA-Mandat %s)', 'tc-grubweg' ), $result['mandate_reference'] ) : '',
            $data['beitragsmodell']
        );
        return $result;
    }

    $type_key = md5( $data['beitragsmodell'] );
    $type_id  = isset( $settings['type_map'][ $type_key ] ) ? (int) $settings['type_map'][ $type_key ] : 0;

    if ( ! $type_id ) {
        $result['message'] = sprintf(
            /* translators: 1: Personen-ID, 2: Beitragsmodell */
            __( 'Person #%1$d in eBuSy angelegt – Mitgliedschaft NICHT angelegt: für „%2$s" ist keine Mitgliedschaftsart zugeordnet (Einstellungen → eBuSy-Schnittstelle). Bitte manuell nachtragen.', 'tc-grubweg' ),
            $person_id,
            $data['beitragsmodell']
        );
        return $result;
    }

    $membership_response = tcg_ebusy_create_membership(
        $module_id,
        tcg_ebusy_build_membership( $data, $settings, $person_id, $type_id )
    );

    if ( ! $membership_response['ok'] ) {
        $result['message'] = sprintf(
            /* translators: 1: Personen-ID, 2: Fehlermeldung */
            __( 'Person #%1$d in eBuSy angelegt, FEHLER bei der Mitgliedschaft: %2$s – bitte manuell anlegen.', 'tc-grubweg' ),
            $person_id,
            $membership_response['error']
        );
        return $result;
    }

    $result['membership_id'] = isset( $membership_response['data']['id'] ) ? (int) $membership_response['data']['id'] : 0;
    $result['ok']            = true;
    $result['message']       = sprintf(
        /* translators: 1: Personen-ID, 2: Mitgliedschafts-ID, 3: Mandatsreferenz */
        __( 'Person #%1$d angelegt, Mitgliedschaft #%2$d beantragt (SEPA-Mandat %3$s).', 'tc-grubweg' ),
        $person_id,
        $result['membership_id'],
        $result['mandate_reference']
    );

    return $result;
}

/**
 * Benachrichtigt den Vorstand bei fehlgeschlagener Übertragung (ohne Bankdaten).
 */
function tcg_ebusy_notify_failure( array $result, array $settings ) {
    $to = $settings['notify_email'] ? $settings['notify_email'] : get_option( 'admin_email' );
    if ( ! is_email( $to ) ) {
        return;
    }

    /* translators: 1: Website-Name, 2: Name des Antragstellers */
    $subject = sprintf( __( '[%1$s] eBuSy-Übertragung fehlgeschlagen: %2$s', 'tc-grubweg' ), get_bloginfo( 'name' ), $result['name'] );

    /* translators: %s = Name des Antragstellers */
    $body  = sprintf( __( 'Der Mitgliedsantrag von %s konnte nicht (vollständig) an eBuSy übertragen werden.', 'tc-grubweg' ), $result['name'] ) . "\n\n";
    $body .= __( 'Status:', 'tc-grubweg' ) . ' ' . $result['message'] . "\n";
    $body .= __( 'Zeitpunkt:', 'tc-grubweg' ) . ' ' . wp_date( 'd.m.Y H:i' ) . "\n\n";
    $body .= __( 'Die vollständigen Antragsdaten liegen in der Antragsmail und unter Flamingo → Posteingang.', 'tc-grubweg' ) . "\n";
    $body .= admin_url( 'admin.php?page=flamingo_inbound' ) . "\n\n";
    $body .= __( 'Einstellungen der Schnittstelle:', 'tc-grubweg' ) . ' ' . admin_url( 'options-general.php?page=tcg-ebusy' ) . "\n";

    wp_mail( $to, $subject, $body, [ 'Content-Type: text/plain; charset=UTF-8' ] );
}

/**
 * Haupt-Hook: läuft nach erfolgreicher Validierung, vor dem Mailversand.
 */
function tcg_ebusy_cf7_before_send_mail( $contact_form, &$abort, $submission ) {
    // Nur Formulare mit IBAN-Feld (= Mitgliederantrag) betreffen die Schnittstelle.
    if ( ! $contact_form->scan_form_tags( [ 'name' => 'iban' ] ) ) {
        return;
    }

    $settings = tcg_ebusy_settings();
    $data     = tcg_ebusy_collect_form_data( $submission );

    $result = [
        'ok'                => false,
        'transferred'       => false,
        'person_id'         => 0,
        'membership_id'     => 0,
        'mandate_reference' => $data['sepa'] ? tcg_ebusy_generate_mandate_reference() : '',
        'name'              => trim( $data['vorname'] . ' ' . $data['nachname'] ),
        'message'           => '',
    ];

    if ( empty( $settings['enabled'] ) ) {
        $result['message'] = __( 'Übertragung an eBuSy ist deaktiviert (Einstellungen → eBuSy-Schnittstelle).', 'tc-grubweg' );
        tcg_ebusy_last_result( $result );
        return;
    }

    if ( (int) $contact_form->id() !== tcg_ebusy_target_form_id() ) {
        $result['message'] = __( 'Dieses Formular ist nicht für die eBuSy-Übertragung konfiguriert.', 'tc-grubweg' );
        tcg_ebusy_last_result( $result );
        return;
    }

    if ( $contact_form->in_demo_mode() ) {
        $result['message'] = __( 'Demo-Modus – keine Übertragung an eBuSy.', 'tc-grubweg' );
        tcg_ebusy_last_result( $result );
        return;
    }

    $result['transferred'] = true;

    if ( ! tcg_ebusy_is_configured() ) {
        $result['message'] = __( 'FEHLER: eBuSy-Zugangsdaten unvollständig – bitte manuell anlegen.', 'tc-grubweg' );
    } else {
        $result = tcg_ebusy_submit_application( $data, $settings, $result );
    }

    tcg_ebusy_last_result( $result );

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

    // $abort bleibt false: Mail + Flamingo laufen immer weiter.
}
add_action( 'wpcf7_before_send_mail', 'tcg_ebusy_cf7_before_send_mail', 10, 3 );

// ── Mail-Tags und Flamingo ─────────────────────────────────────────────────────

/**
 * [_ebusy_status] und [_ebusy_mandatsreferenz] für die CF7-Mail-Templates.
 */
function tcg_ebusy_cf7_special_mail_tags( $output, $name, $html, $mail_tag = null ) {
    if ( '_ebusy_status' === $name ) {
        $result = tcg_ebusy_last_result();
        return $result ? $result['message'] : __( 'Keine eBuSy-Übertragung ausgeführt.', 'tc-grubweg' );
    }

    if ( '_ebusy_mandatsreferenz' === $name ) {
        $result = tcg_ebusy_last_result();
        return $result ? $result['mandate_reference'] : '';
    }

    return $output;
}
add_filter( 'wpcf7_special_mail_tags', 'tcg_ebusy_cf7_special_mail_tags', 10, 4 );

/**
 * Status + Mandatsreferenz zusätzlich im Flamingo-Eintrag ablegen.
 */
function tcg_ebusy_flamingo_fields( $args ) {
    $result = tcg_ebusy_last_result();
    if ( ! $result ) {
        return $args;
    }

    if ( ! isset( $args['fields'] ) || ! is_array( $args['fields'] ) ) {
        $args['fields'] = [];
    }

    $args['fields']['ebusy-status']          = $result['message'];
    $args['fields']['ebusy-mandatsreferenz'] = $result['mandate_reference'];

    return $args;
}
add_filter( 'wpcf7_flamingo_inbound_message_parameters', 'tcg_ebusy_flamingo_fields' );
