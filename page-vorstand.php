<?php
/**
 * Template Name: Vorstand-Übersicht
 * Listet alle Personen mit Kategorie "Vorstand" als Karten-Grid auf.
 */

get_header();
?>

<?php get_template_part( 'template-parts/page-hero' ); ?>


<!-- ── VORSTAND-INHALT ───────────────────────────────────── -->
<main class="page-layout" id="main-content">

  <?php while ( have_posts() ) : the_post(); ?>
    <?php if ( get_the_content() ) : ?>
      <div class="page-intro"><?php the_content(); ?></div>
    <?php endif; ?>
  <?php endwhile; ?>

  <?php
  get_template_part( 'template-parts/personen-list', null, [
      'term'  => 'vorstand',
      'empty' => __( 'Aktuell sind keine Vorstandsmitglieder hinterlegt.', 'tc-grubweg' ),
  ] );
  ?>

</main>

<?php get_footer(); ?>
