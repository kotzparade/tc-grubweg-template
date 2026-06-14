<?php
/**
 * Template: Single Post (Blogartikel)
 * Zeigt einen einzelnen Beitrag mit Bild-Hero, Artikel-Inhalt und Sidebar.
 */

get_header();

// Lesezeit berechnen
$content    = get_post_field( 'post_content', get_the_ID() );
$word_count = str_word_count( strip_tags( $content ) );
$read_min   = max( 1, (int) ceil( $word_count / 200 ) );

// Blog-Archiv-URL
$blog_page_id = (int) get_option( 'page_for_posts' );
$blog_url     = $blog_page_id ? get_permalink( $blog_page_id ) : home_url( '/' );
$blog_title   = $blog_page_id ? get_the_title( $blog_page_id ) : __( 'Aktuelles', 'tc-grubweg' );

// Erste Kategorie & IDs für "Weitere Beiträge"
$cats     = get_the_category();
$cat_name = $cats ? esc_html( $cats[0]->name ) : '';
$cat_ids  = $cats ? array_column( $cats, 'term_id' ) : [];

// Autoreninitialen (max. 2 Buchstaben)
$author_name     = get_the_author();
$author_initials = mb_strtoupper( mb_substr( $author_name, 0, 2 ) );
$author_bio      = get_the_author_meta( 'description' );

// Kommentaranzahl
$comment_count = get_comments_number();
if ( $comment_count == 0 ) {
    $comment_label = 'Keine Kommentare';
} elseif ( $comment_count == 1 ) {
    $comment_label = '1 Kommentar';
} else {
    $comment_label = $comment_count . ' Kommentare';
}
?>

<!-- ── ARTICLE HERO ─────────────────────────────────────────── -->
<div class="article-hero">

  <?php if ( has_post_thumbnail() ) : ?>
    <img src="<?php echo esc_url( get_the_post_thumbnail_url( null, 'full' ) ); ?>"
         alt="<?php the_title_attribute(); ?>" />
  <?php else : ?>
    <div class="article-hero-placeholder"></div>
  <?php endif; ?>

  <div class="article-hero-overlay"></div>

  <div class="article-hero-content">
    <div class="article-hero-inner">
      <div>

        <nav class="article-breadcrumb" aria-label="Breadcrumb">
          <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Startseite</a>
          <span aria-hidden="true">›</span>
          <a href="<?php echo esc_url( $blog_url ); ?>"><?php echo esc_html( $blog_title ); ?></a>
          <span aria-hidden="true">›</span>
          <span><?php the_title(); ?></span>
        </nav>

        <?php if ( $cat_name ) : ?>
          <span class="article-category"><?php echo $cat_name; ?></span>
        <?php endif; ?>

        <h1 class="article-title"><?php the_title(); ?></h1>

        <div class="article-meta">
          <span class="article-meta-item">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
              <rect x="1.5" y="2.5" width="11" height="10" rx="1.5" stroke="white" stroke-width="1.3"/>
              <path d="M4.5 1.5v2M9.5 1.5v2M1.5 6h11" stroke="white" stroke-width="1.3" stroke-linecap="round"/>
            </svg>
            <?php echo get_the_date(); ?>
          </span>
          <span class="article-meta-item">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
              <circle cx="7" cy="5" r="2.5" stroke="white" stroke-width="1.3"/>
              <path d="M2 12.5c0-2.8 2.2-5 5-5s5 2.2 5 5" stroke="white" stroke-width="1.3" stroke-linecap="round"/>
            </svg>
            <?php echo esc_html( $author_name ); ?>
          </span>
          <span class="article-meta-item">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
              <circle cx="7" cy="7" r="5.5" stroke="white" stroke-width="1.3"/>
              <path d="M7 4v3.5l2 1.5" stroke="white" stroke-width="1.3" stroke-linecap="round"/>
            </svg>
            <?php echo $read_min; ?> Min. Lesezeit
          </span>
          <span class="article-meta-item">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
              <path d="M2 2.5h10a.5.5 0 01.5.5v7a.5.5 0 01-.5.5H8l-2 1.5-2-1.5H2a.5.5 0 01-.5-.5V3a.5.5 0 01.5-.5z" stroke="white" stroke-width="1.3" stroke-linejoin="round"/>
            </svg>
            <?php echo esc_html( $comment_label ); ?>
          </span>
        </div>

      </div>
    </div>
  </div>
</div>


<?php $has_sidebar = is_active_sidebar( 'post-sidebar' ); ?>

