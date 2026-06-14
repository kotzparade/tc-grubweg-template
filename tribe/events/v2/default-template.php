<?php
/**
 * Theme override: Tribe V2 Archive Wrapper
 *
 * Hängt Tribe's Listen-/Monats-/Tagesansicht in den Theme-Hero + .page-layout.
 */

use Tribe\Events\Views\V2\Template_Bootstrap;

get_header();

$tcg_archive_title = function_exists( 'tribe_get_events_title' )
    ? tribe_get_events_title( false )
    : __( 'Veranstaltungen', 'tc-grubweg' );
?>

<!-- ── PAGE HERO ─────────────────────────────────────────── -->
<div class="page-hero">
  <div class="page-hero-inner">

    <nav class="page-breadcrumb" aria-label="Breadcrumb">
      <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Startseite', 'tc-grubweg' ); ?></a>
      <span class="breadcrumb-sep" aria-hidden="true">›</span>
      <span class="breadcrumb-current"><?php echo esc_html( $tcg_archive_title ); ?></span>
    </nav>

    <h1 class="page-hero-title"><?php echo esc_html( $tcg_archive_title ); ?></h1>

  </div>
</div>

<!-- ── INHALT ────────────────────────────────────────────── -->
<div class="page-layout" id="main-content">
  <main class="entry-content tribe-archive">
    <?php echo tribe( Template_Bootstrap::class )->get_view_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
  </main>
</div>

<?php get_footer(); ?>
