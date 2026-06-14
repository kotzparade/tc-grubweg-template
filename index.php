<?php get_header(); ?>

<main style="max-width:1280px; margin:80px auto; padding:0 32px;">
  <?php if ( have_posts() ) : ?>
    <?php while ( have_posts() ) : the_post(); ?>
      <article style="margin-bottom:40px; padding-bottom:40px; border-bottom:1px solid var(--border);">
        <h2 style="font-size:22px; font-weight:700; margin-bottom:8px;">
          <a href="<?php the_permalink(); ?>" style="color:var(--text); text-decoration:none;">
            <?php the_title(); ?>
          </a>
        </h2>
        <p style="font-size:13px; color:var(--text-muted); margin-bottom:12px;">
          <?php echo get_the_date( 'j. F Y' ); ?>
        </p>
        <?php the_excerpt(); ?>
      </article>
    <?php endwhile; ?>
  <?php else : ?>
    <p>Keine Beiträge gefunden.</p>
  <?php endif; ?>
</main>

<?php get_footer(); ?>
