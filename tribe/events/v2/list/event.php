<?php
/**
 * Theme override: List View – Single Event Card
 *
 * Listen-Optik: großes Bild oben, Date-Box + Titel + Zeit, Beschreibung, Venue + Kosten.
 *
 * @var WP_Post $event Event-Objekt mit Tribe-Properties.
 */

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

$thumb       = get_the_post_thumbnail_url( $event->ID, 'large' );
$venue_name  = function_exists( 'tribe_get_venue' ) ? tribe_get_venue( $event->ID ) : '';
$cost        = function_exists( 'tribe_get_formatted_cost' ) ? tribe_get_formatted_cost( $event->ID ) : '';
$excerpt     = has_excerpt( $event->ID ) ? get_the_excerpt( $event->ID ) : wp_trim_words( wp_strip_all_tags( $event->post_content ), 28, '…' );

$cats = get_the_terms( $event->ID, 'tribe_events_cat' );
?>
<li class="archive-event-card<?php echo $event->featured ? ' is-featured' : ''; ?>">

    <a href="<?php echo esc_url( $event->permalink ); ?>" class="archive-event-image" aria-hidden="true" tabindex="-1">
        <?php if ( $thumb ) : ?>
            <img src="<?php echo esc_url( $thumb ); ?>" alt="" loading="lazy" />
        <?php else : ?>
            <span class="archive-event-image-placeholder">
                <svg width="56" height="56" viewBox="0 0 40 40" fill="none">
                    <circle cx="20" cy="20" r="16" stroke="#1a6b35" stroke-width="1.8"/>
                    <path d="M10 20c5-4 15-4 20 0" stroke="#1a6b35" stroke-width="1.8" stroke-linecap="round"/>
                    <path d="M20 10c-4 5-4 15 0 20" stroke="#a8cb1a" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
            </span>
        <?php endif; ?>
    </a>

    <div class="archive-event-body">

        <div class="archive-event-head">
            <time class="archive-event-date" datetime="<?php echo esc_attr( $event->dates->start->format( 'Y-m-d' ) ); ?>">
                <span class="archive-event-day"><?php echo esc_html( $day ); ?></span>
                <span class="archive-event-month"><?php echo esc_html( $month ); ?></span>
            </time>

            <div class="archive-event-headline">
                <h2 class="archive-event-title">
                    <a href="<?php echo esc_url( $event->permalink ); ?>"><?php echo esc_html( $event->title ); ?></a>
                </h2>
                <div class="archive-event-time">
                    <svg width="13" height="13" viewBox="0 0 13 13" fill="none" aria-hidden="true">
                        <circle cx="6.5" cy="6.5" r="5.5" stroke="currentColor" stroke-width="1.3"/>
                        <path d="M6.5 3.5v3l2 1.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
                    </svg>
                    <?php echo esc_html( $time_label ); ?>
                </div>
            </div>
        </div>

        <?php if ( $excerpt ) : ?>
            <p class="archive-event-excerpt"><?php echo esc_html( $excerpt ); ?></p>
        <?php endif; ?>

        <div class="archive-event-meta">
            <?php if ( $venue_name ) : ?>
                <span class="archive-event-meta-item">
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                        <path d="M7 1.5c-2.5 0-4.5 2-4.5 4.5 0 3.5 4.5 6.5 4.5 6.5s4.5-3 4.5-6.5c0-2.5-2-4.5-4.5-4.5z" stroke="currentColor" stroke-width="1.3"/>
                        <circle cx="7" cy="6" r="1.6" stroke="currentColor" stroke-width="1.3"/>
                    </svg>
                    <?php echo esc_html( $venue_name ); ?>
                </span>
            <?php endif; ?>

            <?php if ( $cost ) : ?>
                <span class="archive-event-meta-item">
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                        <circle cx="7" cy="7" r="5.5" stroke="currentColor" stroke-width="1.3"/>
                        <path d="M9 5.5c-.4-.8-1.2-1.3-2-1.3-1.3 0-2.3.9-2.3 2.3M9 9c-.4.5-1.2.8-2 .8-1.3 0-2.3-.6-2.3-1.6M3.8 6.5h4.2M3.8 8h4.2" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                    </svg>
                    <?php echo esc_html( $cost ); ?>
                </span>
            <?php endif; ?>

            <?php if ( ! empty( $cats ) && ! is_wp_error( $cats ) ) : ?>
                <span class="archive-event-tags">
                    <?php foreach ( $cats as $term ) : ?>
                        <span class="archive-event-tag"><?php echo esc_html( $term->name ); ?></span>
                    <?php endforeach; ?>
                </span>
            <?php endif; ?>
        </div>

    </div>
</li>
