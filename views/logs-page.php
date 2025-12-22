<?php
/** @var array $logs */
/** @var int $retention */
/** @var string $table_name */
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Bouncer WhatsApp Logs', 'wc-bouncer-whatsapp' ); ?></h1>

    <p><?php echo esc_html( sprintf( __( 'Displaying the last %d log entries. Retention: %d days.', 'wc-bouncer-whatsapp' ), 100, $retention ) ); ?></p>
    <p class="description"><?php echo esc_html( sprintf( __( 'Logs stored in %s.', 'wc-bouncer-whatsapp' ), $table_name ) ); ?></p>

    <?php if ( isset( $_GET['cleared'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Logs cleared.', 'wc-bouncer-whatsapp' ); ?></p></div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom: 1em;">
        <?php wp_nonce_field( 'wc_bouncer_clear_logs' ); ?>
        <input type="hidden" name="action" value="wc_bouncer_clear_logs" />
        <?php submit_button( __( 'Clear Logs', 'wc-bouncer-whatsapp' ), 'delete', 'submit', false ); ?>
    </form>

    <table class="widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Created At', 'wc-bouncer-whatsapp' ); ?></th>
                <th><?php esc_html_e( 'Order ID', 'wc-bouncer-whatsapp' ); ?></th>
                <th><?php esc_html_e( 'Phone', 'wc-bouncer-whatsapp' ); ?></th>
                <th><?php esc_html_e( 'Status', 'wc-bouncer-whatsapp' ); ?></th>
                <th><?php esc_html_e( 'HTTP', 'wc-bouncer-whatsapp' ); ?></th>
                <th><?php esc_html_e( 'Message', 'wc-bouncer-whatsapp' ); ?></th>
                <th><?php esc_html_e( 'Response', 'wc-bouncer-whatsapp' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $logs ) ) : ?>
                <tr>
                    <td colspan="7"><?php esc_html_e( 'No logs recorded yet.', 'wc-bouncer-whatsapp' ); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ( $logs as $log ) : ?>
                    <tr>
                        <td>
                            <?php
                            $gmt       = $log['created_at'];
                            $timestamp = get_date_from_gmt( $gmt, 'Y-m-d H:i:s' );
                            echo esc_html( $timestamp );
                            ?>
                        </td>
                        <td><?php echo esc_html( $log['order_id'] ); ?></td>
                        <td><?php echo esc_html( $log['phone'] ); ?></td>
                        <td><?php echo esc_html( ucfirst( $log['status'] ) ); ?></td>
                        <td><?php echo esc_html( $log['response_code'] ); ?></td>
                        <td>
                            <details>
                                <summary><?php esc_html_e( 'View', 'wc-bouncer-whatsapp' ); ?></summary>
                                <pre style="white-space: pre-wrap; word-break: break-word;"><?php echo esc_html( $log['message'] ); ?></pre>
                            </details>
                        </td>
                        <td>
                            <details>
                                <summary><?php esc_html_e( 'View', 'wc-bouncer-whatsapp' ); ?></summary>
                                <pre style="white-space: pre-wrap; word-break: break-word; max-height: 200px; overflow:auto;"><?php echo esc_html( $log['response_body'] ); ?></pre>
                            </details>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
