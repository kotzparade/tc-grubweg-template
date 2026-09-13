<?php
/**
 * eBuSy-Schnittstelle: Einstellungsseite unter Einstellungen → eBuSy-Schnittstelle.
 *
 * Pflegbar ohne Code-Kenntnisse: Zugangsdaten, Formular, Modul-ID, Mitgliedschaftsart,
 * Zuordnung der Beitragsmodelle zu eBuSy-Beitragsarten, Abteilungen, Zahlungsart, Benachrichtigung.
 * „Verbindung testen" listet Module, Mitgliedschaftsarten, Beitragsarten (aus vorhandenen
 * Mitgliedschaften), Abteilungen und Zahlungsarten mit IDs.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const TCG_EBUSY_PAGE_SLUG = 'tcg-ebusy';

// ── Menü + Settings API ────────────────────────────────────────────────────────

function tcg_ebusy_admin_menu() {
    add_options_page(
        __( 'eBuSy-Schnittstelle', 'tc-grubweg' ),
        __( 'eBuSy-Schnittstelle', 'tc-grubweg' ),
        'manage_options',
        TCG_EBUSY_PAGE_SLUG,
        'tcg_ebusy_render_settings_page'
    );
}
add_action( 'admin_menu', 'tcg_ebusy_admin_menu' );

function tcg_ebusy_admin_init() {
    register_setting( 'tcg_ebusy', TCG_EBUSY_OPTION, [
        'type'              => 'array',
        'sanitize_callback' => 'tcg_ebusy_sanitize_settings',
        'default'           => [],
    ] );
}
add_action( 'admin_init', 'tcg_ebusy_admin_init' );

/**
 * Bereinigt die Eingaben der Einstellungsseite.
 */
function tcg_ebusy_sanitize_settings( $input ) {
    $old   = tcg_ebusy_settings();
    $input = is_array( $input ) ? $input : [];
    $clean = tcg_ebusy_default_settings();

    $clean['enabled']   = empty( $input['enabled'] ) ? 0 : 1;
    $clean['subdomain'] = isset( $input['subdomain'] ) ? preg_replace( '/[^a-z0-9-]/', '', strtolower( trim( $input['subdomain'] ) ) ) : '';
    if ( '' === $clean['subdomain'] ) {
        $clean['subdomain'] = 'tc-grubweg';
    }

    $clean['username'] = isset( $input['username'] ) ? sanitize_text_field( $input['username'] ) : '';

    // Leeres Passwortfeld = bisheriges Passwort behalten.
    $password          = isset( $input['password'] ) ? trim( (string) $input['password'] ) : '';
    $clean['password'] = '' !== $password ? $password : $old['password'];

    $clean['form_id']   = isset( $input['form_id'] ) ? absint( $input['form_id'] ) : 0;
    $clean['module_id'] = isset( $input['module_id'] ) ? absint( $input['module_id'] ) : 0;

    $clean['membership_type_id'] = isset( $input['membership_type_id'] ) ? absint( $input['membership_type_id'] ) : 0;
    $clean['membership_status']  = ( isset( $input['membership_status'] ) && 'ACTIVE' === $input['membership_status'] ) ? 'ACTIVE' : 'REQUESTED';

    $clean['fee_map'] = [];
    if ( ! empty( $input['fee_map'] ) && is_array( $input['fee_map'] ) ) {
        foreach ( $input['fee_map'] as $key => $id ) {
            $id = absint( $id );
            if ( preg_match( '/^[a-f0-9]{32}$/', (string) $key ) && $id > 0 ) {
                $clean['fee_map'][ $key ] = $id;
            }
        }
    }

    $clean['passive_map'] = [];
    if ( ! empty( $input['passive_map'] ) && is_array( $input['passive_map'] ) ) {
        foreach ( $input['passive_map'] as $key => $flag ) {
            if ( preg_match( '/^[a-f0-9]{32}$/', (string) $key ) && ! empty( $flag ) ) {
                $clean['passive_map'][ $key ] = 1;
            }
        }
    }

    $clean['section_ids'] = [];
    if ( isset( $input['section_ids'] ) ) {
        foreach ( preg_split( '/[\s,;]+/', (string) $input['section_ids'] ) as $id ) {
            if ( ctype_digit( $id ) && (int) $id > 0 ) {
                $clean['section_ids'][] = (int) $id;
            }
        }
        $clean['section_ids'] = array_values( array_unique( $clean['section_ids'] ) );
    }

    $ordinal                       = isset( $input['payment_type_ordinal'] ) ? trim( (string) $input['payment_type_ordinal'] ) : '';
    $clean['payment_type_ordinal'] = ( '' !== $ordinal && ctype_digit( $ordinal ) ) ? $ordinal : '';
    $clean['payment_type_name']    = isset( $input['payment_type_name'] ) ? sanitize_text_field( $input['payment_type_name'] ) : '';

    $clean['notify_email']   = isset( $input['notify_email'] ) ? sanitize_email( $input['notify_email'] ) : '';
    $clean['send_user_info'] = empty( $input['send_user_info'] ) ? 0 : 1;

    if ( $clean['enabled'] && ( '' === $clean['username'] || '' === $clean['password'] ) ) {
        add_settings_error(
            'tcg_ebusy',
            'tcg_ebusy_credentials',
            __( 'Die Übertragung ist aktiviert, aber API-Benutzer oder Passwort fehlen. Anträge werden bis dahin nicht an eBuSy übertragen.', 'tc-grubweg' ),
            'warning'
        );
    }

    return $clean;
}

