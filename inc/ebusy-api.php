<?php
/**
 * eBuSy-Schnittstelle: Einstellungen, HTTP-Client und Protokoll.
 *
 * API-Doku: openapi-ebusy.json (Projektwurzel). Basis-URL https://{subdomain}.ebusy.de/api,
 * Authentifizierung per HTTP Basic mit einem API-Nutzer aus dem eBuSy-Backend
 * (Allgemein → Schnittstellen → API-Nutzer). Jede Antwort hat die Form
 * { error: null|"MISSING_DATA"|…, message: string|null, response: mixed }.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const TCG_EBUSY_OPTION     = 'tcg_ebusy_settings';
const TCG_EBUSY_LOG_OPTION = 'tcg_ebusy_log';

/**
 * Standardwerte der Einstellungen.
 */
function tcg_ebusy_default_settings() {
    return [
        'enabled'              => 0,
        'subdomain'            => 'tc-grubweg',
        'username'             => '',
        'password'             => '',
        'form_id'              => 0,
        'module_id'            => 0,
        'membership_type_id'   => 0,  // eBuSy-Mitgliedschaftsart (bei TCG genau eine)
        'membership_status'    => 'REQUESTED', // REQUESTED = Vorstand bestätigt in eBuSy | ACTIVE = sofort aktiv
        'fee_attribute_id'     => 0,  // Personen-Attribut „Beiträge" – daraus vergibt eBuSy Gruppe + Beitragsart
        'attr_map'             => [], // md5(Beitragsmodell-Label) => Attributwert-ID von „Beiträge"
        'passive_map'          => [], // md5(Beitragsmodell-Label) => 1, wenn passive/ruhende Mitgliedschaft
        'section_ids'          => [], // Abteilungs-IDs, die jeder Mitgliedschaft zugewiesen werden
        'payment_type_ordinal' => '',
        'payment_type_name'    => '',
        'notify_email'         => '',
        'send_user_info'       => 0,
    ];
}

/**
 * Gespeicherte Einstellungen inkl. Defaults.
 */
function tcg_ebusy_settings() {
    $saved = get_option( TCG_EBUSY_OPTION, [] );
    if ( ! is_array( $saved ) ) {
        $saved = [];
    }
    return array_merge( tcg_ebusy_default_settings(), $saved );
}

/**
 * Sind Subdomain, Benutzer und Passwort gesetzt?
 */
function tcg_ebusy_is_configured() {
    $s = tcg_ebusy_settings();
    return '' !== $s['subdomain'] && '' !== $s['username'] && '' !== $s['password'];
}

/**
 * Basis-URL der API.
 */
function tcg_ebusy_base_url() {
    $s = tcg_ebusy_settings();
    return 'https://' . $s['subdomain'] . '.ebusy.de/api';
}

/**
 * ID des CF7-Formulars, das an eBuSy übertragen wird.
 * Fällt ohne Einstellung auf das Formular mit dem Titel „Mitgliederantrag" zurück.
 */
function tcg_ebusy_target_form_id() {
    static $cached = null;
    if ( null !== $cached ) {
        return $cached;
    }

    $s  = tcg_ebusy_settings();
    $id = (int) $s['form_id'];

    if ( ! $id && class_exists( 'WPCF7_ContactForm' ) ) {
        foreach ( WPCF7_ContactForm::find( [ 'posts_per_page' => -1 ] ) as $form ) {
            if ( 'Mitgliederantrag' === $form->title() ) {
                $id = (int) $form->id();
                break;
            }
        }
    }

    $cached = $id;
    return $cached;
}

/**
 * Nutzdaten einer API-Antwort. Erfolgsantworten nutzen den Schlüssel „response"
 * (wie dokumentiert), Fehlerantworten der Auth-Schicht „result". Kommt gar kein
 * Wrapper (Liste oder Objekt ohne „error"-Schlüssel), ist die Antwort selbst die Nutzlast.
 */
function tcg_ebusy_payload( array $json ) {
    if ( array_key_exists( 'response', $json ) ) {
        return $json['response'];
    }
    if ( array_key_exists( 'result', $json ) ) {
        return $json['result'];
    }
    if ( ! array_key_exists( 'error', $json ) && ! array_key_exists( 'message', $json ) ) {
        return $json;
    }
    return null;
}

/**
 * Führt einen API-Aufruf aus.
 *
 * @param string     $method GET|POST|PUT|PATCH
 * @param string     $path   Pfad relativ zu /api, z. B. "general/person"
 * @param array|null $body   Wird als JSON gesendet
 * @return array { ok: bool, error: string, data: mixed (Inhalt von "response") }
 */
