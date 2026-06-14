<?php
/**
 * Template: Single Event (The Events Calendar)
 *
 * Überschreibt das Tribe-Default via WordPress-Template-Hierarchie.
 * Liegt im Theme-Root, damit kein Tribe-Wrapper Markup injiziert.
 */

get_header();

while ( have_posts() ) : the_post();

    $event_id = get_the_ID();
    $event    = function_exists( 'tribe_get_event' ) ? tribe_get_event( $event_id ) : null;

    $start_ts = $event ? $event->dates->start->getTimestamp() : 0;
    $end_ts   = ( $event && $event->dates->end ) ? $event->dates->end->getTimestamp() : $start_ts;

    if ( $event && $event->all_day ) {
        $time_line = __( 'Ganztägig', 'tc-grubweg' );
        $date_line = wp_date( 'l, j. F Y', $start_ts );
    } elseif ( $event && $event->multiday ) {
        $time_line = '';
        $date_line = wp_date( 'j. F Y', $start_ts ) . ' – ' . wp_date( 'j. F Y', $end_ts );
    } elseif ( $event ) {
        $date_line = wp_date( 'l, j. F Y', $start_ts );
        if ( wp_date( 'H:i', $start_ts ) === wp_date( 'H:i', $end_ts ) ) {
            $time_line = wp_date( 'H:i', $start_ts ) . ' ' . __( 'Uhr', 'tc-grubweg' );
        } else {
            $time_line = wp_date( 'H:i', $start_ts ) . ' – ' . wp_date( 'H:i', $end_ts ) . ' ' . __( 'Uhr', 'tc-grubweg' );
        }
    } else {
        $date_line = '';
        $time_line = '';
    }

    $venue_name    = function_exists( 'tribe_get_venue' )         ? tribe_get_venue( $event_id )         : '';
    $venue_id      = function_exists( 'tribe_get_venue_id' )      ? tribe_get_venue_id( $event_id )      : 0;
    $venue_address = function_exists( 'tribe_get_full_address' )  ? tribe_get_full_address( $event_id )  : '';
    $cost          = function_exists( 'tribe_get_formatted_cost' ) ? tribe_get_formatted_cost( $event_id ) : '';
    $organizer_ids = function_exists( 'tribe_get_organizer_ids' ) ? tribe_get_organizer_ids( $event_id ) : [];
    $website       = function_exists( 'tribe_get_event_website_url' ) ? tribe_get_event_website_url( $event_id ) : '';
    $archive_url   = function_exists( 'tribe_get_events_link' )   ? tribe_get_events_link()              : '';

    $cats = get_the_terms( $event_id, 'tribe_events_cat' );
?>

<!-- ── EVENT HERO ────────────────────────────────────────── -->
<div class="page-hero event-hero">
  <div class="page-hero-inner">

    <nav class="page-breadcrumb" aria-label="Breadcrumb">
      <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Startseite', 'tc-grubweg' ); ?></a>
      <span class="breadcrumb-sep" aria-hidden="true">›</span>
      <?php if ( $archive_url ) : ?>
        <a href="<?php echo esc_url( $archive_url ); ?>"><?php esc_html_e( 'Veranstaltungen', 'tc-grubweg' ); ?></a>
        <span class="breadcrumb-sep" aria-hidden="true">›</span>
      <?php endif; ?>
      <span class="breadcrumb-current"><?php the_title(); ?></span>
    </nav>

    <h1 class="page-hero-title"><?php the_title(); ?></h1>

    <?php if ( $date_line ) : ?>
      <div class="event-hero-meta">
        <span class="event-hero-meta-item">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
            <rect x="2.5" y="3.5" width="11" height="10" rx="1.5" stroke="currentColor" stroke-width="1.3"/>
            <path d="M5 2v3M11 2v3M2.5 7h11" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
          </svg>
          <?php echo esc_html( $date_line ); ?>
        </span>
        <?php if ( $time_line ) : ?>
        <span class="event-hero-meta-item">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
            <circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.3"/>
            <path d="M8 4.5v3.5l2.5 1.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
          </svg>
          <?php echo esc_html( $time_line ); ?>
        </span>
        <?php endif; ?>
        <?php if ( $venue_name ) : ?>
        <span class="event-hero-meta-item">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
            <path d="M8 1.5c-2.8 0-5 2.2-5 5 0 4 5 8 5 8s5-4 5-8c0-2.8-2.2-5-5-5z" stroke="currentColor" stroke-width="1.3"/>
            <circle cx="8" cy="6.5" r="1.8" stroke="currentColor" stroke-width="1.3"/>
          </svg>
          <?php echo esc_html( $venue_name ); ?>
        </span>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  </div>
