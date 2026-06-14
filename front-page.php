<?php get_header(); ?>

<!-- ── HERO ──────────────────────────────────────────────────────────────── -->
<section class="hero">

  <?php if ( has_post_thumbnail() ) : ?>
    <?php the_post_thumbnail( 'full', [ 'alt' => esc_attr__( 'Hero-Bild', 'tc-grubweg' ) ] ); ?>
  <?php else : ?>
    <img src="<?php echo esc_url( get_theme_file_uri( 'assets/img/2021_Pfingsten-Tenniscamp-scaled.jpg' ) ); ?>"
         alt="Pfingsten Tenniscamp DJK-TC Passau Grubweg" />
  <?php endif; ?>

  <div class="hero-overlay"></div>

  <div class="hero-content">
    <div class="hero-badge">
      <svg width="10" height="10" viewBox="0 0 10 10" fill="currentColor"><circle cx="5" cy="5" r="5"/></svg>
      Saison <?php echo date( 'Y' ); ?> hat begonnen
    </div>
    <h1 class="hero-title">Tennis in Passau.<br>Für alle. Mit Herz.</h1>
    <p class="hero-sub">Willkommen beim DJK-TC Passau Grubweg — Ihr Tennisverein mit langer Tradition und aktiver Gemeinschaft.</p>
    <div class="hero-btns">
      <a href="https://tc-grubweg.ebusy.de/" class="btn-primary">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><rect x="2" y="3" width="12" height="11" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M5 2v2M11 2v2M2 7h12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        Platz buchen
      </a>
      <a href="/verein/mitgliedschaft/" class="btn-ghost">Mitglied werden</a>
    </div>
  </div>

  <div class="hero-scroll">
    <div class="hero-scroll-line"></div>
    Scroll
  </div>

</section>

<!-- ── NEWS ──────────────────────────────────────────────────────────────── -->
<section class="news-section" id="news">
  <div class="section-inner">

    <div class="section-header">
      <div>
        <span class="section-label">Aktuelles</span>
        <h2 class="section-title">Neuigkeiten aus dem Verein</h2>
      </div>
      <?php
      $tcg_posts_page = get_option( 'page_for_posts' );
      $tcg_news_url   = $tcg_posts_page ? get_permalink( $tcg_posts_page ) : home_url( '/?post_type=post' );
      ?>
      <a href="<?php echo esc_url( $tcg_news_url ); ?>" class="section-link">
        Alle Beiträge
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </a>
    </div>

    <div class="news-grid">
      <?php
      $news_query = new WP_Query( [
          'post_type'      => 'post',
          'posts_per_page' => 3,
          'post_status'    => 'publish',
      ] );

      if ( $news_query->have_posts() ) :
          while ( $news_query->have_posts() ) : $news_query->the_post();
              $categories = get_the_category();
              $cat_name   = ! empty( $categories ) ? esc_html( $categories[0]->name ) : 'Vereinsnews';
      ?>
      <article class="news-card">

        <?php if ( has_post_thumbnail() ) : ?>
          <?php the_post_thumbnail( 'medium_large', [ 'class' => 'news-card-img', 'alt' => esc_attr( get_the_title() ) ] ); ?>
        <?php else : ?>
          <div class="news-card-img-placeholder">
            <svg width="64" height="64" viewBox="0 0 64 64" fill="none">
              <circle cx="32" cy="32" r="28" stroke="#1a6b35" stroke-width="2.5"/>
              <path d="M18 32c7-6 21-6 28 0" stroke="#1a6b35" stroke-width="2.5" stroke-linecap="round"/>
              <path d="M32 18c-6 7-6 21 0 28" stroke="#a8cb1a" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
          </div>
        <?php endif; ?>

        <div class="news-card-body">
          <div class="news-meta">
            <span class="news-date"><?php echo get_the_date( 'j. F Y' ); ?></span>
            <span class="news-category"><?php echo $cat_name; ?></span>
          </div>
          <h3 class="news-card-title">
            <a href="<?php the_permalink(); ?>" style="color:inherit; text-decoration:none;"><?php the_title(); ?></a>
          </h3>
          <p class="news-card-teaser"><?php echo wp_strip_all_tags( get_the_excerpt() ); ?></p>
          <a href="<?php the_permalink(); ?>" class="btn-readmore">
            Weiterlesen
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2.5 7h9M8 3.5l3.5 3.5L8 10.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </a>
        </div>

      </article>
      <?php
          endwhile;
          wp_reset_postdata();
      else :
      ?>
        <!-- Placeholder when no posts exist yet -->
        <?php for ( $i = 0; $i < 3; $i++ ) : ?>
        <article class="news-card">
          <div class="news-card-img-placeholder">
            <svg width="64" height="64" viewBox="0 0 64 64" fill="none">
              <circle cx="32" cy="32" r="28" stroke="#1a6b35" stroke-width="2.5"/>
              <path d="M18 32c7-6 21-6 28 0" stroke="#1a6b35" stroke-width="2.5" stroke-linecap="round"/>
              <path d="M32 18c-6 7-6 21 0 28" stroke="#a8cb1a" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
          </div>
          <div class="news-card-body">
            <div class="news-meta">
              <span class="news-date"><?php echo date( 'j. F Y' ); ?></span>
              <span class="news-category">Vereinsnews</span>
            </div>
            <h3 class="news-card-title">Beiträge folgen in Kürze</h3>
            <p class="news-card-teaser">Hier erscheinen bald aktuelle Neuigkeiten aus dem Vereinsleben des DJK-TC Passau Grubweg.</p>
          </div>
        </article>
        <?php endfor; ?>
      <?php endif; ?>
    </div>

  </div>