function tcg_ebusy_request( $method, $path, $body = null ) {
    if ( ! tcg_ebusy_is_configured() ) {
        return [
            'ok'    => false,
            'error' => __( 'eBuSy-Zugangsdaten sind nicht vollständig konfiguriert (Einstellungen → eBuSy-Schnittstelle).', 'tc-grubweg' ),
            'data'  => null,
        ];
    }

    $s    = tcg_ebusy_settings();
    $args = [
        'method'  => strtoupper( $method ),
        'timeout' => 15,
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode( $s['username'] . ':' . $s['password'] ),
            'Accept'        => 'application/json',
        ],
    ];

    if ( null !== $body ) {
        $args['headers']['Content-Type'] = 'application/json; charset=utf-8';
        $args['body']                    = wp_json_encode( $body );
    }

    $url      = tcg_ebusy_base_url() . '/' . ltrim( $path, '/' );
    $response = wp_remote_request( $url, $args );

    if ( is_wp_error( $response ) ) {
        return [
            'ok'    => false,
            /* translators: %s = technische Fehlermeldung */
            'error' => sprintf( __( 'Verbindung zu eBuSy fehlgeschlagen: %s', 'tc-grubweg' ), $response->get_error_message() ),
            'data'  => null,
        ];
    }

    $code = (int) wp_remote_retrieve_response_code( $response );
    $raw  = wp_remote_retrieve_body( $response );
    $json = json_decode( $raw, true );

    if ( 401 === $code || 403 === $code ) {
        return [
            'ok'    => false,
            /* translators: %d = HTTP-Statuscode */
            'error' => sprintf( __( 'eBuSy hat die Zugangsdaten abgelehnt (HTTP %d). Bitte API-Benutzer und Passwort prüfen.', 'tc-grubweg' ), $code ),
            'data'  => null,
        ];
    }

    if ( $code < 200 || $code >= 300 ) {
        $detail = is_array( $json ) && ! empty( $json['message'] ) ? $json['message'] : wp_strip_all_tags( substr( (string) $raw, 0, 200 ) );
        return [
            'ok'    => false,
            /* translators: 1: HTTP-Statuscode, 2: Detail */
            'error' => sprintf( __( 'eBuSy antwortete mit HTTP %1$d: %2$s', 'tc-grubweg' ), $code, $detail ),
            'data'  => null,
        ];
    }

    if ( ! is_array( $json ) ) {
        return [
            'ok'    => false,
            'error' => __( 'eBuSy lieferte keine gültige JSON-Antwort.', 'tc-grubweg' ),
            'data'  => null,
        ];
    }

    if ( ! empty( $json['error'] ) ) {
        $message = ! empty( $json['message'] ) ? $json['message'] : '';
        return [
            'ok'    => false,
            'error' => trim( $json['error'] . ( $message ? ': ' . $message : '' ) ),
            'data'  => tcg_ebusy_payload( $json ),
        ];
    }

    return [
        'ok'    => true,
        'error' => '',
        'data'  => tcg_ebusy_payload( $json ),
    ];
}

// ── Wrapper für die genutzten Endpunkte ────────────────────────────────────────

/** POST /general/person → { id, name } */
function tcg_ebusy_create_person( array $person ) {
    return tcg_ebusy_request( 'POST', 'general/person', $person );
}

/** POST /member/modules/{module_id}/membership → { id, name } */
function tcg_ebusy_create_membership( $module_id, array $membership ) {
    return tcg_ebusy_request( 'POST', 'member/modules/' . (int) $module_id . '/membership', $membership );
}

/**
 * PATCH /member/modules/{module_id}/membership/{membership_id} → { id, name }
 * Hinweis: „membershipFeeTypes" und „paymentType" ignoriert eBuSy hier (Tests 30.09.2026) – die
 * Beitragsart ergibt sich aus Gruppenregeln, siehe tcg_ebusy_set_attributes().
 */
function tcg_ebusy_update_membership( $module_id, $membership_id, array $patch ) {
    return tcg_ebusy_request( 'PATCH', 'member/modules/' . (int) $module_id . '/membership/' . (int) $membership_id, $patch );
}

/**
 * POST /general/person/{person_id}/set-attributes
 *
 * Beitragsarten weist eBuSy über Gruppen „MGV – …" zu, deren Regeln auf aktive Mitgliedschaft,
 * Alter am 1.1. und das Personen-Attribut „Beiträge" schauen. Das Attribut ist damit der einzige
 * Weg, die Beitragsart per API zu steuern.
 *
 * @param array $attributes [ Attribut-ID => Attributwert-ID, bei Freitext-Attributen der Text ]
 */
function tcg_ebusy_set_attributes( $person_id, array $attributes ) {
    $body = [];
    foreach ( $attributes as $attribute_id => $value ) {
        $body[ (string) (int) $attribute_id ] = is_int( $value ) || ctype_digit( (string) $value ) ? (string) (int) $value : trim( (string) $value );
    }
    return tcg_ebusy_request( 'POST', 'general/person/' . (int) $person_id . '/set-attributes', [ 'attributes' => $body ] );
}

