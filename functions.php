<?php
/**
 * DJK-TC Passau Grubweg — Theme Functions
 */

// ── SEITENSPEZIFISCHE MODULE ───────────────────────────────────────────────────
require_once get_theme_file_path( 'inc/hero-meta.php' );
require_once get_theme_file_path( 'inc/mitgliedschaft.php' );

// ── THEME SETUP ────────────────────────────────────────────────────────────────

function tcg_setup() {
    load_theme_textdomain( 'tc-grubweg', get_template_directory() . '/languages' );

    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'html5', [ 'comment-list', 'comment-form', 'search-form', 'gallery', 'caption', 'style', 'script' ] );
    add_post_type_support( 'page', 'excerpt' ); // Ermöglicht Kurzbeschreibung im Seiten-Hero

    add_theme_support( 'custom-logo', [
        'height'      => 64,
        'width'       => 200,
        'flex-height' => true,
        'flex-width'  => true,
    ] );

    register_nav_menus( [
        'primary' => __( 'Hauptnavigation', 'tc-grubweg' ),
        'footer'  => __( 'Footer Navigation', 'tc-grubweg' ),
    ] );
}
add_action( 'after_setup_theme', 'tcg_setup' );

// ── ENQUEUE ASSETS ─────────────────────────────────────────────────────────────

function tcg_enqueue_assets() {
    wp_enqueue_style(
        'outfit-font',
        'https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap',
        [],
        null
    );

    wp_enqueue_style(
        'tcg-style',
        get_theme_file_uri( 'assets/css/style.css' ),
        [ 'outfit-font' ],
        '1.3.6'
    );

    wp_enqueue_script(
        'tcg-main',
        get_theme_file_uri( 'assets/js/main.js' ),
        [],
        '1.1.0',
        true // load in footer
    );
}
add_action( 'wp_enqueue_scripts', 'tcg_enqueue_assets' );

// ── CONTACT FORM 7 / reCAPTCHA ──────────────────────────────────────────────────
// reCAPTCHA v3 schützt alle CF7-Formulare unsichtbar. Das schwebende Google-Badge
// blenden wir per CSS aus (.grecaptcha-badge); Google verlangt dafür den folgenden
// Attributionstext sichtbar am Formular. Wird nur ausgegeben, wenn reCAPTCHA aktiv ist
// (Site-/Secret-Key unter Kontakt → Integration gesetzt).

function tcg_cf7_recaptcha_notice( $form ) {
    if ( ! class_exists( 'WPCF7_RECAPTCHA' ) ) {
        return $form;
    }

    $service = WPCF7_RECAPTCHA::get_instance();
    if ( ! $service || ! $service->is_active() ) {
        return $form;
    }

    $note = sprintf(
        '<p class="tcg-recaptcha-note">%s</p>',
        sprintf(
            /* translators: %1$s = Link Datenschutzerklärung, %2$s = Link Nutzungsbedingungen */
            __( 'Diese Seite ist durch reCAPTCHA geschützt; es gelten die %1$s und %2$s von Google.', 'tc-grubweg' ),
            '<a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer">' . __( 'Datenschutzerklärung', 'tc-grubweg' ) . '</a>',
            '<a href="https://policies.google.com/terms" target="_blank" rel="noopener noreferrer">' . __( 'Nutzungsbedingungen', 'tc-grubweg' ) . '</a>'
        )
    );

    return $form . $note;
}
add_filter( 'wpcf7_form_elements', 'tcg_cf7_recaptcha_notice' );

// ── NAV WALKER ─────────────────────────────────────────────────────────────────
// Converts WordPress nav menu to the design's div.nav-item / div.dropdown structure.
// Add class "nav-cta" to the "Platz buchen" menu item in WP Admin → Appearance → Menus
// to render it as the lime-green CTA button.

class TC_Nav_Walker extends Walker_Nav_Menu {

    /** Open a sub-menu level → becomes .dropdown div */
    public function start_lvl( &$output, $depth = 0, $args = null ) {
        $output .= '<div class="dropdown">';
    }

    /** Close a sub-menu level */
    public function end_lvl( &$output, $depth = 0, $args = null ) {
        $output .= '</div>';
    }

