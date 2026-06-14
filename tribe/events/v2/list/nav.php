<?php
/**
 * Theme override: List View Navigation (Pagination)
 *
 * @var string $prev_url
 * @var string $next_url
 */

if ( empty( $prev_url ) && empty( $next_url ) ) {
    return;
}
?>
<nav class="archive-events-nav" aria-label="<?php esc_attr_e( 'Veranstaltungs-Navigation', 'tc-grubweg' ); ?>">

    <?php if ( ! empty( $prev_url ) ) : ?>
        <a href="<?php echo esc_url( $prev_url ); ?>" class="archive-events-nav-link archive-events-nav-prev">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <path d="M13 8H3M7 4L3 8l4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span><?php esc_html_e( 'Vorherige Events', 'tc-grubweg' ); ?></span>
        </a>
    <?php else : ?>
        <span></span>
    <?php endif; ?>

    <?php if ( ! empty( $next_url ) ) : ?>
        <a href="<?php echo esc_url( $next_url ); ?>" class="archive-events-nav-link archive-events-nav-next">
            <span><?php esc_html_e( 'Weitere Events', 'tc-grubweg' ); ?></span>
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </a>
    <?php endif; ?>

</nav>
