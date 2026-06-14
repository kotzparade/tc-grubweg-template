<?php
/**
 * Theme override: Tribe V2 List View
 *
 * Schlanke Liste ohne Filter-/Suchleiste, ohne View-Switcher.
 *
 * @var array $events
 */
?>
<div class="archive-events">

    <?php if ( ! empty( $events ) ) : ?>

        <ul class="archive-events-list" aria-label="<?php echo esc_attr( sprintf( __( 'Liste der %s', 'tc-grubweg' ), tribe_get_event_label_plural() ) ); ?>">
            <?php $current_month_label = ''; ?>
            <?php foreach ( $events as $event ) : ?>
                <?php
                $this->setup_postdata( $event );

                // Monats-Trenner
                $event_month = wp_date( 'F Y', $event->dates->start->getTimestamp() );
                if ( $event_month !== $current_month_label ) :
                    $current_month_label = $event_month;
                ?>
                <li class="archive-events-month-sep"><span><?php echo esc_html( $event_month ); ?></span></li>
                <?php endif; ?>

                <?php $this->template( 'list/event', [ 'event' => $event ] ); ?>
            <?php endforeach; ?>
        </ul>

        <?php $this->template( 'list/nav' ); ?>

    <?php else : ?>

        <div class="archive-events-empty">
            <p><?php esc_html_e( 'Aktuell sind keine kommenden Veranstaltungen geplant. Schauen Sie bald wieder vorbei.', 'tc-grubweg' ); ?></p>
        </div>

    <?php endif; ?>

</div>
