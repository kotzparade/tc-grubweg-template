<?php
/**
 * Universelles Seiten-Hero.
 *
 * Liest die drei Hero-Meta-Felder (Eyebrow, Titel-mit-Akzent, Pills) und
 * rendert den Hero entsprechend. Sind alle Felder leer, fällt der Hero auf
 * den Standard zurück: Breadcrumb + Seitentitel + optional Excerpt-Subtitel.
 *
 * Mitgliedschaft-Template stellt Marketing-Defaults bereit, wenn die Meta
 * leer sind — andere Templates zeigen Eyebrow/Pills nur, wenn redaktionell
 * gepflegt.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $post;
$post_id = $post ? $post->ID : 0;

$eyebrow   = get_post_meta( $post_id, '_tcg_hero_eyebrow', true );
$title_raw = get_post_meta( $post_id, '_tcg_hero_title', true );
$pills_raw = get_post_meta( $post_id, '_tcg_hero_pills', true );

// Template-spezifische Defaults für die Mitgliedschafts-Seite.
if ( is_page_template( 'page-mitgliedschaft.php' ) ) {
    if ( $eyebrow === '' || $eyebrow === null ) {
        $eyebrow = 'Werde Teil der Tennisfamilie';
    }
    if ( $title_raw === '' || $title_raw === null ) {
        $title_raw = 'Mitgliedschaft &|Beiträge.';
    }
    if ( $pills_raw === '' || $pills_raw === null ) {
        $pills_raw = "Keine Aufnahmegebühr\nFamilie bis 13 J. inklusive";
    }
}

// Titel am Pipe splitten: erster Teil normal, zweiter Teil <em> in Lime.
$has_custom_title = ( $title_raw !== '' && $title_raw !== null );
list( $title_lead, $title_accent ) = $has_custom_title
    ? array_pad( explode( '|', $title_raw, 2 ), 2, '' )
    : [ '', '' ];

// Pills: Textarea → Array, leere Zeilen weg.
$pills = ( $pills_raw !== '' && $pills_raw !== null )
    ? array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $pills_raw ) ) ) )
    : [];

$has_eyebrow = ( $eyebrow !== '' && $eyebrow !== null );
$has_pills   = ! empty( $pills );

// .page-hero--mb aktiviert das 2-Spalten-Grid + die radialen Lime-Overlays.
// Sinnvoll, sobald Pills oder ein akzentuierter Titel gesetzt sind.
$hero_classes = [ 'page-hero' ];
if ( $has_pills || $has_custom_title ) {
    $hero_classes[] = 'page-hero--mb';
}
?>

<div class="<?php echo esc_attr( implode( ' ', $hero_classes ) ); ?>">
  <div class="page-hero-inner">

    <?php $ancestors = array_reverse( get_post_ancestors( $post_id ) ); ?>
    <nav class="page-breadcrumb" aria-label="Breadcrumb">
      <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Startseite</a>
      <?php foreach ( $ancestors as $ancestor_id ) : ?>
        <span class="breadcrumb-sep" aria-hidden="true">›</span>
        <a href="<?php echo esc_url( get_permalink( $ancestor_id ) ); ?>"><?php echo esc_html( get_the_title( $ancestor_id ) ); ?></a>
      <?php endforeach; ?>
      <span class="breadcrumb-sep" aria-hidden="true">›</span>
      <span class="breadcrumb-current"><?php the_title(); ?></span>
    </nav>

    <div>
      <?php if ( $has_eyebrow ) : ?>
        <div class="page-hero-eyebrow"><?php echo esc_html( $eyebrow ); ?></div>
      <?php endif; ?>

      <?php if ( $has_custom_title ) : ?>
        <h1 class="page-hero-title page-hero-title--accent">
          <?php echo esc_html( $title_lead ); ?>
          <?php if ( $title_accent !== '' ) : ?>
            <br><em><?php echo esc_html( $title_accent ); ?></em>
          <?php endif; ?>
        </h1>
      <?php else : ?>
        <h1 class="page-hero-title"><?php the_title(); ?></h1>
      <?php endif; ?>

      <?php if ( has_excerpt() ) : ?>
        <p class="page-hero-sub"><?php echo wp_kses_post( get_the_excerpt() ); ?></p>
      <?php endif; ?>
    </div>

    <?php if ( $has_pills ) : ?>
      <div class="page-hero-pills">
        <?php foreach ( $pills as $pill ) : ?>
          <span class="page-hero-pill"><?php echo esc_html( $pill ); ?></span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>
</div>