    /** Render a single nav item */
    public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
        $classes       = (array) $item->classes;
        $has_children  = in_array( 'menu-item-has-children', $classes );
        $is_cta        = in_array( 'nav-cta', $classes );
        $url           = esc_url( $item->url );
        $title         = esc_html( $item->title );

        $chevron = '<svg width="12" height="12" viewBox="0 0 12 12" fill="none">'
                 . '<path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>'
                 . '</svg>';

        if ( $depth === 0 ) {
            if ( $is_cta ) {
                // Lime-green CTA button — no wrapper div needed
                $output .= '<a href="' . $url . '" class="nav-cta">' . $title . '</a>';
            } else {
                $output .= '<div class="nav-item">';
                $output .= '<a href="' . $url . '">';
                $output .= $title;
                if ( $has_children ) {
                    $output .= ' ' . $chevron;
                }
                $output .= '</a>';
            }
        } else {
            // Sub-menu item: plain anchor inside .dropdown
            $output .= '<a href="' . $url . '">' . $title . '</a>';
        }
    }

    /** Close a nav item */
    public function end_el( &$output, $item, $depth = 0, $args = null ) {
        $is_cta = in_array( 'nav-cta', (array) $item->classes );

        if ( $depth === 0 && ! $is_cta ) {
            $output .= '</div>'; // close .nav-item
        }
        // depth > 0: anchor is already self-closed in start_el
    }
}

// ── SIDEBAR ─────────────────────────────────────────────────────────────────────

function tcg_register_sidebars() {
    register_sidebar( [
        'name'          => __( 'Seiten-Sidebar', 'tc-grubweg' ),
        'id'            => 'page-sidebar',
        'description'   => __( 'Rechte Spalte auf allgemeinen Seiten. Nur sichtbar wenn mindestens ein Widget aktiv ist.', 'tc-grubweg' ),
        'before_widget' => '<div id="%1$s" class="sidebar-widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h4>',
        'after_title'   => '</h4>',
    ] );

    register_sidebar( [
        'name'          => __( 'Beitrags-Sidebar', 'tc-grubweg' ),
        'id'            => 'post-sidebar',
        'description'   => __( 'Rechte Spalte in einzelnen Blog-Beiträgen.', 'tc-grubweg' ),
        'before_widget' => '<div id="%1$s" class="sidebar-widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h4>',
        'after_title'   => '</h4>',
    ] );
}
add_action( 'widgets_init', 'tcg_register_sidebars' );

// ── EXCERPT LENGTH ─────────────────────────────────────────────────────────────

function tcg_excerpt_length( $length ) {
    return 20;
}
add_filter( 'excerpt_length', 'tcg_excerpt_length' );

function tcg_excerpt_more( $more ) {
    return '…';
}
add_filter( 'excerpt_more', 'tcg_excerpt_more' );

// ── THE EVENTS CALENDAR: Plugin-CSS entfernen ──────────────────────────────────

function tcg_dequeue_tribe_widget_styles() {
    // Sidebar-Widget Styles entfernen wir global (Widget kann auf jeder Seite stehen).
    wp_dequeue_style( 'tribe-events-widgets-v2-events-list-skeleton' );
    wp_dequeue_style( 'tribe-events-widgets-v2-events-list-full' );

    // Auf Tribe-Archiv und Single-Event übernimmt das Theme das komplette Styling.
    $is_tribe_view = ( function_exists( 'tribe_is_event_query' ) && tribe_is_event_query() )
        || is_singular( 'tribe_events' )
        || is_post_type_archive( 'tribe_events' );

    if ( $is_tribe_view ) {
        wp_dequeue_style( 'tribe-events-views-v2-skeleton' );
        wp_dequeue_style( 'tribe-events-views-v2-full' );
        wp_dequeue_style( 'tribe-events-calendar-style' );
        wp_dequeue_style( 'tribe-events-views-v2-bootstrap-datepicker-css' );
        wp_dequeue_style( 'tribe-common-skeleton-style' );
        wp_dequeue_style( 'tribe-common-full-style' );
    }
}
add_action( 'wp_enqueue_scripts', 'tcg_dequeue_tribe_widget_styles', 100 );

