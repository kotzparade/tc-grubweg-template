<?php
/**
 * Template Name: Trainer-Übersicht
 * Listet alle Personen mit Kategorie "Trainer" als Karten-Grid auf.
 */

get_header();
?>

<?php get_template_part( 'template-parts/page-hero' ); ?>


<!-- ── TRAINER-INHALT ────────────────────────────────────── -->
<main class="page-layout" id="main-content">

  <?php while ( have_posts() ) : the_post(); ?>
    <?php if ( get_the_content() ) : ?>
      <div class="page-intro"><?php the_content(); ?></div>
    <?php endif; ?>
  <?php endwhile; ?>

  <?php
  get_template_part( 'template-parts/personen-list', null, [
      'term'  => 'trainer',
      'empty' => __( 'Aktuell sind keine Trainer hinterlegt.', 'tc-grubweg' ),
  ] );
  ?>

</main>

<?php get_footer(); ?>