// ── Verbindungstest ────────────────────────────────────────────────────────────

function tcg_ebusy_test_transient_key() {
    return 'tcg_ebusy_test_' . get_current_user_id();
}

function tcg_ebusy_handle_test() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Keine Berechtigung.', 'tc-grubweg' ) );
    }
    check_admin_referer( 'tcg_ebusy_test' );

    $settings = tcg_ebusy_settings();
    $out      = [
        'modules'       => tcg_ebusy_get_modules(),
        'types'         => null,
        'fee_types'     => null,
        'sections'      => null,
        'payment_types' => tcg_ebusy_get_payment_types(),
    ];

    if ( $settings['module_id'] ) {
        $out['types']     = tcg_ebusy_get_membership_types( (int) $settings['module_id'] );
        $out['fee_types'] = tcg_ebusy_collect_fee_types( (int) $settings['module_id'] );
        $out['sections']  = tcg_ebusy_get_sections( (int) $settings['module_id'] );
    }

    set_transient( tcg_ebusy_test_transient_key(), $out, 5 * MINUTE_IN_SECONDS );

    wp_safe_redirect( add_query_arg( [ 'page' => TCG_EBUSY_PAGE_SLUG, 'tested' => 1 ], admin_url( 'options-general.php' ) ) );
    exit;
}
add_action( 'admin_post_tcg_ebusy_test', 'tcg_ebusy_handle_test' );

// ── Seite ──────────────────────────────────────────────────────────────────────

/**
 * Optionen des Radio-Feldes „beitragsmodell" aus dem Zielformular (Label = gesendeter Wert).
 */
function tcg_ebusy_form_tier_options( $form_id ) {
    if ( ! $form_id || ! function_exists( 'wpcf7_contact_form' ) ) {
        return [];
    }
    $form = wpcf7_contact_form( $form_id );
    if ( ! $form ) {
        return [];
    }
    $tags = $form->scan_form_tags( [ 'name' => 'beitragsmodell' ] );
    if ( ! $tags ) {
        return [];
    }
    return array_values( array_filter( array_map( 'trim', (array) $tags[0]->values ) ) );
}

