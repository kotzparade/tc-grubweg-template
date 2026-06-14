<?php
/**
 * Personen-Liste (Karten-Grid).
 *
 * Erwartete Argumente:
 *   $args['term']  string  Slug des person_kategorie-Terms (z. B. "trainer", "vorstand").
 *   $args['empty'] string  Optional: Text, wenn keine Personen vorhanden.
 */

$term  = isset( $args['term'] )  ? sanitize_key( $args['term'] ) : '';
$empty = isset( $args['empty'] ) ? $args['empty']                : __( 'Aktuell sind keine Einträge hinterlegt.', 'tc-grubweg' );

if ( ! $term ) {
    return;
}

$personen = new WP_Query( [
    'post_type'      => 'person',
    'posts_per_page' => -1,
    'tax_query'      => [
        [
            'taxonomy' => 'person_kategorie',
            'field'    => 'slug',
            'terms'    => $term,
        ],
    ],
    'no_found_rows'  => true,
] );

if ( ! $personen->have_posts() ) :
    echo '<p class="personen-empty">' . esc_html( $empty ) . '</p>';
    return;
endif;
?>

<ul class="personen-grid">
  <?php while ( $personen->have_posts() ) : $personen->the_post(); ?>
    <?php
    $aufgabe = get_post_meta( get_the_ID(), '_tcg_aufgabe', true );
    $telefon = get_post_meta( get_the_ID(), '_tcg_telefon', true );
    $email   = get_post_meta( get_the_ID(), '_tcg_email', true );
    ?>
    <li class="person-card">
      <div class="person-card-img">
        <?php if ( has_post_thumbnail() ) : ?>
          <?php the_post_thumbnail( 'medium_large', [ 'loading' => 'lazy' ] ); ?>
        <?php else : ?>
          <span class="person-card-initial" aria-hidden="true"><?php echo esc_html( mb_substr( get_the_title(), 0, 1 ) ); ?></span>
        <?php endif; ?>
      </div>

      <div class="person-card-body">
        <h2 class="person-card-name"><?php the_title(); ?></h2>

        <?php if ( $aufgabe ) : ?>
          <p class="person-card-role"><?php echo esc_html( $aufgabe ); ?></p>
        <?php endif; ?>

        <?php if ( $telefon || $email ) : ?>
          <ul class="person-card-contact">
            <?php if ( $telefon ) : ?>
              <li>
                <span class="person-card-contact-label" aria-hidden="true">Tel.</span>
                <a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $telefon ) ); ?>"><?php echo esc_html( $telefon ); ?></a>
              </li>
            <?php endif; ?>
            <?php if ( $email ) : ?>
              <li>
                <span class="person-card-contact-label" aria-hidden="true">E-Mail</span>
                <a href="mailto:<?php echo esc_attr( antispambot( $email ) ); ?>"><?php echo esc_html( antispambot( $email ) ); ?></a>
              </li>
            <?php endif; ?>
          </ul>
        <?php endif; ?>
      </div>
    </li>
  <?php endwhile; wp_reset_postdata(); ?>
</ul>
