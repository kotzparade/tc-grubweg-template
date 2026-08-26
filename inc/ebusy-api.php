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
        'type_map'             => [], // md5(Beitragsmodell-Label) => Mitgliedschaftsart-ID
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
            'data'  => isset( $json['response'] ) ? $json['response'] : null,
        ];
    }

    return [
        'ok'    => true,
        'error' => '',
        'data'  => isset( $json['response'] ) ? $json['response'] : null,
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

/** GET /general/modules → [ { id, name, displayName, type } ] */
function tcg_ebusy_get_modules() {
    return tcg_ebusy_request( 'GET', 'general/modules' );
}

/** GET /member/modules/{module_id}/membership-types → Page { content: [ { id, name } ] } */
function tcg_ebusy_get_membership_types( $module_id ) {
    return tcg_ebusy_request( 'GET', 'member/modules/' . (int) $module_id . '/membership-types?offset=0&limit=100' );
}

/** GET /accounting/payment-types → [ { name, ordinal } ] */
function tcg_ebusy_get_payment_types() {
    return tcg_ebusy_request( 'GET', 'accounting/payment-types' );
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
