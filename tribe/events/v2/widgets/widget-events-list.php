<?php
/**
 * Theme override: The Events Calendar – Events List Widget
 *
 * Erzeugt ein schlankes Sidebar-Widget statt der V2-View-Wrapper.
 *
 * @var array<\WP_Post> $events
 * @var string          $widget_title
 * @var bool            $hide_if_no_upcoming_events
 */

if ( empty( $events ) && $hide_if_no_upcoming_events ) {
    return;
}
?>
<div class="sidebar-events-widget">

    <?php if ( ! empty( $widget_title ) ) : ?>
        <h4 class="sidebar-events-widget-title"><?php echo esc_html( $widget_title ); ?></h4>
    <?php endif; ?>

    <?php if ( ! empty( $events ) ) : ?>
        <ul class="sidebar-events">
            <?php foreach ( $events as $event ) : ?>
                <?php $this->template( 'widgets/widget-events-list/event', [ 'event' => $event ] ); ?>
            <?php endforeach; ?>
        </ul>
    <?php else : ?>
        <p class="sidebar-events-empty"><?php esc_html_e( 'Aktuell keine kommenden Veranstaltungen.', 'tc-grubweg' ); ?></p>
    <?php endif; ?>

</div>