// Bei Single-Events die WordPress-Template-Hierarchie respektieren (damit single-tribe_events.php greift).
add_filter( 'tribe_events_views_v2_use_wp_template_hierarchy', function( $load_template, $template, $context, $query ) {
    if ( $query && method_exists( $query, 'is_singular' ) && $query->is_singular( 'tribe_events' ) ) {
        return true;
    }
    return $load_template;
}, 10, 4 );

// ── CUSTOM POST TYPE: PERSONEN ─────────────────────────────────────────────────
// Vereinsmitglieder (Vorstand, Trainer …) mit Bild, Aufgabe, Telefon, E-Mail.

function tcg_register_personen_cpt() {
    $labels = [
        'name'                  => _x( 'Personen', 'post type general name', 'tc-grubweg' ),
        'singular_name'         => _x( 'Person', 'post type singular name', 'tc-grubweg' ),
        'menu_name'             => _x( 'Personen', 'admin menu', 'tc-grubweg' ),
        'name_admin_bar'        => _x( 'Person', 'add new on admin bar', 'tc-grubweg' ),
        'add_new'               => __( 'Hinzufügen', 'tc-grubweg' ),
        'add_new_item'          => __( 'Neue Person hinzufügen', 'tc-grubweg' ),
        'new_item'              => __( 'Neue Person', 'tc-grubweg' ),
        'edit_item'             => __( 'Person bearbeiten', 'tc-grubweg' ),
        'view_item'             => __( 'Person ansehen', 'tc-grubweg' ),
        'all_items'             => __( 'Alle Personen', 'tc-grubweg' ),
        'search_items'          => __( 'Personen suchen', 'tc-grubweg' ),
        'not_found'             => __( 'Keine Personen gefunden.', 'tc-grubweg' ),
        'not_found_in_trash'    => __( 'Keine Personen im Papierkorb.', 'tc-grubweg' ),
        'featured_image'        => __( 'Bild', 'tc-grubweg' ),
        'set_featured_image'    => __( 'Bild festlegen', 'tc-grubweg' ),
        'remove_featured_image' => __( 'Bild entfernen', 'tc-grubweg' ),
        'use_featured_image'    => __( 'Als Bild verwenden', 'tc-grubweg' ),
    ];

    register_post_type( 'person', [
        'labels'              => $labels,
        'public'              => true,
        'publicly_queryable'  => false, // keine Einzelseiten im Frontend
        'exclude_from_search' => true,
        'has_archive'         => false, // kein /personen/-Archiv
        'rewrite'             => false,
        'menu_icon'           => 'dashicons-groups',
        'menu_position'       => 22,
        'supports'            => [ 'title', 'thumbnail', 'revisions', 'page-attributes' ],
        'show_in_rest'        => true,
    ] );
}
add_action( 'init', 'tcg_register_personen_cpt' );

// Taxonomie: Kategorien (Vorstand, Trainer)

function tcg_register_personen_taxonomy() {
    $labels = [
        'name'              => _x( 'Kategorien', 'taxonomy general name', 'tc-grubweg' ),
        'singular_name'     => _x( 'Kategorie', 'taxonomy singular name', 'tc-grubweg' ),
        'search_items'      => __( 'Kategorien suchen', 'tc-grubweg' ),
        'all_items'         => __( 'Alle Kategorien', 'tc-grubweg' ),
        'edit_item'         => __( 'Kategorie bearbeiten', 'tc-grubweg' ),
        'update_item'       => __( 'Kategorie aktualisieren', 'tc-grubweg' ),
        'add_new_item'      => __( 'Neue Kategorie', 'tc-grubweg' ),
        'new_item_name'     => __( 'Name der neuen Kategorie', 'tc-grubweg' ),
        'menu_name'         => __( 'Kategorien', 'tc-grubweg' ),
    ];

    register_taxonomy( 'person_kategorie', [ 'person' ], [
        'labels'            => $labels,
        'hierarchical'      => true,
        'public'            => true,
        'show_admin_column' => true,
        'show_in_rest'      => true,
        'rewrite'           => [ 'slug' => 'personen-kategorie' ],
    ] );

    // Standard-Terms anlegen, falls noch nicht vorhanden.
    foreach ( [ 'Vorstand', 'Trainer' ] as $term ) {
        if ( ! term_exists( $term, 'person_kategorie' ) ) {
            wp_insert_term( $term, 'person_kategorie' );
        }
    }
}
add_action( 'init', 'tcg_register_personen_taxonomy' );

