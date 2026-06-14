<?php
/**
 * Template: Allgemeine Seite
 * Sidebar wird nur angezeigt wenn der Widget-Bereich "page-sidebar" aktiv ist.
 */

get_header();

$has_sidebar = is_active_sidebar( 'page-sidebar' );
?>

<?php get_template_part( 'template-parts/page-hero' ); ?>

<!-- ── INHALT + SIDEBAR ──────────────────────────────────── -->
<div class="page-layout<?php echo $has_sidebar ? ' has-sidebar' : ''; ?>" id="main-content">

  <main class="entry-content">
    <?php while ( have_posts() ) : the_post(); ?>
      <article id="page-<?php the_ID(); ?>" <?php post_class(); ?>>
        <?php the_content(); ?>
      </article>
    <?php endwhile; ?>
  </main>

  <?php if ( $has_sidebar ) : ?>
    <aside class="sidebar" role="complementary" aria-label="Seitenleiste">
      <?php dynamic_sidebar( 'page-sidebar' ); ?>
    </aside>
  <?php endif; ?>

</div>

<?php get_footer(); ?>