</section>

<!-- ── EVENTS ────────────────────────────────────────────────────────────── -->
<?php
$tcg_events = function_exists( 'tribe_get_events' )
    ? tribe_get_events( [
        'posts_per_page' => 4,
        'start_date'     => 'now',
        'eventDisplay'   => 'list',
    ] )
    : [];

// Date-Box Farbvarianten (zyklisch) — Reihenfolge wie im Original-Design.
$tcg_date_box_styles = [
    [ 'box' => '',                                'month' => '', 'day' => '' ],
    [ 'box' => 'background:var(--green-mid);',    'month' => '', 'day' => '' ],
    [ 'box' => 'background:#a8cb1a;',             'month' => 'color:rgba(0,0,0,.5)', 'day' => 'color:#1a6b35' ],
    [ 'box' => 'background:var(--green-mid);',    'month' => '', 'day' => '' ],
];

$tcg_events_archive = function_exists( 'tribe_get_events_link' ) ? tribe_get_events_link() : '#';

$tcg_clock_svg       = '<svg width="13" height="13" viewBox="0 0 13 13" fill="none"><circle cx="6.5" cy="6.5" r="5.5" stroke="currentColor" stroke-width="1.3"/><path d="M6.5 3.5v3l2 1.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>';
$tcg_placeholder_svg = '<svg width="40" height="40" viewBox="0 0 40 40" fill="none"><circle cx="20" cy="20" r="16" stroke="#1a6b35" stroke-width="1.8"/><path d="M10 20c5-4 15-4 20 0" stroke="#1a6b35" stroke-width="1.8" stroke-linecap="round"/><path d="M20 10c-4 5-4 15 0 20" stroke="#a8cb1a" stroke-width="1.8" stroke-linecap="round"/></svg>';
?>
<section class="events-section" id="events">
  <div class="section-inner">

    <div class="section-header">
      <div>
        <span class="section-label">Veranstaltungen</span>
        <h2 class="section-title">Anstehende Events</h2>
      </div>
      <a href="<?php echo esc_url( $tcg_events_archive ); ?>" class="section-link">
        Alle Events
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </a>
    </div>

    <div class="events-list">

    <?php if ( ! empty( $tcg_events ) ) : ?>
      <?php foreach ( $tcg_events as $i => $tcg_event_post ) :
          $event = tribe_get_event( $tcg_event_post->ID );
          if ( ! $event ) continue;

          $start_ts = $event->dates->start->getTimestamp();
          $end_ts   = $event->dates->end ? $event->dates->end->getTimestamp() : $start_ts;

          $month = wp_date( 'M', $start_ts );
          $day   = wp_date( 'j', $start_ts );

          if ( $event->all_day ) {
              $time_label = __( 'Ganztägig', 'tc-grubweg' );
          } elseif ( $event->multiday ) {
              $time_label = wp_strip_all_tags( $event->schedule_details->value() );
          } elseif ( wp_date( 'H:i', $start_ts ) === wp_date( 'H:i', $end_ts ) ) {
              $time_label = wp_date( 'H:i', $start_ts ) . ' ' . __( 'Uhr', 'tc-grubweg' );
          } else {
              $time_label = wp_date( 'H:i', $start_ts ) . ' – ' . wp_date( 'H:i', $end_ts ) . ' ' . __( 'Uhr', 'tc-grubweg' );
          }

          $styles = $tcg_date_box_styles[ $i % count( $tcg_date_box_styles ) ];

          $cats = get_the_terms( $event->ID, 'tribe_events_cat' );
          if ( empty( $cats ) || is_wp_error( $cats ) ) {
              $cats = get_the_terms( $event->ID, 'post_tag' );
          }

          $thumb = get_the_post_thumbnail_url( $event->ID, 'medium' );
      ?>
      <div class="event-card">
        <div class="event-date-box"<?php echo $styles['box'] ? ' style="' . esc_attr( $styles['box'] ) . '"' : ''; ?>>
          <span class="event-month"<?php echo $styles['month'] ? ' style="' . esc_attr( $styles['month'] ) . '"' : ''; ?>><?php echo esc_html( $month ); ?></span>
          <span class="event-day"<?php echo $styles['day'] ? ' style="' . esc_attr( $styles['day'] ) . '"' : ''; ?>><?php echo esc_html( $day ); ?></span>
        </div>
        <div class="event-body">
          <div class="event-time">
            <?php echo $tcg_clock_svg; // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <?php echo esc_html( $time_label ); ?>
          </div>
          <div class="event-title"><a href="<?php echo esc_url( $event->permalink ); ?>"><?php echo esc_html( $event->title ); ?></a></div>
          <?php if ( ! empty( $cats ) && ! is_wp_error( $cats ) ) : ?>
          <div class="event-tags">
            <?php foreach ( $cats as $term ) : ?>
              <span class="event-tag"><?php echo esc_html( $term->name ); ?></span>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php if ( $thumb ) : ?>
        <img class="event-img" src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $event->title ); ?>" />
        <?php else : ?>
        <div class="event-img-placeholder">
          <?php echo $tcg_placeholder_svg; // phpcs:ignore WordPress.Security.EscapeOutput ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    <?php else : ?>
      <p class="events-empty"><?php esc_html_e( 'Aktuell sind keine kommenden Veranstaltungen geplant.', 'tc-grubweg' ); ?></p>
    <?php endif; ?>

    </div>
  </div>
