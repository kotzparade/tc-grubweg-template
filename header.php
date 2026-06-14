<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo( 'charset' ); ?>" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header>
  <div class="nav-inner">

    <?php
    // Logo: custom logo via WP Customizer or fallback to theme asset
    $custom_logo_id = get_theme_mod( 'custom_logo' );
    if ( $custom_logo_id ) {
        $logo_img = wp_get_attachment_image( $custom_logo_id, 'full', false, [ 'alt' => esc_attr( get_bloginfo( 'name' ) ) ] );
    } else {
        $logo_img = '<img src="' . esc_url( get_theme_file_uri( 'assets/img/tc_grubweg_logo_v1.png' ) ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '" />';
    }
    ?>
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="nav-logo">
      <?php echo $logo_img; ?>
      <div class="nav-logo-text">
        <strong><?php bloginfo( 'name' ); ?></strong>
        <span>e.V. · Gegründet 1962</span>
      </div>
    </a>

    <button class="nav-burger" id="navBurger" aria-label="Menü öffnen">
      <span></span><span></span><span></span>
    </button>

    <?php
    wp_nav_menu( [
        'theme_location' => 'primary',
        'container'      => 'nav',
        'container_id'   => 'mainNav',
        'items_wrap'     => '%3$s',
        'walker'         => new TC_Nav_Walker(),
        'fallback_cb'    => false,
    ] );
    ?>

  </div>
</header>
