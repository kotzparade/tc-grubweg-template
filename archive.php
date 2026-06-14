<?php
/**
 * Template: Archiv
 *
 * Wird für Kategorie-, Tag-, Datums-, Autoren- und CPT-Archive verwendet.
 * The Events Calendar liefert eigene Templates und greift hier nicht.
 */

get_header();

$tcg_posts_page_id = (int) get_option( 'page_for_posts' );
$tcg_posts_page    = $tcg_posts_page_id ? get_permalink( $tcg_posts_page_id ) : home_url( '/' );
$tcg_posts_label   = $tcg_posts_page_id ? get_the_title( $tcg_posts_page_id ) : __( 'Aktuelles', 'tc-grubweg' );

$tcg_archive_title = wp_strip_all_tags( get_the_archive_title() );
$tcg_archive_desc  = get_the_archive_description();

$has_sidebar = is_active_sidebar( 'page-sidebar' );
?>

<!-- ── PAGE HERO ─────────────────────────────────────────── -->
<div class="page-hero">
  <div class="page-hero-inner">

    <nav class="page-breadcrumb" aria-label="Breadcrumb">
      <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Startseite', 'tc-grubweg' ); ?></a>
      <span class="breadcrumb-sep" aria-hidden="true">›</span>
      <a href="<?php echo esc_url( $tcg_posts_page ); ?>"><?php echo esc_html( $tcg_posts_label ); ?></a>
      <span class="breadcrumb-sep" aria-hidden="true">›</span>
      <span class="breadcrumb-current"><?php echo esc_html( $tcg_archive_title ); ?></span>
    </nav>

    <h1 class="page-hero-title"><?php echo esc_html( $tcg_archive_title ); ?></h1>

    <?php if ( $tcg_archive_desc ) : ?>
      <div class="page-hero-sub"><?php echo wp_kses_post( $tcg_archive_desc ); ?></div>
    <?php endif; ?>

  </div>
</div>

<!-- ── INHALT + SIDEBAR ──────────────────────────────────── -->
<div class="page-layout<?php echo $has_sidebar ? ' has-sidebar' : ''; ?>" id="main-content">

  <main class="entry-content">

    <?php if ( have_posts() ) : ?>

      <div class="news-grid">
        <?php while ( have_posts() ) : the_post();
            $categories = get_the_category();
            $cat_name   = ! empty( $categories ) ? esc_html( $categories[0]->name ) : __( 'Vereinsnews', 'tc-grubweg' );
        ?>
          <article id="post-<?php the_ID(); ?>" <?php post_class( 'news-card' ); ?>>

            <?php if ( has_post_thumbnail() ) : ?>
              <a href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr( get_the_title() ); ?>">
                <?php the_post_thumbnail( 'medium_large', [ 'class' => 'news-card-img', 'alt' => esc_attr( get_the_title() ) ] ); ?>
              </a>
            <?php else : ?>
              <a href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr( get_the_title() ); ?>" class="news-card-img-placeholder">
                <svg width="64" height="64" viewBox="0 0 64 64" fill="none">
                  <circle cx="32" cy="32" r="28" stroke="#1a6b35" stroke-width="2.5"/>
                  <path d="M18 32c7-6 21-6 28 0" stroke="#1a6b35" stroke-width="2.5" stroke-linecap="round"/>
                  <path d="M32 18c-6 7-6 21 0 28" stroke="#a8cb1a" stroke-width="2.5" stroke-linecap="round"/>
                </svg>
              </a>
            <?php endif; ?>

            <div class="news-card-body">
              <div class="news-meta">
                <span class="news-date"><?php echo esc_html( get_the_date( 'j. F Y' ) ); ?></span>
                <span class="news-category"><?php echo $cat_name; ?></span>
              </div>
              <h3 class="news-card-title">
                <a href="<?php the_permalink(); ?>" style="color:inherit; text-decoration:none;"><?php the_title(); ?></a>
              </h3>
              <p class="news-card-teaser"><?php echo esc_html( wp_strip_all_tags( get_the_excerpt() ) ); ?></p>
              <a href="<?php the_permalink(); ?>" class="btn-readmore">
                <?php esc_html_e( 'Weiterlesen', 'tc-grubweg' ); ?>
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2.5 7h9M8 3.5l3.5 3.5L8 10.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </a>
            </div>

          </article>
        <?php endwhile; ?>
      </div>

      <?php
      the_posts_pagination( [
          'mid_size'  => 1,
          'prev_text' => __( '‹ Zurück', 'tc-grubweg' ),
          'next_text' => __( 'Weiter ›', 'tc-grubweg' ),
          'class'     => 'tcg-pagination',
      ] );
      ?>

    <?php else : ?>

      <div class="tcg-no-posts">
        <p><?php esc_html_e( 'In diesem Archiv liegen aktuell keine Beiträge.', 'tc-grubweg' ); ?></p>
      </div>

    <?php endif; ?>

  </main>

  <?php if ( $has_sidebar ) : ?>
    <aside class="sidebar" role="complementary" aria-label="<?php esc_attr_e( 'Seitenleiste', 'tc-grubweg' ); ?>">
      <?php dynamic_sidebar( 'page-sidebar' ); ?>
    </aside>
  <?php endif; ?>

</div>

<?php get_footer(); ?>
