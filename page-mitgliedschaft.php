<?php
/**
 * Template Name: Mitgliedschaft
 * Beitrags-/Mitgliedschaftsseite mit erweitertem Hero (Eyebrow + Pills)
 * und Gutenberg-editierbarem Inhaltsbereich.
 *
 * Hero: gemeinsamer Partial template-parts/page-hero.php — die Hero-Felder
 * (Eyebrow, Titel mit Akzent, Pills) sind über die Meta-Box „Hero-Bereich"
 * editierbar. Für dieses Template gelten Marketing-Defaults, wenn Meta leer.
 *
 * Body: Gutenberg-Pattern „tcg/mitgliedschaft-page" enthält die komplette
 * Sektionsstruktur (Tier-Karten, Ausnahmen, Info-Row, CTA).
 */

get_header();
?>

<?php get_template_part( 'template-parts/page-hero' ); ?>

<!-- ── INHALT (Gutenberg) ─────────────────────────────────── -->
<main class="page-layout" id="main-content">
  <article id="page-<?php the_ID(); ?>" <?php post_class( 'entry-content' ); ?>>
    <?php while ( have_posts() ) : the_post(); the_content(); endwhile; ?>
  </article>
</main>

<?php get_footer(); ?>