// Post-Meta für Aufgabe, Telefon, E-Mail (REST/Gutenberg-fähig).

function tcg_register_personen_meta() {
    register_post_meta( 'person', '_tcg_aufgabe', [
        'type'              => 'string',
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'sanitize_text_field',
        'auth_callback'     => function() { return current_user_can( 'edit_posts' ); },
    ] );

    register_post_meta( 'person', '_tcg_telefon', [
        'type'              => 'string',
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'sanitize_text_field',
        'auth_callback'     => function() { return current_user_can( 'edit_posts' ); },
    ] );

    register_post_meta( 'person', '_tcg_email', [
        'type'              => 'string',
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'sanitize_email',
        'auth_callback'     => function() { return current_user_can( 'edit_posts' ); },
    ] );
}
add_action( 'init', 'tcg_register_personen_meta' );

// Meta-Box im Classic-Editor / als Sidebar-Panel im Block-Editor (klassisch).

function tcg_add_personen_meta_box() {
    add_meta_box(
        'tcg_person_kontakt',
        __( 'Kontakt', 'tc-grubweg' ),
        'tcg_render_personen_meta_box',
        'person',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'tcg_add_personen_meta_box' );

function tcg_render_personen_meta_box( $post ) {
    wp_nonce_field( 'tcg_save_person_meta', 'tcg_person_meta_nonce' );

    $aufgabe = get_post_meta( $post->ID, '_tcg_aufgabe', true );
    $telefon = get_post_meta( $post->ID, '_tcg_telefon', true );
    $email   = get_post_meta( $post->ID, '_tcg_email', true );
    ?>
    <p>
        <label for="tcg_aufgabe"><strong><?php esc_html_e( 'Aufgabe', 'tc-grubweg' ); ?></strong></label><br>
        <input type="text" id="tcg_aufgabe" name="tcg_aufgabe" value="<?php echo esc_attr( $aufgabe ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'z. B. 1. Vorsitzender, Jugendtrainer', 'tc-grubweg' ); ?>">
    </p>
    <p>
        <label for="tcg_telefon"><strong><?php esc_html_e( 'Telefonnummer', 'tc-grubweg' ); ?></strong></label><br>
        <input type="text" id="tcg_telefon" name="tcg_telefon" value="<?php echo esc_attr( $telefon ); ?>" class="widefat">
    </p>
    <p>
        <label for="tcg_email"><strong><?php esc_html_e( 'E-Mail', 'tc-grubweg' ); ?></strong></label><br>
        <input type="email" id="tcg_email" name="tcg_email" value="<?php echo esc_attr( $email ); ?>" class="widefat">
    </p>
    <?php
}

// Frontend-Sortierung: Personen mit gesetzter Reihenfolge zuerst (nach menu_order),
// danach alle mit Standardwert 0 alphabetisch.
function tcg_personen_frontend_orderby( $orderby, $query ) {
    if ( is_admin() || ! $query instanceof WP_Query ) {
        return $orderby;
    }
    if ( $query->get( 'post_type' ) !== 'person' ) {
        return $orderby;
    }
    global $wpdb;
    return "({$wpdb->posts}.menu_order = 0) ASC, {$wpdb->posts}.menu_order ASC, {$wpdb->posts}.post_title ASC";
}
add_filter( 'posts_orderby', 'tcg_personen_frontend_orderby', 10, 2 );

// Admin-Liste: Spalte "Reihenfolge" + Standard-Sortierung nach menu_order.

function tcg_personen_admin_columns( $columns ) {
    // 'order' nach 'title' einfügen, sonstige Spalten erhalten.
    $new = [];
    foreach ( $columns as $key => $label ) {
        $new[ $key ] = $label;
        if ( $key === 'title' ) {
            $new['menu_order'] = __( 'Reihenfolge', 'tc-grubweg' );
        }
    }
    return $new;
}
add_filter( 'manage_person_posts_columns', 'tcg_personen_admin_columns' );

function tcg_personen_admin_column_content( $column, $post_id ) {
    if ( $column === 'menu_order' ) {
        echo (int) get_post_field( 'menu_order', $post_id );
    }
}
add_action( 'manage_person_posts_custom_column', 'tcg_personen_admin_column_content', 10, 2 );

function tcg_personen_sortable_columns( $columns ) {
    $columns['menu_order'] = 'menu_order';
    return $columns;
}
add_filter( 'manage_edit-person_sortable_columns', 'tcg_personen_sortable_columns' );

function tcg_personen_default_admin_order( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) {
        return;
    }
    if ( $query->get( 'post_type' ) !== 'person' ) {
        return;
    }
    if ( ! $query->get( 'orderby' ) ) {
        $query->set( 'orderby', 'menu_order title' );
        $query->set( 'order', 'ASC' );
    }
}
add_action( 'pre_get_posts', 'tcg_personen_default_admin_order' );

