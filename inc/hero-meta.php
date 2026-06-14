<?php
/**
 * Hero-Meta: Eyebrow, Titel-mit-Lime-Akzent und Pills für alle Seiten-Templates.
 *
 * Felder werden im Frontend von `template-parts/page-hero.php` ausgewertet
 * (oder von einzelnen Templates direkt). Defaults greifen pro Template, wenn
 * Meta leer ist – auf der Mitgliedschaft-Seite sind die Marketing-Defaults
 * hardcoded, andere Seiten zeigen die Felder nur wenn redaktionell gefüllt.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function tcg_register_hero_meta() {
    $args = [
        'type'          => 'string',
        'single'        => true,
        'show_in_rest'  => true,
        'auth_callback' => function() { return current_user_can( 'edit_pages' ); },
    ];

    register_post_meta( 'page', '_tcg_hero_eyebrow', array_merge( $args, [
        'sanitize_callback' => 'sanitize_text_field',
    ] ) );

    register_post_meta( 'page', '_tcg_hero_title', array_merge( $args, [
        'sanitize_callback' => 'sanitize_text_field',
    ] ) );

    register_post_meta( 'page', '_tcg_hero_pills', array_merge( $args, [
        'sanitize_callback' => 'sanitize_textarea_field',
    ] ) );
}
add_action( 'init', 'tcg_register_hero_meta' );

/**
 * Meta-Box auf allen Seiten anzeigen (Post-Type page).
 */
function tcg_add_hero_meta_box() {
    add_meta_box(
        'tcg_hero',
        __( 'Hero-Bereich', 'tc-grubweg' ),
        'tcg_render_hero_meta_box',
        'page',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'tcg_add_hero_meta_box' );

function tcg_render_hero_meta_box( $post ) {
    wp_nonce_field( 'tcg_save_hero', 'tcg_hero_nonce' );

    $eyebrow = get_post_meta( $post->ID, '_tcg_hero_eyebrow', true );
    $title   = get_post_meta( $post->ID, '_tcg_hero_title', true );
    $pills   = get_post_meta( $post->ID, '_tcg_hero_pills', true );
    ?>
    <p>
        <label for="tcg_hero_eyebrow"><strong><?php esc_html_e( 'Eyebrow', 'tc-grubweg' ); ?></strong></label><br>
        <input type="text" id="tcg_hero_eyebrow" name="tcg_hero_eyebrow" value="<?php echo esc_attr( $eyebrow ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'z. B. Werde Teil der Tennisfamilie', 'tc-grubweg' ); ?>">
        <span class="description"><?php esc_html_e( 'Kleiner grüner Großbuchstaben-Text über dem Titel. Leer lassen, wenn nicht erwünscht.', 'tc-grubweg' ); ?></span>
    </p>
    <p>
        <label for="tcg_hero_title"><strong><?php esc_html_e( 'Titel mit Lime-Akzent', 'tc-grubweg' ); ?></strong></label><br>
        <input type="text" id="tcg_hero_title" name="tcg_hero_title" value="<?php echo esc_attr( $title ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'z. B. Mitgliedschaft &|Beiträge.', 'tc-grubweg' ); ?>">
        <span class="description"><?php echo wp_kses_post( __( 'Optional. Wenn gesetzt, ersetzt diesen Wert den normalen Seitentitel im Hero und wird größer dargestellt. Pipe-Zeichen <code>|</code> trennt den normalen Teil vom Lime-Akzent (zweite Zeile). Leer lassen, um den Standard-Seitentitel zu verwenden.', 'tc-grubweg' ) ); ?></span>
    </p>
    <p>
        <label for="tcg_hero_pills"><strong><?php esc_html_e( 'Pills (rechts oben)', 'tc-grubweg' ); ?></strong></label><br>
        <textarea id="tcg_hero_pills" name="tcg_hero_pills" rows="3" class="widefat" placeholder="<?php esc_attr_e( "Keine Aufnahmegebühr\nFamilie bis 13 J. inklusive", 'tc-grubweg' ); ?>"><?php echo esc_textarea( $pills ); ?></textarea>
        <span class="description"><?php esc_html_e( 'Eine Pill pro Zeile, grüne Häkchen-Badges. Leer lassen für Layout ohne Pills.', 'tc-grubweg' ); ?></span>
    </p>
    <p>
        <em><?php esc_html_e( 'Tipp: Der Subtitel unter dem Titel wird aus dem Auszug-Feld (Excerpt) der Seite gelesen.', 'tc-grubweg' ); ?></em>
    </p>
    <?php
}

function tcg_save_hero_meta( $post_id, $post ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( $post->post_type !== 'page' ) {
        return;
    }
    if ( ! isset( $_POST['tcg_hero_nonce'] )
        || ! wp_verify_nonce( $_POST['tcg_hero_nonce'], 'tcg_save_hero' ) ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $eyebrow = isset( $_POST['tcg_hero_eyebrow'] ) ? sanitize_text_field( wp_unslash( $_POST['tcg_hero_eyebrow'] ) ) : '';
    $title   = isset( $_POST['tcg_hero_title'] )   ? sanitize_text_field( wp_unslash( $_POST['tcg_hero_title'] ) )   : '';
    $pills   = isset( $_POST['tcg_hero_pills'] )   ? sanitize_textarea_field( wp_unslash( $_POST['tcg_hero_pills'] ) ) : '';

    update_post_meta( $post_id, '_tcg_hero_eyebrow', $eyebrow );
    update_post_meta( $post_id, '_tcg_hero_title',   $title );
    update_post_meta( $post_id, '_tcg_hero_pills',   $pills );
}
add_action( 'save_post_page', 'tcg_save_hero_meta', 10, 2 );