</section>

<!-- ── MANNSCHAFTEN ───────────────────────────────────────────────────────── -->
<section class="teams-section" id="mannschaften">
  <div class="section-inner">

    <div class="section-header">
      <div>
        <span class="section-label">Rundenspielbetrieb</span>
        <h2 class="section-title">Unsere Mannschaften</h2>
      </div>
      <a href="/tennis/mannschaftssport/" target="_self" rel="noopener" class="section-link">
        Alle Ergebnisse
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </a>
    </div>

    <?php
    // SVG helpers
    $star_svg = '<svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M7 1.5l1.5 3 3.5.5-2.5 2.5.5 3.5L7 9.5l-3 1.5.5-3.5L2 5l3.5-.5z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/></svg>';
    $ext_svg  = '<svg width="13" height="13" viewBox="0 0 13 13" fill="none"><path d="M5 2.5h-2a1 1 0 00-1 1v7a1 1 0 001 1h7a1 1 0 001-1v-2M8 2.5h2.5v2.5M5.5 7.5l5-5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    $teams = [
        [ 'badge' => 'H',   'name' => 'Herren 1',    'liga' => 'Landesliga 2',    'color' => '' ],
        [ 'badge' => 'H2',  'name' => 'Herren 2',    'liga' => 'Südliga 3',     'color' => '' ],
        [ 'badge' => 'D',   'name' => 'Damen',       'liga' => 'Südliga 2',     'color' => 'background:var(--green-lime);color:var(--green-dark)' ],
        [ 'badge' => 'H30',  'name' => 'Herren 30',     'liga' => 'Südliga 3',     'color' => 'background:#237a40' ],
        [ 'badge' => 'H40', 'name' => 'Herren 40', 'liga' => 'Südliga 1',   'color' => 'background:#237a40' ],
        [ 'badge' => 'J18', 'name' => 'Junioren 18', 'liga' => 'Südliga 1','color' => 'background:#237a40' ],
        [ 'badge' => 'uvm.', 'name' => 'Alle Teams', 'liga' => 'Alle Informationen',   'color' => 'background:#237a40' ],
    ];
    ?>

    <div class="teams-grid">

      <?php foreach ( $teams as $team ) : ?>
      <div class="team-card">
        <div class="team-badge" <?php echo $team['color'] ? 'style="' . esc_attr( $team['color'] ) . '"' : ''; ?>>
          <?php echo esc_html( $team['badge'] ); ?>
        </div>
        <div class="team-name"><?php echo esc_html( $team['name'] ); ?></div>
        <div class="team-liga"><?php echo $star_svg; ?> <?php echo esc_html( $team['liga'] ); ?></div>
        <a href="/tennis/mannschaftssport/" target="_blank" rel="noopener" class="team-link">
          Zum Team <?php echo $ext_svg; ?>
        </a>
      </div>
      <?php endforeach; ?>

      <!-- Weitere Infos Karte -->
      <div class="team-card" style="background:var(--green-pale);border-color:var(--green-lime);">
        <div style="font-size:28px;margin-bottom:4px;">🎾</div>
        <div class="team-name" style="font-size:15px;color:var(--green-dark);">Weitere Infos?</div>
        <div class="team-liga">Alle Ergebnisse und Tabellen auf der BTV-Seite</div>
        <a href="https://www.btv.de/de/mein-verein/vereinsseite/djk-tc-passau-grubweg.html" target="_blank" rel="noopener"
           class="team-link" style="background:var(--green-dark);color:white;border-color:var(--green-dark);">
          btv.de
          <svg width="13" height="13" viewBox="0 0 13 13" fill="none"><path d="M5 2.5h-2a1 1 0 00-1 1v7a1 1 0 001 1h7a1 1 0 001-1v-2M8 2.5h2.5v2.5M5.5 7.5l5-5" stroke="white" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
      </div>

    </div>
  </div>
</section>

<?php get_footer(); ?>
