<?php
/** @var array $logs */
/** @var int $retention */
/** @var string $table_name */
/** @var string $filter */
/** @var array $counts */
$success_logs = (int) ( $stats['by_status']['success'] ?? 0 ) + (int) ( $stats['by_status']['sent'] ?? 0 );
$failed_logs  = max( 0, $total_logs - $success_logs );
$base_url     = admin_url( 'admin.php?page=wc-bouncer-whatsapp-logs' );
$filter_url   = function ( string $type ) use ( $base_url ) {
    return 'all' === $type ? $base_url : add_query_arg( 'type', $type, $base_url );
};
$paged_url    = function ( int $page ) use ( $base_url, $filter ) {
    $url = add_query_arg( 'paged', $page, $base_url );
    if ( 'all' !== $filter ) {
        $url = add_query_arg( 'type', $filter, $url );
    }
    return $url;
};
$filter_pills = [
    'all'     => __( 'All', 'wc-bouncer-whatsapp' ),
    'message' => __( 'WhatsApp messages', 'wc-bouncer-whatsapp' ),
    'webhook' => __( 'Webhooks', 'wc-bouncer-whatsapp' ),
];
$response_is_success = function ( array $log ): bool {
    if ( in_array( $log['status'], [ 'success', 'sent' ], true ) ) {
        return true;
    }

    if ( 200 !== (int) ( $log['response_code'] ?? 0 ) || empty( $log['response_body'] ) ) {
        return false;
    }

    $response = json_decode( (string) $log['response_body'], true );

    return is_array( $response ) && true === ( $response['success'] ?? false );
};
$extract_phone_from_response = function ( string $response_body ): string {
    if ( '' === $response_body ) {
        return '';
    }

    $response = json_decode( $response_body, true );
    if ( ! is_array( $response ) || empty( $response['messageId'] ) || ! is_string( $response['messageId'] ) ) {
        return '';
    }

    if ( ! preg_match( '/wamid\.HBg[LM]([A-Za-z0-9+\/=]+?)(?:VAg|FQIA)/', $response['messageId'], $matches ) ) {
        return '';
    }

    $phone = base64_decode( $matches[1], true );

    return false !== $phone && preg_match( '/^\d{8,15}$/', $phone ) ? $phone : '';
};
$extract_message_from_response = function ( string $response_body ): string {
    if ( '' === $response_body ) {
        return '';
    }

    $response = json_decode( $response_body, true );
    if ( ! is_array( $response ) || empty( $response['renderedContent'] ) || ! is_string( $response['renderedContent'] ) ) {
        return '';
    }

    return $response['renderedContent'];
};
?>
<div class="bouncer-admin-wrap">
    <!-- Page Header -->
    <div class="bouncer-page-header">
        <h1 class="bouncer-page-title">
            <span class="dashicons dashicons-list-view"></span>
            <?php esc_html_e( 'Message Logs', 'wc-bouncer-whatsapp' ); ?>
        </h1>
        <p class="bouncer-page-description">
            <?php echo esc_html( sprintf( __( 'Showing %1$d logs per page. Logs older than %2$d days are auto-deleted.', 'wc-bouncer-whatsapp' ), $per_page, $retention ) ); ?>
        </p>
    </div>

    <?php if ( isset( $_GET['cleared'] ) ) : ?>
        <div class="bouncer-alert bouncer-alert-success">
            <span class="dashicons dashicons-yes-alt"></span>
            <div class="bouncer-alert-content"><?php esc_html_e( 'All logs have been cleared.', 'wc-bouncer-whatsapp' ); ?></div>
        </div>
    <?php endif; ?>

    <?php if ( isset( $_GET['purged'] ) ) : ?>
        <div class="bouncer-alert bouncer-alert-success">
            <span class="dashicons dashicons-yes-alt"></span>
            <div class="bouncer-alert-content"><?php esc_html_e( 'Expired logs have been purged.', 'wc-bouncer-whatsapp' ); ?></div>
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

    <!-- Filter pills -->
    <div class="bouncer-tabs" style="margin-bottom: 16px;">
        <?php foreach ( $filter_pills as $pill_key => $pill_label ) :
            $count = (int) ( $counts[ $pill_key ] ?? 0 );
            ?>
            <a href="<?php echo esc_url( $filter_url( $pill_key ) ); ?>" class="bouncer-tab <?php echo $filter === $pill_key ? 'active' : ''; ?>">
                <?php echo esc_html( $pill_label ); ?>
                <span style="opacity: 0.6; margin-left: 4px;">(<?php echo (int) $count; ?>)</span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Logs Table -->
    <div class="bouncer-card">
        <div class="bouncer-card-header">
            <h3 class="bouncer-card-title">
                <span class="dashicons dashicons-database"></span>
                <?php esc_html_e( 'Recent Activity', 'wc-bouncer-whatsapp' ); ?>
            </h3>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 0;">
                    <?php wp_nonce_field( 'wc_bouncer_purge_old_logs' ); ?>
                    <input type="hidden" name="action" value="wc_bouncer_purge_old_logs" />
                    <button type="submit" class="bouncer-btn bouncer-btn-secondary bouncer-btn-sm" onclick="return confirm('<?php echo esc_js( sprintf( __( 'Purge all logs older than %d days?', 'wc-bouncer-whatsapp' ), $retention ) ); ?>');">
                        <span class="dashicons dashicons-filter"></span>
                        <?php echo esc_html( sprintf( __( 'Purge Expired (%d)', 'wc-bouncer-whatsapp' ), $expired_count ) ); ?>
                    </button>
                </form>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 0;">
                    <?php wp_nonce_field( 'wc_bouncer_clear_logs' ); ?>
                    <input type="hidden" name="action" value="wc_bouncer_clear_logs" />
                    <button type="submit" class="bouncer-btn bouncer-btn-ghost bouncer-btn-sm" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to clear all logs?', 'wc-bouncer-whatsapp' ) ); ?>');">
                        <span class="dashicons dashicons-trash"></span>
                        <?php esc_html_e( 'Clear All', 'wc-bouncer-whatsapp' ); ?>
                    </button>
                </form>
            </div>
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
                        $is_success = $response_is_success( $log );
                        $display_phone = (string) $log['phone'];
                        if ( '' === $display_phone && $is_success ) {
                            $display_phone = $extract_phone_from_response( (string) ( $log['response_body'] ?? '' ) );
                        }
                        $display_message = (string) $log['message'];
                        if ( '[FunnelKit] Message' === $display_message ) {
                            $response_message = $extract_message_from_response( (string) ( $log['response_body'] ?? '' ) );
                            if ( '' !== $response_message ) {
                                $display_message = $response_message;
                            }
                        }
                    ?>
                        <tr>
                            <td>
                                <span style="color: #6b7280; font-size: 12px;"><?php echo esc_html( $timestamp ); ?></span>
                            </td>
                            <td>
                                <?php if ( ! empty( $log['order_id'] ) ) : ?>
                                    <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $log['order_id'] . '&action=edit' ) ); ?>" class="bouncer-order-link">
                                        #<?php echo esc_html( $log['order_id'] ); ?>
                                    </a>
                                <?php else : ?>
                                    <span style="color: #9ca3af;" title="<?php esc_attr_e( 'Abandoned cart (no order)', 'wc-bouncer-whatsapp' ); ?>">—</span>
                                <?php endif; ?>
                            </td>
                            <td><code><?php echo esc_html( $display_phone ); ?></code></td>
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
                                <div class="bouncer-log-message"><?php echo esc_html( mb_strimwidth( $display_message, 0, 60, '...' ) ); ?></div>
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

            <?php if ( $total_pages > 1 ) : ?>
                <div class="bouncer-card-body" style="border-top: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <div class="bouncer-form-description" style="margin: 0;">
                        <?php
                        $from = ( ( $current_page - 1 ) * $per_page ) + 1;
                        $to   = min( $total_logs, $current_page * $per_page );
                        echo esc_html( sprintf( __( 'Showing %1$d–%2$d of %3$d logs', 'wc-bouncer-whatsapp' ), $from, $to, $total_logs ) );
                        ?>
                    </div>
                    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                        <?php if ( $current_page > 1 ) : ?>
                            <a class="bouncer-btn bouncer-btn-ghost bouncer-btn-sm" href="<?php echo esc_url( $paged_url( $current_page - 1 ) ); ?>">
                                <span class="dashicons dashicons-arrow-left-alt2"></span>
                                <?php esc_html_e( 'Previous', 'wc-bouncer-whatsapp' ); ?>
                            </a>
                        <?php endif; ?>

                        <span class="bouncer-form-description" style="margin: 0; white-space: nowrap;">
                            <?php echo esc_html( sprintf( __( 'Page %1$d of %2$d', 'wc-bouncer-whatsapp' ), $current_page, $total_pages ) ); ?>
                        </span>

                        <?php if ( $current_page < $total_pages ) : ?>
                            <a class="bouncer-btn bouncer-btn-ghost bouncer-btn-sm" href="<?php echo esc_url( $paged_url( $current_page + 1 ) ); ?>">
                                <?php esc_html_e( 'Next', 'wc-bouncer-whatsapp' ); ?>
                                <span class="dashicons dashicons-arrow-right-alt2"></span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
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