</div>

<!-- ── EVENT INHALT ──────────────────────────────────────── -->
<div class="page-layout has-sidebar tribe-single" id="main-content">

  <main class="entry-content">

    <?php if ( has_post_thumbnail() ) : ?>
      <figure class="tribe-single-image">
        <?php the_post_thumbnail( 'large' ); ?>
      </figure>
    <?php endif; ?>

    <article id="event-<?php echo esc_attr( $event_id ); ?>" <?php post_class( 'tribe-single-content' ); ?>>
      <?php the_content(); ?>
    </article>

    <?php if ( ! empty( $cats ) && ! is_wp_error( $cats ) ) : ?>
      <div class="tribe-single-tags">
        <span class="tribe-single-tags-label"><?php esc_html_e( 'Kategorien:', 'tc-grubweg' ); ?></span>
        <?php foreach ( $cats as $term ) : ?>
          <a href="<?php echo esc_url( get_term_link( $term ) ); ?>" class="tribe-single-tag"><?php echo esc_html( $term->name ); ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ( $archive_url ) : ?>
      <p class="tribe-single-back">
        <a href="<?php echo esc_url( $archive_url ); ?>">
          <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true">
            <path d="M13 8H3M7 4L3 8l4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <?php esc_html_e( 'Zurück zur Übersicht', 'tc-grubweg' ); ?>
        </a>
      </p>
    <?php endif; ?>

  </main>

  <aside class="sidebar tribe-single-sidebar" role="complementary" aria-label="Event-Details">

    <div class="sidebar-widget">
      <h4><?php esc_html_e( 'Wann & Wo', 'tc-grubweg' ); ?></h4>
      <ul class="tribe-single-details">
        <?php if ( $date_line ) : ?>
          <li>
            <span class="tribe-single-detail-label"><?php esc_html_e( 'Datum', 'tc-grubweg' ); ?></span>
            <span class="tribe-single-detail-value"><?php echo esc_html( $date_line ); ?></span>
          </li>
        <?php endif; ?>
        <?php if ( $time_line ) : ?>
          <li>
            <span class="tribe-single-detail-label"><?php esc_html_e( 'Uhrzeit', 'tc-grubweg' ); ?></span>
            <span class="tribe-single-detail-value"><?php echo esc_html( $time_line ); ?></span>
          </li>
        <?php endif; ?>
        <?php if ( $cost ) : ?>
          <li>
            <span class="tribe-single-detail-label"><?php esc_html_e( 'Kosten', 'tc-grubweg' ); ?></span>
            <span class="tribe-single-detail-value"><?php echo esc_html( $cost ); ?></span>
          </li>
        <?php endif; ?>
        <?php if ( $venue_name ) : ?>
          <li>
            <span class="tribe-single-detail-label"><?php esc_html_e( 'Ort', 'tc-grubweg' ); ?></span>
            <span class="tribe-single-detail-value">
              <?php echo esc_html( $venue_name ); ?>
              <?php if ( $venue_address ) : ?>
                <small><?php echo esc_html( wp_strip_all_tags( $venue_address ) ); ?></small>
              <?php endif; ?>
            </span>
          </li>
        <?php endif; ?>
        <?php if ( ! empty( $organizer_ids ) ) : ?>
          <li>
            <span class="tribe-single-detail-label"><?php esc_html_e( 'Veranstalter', 'tc-grubweg' ); ?></span>
            <span class="tribe-single-detail-value">
              <?php
              $names = [];
              foreach ( $organizer_ids as $oid ) {
                  $names[] = esc_html( get_the_title( $oid ) );
              }
              echo implode( ', ', $names ); // phpcs:ignore WordPress.Security.EscapeOutput
              ?>
            </span>
          </li>
        <?php endif; ?>
      </ul>

      <?php if ( $website ) : ?>
        <a href="<?php echo esc_url( $website ); ?>" class="tribe-single-website" target="_blank" rel="noopener">
          <?php esc_html_e( 'Zur Veranstaltungs-Website', 'tc-grubweg' ); ?>
          <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
            <path d="M4 2h6v6M10 2L4 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </a>
      <?php endif; ?>
    </div>

  </aside>

</div>

<?php
endwhile;

get_footer();