/** GET /general/attributes → [ { id, name, description, values: [ { id, name } ] } ] (eBuSy liefert { attributes: [...] }) */
function tcg_ebusy_get_attributes() {
    $r = tcg_ebusy_request( 'GET', 'general/attributes' );
    if ( $r['ok'] && isset( $r['data']['attributes'] ) && is_array( $r['data']['attributes'] ) ) {
        $r['data'] = $r['data']['attributes'];
    }
    return $r;
}

/** GET /general/modules → [ { id, name, displayName, type } ] */
function tcg_ebusy_get_modules() {
    return tcg_ebusy_request( 'GET', 'general/modules' );
}

/** GET /member/modules/{module_id}/membership-types → Page { content: [ { id, name } ] } */
function tcg_ebusy_get_membership_types( $module_id ) {
    return tcg_ebusy_request( 'GET', 'member/modules/' . (int) $module_id . '/membership-types?offset=0&limit=100' );
}

/** GET /member/modules/{module_id}/sections → Page { content: [ { id, name } ] } */
function tcg_ebusy_get_sections( $module_id ) {
    return tcg_ebusy_request( 'GET', 'member/modules/' . (int) $module_id . '/sections?offset=0&limit=100' );
}

/** GET /member/modules/{module_id}/memberships → Page { content: [ Mitgliedschaft ] } */
function tcg_ebusy_get_memberships( $module_id, $offset = 0, $limit = 100 ) {
    return tcg_ebusy_request( 'GET', 'member/modules/' . (int) $module_id . '/memberships?offset=' . (int) $offset . '&limit=' . (int) $limit );
}

/**
 * Beitragsarten (membershipFeeTypes) sind in der API nicht dokumentiert und haben keinen
 * Listen-Endpunkt. Als Hilfe für die Zuordnung werden sie aus den vorhandenen
 * Mitgliedschaften gesammelt (max. 10 Seiten à 100). Beitragsarten ohne Mitglied
 * fehlen hier – ihre ID steht im eBuSy-Backend.
 *
 * @return array { ok: bool, error: string, data: [ id => name ] }
 */
function tcg_ebusy_collect_fee_types( $module_id ) {
    $found  = [];
    $offset = 0;

    for ( $page = 0; $page < 10; $page++ ) {
        $r = tcg_ebusy_get_memberships( $module_id, $offset, 100 );
        if ( ! $r['ok'] ) {
            return [ 'ok' => false, 'error' => $r['error'], 'data' => $found ];
        }
        $content = isset( $r['data']['content'] ) ? (array) $r['data']['content'] : [];
        foreach ( $content as $membership ) {
            foreach ( (array) ( $membership['membershipFeeTypes'] ?? [] ) as $fee ) {
                if ( isset( $fee['id'] ) ) {
                    $found[ (int) $fee['id'] ] = isset( $fee['name'] ) ? (string) $fee['name'] : '';
                }
            }
        }
        if ( ! empty( $r['data']['last'] ) || ! $content ) {
            break;
        }
        $offset += 100;
    }

    ksort( $found );
    return [ 'ok' => true, 'error' => '', 'data' => $found ];
}

/** GET /accounting/payment-types → [ { name, ordinal } ] (eBuSy liefert { paymentTypes: [...] }) */
function tcg_ebusy_get_payment_types() {
    $r = tcg_ebusy_request( 'GET', 'accounting/payment-types' );
    if ( $r['ok'] && isset( $r['data']['paymentTypes'] ) && is_array( $r['data']['paymentTypes'] ) ) {
        $r['data'] = $r['data']['paymentTypes'];
    }
    return $r;
}

// ── Protokoll ──────────────────────────────────────────────────────────────────

/**
 * Schreibt einen Eintrag ins Übertragungsprotokoll (letzte 20, neueste zuerst).
 * Enthält bewusst keine Bankdaten.
 */
function tcg_ebusy_log( array $entry ) {
    $entry = wp_parse_args( $entry, [
        'time'          => current_time( 'mysql' ),
        'name'          => '',
        'ok'            => false,
        'person_id'     => 0,
        'membership_id' => 0,
        'message'       => '',
    ] );

    $log = get_option( TCG_EBUSY_LOG_OPTION, [] );
    if ( ! is_array( $log ) ) {
        $log = [];
    }

    array_unshift( $log, $entry );
    $log = array_slice( $log, 0, 20 );
    update_option( TCG_EBUSY_LOG_OPTION, $log, false );

    if ( ! $entry['ok'] ) {
        error_log( '[tc-grubweg/ebusy] ' . $entry['name'] . ': ' . $entry['message'] );
    }
}
