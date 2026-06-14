<?php
/**
 * Template Name: Mannschaften
 * Zeigt die Mannschaftsseite mit Tab-Navigation (Herren / Damen / Junioren).
 * Immer ohne Sidebar. Mannschaftskarten kommen per Shortcode vom btv-Plugin.
 *
 * Gutenberg-Aufbau in der Seite:
 *   – Gruppe (Anker: "tab-herren")  → Abschnittsüberschrift + Shortcode
 *   – Gruppe (Anker: "tab-damen")   → Abschnittsüberschrift + Shortcode
 *   – Gruppe (Anker: "tab-junioren")→ Abschnittsüberschrift + Shortcode
 */

get_header();
?>

<?php get_template_part( 'template-parts/page-hero' ); ?>


<!-- ── MANNSCHAFTEN-INHALT ────────────────────────────────── -->
<main class="page-layout" id="main-content">
  <?php while ( have_posts() ) : the_post(); ?>
    <?php the_content(); ?>
  <?php endwhile; ?>
</main>

<?php get_footer(); ?>
