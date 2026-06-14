<?php
/**
 * Theme override: Events List Widget – Single Event Row
 *
 * Datum-Badge links, Titel und Uhrzeit rechts.
 *
 * @var WP_Post $event Event-Objekt mit Tribe-Properties (siehe tribe_get_event()).
 */

$start_ts = $event->dates->start->getTimestamp();
$day      = wp_date( 'j', $start_ts );
$month    = wp_date( 'M', $start_ts );

if ( $event->multiday ) {
    $time_label = wp_strip_all_tags( $event->schedule_details->value() );
} elseif ( $event->all_day ) {
    $time_label = __( 'Ganztägig', 'tc-grubweg' );
} else {
    $time_label = wp_date( 'H:i', $start_ts ) . ' ' . __( 'Uhr', 'tc-grubweg' );
}
?>
<li class="sidebar-event">
    <time class="sidebar-event-badge" datetime="<?php echo esc_attr( $event->dates->start->format( 'Y-m-d' ) ); ?>">
        <span class="sidebar-event-day"><?php echo esc_html( $day ); ?></span>
        <span class="sidebar-event-month"><?php echo esc_html( $month ); ?></span>
    </time>
    <div class="sidebar-event-body">
        <h5 class="sidebar-event-title">
            <a href="<?php echo esc_url( $event->permalink ); ?>" rel="bookmark">
                <?php echo esc_html( $event->title ); ?>
            </a>
        </h5>
        <div class="sidebar-event-time"><?php echo esc_html( $time_label ); ?></div>
    </div>
</li>
