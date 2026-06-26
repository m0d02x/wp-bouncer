<?php
/**
 * @var array      $config
 * @var array      $webhook_config
 * @var bool       $webhook_configured
 * @var int|false  $next_scheduled
 * @var array|null $sweep_result
 * @var array|null $test_result
 * @var string     $active_tab
 * @var array      $pipeline
 */

use Bouncer\WooCommerce\WhatsApp\Service\AbandonedWebhookDispatcher;

$statuses = [
    'pending' => __( 'Pending payment', 'wc-bouncer-whatsapp' ),
    'on-hold' => __( 'On hold', 'wc-bouncer-whatsapp' ),
    'failed'  => __( 'Failed', 'wc-bouncer-whatsapp' ),
];

$is_enabled = ! empty( $config['enabled'] );
?>
<div class="bouncer-admin-wrap">

    <!-- Page Header -->
    <div class="bouncer-page-header">
        <h1 class="bouncer-page-title">
            <?php esc_html_e( 'Abandoned Orders', 'wc-bouncer-whatsapp' ); ?>
            <?php if ( ! $webhook_configured ) : ?>
                <span class="bouncer-status-badge disconnected">
                    <span class="dashicons dashicons-warning"></span>
                    <?php esc_html_e( 'Webhook not configured', 'wc-bouncer-whatsapp' ); ?>
                </span>
            <?php elseif ( $is_enabled ) : ?>
                <span class="bouncer-status-badge connected">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php esc_html_e( 'Active', 'wc-bouncer-whatsapp' ); ?>
                </span>
            <?php else : ?>
                <span class="bouncer-status-badge unknown">
                    <span class="dashicons dashicons-marker"></span>
                    <?php esc_html_e( 'Disabled', 'wc-bouncer-whatsapp' ); ?>
                </span>
            <?php endif; ?>
        </h1>
        <p class="bouncer-page-description">
            <?php esc_html_e( 'Detects orders stuck in pending, on-hold, or failed past a threshold and sends a webhook to Bouncer so workflows can react.', 'wc-bouncer-whatsapp' ); ?>
        </p>
    </div>

    <?php if ( ! empty( $_GET['abandoned_saved'] ) ) : ?>
        <div class="bouncer-alert bouncer-alert-success">
            <span class="dashicons dashicons-yes-alt"></span>
            <div class="bouncer-alert-content"><?php esc_html_e( 'Abandoned-order settings saved.', 'wc-bouncer-whatsapp' ); ?></div>
        </div>
    <?php endif; ?>

    <?php if ( ! $webhook_configured ) :
        $webhook_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url( admin_url( 'admin.php?page=' . \Bouncer\WooCommerce\WhatsApp\Admin\GeneralSettingsPage::MENU_SLUG . '#webhooks' ) ),
            esc_html__( 'Settings → Real-time Events', 'wc-bouncer-whatsapp' )
        );
        ?>
        <div class="bouncer-alert bouncer-alert-warning">
            <span class="dashicons dashicons-warning"></span>
            <div class="bouncer-alert-content">
                <div class="bouncer-alert-title"><?php esc_html_e( 'Webhook credentials missing', 'wc-bouncer-whatsapp' ); ?></div>
                <?php
                /* translators: %s: link to webhook settings */
                printf(
                    wp_kses( __( 'Set the Bouncer webhook URL and secret in %s before enabling abandoned-order tracking.', 'wc-bouncer-whatsapp' ), [ 'a' => [ 'href' => [] ] ] ),
                    $webhook_link // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped.
                );
                ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="bouncer-tabs">
        <a href="#activity" class="bouncer-tab <?php echo 'activity' === $active_tab ? 'active' : ''; ?>" data-tab="activity">
            <span class="dashicons dashicons-chart-bar"></span>
            <?php esc_html_e( 'Activity', 'wc-bouncer-whatsapp' ); ?>
        </a>
        <a href="#settings" class="bouncer-tab <?php echo 'settings' === $active_tab ? 'active' : ''; ?>" data-tab="settings">
            <span class="dashicons dashicons-admin-generic"></span>
            <?php esc_html_e( 'Settings', 'wc-bouncer-whatsapp' ); ?>
        </a>
        <a href="#sweep" class="bouncer-tab <?php echo 'sweep' === $active_tab ? 'active' : ''; ?>" data-tab="sweep">
            <span class="dashicons dashicons-update"></span>
            <?php esc_html_e( 'Check Now', 'wc-bouncer-whatsapp' ); ?>
        </a>
        <a href="#test" class="bouncer-tab <?php echo 'test' === $active_tab ? 'active' : ''; ?>" data-tab="test">
            <span class="dashicons dashicons-email-alt"></span>
            <?php esc_html_e( 'Send Test Webhook', 'wc-bouncer-whatsapp' ); ?>
        </a>
    </div>

    <!-- Settings Tab -->
    <div id="settings" class="bouncer-tab-content <?php echo 'settings' === $active_tab ? 'active' : ''; ?>">
        <form method="post">
            <?php wp_nonce_field( 'wc_bouncer_abandoned_save' ); ?>
            <input type="hidden" name="wc_bouncer_abandoned_action" value="save" />

            <div class="bouncer-card">
                <div class="bouncer-card-header">
                    <h3 class="bouncer-card-title">
                        <span class="dashicons dashicons-admin-generic"></span>
                        <?php esc_html_e( 'Detection Settings', 'wc-bouncer-whatsapp' ); ?>
                    </h3>
                </div>
                <div class="bouncer-card-body">
                    <div class="bouncer-form-row">
                        <div class="bouncer-form-group">
                            <label class="bouncer-form-label" for="abandoned_enabled">
                                <?php esc_html_e( 'Enable tracking', 'wc-bouncer-whatsapp' ); ?>
                            </label>
                            <label class="bouncer-inline-row" style="font-size: 13px; color: #374151;">
                                <input type="checkbox" id="abandoned_enabled" name="enabled" value="1" <?php checked( $config['enabled'] ); ?> />
                                <span><?php esc_html_e( 'Check for abandoned orders on the schedule below.', 'wc-bouncer-whatsapp' ); ?></span>
                            </label>
                        </div>

                        <div class="bouncer-form-group">
                            <label class="bouncer-form-label" for="sweep_interval_minutes">
                                <?php esc_html_e( 'Check frequency', 'wc-bouncer-whatsapp' ); ?>
                            </label>
                            <select id="sweep_interval_minutes" name="sweep_interval_minutes" class="bouncer-form-select">
                                <?php foreach ( [ 5, 10, 15 ] as $minutes ) : ?>
                                    <option value="<?php echo esc_attr( $minutes ); ?>" <?php selected( (int) $config['sweep_interval_minutes'], $minutes ); ?>>
                                        <?php
                                        printf(
                                            /* translators: %d: minutes */
                                            esc_html( _n( 'Every %d minute', 'Every %d minutes', $minutes, 'wc-bouncer-whatsapp' ) ),
                                            $minutes
                                        );
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="bouncer-form-description">
                                <?php if ( $next_scheduled ) :
                                    /* translators: %s: next check time */
                                    printf(
                                        esc_html__( 'Next check: %s', 'wc-bouncer-whatsapp' ),
                                        esc_html( wp_date( 'M j, H:i', $next_scheduled ) )
                                    );
                                else :
                                    esc_html_e( 'Not yet scheduled.', 'wc-bouncer-whatsapp' );
                                endif; ?>
                            </p>
                        </div>
                    </div>

                    <div class="bouncer-divider"></div>

                    <div class="bouncer-section-title"><?php esc_html_e( 'Tracked statuses', 'wc-bouncer-whatsapp' ); ?></div>

                    <table class="bouncer-table" style="margin-bottom: 12px;">
                        <thead>
                            <tr>
                                <th style="width: 220px;"><?php esc_html_e( 'Status', 'wc-bouncer-whatsapp' ); ?></th>
                                <th><?php esc_html_e( 'Threshold (minutes)', 'wc-bouncer-whatsapp' ); ?></th>
                                <th style="width: 240px;"><?php esc_html_e( 'Bouncer event', 'wc-bouncer-whatsapp' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $statuses as $status => $label ) :
                                $track_key     = 'track_' . str_replace( '-', '_', $status );
                                $threshold_key = 'threshold_' . str_replace( '-', '_', $status );
                                ?>
                                <tr>
                                    <td>
                                        <label class="bouncer-inline-row" style="font-weight: 500; color: #111827;">
                                            <input type="checkbox" name="<?php echo esc_attr( $track_key ); ?>" value="1" <?php checked( ! empty( $config[ $track_key ] ) ); ?> />
                                            <span><?php echo esc_html( $label ); ?></span>
                                        </label>
                                    </td>
                                    <td>
                                        <input
                                            type="number"
                                            min="0"
                                            step="1"
                                            class="bouncer-form-input small"
                                            name="<?php echo esc_attr( $threshold_key ); ?>"
                                            value="<?php echo esc_attr( (int) ( $config[ $threshold_key ] ?? 0 ) ); ?>"
                                        />
                                    </td>
                                    <td>
                                        <code>order.abandoned.<?php echo esc_html( AbandonedWebhookDispatcher::STATUS_EVENT_MAP[ $status ] ); ?></code>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <p class="bouncer-form-description" style="margin: 0 0 16px 0;">
                        <?php esc_html_e( 'Orders modified more than the threshold ago get a single webhook per status entry. Set a threshold to 0 to disable that status individually.', 'wc-bouncer-whatsapp' ); ?>
                    </p>

                    <div class="bouncer-divider"></div>

                    <div class="bouncer-form-group">
                        <label class="bouncer-inline-row" style="font-size: 13px; color: #374151;">
                            <input type="checkbox" name="skip_subscriptions" value="1" <?php checked( ! empty( $config['skip_subscriptions'] ) ); ?> />
                            <span><?php esc_html_e( 'Skip subscription renewals (orders with a parent or `_subscription_renewal` meta).', 'wc-bouncer-whatsapp' ); ?></span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="bouncer-card">
                <div class="bouncer-card-header">
                    <h3 class="bouncer-card-title">
                        <span class="dashicons dashicons-cart"></span>
                        <?php esc_html_e( 'CartBounty Polling', 'wc-bouncer-whatsapp' ); ?>
                    </h3>
                </div>
                <div class="bouncer-card-body">
                    <p class="bouncer-form-description" style="margin: 0 0 16px 0;">
                        <?php esc_html_e( 'These controls share the same cron schedule as the abandoned orders detection above (Check frequency). CartBounty carts are polled at that interval.', 'wc-bouncer-whatsapp' ); ?>
                    </p>

                    <div class="bouncer-form-group">
                        <label class="bouncer-inline-row" style="font-size: 13px; color: #374151;">
                            <input type="checkbox" name="cartbounty_polling_enabled" value="1" <?php checked( ! empty( $cartbounty_settings['enabled'] ) ); ?> />
                            <span><?php esc_html_e( 'Enable CartBounty polling', 'wc-bouncer-whatsapp' ); ?></span>
                        </label>
                    </div>

                    <div class="bouncer-divider"></div>

                    <div class="bouncer-section-title"><?php esc_html_e( 'Steps', 'wc-bouncer-whatsapp' ); ?></div>
                    <div class="bouncer-form-group">
                        <label class="bouncer-inline-row" style="font-size: 13px; color: #374151;">
                            <input type="checkbox" name="cartbounty_steps[]" value="1" <?php checked( in_array( 1, $cartbounty_settings['steps'], true ) ); ?> />
                            <span><?php esc_html_e( 'Step 1 — First reminder (mirrors first CartBounty email)', 'wc-bouncer-whatsapp' ); ?></span>
                        </label>
                    </div>
                    <div class="bouncer-form-group">
                        <label class="bouncer-inline-row" style="font-size: 13px; color: #374151;">
                            <input type="checkbox" name="cartbounty_steps[]" value="2" <?php checked( in_array( 2, $cartbounty_settings['steps'], true ) ); ?> />
                            <span><?php esc_html_e( 'Step 2 — Second reminder', 'wc-bouncer-whatsapp' ); ?></span>
                        </label>
                    </div>
                    <div class="bouncer-form-group">
                        <label class="bouncer-inline-row" style="font-size: 13px; color: #374151;">
                            <input type="checkbox" name="cartbounty_steps[]" value="3" <?php checked( in_array( 3, $cartbounty_settings['steps'], true ) ); ?> />
                            <span><?php esc_html_e( 'Step 3 — Third reminder', 'wc-bouncer-whatsapp' ); ?></span>
                        </label>
                    </div>

                    <p class="bouncer-form-description" style="margin: 12px 0 0 0;">
                        <?php esc_html_e( "Selected steps trigger WhatsApp messages alongside CartBounty's email automation at the same time intervals.", 'wc-bouncer-whatsapp' ); ?>
                    </p>

                    <?php if ( empty( $cartbounty_table_exists ) ) : ?>
                        <div class="bouncer-alert bouncer-alert-warning" style="margin-top:16px;">
                            <span class="dashicons dashicons-warning"></span>
                            <div class="bouncer-alert-content">
                                <?php esc_html_e( 'CartBounty plugin was not detected. Install and activate CartBounty to use polling.', 'wc-bouncer-whatsapp' ); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bouncer-form-actions">
                <button type="submit" class="bouncer-btn bouncer-btn-primary">
                    <span class="dashicons dashicons-saved"></span>
                    <?php esc_html_e( 'Save settings', 'wc-bouncer-whatsapp' ); ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Activity Tab -->
    <div id="activity" class="bouncer-tab-content <?php echo 'activity' === $active_tab ? 'active' : ''; ?>">

        <?php
        $rows = [];
        foreach ( $pipeline as $bucket ) {
            foreach ( $bucket['orders'] as $row ) {
                $row['status_label'] = $bucket['label'];
                $row['tracked']      = $bucket['tracked'];
                $row['threshold']    = $bucket['threshold'];
                $rows[]              = $row;
            }
        }
        usort( $rows, static function ( $a, $b ) {
            return ( $b['age_minutes'] ?? 0 ) <=> ( $a['age_minutes'] ?? 0 );
        } );
        ?>
        <div class="bouncer-card">
            <div class="bouncer-card-header">
                <h3 class="bouncer-card-title">
                    <span class="dashicons dashicons-cart"></span>
                    <?php esc_html_e( 'Live Pipeline', 'wc-bouncer-whatsapp' ); ?>
                </h3>
                <span class="bouncer-form-description" style="margin: 0;">
                    <?php
                    printf(
                        /* translators: %d: number of orders shown */
                        esc_html( _n( '%d order', '%d orders', count( $rows ), 'wc-bouncer-whatsapp' ) ),
                        count( $rows )
                    );
                    ?>
                </span>
            </div>
            <?php if ( empty( $rows ) ) : ?>
                <div class="bouncer-card-body">
                    <div class="bouncer-empty-state">
                        <span class="dashicons dashicons-cart"></span>
                        <p><?php esc_html_e( 'No orders in tracked statuses right now.', 'wc-bouncer-whatsapp' ); ?></p>
                    </div>
                </div>
            <?php else : ?>
                <table class="bouncer-table">
                    <thead>
                        <tr>
                            <th style="width: 80px;"><?php esc_html_e( 'Order', 'wc-bouncer-whatsapp' ); ?></th>
                            <th><?php esc_html_e( 'Customer', 'wc-bouncer-whatsapp' ); ?></th>
                            <th style="width: 140px;"><?php esc_html_e( 'Status', 'wc-bouncer-whatsapp' ); ?></th>
                            <th style="width: 110px;"><?php esc_html_e( 'Total', 'wc-bouncer-whatsapp' ); ?></th>
                            <th style="width: 110px;"><?php esc_html_e( 'Age', 'wc-bouncer-whatsapp' ); ?></th>
                            <th style="width: 170px;"><?php esc_html_e( 'State', 'wc-bouncer-whatsapp' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $rows as $row ) :
                            switch ( $row['state'] ) {
                                case 'notified':
                                    $badge_class = 'connected';
                                    $badge_label = __( 'Notified', 'wc-bouncer-whatsapp' );
                                    break;
                                case 'due':
                                    $badge_class = 'unknown';
                                    $badge_label = __( 'Due next check', 'wc-bouncer-whatsapp' );
                                    break;
                                case 'waiting':
                                    $badge_class = 'unknown';
                                    $badge_label = __( 'Below threshold', 'wc-bouncer-whatsapp' );
                                    break;
                                default:
                                    $badge_class = 'unknown';
                                    $badge_label = __( 'Untracked', 'wc-bouncer-whatsapp' );
                                    break;
                            }

                            $status_caption = $row['tracked']
                                ? sprintf(
                                    /* translators: %d: threshold minutes */
                                    _n( '%d min threshold', '%d min threshold', (int) $row['threshold'], 'wc-bouncer-whatsapp' ),
                                    (int) $row['threshold']
                                )
                                : __( 'Not tracked', 'wc-bouncer-whatsapp' );
                            ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $row['id'] . '&action=edit' ) ); ?>">
                                        #<?php echo esc_html( $row['number'] ); ?>
                                    </a>
                                </td>
                                <td>
                                    <div style="font-weight: 500; color: #111827;"><?php echo esc_html( $row['customer'] ); ?></div>
                                    <?php if ( '' !== $row['phone'] ) : ?>
                                        <div style="font-size: 11px; color: #6b7280;"><?php echo esc_html( $row['phone'] ); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-weight: 500; color: #111827;"><?php echo esc_html( $row['status_label'] ); ?></div>
                                    <div style="font-size: 11px; color: #6b7280;"><?php echo esc_html( $status_caption ); ?></div>
                                </td>
                                <td><?php echo esc_html( $row['total'] ); ?></td>
                                <td>
                                    <?php
                                    printf(
                                        /* translators: %d: minutes since order was last modified */
                                        esc_html( _n( '%d min', '%d min', $row['age_minutes'], 'wc-bouncer-whatsapp' ) ),
                                        (int) $row['age_minutes']
                                    );
                                    ?>
                                    <div style="font-size: 11px; color: #6b7280;">
                                        <?php echo esc_html( $row['date_modified'] ); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="bouncer-status-badge <?php echo esc_attr( $badge_class ); ?>" style="font-size: 11px;">
                                        <?php echo esc_html( $badge_label ); ?>
                                    </span>
                                    <?php if ( 'notified' === $row['state'] && '' !== $row['notified_at'] ) : ?>
                                        <div style="font-size: 11px; color: #6b7280; margin-top: 2px;">
                                            <?php echo esc_html( $row['notified_at'] ); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <p class="bouncer-form-description" style="margin-top: 12px;">
            <?php
            $logs_link = sprintf(
                '<a href="%s">%s</a>',
                esc_url( add_query_arg( 'type', 'webhook', admin_url( 'admin.php?page=wc-bouncer-whatsapp-logs' ) ) ),
                esc_html__( 'Logs → Webhooks', 'wc-bouncer-whatsapp' )
            );
            /* translators: %s: link to the filtered logs page */
            printf(
                wp_kses( __( 'Webhook delivery history is available in %s.', 'wc-bouncer-whatsapp' ), [ 'a' => [ 'href' => [] ] ] ),
                $logs_link // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped.
            );
            ?>
        </p>

    </div>

    <!-- Check Now Tab -->
    <div id="sweep" class="bouncer-tab-content <?php echo 'sweep' === $active_tab ? 'active' : ''; ?>">
        <div class="bouncer-card">
            <div class="bouncer-card-header">
                <h3 class="bouncer-card-title">
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e( 'Check Now', 'wc-bouncer-whatsapp' ); ?>
                </h3>
            </div>
            <div class="bouncer-card-body">
                <p class="bouncer-form-description" style="margin: 0 0 16px 0;">
                    <?php esc_html_e( 'Run one check immediately. Preview lists candidates without sending webhooks or marking orders as notified.', 'wc-bouncer-whatsapp' ); ?>
                </p>

                <div class="bouncer-inline-row" style="gap: 8px;">
                    <form method="post" style="display: inline;">
                        <?php wp_nonce_field( 'wc_bouncer_abandoned_sweep' ); ?>
                        <input type="hidden" name="wc_bouncer_abandoned_action" value="dry_run" />
                        <button type="submit" class="bouncer-btn bouncer-btn-secondary">
                            <span class="dashicons dashicons-search"></span>
                            <?php esc_html_e( 'Preview', 'wc-bouncer-whatsapp' ); ?>
                        </button>
                    </form>

                    <form method="post" style="display: inline;">
                        <?php wp_nonce_field( 'wc_bouncer_abandoned_sweep' ); ?>
                        <input type="hidden" name="wc_bouncer_abandoned_action" value="run_sweep" />
                        <button type="submit" class="bouncer-btn bouncer-btn-primary">
                            <span class="dashicons dashicons-controls-play"></span>
                            <?php esc_html_e( 'Check now', 'wc-bouncer-whatsapp' ); ?>
                        </button>
                    </form>
                </div>

                <?php if ( $sweep_result ) :
                    $result = $sweep_result['result'];
                    $dry    = ! empty( $sweep_result['dry_run'] );
                    ?>
                    <div style="margin-top: 20px;">
                        <?php if ( ! empty( $result['skipped'] ) ) : ?>
                            <div class="bouncer-alert bouncer-alert-info" style="margin: 0;">
                                <span class="dashicons dashicons-info"></span>
                                <div class="bouncer-alert-content">
                                    <?php esc_html_e( 'Skipped:', 'wc-bouncer-whatsapp' ); ?>
                                    <code><?php echo esc_html( $result['skipped'] ); ?></code>
                                </div>
                            </div>
                        <?php else : ?>
                            <div class="bouncer-section-title" style="margin-bottom: 8px;">
                                <?php echo $dry
                                    ? esc_html__( 'Candidates found per status', 'wc-bouncer-whatsapp' )
                                    : esc_html__( 'Notified per status', 'wc-bouncer-whatsapp' ); ?>
                            </div>
                            <table class="bouncer-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'Status', 'wc-bouncer-whatsapp' ); ?></th>
                                        <th style="width: 220px;"><?php esc_html_e( 'Count', 'wc-bouncer-whatsapp' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $statuses as $status => $label ) :
                                        $count = (int) ( $result['notified'][ $status ] ?? 0 );
                                        $errs  = (int) ( $result['errors'][ $status ] ?? 0 );
                                        ?>
                                        <tr>
                                            <td><?php echo esc_html( $label ); ?></td>
                                            <td>
                                                <strong><?php echo esc_html( (string) $count ); ?></strong>
                                                <?php if ( ! $dry && $errs > 0 ) : ?>
                                                    <span style="color: #b91c1c; margin-left: 8px;">
                                                        (<?php
                                                        printf(
                                                            /* translators: %d: error count */
                                                            esc_html( _n( '%d error', '%d errors', $errs, 'wc-bouncer-whatsapp' ) ),
                                                            $errs
                                                        );
                                                        ?>)
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Test Webhook Tab -->
    <div id="test" class="bouncer-tab-content <?php echo 'test' === $active_tab ? 'active' : ''; ?>">
        <div class="bouncer-card">
            <div class="bouncer-card-header">
                <h3 class="bouncer-card-title">
                    <span class="dashicons dashicons-email-alt"></span>
                    <?php esc_html_e( 'Send Test Webhook', 'wc-bouncer-whatsapp' ); ?>
                </h3>
            </div>
            <div class="bouncer-card-body">
                <p class="bouncer-form-description" style="margin: 0 0 16px 0;">
                    <?php esc_html_e( 'Send a one-off abandoned webhook for a specific order. The order will still be picked up on the next scheduled check.', 'wc-bouncer-whatsapp' ); ?>
                </p>

                <form method="post">
                    <?php wp_nonce_field( 'wc_bouncer_abandoned_test' ); ?>
                    <input type="hidden" name="wc_bouncer_abandoned_action" value="send_test" />

                    <div class="bouncer-form-row">
                        <div class="bouncer-form-group">
                            <label class="bouncer-form-label" for="test_order_id">
                                <?php esc_html_e( 'Order ID', 'wc-bouncer-whatsapp' ); ?>
                            </label>
                            <input
                                type="number"
                                id="test_order_id"
                                name="test_order_id"
                                min="0"
                                class="bouncer-form-input"
                                value="0"
                                placeholder="0"
                            />
                            <p class="bouncer-form-description">
                                <?php esc_html_e( 'Use 0 to pick the most recent order in the selected status.', 'wc-bouncer-whatsapp' ); ?>
                            </p>
                        </div>

                        <div class="bouncer-form-group">
                            <label class="bouncer-form-label" for="test_status">
                                <?php esc_html_e( 'Event', 'wc-bouncer-whatsapp' ); ?>
                            </label>
                            <select id="test_status" name="test_status" class="bouncer-form-select">
                                <?php foreach ( $statuses as $status => $label ) : ?>
                                    <option value="<?php echo esc_attr( $status ); ?>">
                                        <?php echo esc_html( sprintf( '%s — abandoned.%s', $label, AbandonedWebhookDispatcher::STATUS_EVENT_MAP[ $status ] ) ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="bouncer-btn bouncer-btn-primary">
                        <span class="dashicons dashicons-migrate"></span>
                        <?php esc_html_e( 'Send test webhook', 'wc-bouncer-whatsapp' ); ?>
                    </button>
                </form>

                <?php if ( $test_result ) :
                    $is_success = ! empty( $test_result['success'] );
                    ?>
                    <div style="margin-top: 20px;">
                        <div class="bouncer-alert bouncer-alert-<?php echo $is_success ? 'success' : 'error'; ?>" style="margin: 0;">
                            <span class="dashicons <?php echo $is_success ? 'dashicons-yes-alt' : 'dashicons-dismiss'; ?>"></span>
                            <div class="bouncer-alert-content">
                                <div class="bouncer-alert-title">
                                    <?php
                                    if ( $is_success ) {
                                        esc_html_e( 'Webhook delivered', 'wc-bouncer-whatsapp' );
                                    } else {
                                        esc_html_e( 'Webhook failed', 'wc-bouncer-whatsapp' );
                                    }
                                    ?>
                                </div>
                                <div>
                                    <?php esc_html_e( 'HTTP', 'wc-bouncer-whatsapp' ); ?>
                                    <strong><?php echo (int) $test_result['code']; ?></strong>
                                    <?php if ( ! empty( $test_result['event_slug'] ) ) : ?>
                                        — <code>order.abandoned.<?php echo esc_html( $test_result['event_slug'] ); ?></code>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $test_result['order_id'] ) ) : ?>
                                        — <?php esc_html_e( 'Order', 'wc-bouncer-whatsapp' ); ?> #<?php echo (int) $test_result['order_id']; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php if ( ! empty( $test_result['body'] ) ) : ?>
                            <div class="bouncer-response-box"><?php echo esc_html( $test_result['body'] ); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    (function() {
        var tabs = document.querySelectorAll('.bouncer-admin-wrap .bouncer-tab');
        var contents = document.querySelectorAll('.bouncer-admin-wrap .bouncer-tab-content');

        function activate(slug) {
            tabs.forEach(function(t) {
                t.classList.toggle('active', t.getAttribute('data-tab') === slug);
            });
            contents.forEach(function(c) {
                c.classList.toggle('active', c.id === slug);
            });
        }

        tabs.forEach(function(t) {
            t.addEventListener('click', function(e) {
                e.preventDefault();
                var slug = this.getAttribute('data-tab');
                activate(slug);
                if (history.replaceState) {
                    history.replaceState(null, null, '#' + slug);
                }
            });
        });

        // Honor URL hash on load.
        if (window.location.hash) {
            var hash = window.location.hash.substring(1);
            if (document.getElementById(hash)) {
                activate(hash);
            }
        }
    })();
    </script>
</div>