<!-- ── SEITEN-LAYOUT ─────────────────────────────────────────── -->
<div class="page-layout page-layout--single<?php echo $has_sidebar ? ' has-sidebar' : ''; ?>" id="main-content">

  <!-- ── ARTIKEL-INHALT ──────────────────────────────────────── -->
  <main class="article-content">

    <a href="<?php echo esc_url( $blog_url ); ?>" class="back-link">
      <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
        <path d="M10 3L5 8l5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      Zurück zur Übersicht
    </a>

    <?php while ( have_posts() ) : the_post(); ?>

      <?php if ( has_excerpt() ) : ?>
        <p class="lead"><?php echo wp_kses_post( get_the_excerpt() ); ?></p>
      <?php endif; ?>

      <div class="entry-content">
        <?php the_content(); ?>
      </div>

      <!-- ── ARTIKEL-FOOTER ───────────────────────────────────── -->
      <div class="article-footer">
        <?php $tags = get_the_tags(); ?>
        <?php if ( $tags ) : ?>
          <div class="article-tags">
            <span class="tags-label">Tags:</span>
            <?php foreach ( $tags as $tag ) : ?>
              <a href="<?php echo esc_url( get_tag_link( $tag->term_id ) ); ?>"
                 class="article-tag"><?php echo esc_html( $tag->name ); ?></a>
            <?php endforeach; ?>
          </div>
        <?php else : ?>
          <div></div>
        <?php endif; ?>
        <div class="share-btns">
          <a href="<?php echo esc_url( 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( get_permalink() ) ); ?>"
             class="share-btn" target="_blank" rel="noopener noreferrer">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
              <circle cx="11" cy="3" r="1.5" stroke="currentColor" stroke-width="1.3"/>
              <circle cx="11" cy="11" r="1.5" stroke="currentColor" stroke-width="1.3"/>
              <circle cx="3" cy="7" r="1.5" stroke="currentColor" stroke-width="1.3"/>
              <path d="M4.5 6.2l5-2.5M4.5 7.8l5 2.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
            </svg>
            Teilen
          </a>
          <a href="javascript:window.print()" class="share-btn">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
              <path d="M2 12V5.5l5-3.5 5 3.5V12H9V8.5H5V12H2z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/>
            </svg>
            Drucken
          </a>
        </div>
      </div>

      <!-- ── AUTOR-BOX ─────────────────────────────────────────── -->
      <div class="author-box">
        <div class="author-avatar"><?php echo esc_html( $author_initials ); ?></div>
        <div class="author-info">
          <div class="author-label">Autor</div>
          <div class="author-name"><?php echo esc_html( $author_name ); ?></div>
          <?php if ( $author_bio ) : ?>
            <div class="author-bio"><?php echo esc_html( $author_bio ); ?></div>
          <?php endif; ?>
        </div>
      </div>

      <!-- ── WEITERE BEITRÄGE ──────────────────────────────────── -->
      <?php
      $related_query = new WP_Query( [
          'category__in'   => $cat_ids,
          'post__not_in'   => [ get_the_ID() ],
          'posts_per_page' => 2,
          'orderby'        => 'date',
          'order'          => 'DESC',
          'no_found_rows'  => true,
      ] );
      ?>

      <?php if ( $related_query->have_posts() ) : ?>
        <div class="related-posts">
          <h3>Weitere Beiträge</h3>
          <div class="related-grid">
            <?php while ( $related_query->have_posts() ) : $related_query->the_post(); ?>
              <a href="<?php the_permalink(); ?>" class="related-card">
                <?php if ( has_post_thumbnail() ) : ?>
                  <img class="related-card-img"
                       src="<?php echo esc_url( get_the_post_thumbnail_url( null, 'medium_large' ) ); ?>"
                       alt="<?php the_title_attribute(); ?>" />
                <?php else : ?>
                  <div class="related-card-img placeholder"></div>
                <?php endif; ?>
                <div class="related-card-body">
                  <div class="related-card-date"><?php echo get_the_date(); ?></div>
                  <div class="related-card-title"><?php the_title(); ?></div>
                </div>
              </a>
            <?php endwhile; wp_reset_postdata(); ?>
          </div>
        </div>
      <?php endif; ?>

    <?php endwhile; ?>

  </main>

  <!-- ── SIDEBAR ───────────────────────────────────────────────── -->
  <?php if ( is_active_sidebar( 'post-sidebar' ) ) : ?>
    <aside class="sidebar" role="complementary" aria-label="Seitenleiste">
      <?php dynamic_sidebar( 'post-sidebar' ); ?>
    </aside>
  <?php endif; ?>

</div>

<?php get_footer(); ?>
