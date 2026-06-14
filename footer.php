<footer>
  <?php
  $sponsors = new WP_Query( [
      'post_type'      => 'sponsor',
      'posts_per_page' => -1,
      'orderby'        => [ 'menu_order' => 'ASC', 'title' => 'ASC' ],
      'no_found_rows'  => true,
  ] );
  if ( $sponsors->have_posts() ) :
  ?>
  <div class="sponsors-slider" aria-label="<?php esc_attr_e( 'Unsere Sponsoren', 'tc-grubweg' ); ?>">
    <div class="sponsors-inner">
      <h3 class="sponsors-title"><?php esc_html_e( 'Wir danken unseren Sponsoren', 'tc-grubweg' ); ?></h3>

      <div class="sponsors-track" data-sponsors-track tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Sponsoren-Logos', 'tc-grubweg' ); ?>">
        <?php while ( $sponsors->have_posts() ) : $sponsors->the_post();
            if ( ! has_post_thumbnail() ) { continue; }
            $url   = get_post_meta( get_the_ID(), '_tcg_sponsor_url', true );
            $name  = get_the_title();
            $logo  = get_the_post_thumbnail( get_the_ID(), 'medium', [
                'alt'     => esc_attr( $name ),
                'loading' => 'lazy',
            ] );
        ?>
          <div class="sponsor-item">
            <?php if ( $url ) : ?>
              <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( $name ); ?>">
                <?php echo $logo; ?>
              </a>
            <?php else : ?>
              <span aria-label="<?php echo esc_attr( $name ); ?>"><?php echo $logo; ?></span>
            <?php endif; ?>
          </div>
        <?php endwhile; wp_reset_postdata(); ?>
      </div>

      <div class="sponsors-dots" data-sponsors-dots aria-hidden="true"></div>
    </div>
  </div>
  <?php endif; ?>

  <div class="footer-inner">
    <div class="footer-grid">

      <!-- Marke / Logo -->
      <div class="footer-brand">
        <div class="footer-logo">
          <?php
          $custom_logo_id = get_theme_mod( 'custom_logo' );
          if ( $custom_logo_id ) {
              echo wp_get_attachment_image( $custom_logo_id, 'full', false, [ 'alt' => esc_attr( get_bloginfo( 'name' ) ) ] );
          } else {
              echo '<img src="' . esc_url( get_theme_file_uri( 'assets/img/tc_grubweg_logo_v1.png' ) ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '" />';
          }
          ?>
          <span class="footer-logo-name"><?php bloginfo( 'name' ); ?></span>
        </div>
        <p class="footer-desc">Ihr Tennisverein in Passau-Grubweg seit 1962. Sport, Gemeinschaft und Freude am Spiel — für Jung und Alt.</p>
      </div>

      <!-- Verein -->
      <div class="footer-col">
        <h4>Verein</h4>
        <a href="#">Geschichte</a>
        <a href="#">Vorstand</a>
        <a href="#">Mitglied werden</a>
        <a href="#">Kontakt</a>
      </div>

      <!-- Tennis -->
      <div class="footer-col">
        <h4>Tennis</h4>
        <a href="#">Mannschaften</a>
        <a href="#">Training</a>
        <a href="#">Jugend</a>
        <a href="#">Platzbuchung</a>
      </div>

      <!-- Kontakt -->
      <div class="footer-col">
        <h4>Kontakt</h4>
        <a href="#">Grubweg, Passau</a>
        <a href="mailto:info@tcg-passau.de">info@tcg-passau.de</a>
        <a href="<?php echo esc_url( get_privacy_policy_url() ); ?>">Datenschutz</a>
        <a href="#">Impressum</a>
      </div>

    </div>

    <div class="footer-bottom">
      <span>© <?php echo date( 'Y' ); ?> <?php bloginfo( 'name' ); ?>. Alle Rechte vorbehalten.</span>
      <span>Mit ❤ für den Tennissport</span>
    </div>
  </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