// ── CUSTOM POST TYPE: SPONSOREN ────────────────────────────────────────────────
// Sponsoren-Logos für den Footer-Slider. Keine Einzelseiten — nur Logo + optionale Link-URL.

function tcg_register_sponsor_cpt() {
    $labels = [
        'name'                  => _x( 'Sponsoren', 'post type general name', 'tc-grubweg' ),
        'singular_name'         => _x( 'Sponsor', 'post type singular name', 'tc-grubweg' ),
        'menu_name'             => _x( 'Sponsoren', 'admin menu', 'tc-grubweg' ),
        'name_admin_bar'        => _x( 'Sponsor', 'add new on admin bar', 'tc-grubweg' ),
        'add_new'               => __( 'Hinzufügen', 'tc-grubweg' ),
        'add_new_item'          => __( 'Neuen Sponsor hinzufügen', 'tc-grubweg' ),
        'new_item'              => __( 'Neuer Sponsor', 'tc-grubweg' ),
        'edit_item'             => __( 'Sponsor bearbeiten', 'tc-grubweg' ),
        'view_item'             => __( 'Sponsor ansehen', 'tc-grubweg' ),
        'all_items'             => __( 'Alle Sponsoren', 'tc-grubweg' ),
        'search_items'          => __( 'Sponsoren suchen', 'tc-grubweg' ),
        'not_found'             => __( 'Keine Sponsoren gefunden.', 'tc-grubweg' ),
        'not_found_in_trash'    => __( 'Keine Sponsoren im Papierkorb.', 'tc-grubweg' ),
        'featured_image'        => __( 'Logo', 'tc-grubweg' ),
        'set_featured_image'    => __( 'Logo festlegen', 'tc-grubweg' ),
        'remove_featured_image' => __( 'Logo entfernen', 'tc-grubweg' ),
        'use_featured_image'    => __( 'Als Logo verwenden', 'tc-grubweg' ),
    ];

    register_post_type( 'sponsor', [
        'labels'              => $labels,
        'public'              => false,
        'show_ui'             => true,
        'publicly_queryable'  => false,
        'exclude_from_search' => true,
        'has_archive'         => false,
        'rewrite'             => false,
        'menu_icon'           => 'dashicons-awards',
        'menu_position'       => 23,
        'supports'            => [ 'title', 'thumbnail', 'page-attributes' ],
        'show_in_rest'        => true,
    ] );
}
add_action( 'init', 'tcg_register_sponsor_cpt' );

function tcg_register_sponsor_meta() {
    register_post_meta( 'sponsor', '_tcg_sponsor_url', [
        'type'              => 'string',
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'esc_url_raw',
        'auth_callback'     => function() { return current_user_can( 'edit_posts' ); },
    ] );
}
add_action( 'init', 'tcg_register_sponsor_meta' );