function tcg_ebusy_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $s          = tcg_ebusy_settings();
    $cf7_active = class_exists( 'WPCF7_ContactForm' );
    $forms      = $cf7_active ? WPCF7_ContactForm::find( [ 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ] ) : [];
    $form_id    = tcg_ebusy_target_form_id();
    $tiers      = tcg_ebusy_form_tier_options( $form_id );
    $log        = get_option( TCG_EBUSY_LOG_OPTION, [] );
    $test       = null;

    if ( isset( $_GET['tested'] ) ) {
        $test = get_transient( tcg_ebusy_test_transient_key() );
        delete_transient( tcg_ebusy_test_transient_key() );
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'eBuSy-Schnittstelle', 'tc-grubweg' ); ?></h1>
        <p><?php esc_html_e( 'Überträgt eingehende Mitgliedsanträge automatisch an eBuSy: Die Person wird mit Anschrift, Kontakt, Bankverbindung und SEPA-Mandat angelegt. Ist eine Modul-ID der Mitgliederverwaltung eingetragen, wird zusätzlich eine Mitgliedschaft erstellt (Status „beantragt" oder „aktiv", siehe unten) – mit der eingetragenen Mitgliedschaftsart, Abteilung(en), Zahlungsart und dem Beitragsmodell als Beitragsart. Beitragsmodell, Eintrittsdatum, Bemerkungen und Einwilligungen stehen zusätzlich im Kommentar der Person.', 'tc-grubweg' ); ?></p>
        <p class="description">
            <?php
            printf(
                /* translators: %s = Link zum eBuSy-Backend */
                esc_html__( 'Voraussetzung: In eBuSy muss das API-Modul aktiv und ein API-Nutzer mit Schreibrechten angelegt sein (Allgemein → Schnittstellen → API-Nutzer): %s', 'tc-grubweg' ),
                '<a href="' . esc_url( 'https://' . $s['subdomain'] . '.ebusy.de/backend/system/general/features/index' ) . '" target="_blank" rel="noopener">' . esc_html( $s['subdomain'] . '.ebusy.de' ) . '</a>'
            );
            ?>
        </p>

        <?php settings_errors( 'tcg_ebusy' ); ?>

        <div class="notice notice-info inline">
            <p>
                <strong><?php esc_html_e( 'Aktueller Modus:', 'tc-grubweg' ); ?></strong>
                <?php
                if ( $s['module_id'] ) {
                    /* translators: %d = Modul-ID */
                    printf( esc_html__( 'Person + Mitgliedschaft (Mitgliedermodul #%d).', 'tc-grubweg' ), (int) $s['module_id'] );
                } else {
                    esc_html_e( 'Nur Person anlegen – keine Modul-ID eingetragen, es wird keine Mitgliedschaft erstellt.', 'tc-grubweg' );
                }
                echo ' ';
                echo $s['enabled'] ? esc_html__( 'Übertragung ist aktiv.', 'tc-grubweg' ) : esc_html__( 'Übertragung ist deaktiviert.', 'tc-grubweg' );
                ?>
            </p>
        </div>

        <?php if ( ! $cf7_active ) : ?>
            <div class="notice notice-error"><p><?php esc_html_e( 'Contact Form 7 ist nicht aktiv – die Schnittstelle kann ohne das Plugin nicht arbeiten.', 'tc-grubweg' ); ?></p></div>
        <?php endif; ?>

        <form method="post" action="options.php">
            <?php settings_fields( 'tcg_ebusy' ); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Übertragung', 'tc-grubweg' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="<?php echo esc_attr( TCG_EBUSY_OPTION ); ?>[enabled]" value="1" <?php checked( $s['enabled'] ); ?>>
                            <?php esc_html_e( 'Anträge an eBuSy übertragen', 'tc-grubweg' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Deaktiviert bleibt alles wie bisher: Mail an den Vorstand + Speicherung in Flamingo.', 'tc-grubweg' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="tcg_ebusy_subdomain"><?php esc_html_e( 'eBuSy-Subdomain', 'tc-grubweg' ); ?></label></th>
                    <td>
                        <input type="text" id="tcg_ebusy_subdomain" name="<?php echo esc_attr( TCG_EBUSY_OPTION ); ?>[subdomain]" value="<?php echo esc_attr( $s['subdomain'] ); ?>" class="regular-text" placeholder="tc-grubweg">
                        <p class="description"><?php esc_html_e( 'Aus https://tc-grubweg.ebusy.de → „tc-grubweg".', 'tc-grubweg' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="tcg_ebusy_username"><?php esc_html_e( 'API-Benutzer', 'tc-grubweg' ); ?></label></th>
                    <td><input type="text" id="tcg_ebusy_username" name="<?php echo esc_attr( TCG_EBUSY_OPTION ); ?>[username]" value="<?php echo esc_attr( $s['username'] ); ?>" class="regular-text" autocomplete="off"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="tcg_ebusy_password"><?php esc_html_e( 'API-Passwort', 'tc-grubweg' ); ?></label></th>
                    <td>
                        <input type="password" id="tcg_ebusy_password" name="<?php echo esc_attr( TCG_EBUSY_OPTION ); ?>[password]" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo $s['password'] ? esc_attr__( '•••••••• (gespeichert)', 'tc-grubweg' ) : ''; ?>">
                        <p class="description"><?php esc_html_e( 'Leer lassen, um das gespeicherte Passwort zu behalten.', 'tc-grubweg' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="tcg_ebusy_form_id"><?php esc_html_e( 'Formular', 'tc-grubweg' ); ?></label></th>
                    <td>
                        <select id="tcg_ebusy_form_id" name="<?php echo esc_attr( TCG_EBUSY_OPTION ); ?>[form_id]">
                            <option value="0"><?php esc_html_e( '– automatisch („Mitgliederantrag") –', 'tc-grubweg' ); ?></option>
                            <?php foreach ( $forms as $form ) : ?>
                                <option value="<?php echo esc_attr( $form->id() ); ?>" <?php selected( (int) $s['form_id'], (int) $form->id() ); ?>><?php echo esc_html( $form->title() ); ?> (ID <?php echo esc_html( $form->id() ); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'Das Contact-Form-7-Formular, dessen Einsendungen übertragen werden. Es muss die Felder vorname, nachname, email, iban usw. enthalten.', 'tc-grubweg' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="tcg_ebusy_module_id"><?php esc_html_e( 'Modul-ID Mitgliederverwaltung', 'tc-grubweg' ); ?></label></th>
                    <td>
                        <input type="number" min="0" id="tcg_ebusy_module_id" name="<?php echo esc_attr( TCG_EBUSY_OPTION ); ?>[module_id]" value="<?php echo esc_attr( $s['module_id'] ?: '' ); ?>" class="small-text">
                        <p class="description"><?php esc_html_e( 'ID des eBuSy-Moduls vom Typ MEMBER – siehe „Verbindung testen" weiter unten (beim TC Grubweg: 1349 „Mitglieder"). Leer lassen, wenn in eBuSy kein Mitgliedermodul gebucht ist: Dann wird nur die Person angelegt.', 'tc-grubweg' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="tcg_ebusy_membership_type_id"><?php esc_html_e( 'Mitgliedschaftsart-ID', 'tc-grubweg' ); ?></label></th>
                    <td>
                        <input type="number" min="0" id="tcg_ebusy_membership_type_id" name="<?php echo esc_attr( TCG_EBUSY_OPTION ); ?>[membership_type_id]" value="<?php echo esc_attr( $s['membership_type_id'] ?: '' ); ?>" class="small-text">
                        <p class="description"><?php esc_html_e( 'Die eBuSy-Mitgliedschaftsart, die jeder Antrag bekommt – siehe „Verbindung testen" (beim TC Grubweg gibt es genau eine: 338 „Mitgliedschaft im DJK-TC Passau-Grubweg e. V."). Pflicht, sobald eine Modul-ID eingetragen ist.', 'tc-grubweg' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="tcg_ebusy_membership_status"><?php esc_html_e( 'Status neuer Mitgliedschaften', 'tc-grubweg' ); ?></label></th>
                    <td>
                        <select id="tcg_ebusy_membership_status" name="<?php echo esc_attr( TCG_EBUSY_OPTION ); ?>[membership_status]">
                            <option value="REQUESTED" <?php selected( $s['membership_status'], 'REQUESTED' ); ?>><?php esc_html_e( 'beantragt – Vorstand bestätigt in eBuSy (empfohlen)', 'tc-grubweg' ); ?></option>
                            <option value="ACTIVE" <?php selected( $s['membership_status'], 'ACTIVE' ); ?>><?php esc_html_e( 'aktiv – sofort mit Beitragsart, ohne Bestätigung', 'tc-grubweg' ); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e( 'eBuSy speichert die Beitragsart nur bei aktiven Mitgliedschaften. Bei „beantragt" steht sie im Kommentar der Mitgliedschaft und wird vom Vorstand beim Bestätigen in eBuSy zugewiesen. Bei „aktiv" wird sie sofort gesetzt – das Mitglied ist dann ohne Prüfung aktiv.', 'tc-grubweg' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Beitragsmodell → Beitragsart', 'tc-grubweg' ); ?></th>
                    <td>
                        <?php if ( ! $tiers ) : ?>
                            <p class="description"><?php esc_html_e( 'Im gewählten Formular wurde kein Radio-Feld „beitragsmodell" gefunden. Formular wählen, speichern – dann erscheinen hier die Optionen.', 'tc-grubweg' ); ?></p>
                        <?php else : ?>
                            <table class="widefat striped" style="max-width:720px">
                                <thead><tr><th><?php esc_html_e( 'Option im Formular', 'tc-grubweg' ); ?></th><th style="width:140px"><?php esc_html_e( 'Beitragsart-ID', 'tc-grubweg' ); ?></th><th style="width:120px"><?php esc_html_e( 'Passiv/ruhend', 'tc-grubweg' ); ?></th></tr></thead>
                                <tbody>
                                <?php foreach ( $tiers as $label ) :
                                    $key     = tcg_ebusy_tier_key( $label );
                                    $val     = isset( $s['fee_map'][ $key ] ) ? (int) $s['fee_map'][ $key ] : 0;
                                    $passive = ! empty( $s['passive_map'][ $key ] );
                                    ?>
                                    <tr>
                                        <td><?php echo esc_html( $label ); ?><?php if ( ! $val ) : ?> <span style="color:#b32d2e;font-weight:600"><?php esc_html_e( '– nicht zugeordnet', 'tc-grubweg' ); ?></span><?php endif; ?></td>
                                        <td><input type="number" min="0" name="<?php echo esc_attr( TCG_EBUSY_OPTION ); ?>[fee_map][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $val ?: '' ); ?>" class="small-text"></td>
                                        <td><label><input type="checkbox" name="<?php echo esc_attr( TCG_EBUSY_OPTION ); ?>[passive_map][<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $passive ); ?>> <?php esc_html_e( 'passiv', 'tc-grubweg' ); ?></label></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                            <p class="description"><?php esc_html_e( 'Beitragsarten heißen in eBuSy „Mitgliedsbeiträge" (Mitgliederverwaltung → Einstellungen). Die IDs zeigt „Verbindung testen" für alle Beitragsarten, die bereits bei einem Mitglied verwendet werden; andere stehen im eBuSy-Backend. Die Zuordnung landet bei Status „beantragt" im Kommentar der Mitgliedschaft, bei Status „aktiv" direkt als Beitragsart. „Passiv" setzt die Mitgliedschaft auf passiv/ruhend (z. B. ruhende Mitgliedschaft). Fehlt die Zuordnung für ein Beitragsmodell, wird nur die Person angelegt und der Vorstand per Fehlermail informiert.', 'tc-grubweg' ); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="tcg_ebusy_section_ids"><?php esc_html_e( 'Abteilungen (optional)', 'tc-grubweg' ); ?></label></th>
                    <td>
                        <input type="text" id="tcg_ebusy_section_ids" name="<?php echo esc_attr( TCG_EBUSY_OPTION ); ?>[section_ids]" value="<?php echo esc_attr( implode( ', ', (array) $s['section_ids'] ) ); ?>" class="regular-text" placeholder="200">
                        <p class="description"><?php esc_html_e( 'Abteilungs-IDs, die jede neue Mitgliedschaft bekommt, durch Komma getrennt (z. B. 200 = Tennis). IDs siehe „Verbindung testen". Leer = keine Abteilung.', 'tc-grubweg' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Zahlungsart (optional)', 'tc-grubweg' ); ?></th>
                    <td>
                        <label><?php esc_html_e( 'Ordinal', 'tc-grubweg' ); ?> <input type="number" min="0" name="<?php echo esc_attr( TCG_EBUSY_OPTION ); ?>[payment_type_ordinal]" value="<?php echo esc_attr( $s['payment_type_ordinal'] ); ?>" class="small-text"></label>
                        &nbsp;
                        <label><?php esc_html_e( 'Name', 'tc-grubweg' ); ?> <input type="text" name="<?php echo esc_attr( TCG_EBUSY_OPTION ); ?>[payment_type_name]" value="<?php echo esc_attr( $s['payment_type_name'] ); ?>" class="regular-text" placeholder="Lastschrift"></label>
                        <p class="description"><?php esc_html_e( 'Nur mit Modul-ID relevant. Zahlungsart der Mitgliedschaft (z. B. Lastschrift). Werte siehe „Verbindung testen". Leer = eBuSy-Standard.', 'tc-grubweg' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="tcg_ebusy_notify_email"><?php esc_html_e( 'Fehler-Benachrichtigung an', 'tc-grubweg' ); ?></label></th>
                    <td>
                        <input type="email" id="tcg_ebusy_notify_email" name="<?php echo esc_attr( TCG_EBUSY_OPTION ); ?>[notify_email]" value="<?php echo esc_attr( $s['notify_email'] ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
                        <p class="description"><?php esc_html_e( 'Erhält eine Mail, wenn ein Antrag nicht übertragen werden konnte. Leer = Admin-E-Mail der Website.', 'tc-grubweg' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Zugangsdaten', 'tc-grubweg' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="<?php echo esc_attr( TCG_EBUSY_OPTION ); ?>[send_user_info]" value="1" <?php checked( $s['send_user_info'] ); ?>>
                            <?php esc_html_e( 'eBuSy soll dem neuen Mitglied sofort Zugangsdaten per E-Mail senden (sendUserInfo)', 'tc-grubweg' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Empfehlung: aus – Zugangsdaten erst nach Bestätigung des Antrags aus eBuSy heraus versenden.', 'tc-grubweg' ); ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button( __( 'Einstellungen speichern', 'tc-grubweg' ) ); ?>
        </form>

        <hr>

        <h2><?php esc_html_e( 'Verbindung testen', 'tc-grubweg' ); ?></h2>
        <p><?php esc_html_e( 'Prüft die Zugangsdaten und listet Module, Mitgliedschaftsarten, Beitragsarten, Abteilungen und Zahlungsarten mit ihren IDs. Bitte vorher speichern.', 'tc-grubweg' ); ?></p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="tcg_ebusy_test">
            <?php wp_nonce_field( 'tcg_ebusy_test' ); ?>
            <?php submit_button( __( 'Verbindung testen', 'tc-grubweg' ), 'secondary', 'submit', false ); ?>
        </form>

        <?php if ( isset( $_GET['tested'] ) && ! $test ) : ?>
            <div class="notice notice-warning inline" style="margin-top:12px"><p><?php esc_html_e( 'Kein Testergebnis vorhanden (abgelaufen). Bitte erneut testen.', 'tc-grubweg' ); ?></p></div>
        <?php elseif ( $test ) : ?>
            <?php tcg_ebusy_render_test_results( $test, $s ); ?>
        <?php endif; ?>

        <hr>

        <h2><?php esc_html_e( 'Letzte Übertragungen', 'tc-grubweg' ); ?></h2>
        <?php if ( empty( $log ) ) : ?>
            <p class="description"><?php esc_html_e( 'Noch keine Übertragungen.', 'tc-grubweg' ); ?></p>
        <?php else : ?>
            <table class="widefat striped" style="max-width:960px">
                <thead><tr>
                    <th style="width:150px"><?php esc_html_e( 'Zeit', 'tc-grubweg' ); ?></th>
                    <th style="width:180px"><?php esc_html_e( 'Antragsteller', 'tc-grubweg' ); ?></th>
                    <th><?php esc_html_e( 'Ergebnis', 'tc-grubweg' ); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ( (array) $log as $entry ) : ?>
                    <tr>
                        <td><?php echo esc_html( $entry['time'] ); ?></td>
                        <td><?php echo esc_html( $entry['name'] ); ?></td>
                        <td>
                            <?php if ( ! empty( $entry['ok'] ) ) : ?>
                                <span style="color:#1a6b35;font-weight:600">✓</span>
                            <?php else : ?>
                                <span style="color:#b32d2e;font-weight:600">✗</span>
                            <?php endif; ?>
                            <?php echo esc_html( $entry['message'] ); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Ergebnis des Verbindungstests: Module, Mitgliedschaftsarten, Zahlungsarten.
 */
function tcg_ebusy_render_test_results( array $test, array $settings ) {
    $modules    = $test['modules'];
    $has_member = false;
    if ( ! empty( $modules['ok'] ) ) {
        foreach ( (array) $modules['data'] as $module ) {
            if ( isset( $module['type'] ) && 'MEMBER' === $module['type'] ) {
                $has_member = true;
                break;
            }
        }
    }
    ?>
    <div style="margin-top:16px">
    <?php if ( ! $modules['ok'] ) : ?>
        <div class="notice notice-error inline"><p><strong><?php esc_html_e( 'Verbindung fehlgeschlagen:', 'tc-grubweg' ); ?></strong> <?php echo esc_html( $modules['error'] ); ?></p></div>
    <?php else : ?>
        <div class="notice notice-success inline"><p><?php esc_html_e( 'Verbindung erfolgreich – Zugangsdaten sind gültig.', 'tc-grubweg' ); ?></p></div>

        <h3><?php esc_html_e( 'Module', 'tc-grubweg' ); ?></h3>
        <table class="widefat striped" style="max-width:720px">
            <thead><tr><th><?php esc_html_e( 'ID', 'tc-grubweg' ); ?></th><th><?php esc_html_e( 'Name', 'tc-grubweg' ); ?></th><th><?php esc_html_e( 'Anzeigename', 'tc-grubweg' ); ?></th><th><?php esc_html_e( 'Typ', 'tc-grubweg' ); ?></th></tr></thead>
            <tbody>
            <?php foreach ( (array) $modules['data'] as $module ) :
                $is_member = isset( $module['type'] ) && 'MEMBER' === $module['type'];
                ?>
                <tr<?php echo $is_member ? ' style="background:#eef6e8"' : ''; ?>>
                    <td><strong><?php echo esc_html( isset( $module['id'] ) ? $module['id'] : '' ); ?></strong></td>
                    <td><?php echo esc_html( isset( $module['name'] ) ? $module['name'] : '' ); ?></td>
                    <td><?php echo esc_html( isset( $module['displayName'] ) ? $module['displayName'] : '' ); ?></td>
                    <td><?php echo esc_html( isset( $module['type'] ) ? $module['type'] : '' ); ?><?php if ( $is_member ) : ?> ← <?php esc_html_e( 'Mitgliederverwaltung', 'tc-grubweg' ); endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ( ! $has_member ) : ?>
            <div class="notice notice-warning inline"><p><?php esc_html_e( 'Kein Modul vom Typ MEMBER gefunden – in eBuSy ist keine Mitgliederverwaltung gebucht. Modul-ID leer lassen: Anträge werden dann nur als Person angelegt.', 'tc-grubweg' ); ?></p></div>
        <?php endif; ?>

        <h3><?php esc_html_e( 'Mitgliedschaftsarten', 'tc-grubweg' ); ?></h3>
        <?php if ( ! $settings['module_id'] && ! $has_member ) : ?>
            <p class="description"><?php esc_html_e( 'Entfällt – ohne Mitgliedermodul gibt es keine Mitgliedschaftsarten.', 'tc-grubweg' ); ?></p>
        <?php elseif ( ! $settings['module_id'] ) : ?>
            <p class="description"><?php esc_html_e( 'Bitte zuerst die Modul-ID der Mitgliederverwaltung eintragen und speichern, dann erneut testen.', 'tc-grubweg' ); ?></p>
        <?php elseif ( empty( $test['types']['ok'] ) ) : ?>
            <div class="notice notice-error inline"><p><?php echo esc_html( isset( $test['types']['error'] ) ? $test['types']['error'] : '' ); ?></p></div>
        <?php else :
            $types = isset( $test['types']['data']['content'] ) ? (array) $test['types']['data']['content'] : [];
            ?>
            <table class="widefat striped" style="max-width:480px">
                <thead><tr><th><?php esc_html_e( 'ID', 'tc-grubweg' ); ?></th><th><?php esc_html_e( 'Name', 'tc-grubweg' ); ?></th></tr></thead>
                <tbody>
                <?php if ( ! $types ) : ?>
                    <tr><td colspan="2"><?php esc_html_e( 'Keine Mitgliedschaftsarten gefunden.', 'tc-grubweg' ); ?></td></tr>
                <?php endif; ?>
                <?php foreach ( $types as $type ) : ?>
                    <tr>
                        <td><strong><?php echo esc_html( isset( $type['id'] ) ? $type['id'] : '' ); ?></strong></td>
                        <td><?php echo esc_html( isset( $type['name'] ) ? $type['name'] : '' ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if ( $settings['module_id'] ) : ?>
            <h3><?php esc_html_e( 'Beitragsarten (aus vorhandenen Mitgliedschaften)', 'tc-grubweg' ); ?></h3>
            <?php if ( empty( $test['fee_types']['ok'] ) ) : ?>
                <div class="notice notice-error inline"><p><?php echo esc_html( isset( $test['fee_types']['error'] ) ? $test['fee_types']['error'] : '' ); ?></p></div>
            <?php else : ?>
                <table class="widefat striped" style="max-width:640px">
                    <thead><tr><th><?php esc_html_e( 'ID', 'tc-grubweg' ); ?></th><th><?php esc_html_e( 'Name', 'tc-grubweg' ); ?></th></tr></thead>
                    <tbody>
                    <?php if ( empty( $test['fee_types']['data'] ) ) : ?>
                        <tr><td colspan="2"><?php esc_html_e( 'Noch keine Mitgliedschaft mit Beitragsart vorhanden.', 'tc-grubweg' ); ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ( (array) $test['fee_types']['data'] as $fee_id => $fee_name ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( $fee_id ); ?></strong></td>
                            <td><?php echo esc_html( $fee_name ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="description"><?php esc_html_e( 'Die API kennt keine Liste der Beitragsarten; gezeigt werden nur die, die bereits bei einem Mitglied gesetzt sind. Weitere IDs im eBuSy-Backend nachsehen.', 'tc-grubweg' ); ?></p>
            <?php endif; ?>

            <h3><?php esc_html_e( 'Abteilungen', 'tc-grubweg' ); ?></h3>
            <?php if ( empty( $test['sections']['ok'] ) ) : ?>
                <div class="notice notice-error inline"><p><?php echo esc_html( isset( $test['sections']['error'] ) ? $test['sections']['error'] : '' ); ?></p></div>
            <?php else :
                $sections = isset( $test['sections']['data']['content'] ) ? (array) $test['sections']['data']['content'] : [];
                ?>
                <table class="widefat striped" style="max-width:480px">
                    <thead><tr><th><?php esc_html_e( 'ID', 'tc-grubweg' ); ?></th><th><?php esc_html_e( 'Name', 'tc-grubweg' ); ?></th></tr></thead>
                    <tbody>
                    <?php if ( ! $sections ) : ?>
                        <tr><td colspan="2"><?php esc_html_e( 'Keine Abteilungen gefunden.', 'tc-grubweg' ); ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ( $sections as $section ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( isset( $section['id'] ) ? $section['id'] : '' ); ?></strong></td>
                            <td><?php echo esc_html( isset( $section['name'] ) ? $section['name'] : '' ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>

        <h3><?php esc_html_e( 'Zahlungsarten', 'tc-grubweg' ); ?></h3>
        <?php if ( empty( $test['payment_types']['ok'] ) ) : ?>
            <div class="notice notice-error inline"><p><?php echo esc_html( isset( $test['payment_types']['error'] ) ? $test['payment_types']['error'] : '' ); ?></p></div>
        <?php else : ?>
            <table class="widefat striped" style="max-width:480px">
                <thead><tr><th><?php esc_html_e( 'Ordinal', 'tc-grubweg' ); ?></th><th><?php esc_html_e( 'Name', 'tc-grubweg' ); ?></th></tr></thead>
                <tbody>
                <?php foreach ( (array) $test['payment_types']['data'] as $pt ) : ?>
                    <tr>
                        <td><strong><?php echo esc_html( isset( $pt['ordinal'] ) ? $pt['ordinal'] : '' ); ?></strong></td>
                        <td><?php echo esc_html( isset( $pt['name'] ) ? $pt['name'] : '' ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
    </div>
    <?php
}
