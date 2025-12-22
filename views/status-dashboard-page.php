<?php
/** @var string $instance_id */
/** @var array|null $instance_data */
/** @var array $log_stats */
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Status Dashboard', 'wc-bouncer-whatsapp' ); ?></h1>
    <p><?php esc_html_e( 'Monitor your Bouncer WhatsApp connection and recent messaging activity at a glance.', 'wc-bouncer-whatsapp' ); ?></p>

    <div class="postbox" style="max-width:680px; padding:20px;">
        <h2><?php esc_html_e( 'Instance Status', 'wc-bouncer-whatsapp' ); ?></h2>
        <?php if ( '' === $instance_id ) : ?>
            <p><?php esc_html_e( 'Configure an instance ID in the general settings to see live status.', 'wc-bouncer-whatsapp' ); ?></p>
        <?php elseif ( ! $instance_data ) : ?>
            <p><?php esc_html_e( 'Status could not be retrieved at this time.', 'wc-bouncer-whatsapp' ); ?></p>
        <?php else : ?>
            <p><strong><?php esc_html_e( 'Instance ID:', 'wc-bouncer-whatsapp' ); ?></strong> <?php echo esc_html( $instance_id ); ?></p>
            <p><strong><?php esc_html_e( 'Reachable:', 'wc-bouncer-whatsapp' ); ?></strong> <?php echo $instance_data['success'] ? esc_html__( 'Yes', 'wc-bouncer-whatsapp' ) : esc_html__( 'No', 'wc-bouncer-whatsapp' ); ?></p>
            <p><strong><?php esc_html_e( 'HTTP Code:', 'wc-bouncer-whatsapp' ); ?></strong> <?php echo esc_html( (string) ( $instance_data['response_code'] ?? '' ) ); ?></p>
            <?php if ( isset( $instance_data['data'] ) && is_array( $instance_data['data'] ) ) : ?>
                <pre style="white-space:pre-wrap; word-break:break-word; background:#f6f7f7; padding:12px; border:1px solid #ccd0d4; max-height:220px; overflow:auto;"><?php echo esc_html( wp_json_encode( $instance_data['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
            <?php elseif ( isset( $instance_data['response_body'] ) ) : ?>
                <pre style="white-space:pre-wrap; word-break:break-word; background:#f6f7f7; padding:12px; border:1px solid #ccd0d4; max-height:220px; overflow:auto;"><?php echo esc_html( (string) $instance_data['response_body'] ); ?></pre>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="postbox" style="max-width:680px; padding:20px;">
        <h2><?php esc_html_e( 'Messaging Totals', 'wc-bouncer-whatsapp' ); ?></h2>
        <?php
        $total     = isset( $log_stats['total'] ) ? (int) $log_stats['total'] : 0;
        $by_status = isset( $log_stats['by_status'] ) && is_array( $log_stats['by_status'] ) ? $log_stats['by_status'] : [];
        ?>
        <p><strong><?php esc_html_e( 'Messages Logged:', 'wc-bouncer-whatsapp' ); ?></strong> <?php echo esc_html( (string) $total ); ?></p>
        <table class="widefat striped" style="max-width:420px;">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Status', 'wc-bouncer-whatsapp' ); ?></th>
                    <th><?php esc_html_e( 'Count', 'wc-bouncer-whatsapp' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $by_status ) ) : ?>
                    <tr>
                        <td colspan="2"><?php esc_html_e( 'No message activity recorded yet.', 'wc-bouncer-whatsapp' ); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $by_status as $status => $count ) : ?>
                        <tr>
                            <td><?php echo esc_html( ucfirst( (string) $status ) ); ?></td>
                            <td><?php echo esc_html( (string) (int) $count ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