function tcg_add_sponsor_meta_box() {
    add_meta_box(
        'tcg_sponsor_link',
        __( 'Sponsor-Link', 'tc-grubweg' ),
        'tcg_render_sponsor_meta_box',
        'sponsor',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'tcg_add_sponsor_meta_box' );

function tcg_render_sponsor_meta_box( $post ) {
    wp_nonce_field( 'tcg_save_sponsor_meta', 'tcg_sponsor_meta_nonce' );
    $url = get_post_meta( $post->ID, '_tcg_sponsor_url', true );
    ?>
    <p>
        <label for="tcg_sponsor_url"><strong><?php esc_html_e( 'Website (optional)', 'tc-grubweg' ); ?></strong></label><br>
        <input type="url" id="tcg_sponsor_url" name="tcg_sponsor_url" value="<?php echo esc_attr( $url ); ?>" class="widefat" placeholder="https://...">
        <span class="description"><?php esc_html_e( 'Wenn gesetzt, wird das Logo im Footer auf diese Seite verlinkt.', 'tc-grubweg' ); ?></span>
    </p>
    <?php
}

function tcg_save_sponsor_meta( $post_id, $post ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( $post->post_type !== 'sponsor' ) {
        return;
    }
    if ( ! isset( $_POST['tcg_sponsor_meta_nonce'] )
        || ! wp_verify_nonce( $_POST['tcg_sponsor_meta_nonce'], 'tcg_save_sponsor_meta' ) ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $url = isset( $_POST['tcg_sponsor_url'] ) ? esc_url_raw( wp_unslash( $_POST['tcg_sponsor_url'] ) ) : '';
    update_post_meta( $post_id, '_tcg_sponsor_url', $url );
}
add_action( 'save_post_sponsor', 'tcg_save_sponsor_meta', 10, 2 );

// Admin-Liste: Logo-Vorschau + Sortierung nach menu_order.

function tcg_sponsor_admin_columns( $columns ) {
    $new = [];
    foreach ( $columns as $key => $label ) {
        if ( $key === 'title' ) {
            $new['tcg_logo'] = __( 'Logo', 'tc-grubweg' );
        }
        $new[ $key ] = $label;
        if ( $key === 'title' ) {
            $new['menu_order'] = __( 'Reihenfolge', 'tc-grubweg' );
        }
    }
    return $new;
}
add_filter( 'manage_sponsor_posts_columns', 'tcg_sponsor_admin_columns' );

function tcg_sponsor_admin_column_content( $column, $post_id ) {
    if ( $column === 'tcg_logo' && has_post_thumbnail( $post_id ) ) {
        echo get_the_post_thumbnail( $post_id, [ 80, 40 ], [ 'style' => 'object-fit:contain;max-height:40px;width:auto;' ] );
    }
    if ( $column === 'menu_order' ) {
        echo (int) get_post_field( 'menu_order', $post_id );
    }
}
add_action( 'manage_sponsor_posts_custom_column', 'tcg_sponsor_admin_column_content', 10, 2 );

function tcg_sponsor_sortable_columns( $columns ) {
    $columns['menu_order'] = 'menu_order';
    return $columns;
}
add_filter( 'manage_edit-sponsor_sortable_columns', 'tcg_sponsor_sortable_columns' );

function tcg_sponsor_default_admin_order( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) {
        return;
    }
    if ( $query->get( 'post_type' ) !== 'sponsor' ) {
        return;
    }
    if ( ! $query->get( 'orderby' ) ) {
        $query->set( 'orderby', 'menu_order title' );
        $query->set( 'order', 'ASC' );
    }
}
add_action( 'pre_get_posts', 'tcg_sponsor_default_admin_order' );

function tcg_save_personen_meta( $post_id, $post ) {
    // Autosave / Revisionen / falscher Post-Type ignorieren.
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( $post->post_type !== 'person' ) {
        return;
    }
    if ( ! isset( $_POST['tcg_person_meta_nonce'] )
        || ! wp_verify_nonce( $_POST['tcg_person_meta_nonce'], 'tcg_save_person_meta' ) ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $aufgabe = isset( $_POST['tcg_aufgabe'] ) ? sanitize_text_field( wp_unslash( $_POST['tcg_aufgabe'] ) ) : '';
    $telefon = isset( $_POST['tcg_telefon'] ) ? sanitize_text_field( wp_unslash( $_POST['tcg_telefon'] ) ) : '';
    $email   = isset( $_POST['tcg_email'] )   ? sanitize_email( wp_unslash( $_POST['tcg_email'] ) )       : '';

    update_post_meta( $post_id, '_tcg_aufgabe', $aufgabe );
    update_post_meta( $post_id, '_tcg_telefon', $telefon );
    update_post_meta( $post_id, '_tcg_email',   $email );
}
add_action( 'save_post_person', 'tcg_save_personen_meta', 10, 2 );
