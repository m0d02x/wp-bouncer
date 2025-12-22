<?php
/** @var array $logs */
/** @var int $retention */
/** @var string $table_name */

$total_logs   = count( $logs );
$success_logs = count( array_filter( $logs, fn( $l ) => 'success' === $l['status'] ) );
$failed_logs  = $total_logs - $success_logs;
?>
<div class="bouncer-admin-wrap">
    <!-- Page Header -->
    <div class="bouncer-page-header">
        <h1 class="bouncer-page-title">
            <span class="dashicons dashicons-list-view"></span>
            <?php esc_html_e( 'Message Logs', 'wc-bouncer-whatsapp' ); ?>
        </h1>
        <p class="bouncer-page-description">
            <?php echo esc_html( sprintf( __( 'Displaying the last %d entries. Auto-deleted after %d days.', 'wc-bouncer-whatsapp' ), 100, $retention ) ); ?>
        </p>
    </div>

    <?php if ( isset( $_GET['cleared'] ) ) : ?>
        <div class="bouncer-alert bouncer-alert-success">
            <span class="dashicons dashicons-yes-alt"></span>
            <div class="bouncer-alert-content"><?php esc_html_e( 'All logs have been cleared.', 'wc-bouncer-whatsapp' ); ?></div>
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="bouncer-stats-grid">
        <div class="bouncer-stat-card">
            <div class="bouncer-stat-label">
                <span class="dashicons dashicons-email-alt"></span>
                <?php esc_html_e( 'Total Messages', 'wc-bouncer-whatsapp' ); ?>
            </div>
            <div class="bouncer-stat-value"><?php echo esc_html( $total_logs ); ?></div>
        </div>
        <div class="bouncer-stat-card">
            <div class="bouncer-stat-label">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php esc_html_e( 'Delivered', 'wc-bouncer-whatsapp' ); ?>
            </div>
            <div class="bouncer-stat-value" style="color: #16a34a;"><?php echo esc_html( $success_logs ); ?></div>
        </div>
        <div class="bouncer-stat-card">
            <div class="bouncer-stat-label">
                <span class="dashicons dashicons-dismiss"></span>
                <?php esc_html_e( 'Failed', 'wc-bouncer-whatsapp' ); ?>
            </div>
            <div class="bouncer-stat-value" style="color: #dc2626;"><?php echo esc_html( $failed_logs ); ?></div>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="bouncer-card">
        <div class="bouncer-card-header">
            <h3 class="bouncer-card-title">
                <span class="dashicons dashicons-database"></span>
                <?php esc_html_e( 'Recent Activity', 'wc-bouncer-whatsapp' ); ?>
            </h3>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 0;">
                <?php wp_nonce_field( 'wc_bouncer_clear_logs' ); ?>
                <input type="hidden" name="action" value="wc_bouncer_clear_logs" />
                <button type="submit" class="bouncer-btn bouncer-btn-ghost bouncer-btn-sm" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to clear all logs?', 'wc-bouncer-whatsapp' ) ); ?>');">
                    <span class="dashicons dashicons-trash"></span>
                    <?php esc_html_e( 'Clear All', 'wc-bouncer-whatsapp' ); ?>
                </button>
            </form>
        </div>

        <?php if ( empty( $logs ) ) : ?>
            <div class="bouncer-card-body">
                <div class="bouncer-empty-state">
                    <span class="dashicons dashicons-email-alt"></span>
                    <p><?php esc_html_e( 'No messages have been sent yet.', 'wc-bouncer-whatsapp' ); ?></p>
                </div>
            </div>
        <?php else : ?>
            <table class="bouncer-table">
                <thead>
                    <tr>
                        <th style="width: 140px;"><?php esc_html_e( 'Date', 'wc-bouncer-whatsapp' ); ?></th>
                        <th style="width: 80px;"><?php esc_html_e( 'Order', 'wc-bouncer-whatsapp' ); ?></th>
                        <th style="width: 130px;"><?php esc_html_e( 'Phone', 'wc-bouncer-whatsapp' ); ?></th>
                        <th style="width: 90px;"><?php esc_html_e( 'Status', 'wc-bouncer-whatsapp' ); ?></th>
                        <th><?php esc_html_e( 'Message', 'wc-bouncer-whatsapp' ); ?></th>
                        <th style="width: 100px;"><?php esc_html_e( 'Response', 'wc-bouncer-whatsapp' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $logs as $log ) :
                        $gmt       = $log['created_at'];
                        $timestamp = get_date_from_gmt( $gmt, 'M j, H:i' );
                        $is_success = 'success' === $log['status'];
                    ?>
                        <tr>
                            <td>
                                <span style="color: #6b7280; font-size: 12px;"><?php echo esc_html( $timestamp ); ?></span>
                            </td>
                            <td>
                                <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $log['order_id'] . '&action=edit' ) ); ?>" class="bouncer-order-link">
                                    #<?php echo esc_html( $log['order_id'] ); ?>
                                </a>
                            </td>
                            <td><code><?php echo esc_html( $log['phone'] ); ?></code></td>
                            <td>
                                <?php if ( $is_success ) : ?>
                                    <span class="bouncer-log-status success">
                                        <span class="dashicons dashicons-yes"></span>
                                        <?php esc_html_e( 'Sent', 'wc-bouncer-whatsapp' ); ?>
                                    </span>
                                <?php else : ?>
                                    <span class="bouncer-log-status failed">
                                        <span class="dashicons dashicons-no"></span>
                                        <?php echo esc_html( $log['response_code'] ); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="bouncer-log-message"><?php echo esc_html( mb_strimwidth( $log['message'], 0, 60, '...' ) ); ?></div>
                            </td>
                            <td>
                                <?php if ( ! empty( $log['response_body'] ) ) : ?>
                                    <button type="button" class="bouncer-btn bouncer-btn-ghost bouncer-btn-sm bouncer-view-response" data-response="<?php echo esc_attr( $log['response_body'] ); ?>">
                                        <span class="dashicons dashicons-visibility"></span>
                                    </button>
                                <?php else : ?>
                                    <span style="color: #9ca3af;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <p class="bouncer-form-description" style="margin-top: 12px; text-align: center;">
        <?php echo esc_html( sprintf( __( 'Storage: %s', 'wc-bouncer-whatsapp' ), $table_name ) ); ?>
    </p>
</div>

<!-- Response Modal -->
<div id="bouncer-response-modal" class="bouncer-modal-overlay" style="display: none;">
    <div class="bouncer-modal">
        <div class="bouncer-modal-header">
            <h3><?php esc_html_e( 'API Response', 'wc-bouncer-whatsapp' ); ?></h3>
            <button type="button" class="bouncer-modal-close">&times;</button>
        </div>
        <div class="bouncer-modal-body">
            <pre id="bouncer-response-content" class="bouncer-response-box"></pre>
        </div>
    </div>
</div>

<script>
(function($) {
    // View response modal
    $('.bouncer-view-response').on('click', function() {
        var response = $(this).attr('data-response');
        try {
            var parsed = JSON.parse(response);
            response = JSON.stringify(parsed, null, 2);
        } catch (e) {}
        $('#bouncer-response-content').text(response);
        $('#bouncer-response-modal').fadeIn(150);
    });

    // Close modal
    $('.bouncer-modal-close, .bouncer-modal-overlay').on('click', function(e) {
        if (e.target === this) {
            $('#bouncer-response-modal').fadeOut(150);
        }
    });

    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('#bouncer-response-modal').fadeOut(150);
        }
    });
})(jQuery);
</script>
