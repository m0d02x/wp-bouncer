<?php

namespace Bouncer\WooCommerce\WhatsApp\Service;

use Bouncer\WooCommerce\WhatsApp\Settings\Settings;
use WC_Order;

// AbandonedWebhookDispatcher is in the same namespace; no use statement needed.

/**
 * Scans WooCommerce for orders that have been stuck in pending / on-hold /
 * failed past their configured threshold and dispatches a synthetic
 * `abandoned.<slug>` webhook to Bouncer for each candidate. Dedups per order
 * via a meta flag and resets the flag when the order leaves the status.
 */
class AbandonedOrdersScanner {
    public const CRON_HOOK            = 'wc_bouncer_abandoned_sweep';
    public const OPTION_NAME          = 'wc_bouncer_abandoned_settings';
    public const SCHEDULE_5_MIN       = 'wc_bouncer_every_5_minutes';
    public const SCHEDULE_10_MIN      = 'wc_bouncer_every_10_minutes';
    public const SCHEDULE_15_MIN      = 'wc_bouncer_every_15_minutes';
    public const META_PREFIX          = '_bouncer_abandoned_notified_';
    public const TRACKED_STATUSES     = [ 'pending', 'on-hold', 'failed' ];
    public const BATCH_LIMIT          = 50;

    private Settings $settings;
    private AbandonedWebhookDispatcher $dispatcher;

    public function __construct( Settings $settings, AbandonedWebhookDispatcher $dispatcher ) {
        $this->settings   = $settings;
        $this->dispatcher = $dispatcher;
    }

    public function register(): void {
        add_filter( 'cron_schedules', [ $this, 'add_cron_schedules' ] );
        add_action( self::CRON_HOOK, [ $this, 'run_sweep' ] );
        add_action( 'init', [ $this, 'ensure_schedule' ] );
        add_action( 'woocommerce_order_status_changed', [ $this, 'reset_meta_on_status_change' ], 10, 4 );
    }

    public function add_cron_schedules( $schedules ) {
        if ( ! is_array( $schedules ) ) {
            $schedules = [];
        }

        $schedules[ self::SCHEDULE_5_MIN ] = [
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display'  => __( 'Every 5 minutes', 'wc-bouncer-whatsapp' ),
        ];
        $schedules[ self::SCHEDULE_10_MIN ] = [
            'interval' => 10 * MINUTE_IN_SECONDS,
            'display'  => __( 'Every 10 minutes', 'wc-bouncer-whatsapp' ),
        ];
        $schedules[ self::SCHEDULE_15_MIN ] = [
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display'  => __( 'Every 15 minutes', 'wc-bouncer-whatsapp' ),
        ];

        return $schedules;
    }

    public function ensure_schedule(): void {
        $config        = $this->get_config();
        $desired       = $this->schedule_slug_for_interval( (int) $config['sweep_interval_minutes'] );
        $next_scheduled = wp_next_scheduled( self::CRON_HOOK );

        if ( ! $next_scheduled ) {
            wp_schedule_event( time() + MINUTE_IN_SECONDS, $desired, self::CRON_HOOK );
            return;
        }

        // If the user changed the interval, reschedule.
        $existing = wp_get_schedule( self::CRON_HOOK );
        if ( $existing !== $desired ) {
            wp_unschedule_event( $next_scheduled, self::CRON_HOOK );
            wp_schedule_event( time() + MINUTE_IN_SECONDS, $desired, self::CRON_HOOK );
        }
    }

    public function clear_schedule(): void {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
        }
    }

    /**
     * Reset the dedup meta when an order transitions out of a tracked status,
     * so a later re-entry (e.g. failed -> pending -> failed) can re-fire.
     *
     * @param int      $order_id
     * @param string   $from
     * @param string   $to
     * @param WC_Order $order
     */
    public function reset_meta_on_status_change( $order_id, $from, $to, $order ): void {
        if ( ! $order instanceof WC_Order ) {
            return;
        }

        $changed = false;
        foreach ( self::TRACKED_STATUSES as $status ) {
            if ( $from === $status && $to !== $status ) {
                $meta_key = self::META_PREFIX . $status;
                if ( '' !== (string) $order->get_meta( $meta_key, true ) ) {
                    $order->delete_meta_data( $meta_key );
                    $changed = true;
                }
            }
        }

        if ( $changed ) {
            $order->save();
        }
    }

    /**
     * Cron entry point. Runs one sweep across all tracked statuses.
     *
     * @return array{notified:array<string,int>,errors:array<string,int>,skipped:string}
     */
    public function run_sweep( bool $dry_run = false ): array {
        $result = [
            'notified' => [],
            'errors'   => [],
            'skipped'  => '',
        ];

        $config = $this->get_config();

        if ( empty( $config['enabled'] ) ) {
            $result['skipped'] = 'disabled';
            return $result;
        }

        $webhook = $this->get_webhook_credentials();
        if ( '' === $webhook['url'] || '' === $webhook['secret'] ) {
            $result['skipped'] = 'missing_webhook_credentials';
            return $result;
        }

        if ( ! function_exists( 'wc_get_orders' ) ) {
            $result['skipped'] = 'woocommerce_unavailable';
            return $result;
        }

        foreach ( self::TRACKED_STATUSES as $status ) {
            $result['notified'][ $status ] = 0;
            $result['errors'][ $status ]   = 0;

            $track_key     = 'track_' . str_replace( '-', '_', $status );
            $threshold_key = 'threshold_' . str_replace( '-', '_', $status );

            if ( empty( $config[ $track_key ] ) ) {
                continue;
            }

            $threshold_minutes = (int) ( $config[ $threshold_key ] ?? 0 );
            if ( $threshold_minutes <= 0 ) {
                continue;
            }

            $orders = $this->find_candidates(
                $status,
                $threshold_minutes,
                ! empty( $config['skip_subscriptions'] )
            );

            foreach ( $orders as $order ) {
                if ( ! $order instanceof WC_Order ) {
                    continue;
                }

                if ( $dry_run ) {
                    $result['notified'][ $status ]++;
                    continue;
                }

                $response = $this->dispatcher->dispatch(
                    $order,
                    $status,
                    $webhook['url'],
                    $webhook['secret']
                );

                if ( $response['success'] ) {
                    $order->update_meta_data( self::META_PREFIX . $status, current_time( 'mysql' ) );
                    $order->save();
                    $result['notified'][ $status ]++;
                } else {
                    $result['errors'][ $status ]++;
                }
            }
        }

        return $result;
    }

    /**
     * Build a read-only view of orders currently in tracked statuses.
     * Used by the admin "Activity" tab. Always runs the query regardless of
     * the `enabled` flag so ops can see what would be picked up.
     *
     * @param int $per_status How many orders to surface per status (oldest first).
     *
     * @return array<string, array{
     *     status: string,
     *     label: string,
     *     tracked: bool,
     *     threshold: int,
     *     event_slug: string,
     *     orders: array<int, array{
     *         id: int,
     *         number: string,
     *         customer: string,
     *         phone: string,
     *         total: string,
     *         date_modified: string,
     *         age_minutes: int,
     *         notified_at: string,
     *         state: string,
     *     }>
     * }>
     */
    public function get_pipeline( int $per_status = 25 ): array {
        $config       = $this->get_config();
        $status_meta  = [
            'pending' => __( 'Pending payment', 'wc-bouncer-whatsapp' ),
            'on-hold' => __( 'On hold', 'wc-bouncer-whatsapp' ),
            'failed'  => __( 'Failed', 'wc-bouncer-whatsapp' ),
        ];
        $pipeline     = [];

        if ( ! function_exists( 'wc_get_orders' ) ) {
            return $pipeline;
        }

        $now = time();

        foreach ( self::TRACKED_STATUSES as $status ) {
            $track_key     = 'track_' . str_replace( '-', '_', $status );
            $threshold_key = 'threshold_' . str_replace( '-', '_', $status );
            $threshold     = (int) ( $config[ $threshold_key ] ?? 0 );
            $is_tracked    = ! empty( $config[ $track_key ] ) && $threshold > 0;

            $orders = wc_get_orders(
                [
                    'status'  => $status,
                    'limit'   => max( 1, $per_status ),
                    'orderby' => 'date_modified',
                    'order'   => 'ASC',
                    'return'  => 'objects',
                ]
            );

            $rows = [];
            if ( is_array( $orders ) ) {
                foreach ( $orders as $order ) {
                    if ( ! $order instanceof WC_Order ) {
                        continue;
                    }

                    $modified = $order->get_date_modified();
                    if ( ! $modified ) {
                        $modified = $order->get_date_created();
                    }
                    $modified_ts = $modified ? (int) $modified->getTimestamp() : 0;
                    $age_minutes = $modified_ts > 0 ? (int) floor( ( $now - $modified_ts ) / MINUTE_IN_SECONDS ) : 0;

                    $notified_at = (string) $order->get_meta( self::META_PREFIX . $status, true );

                    if ( '' !== $notified_at ) {
                        $state = 'notified';
                    } elseif ( ! $is_tracked ) {
                        $state = 'untracked';
                    } elseif ( $age_minutes >= $threshold ) {
                        $state = 'due';
                    } else {
                        $state = 'waiting';
                    }

                    $first = $order->get_billing_first_name();
                    $last  = $order->get_billing_last_name();
                    $name  = trim( $first . ' ' . $last );
                    if ( '' === $name ) {
                        $name = __( 'Guest', 'wc-bouncer-whatsapp' );
                    }

                    $rows[] = [
                        'id'            => (int) $order->get_id(),
                        'number'        => (string) $order->get_order_number(),
                        'customer'      => $name,
                        'phone'         => (string) $order->get_billing_phone(),
                        'total'         => function_exists( 'wp_strip_all_tags' )
                            ? wp_strip_all_tags( wc_price( $order->get_total(), [ 'currency' => $order->get_currency() ] ) )
                            : (string) $order->get_total(),
                        'date_modified' => $modified ? $modified->date_i18n( 'Y-m-d H:i' ) : '',
                        'age_minutes'   => $age_minutes,
                        'notified_at'   => $notified_at,
                        'state'         => $state,
                    ];
                }
            }

            $pipeline[ $status ] = [
                'status'     => $status,
                'label'      => $status_meta[ $status ] ?? $status,
                'tracked'    => $is_tracked,
                'threshold'  => $threshold,
                'event_slug' => AbandonedWebhookDispatcher::STATUS_EVENT_MAP[ $status ] ?? '',
                'orders'     => $rows,
            ];
        }

        return $pipeline;
    }

    /**
     * Send a single ad-hoc test webhook for a specific order (no dedup write).
     *
     * @return array{success:bool,code:int,body:string,event_slug:string}|array{success:false,code:0,body:string,event_slug:string}
     */
    public function send_test( int $order_id, string $status ): array {
        if ( ! in_array( $status, self::TRACKED_STATUSES, true ) ) {
            return [
                'success'    => false,
                'code'       => 0,
                'body'       => 'invalid_status',
                'event_slug' => '',
            ];
        }

        $webhook = $this->get_webhook_credentials();
        if ( '' === $webhook['url'] || '' === $webhook['secret'] ) {
            return [
                'success'    => false,
                'code'       => 0,
                'body'       => 'missing_webhook_credentials',
                'event_slug' => '',
            ];
        }

        $order = wc_get_order( $order_id );
        if ( ! $order instanceof WC_Order ) {
            return [
                'success'    => false,
                'code'       => 0,
                'body'       => 'order_not_found',
                'event_slug' => '',
            ];
        }

        return $this->dispatcher->dispatch(
            $order,
            $status,
            $webhook['url'],
            $webhook['secret']
        );
    }

    /**
     * Fetch orders stuck in $status past the cutoff that have not yet been
     * marked notified. Walks pages of `wc_get_orders` and filters dedup and
     * subscription renewals in PHP so the query stays compatible with both
     * HPOS and legacy (CPT) stores -- `wc_get_orders` only supports
     * `meta_query` on HPOS.
     *
     * @return WC_Order[]
     */
    private function find_candidates( string $status, int $threshold_minutes, bool $skip_subscriptions ): array {
        $cutoff   = time() - ( $threshold_minutes * MINUTE_IN_SECONDS );
        $meta_key = self::META_PREFIX . $status;

        $candidates  = [];
        $page        = 1;
        $page_size   = self::BATCH_LIMIT;
        $hard_cap    = self::BATCH_LIMIT * 10; // bound the work per tick

        while ( count( $candidates ) < self::BATCH_LIMIT ) {
            $args = [
                'status'        => $status,
                'limit'         => $page_size,
                'page'          => $page,
                'orderby'       => 'date_modified',
                'order'         => 'ASC',
                'date_modified' => '<' . $cutoff,
                'return'        => 'objects',
            ];

            $orders = wc_get_orders( $args );
            if ( ! is_array( $orders ) || empty( $orders ) ) {
                break;
            }

            foreach ( $orders as $order ) {
                if ( ! $order instanceof WC_Order ) {
                    continue;
                }
                if ( '' !== (string) $order->get_meta( $meta_key, true ) ) {
                    continue;
                }
                if ( $skip_subscriptions && $this->is_subscription_renewal( $order ) ) {
                    continue;
                }
                $candidates[] = $order;
                if ( count( $candidates ) >= self::BATCH_LIMIT ) {
                    break;
                }
            }

            // Stop if the page was not full (no more results) or we have hit the hard cap.
            if ( count( $orders ) < $page_size || ( $page * $page_size ) >= $hard_cap ) {
                break;
            }

            $page++;
        }

        return $candidates;
    }

    /**
     * Get the abandoned-orders config, merged with defaults.
     */
    public function get_config(): array {
        $stored = get_option( self::OPTION_NAME, [] );
        if ( ! is_array( $stored ) ) {
            $stored = [];
        }

        return array_merge( self::defaults(), $stored );
    }

    public function update_config( array $values ): void {
        $sanitized = $this->sanitize_config( $values );
        update_option( self::OPTION_NAME, $sanitized );
        // Reschedule in case the interval changed.
        $this->clear_schedule();
        $this->ensure_schedule();
    }

    public static function defaults(): array {
        return [
            'enabled'                => false,
            'track_pending'          => true,
            'threshold_pending'      => 30,
            'track_on_hold'          => true,
            'threshold_on_hold'      => 1440,
            'track_failed'           => true,
            'threshold_failed'       => 60,
            'sweep_interval_minutes' => 15,
            'skip_subscriptions'     => true,
        ];
    }

    /**
     * @return array{url:string,secret:string}
     */
    private function get_webhook_credentials(): array {
        $config = get_option( 'bouncer_webhook_config', [] );
        if ( ! is_array( $config ) ) {
            $config = [];
        }

        return [
            'url'    => isset( $config['webhook_url'] ) ? (string) $config['webhook_url'] : '',
            'secret' => isset( $config['webhook_secret'] ) ? (string) $config['webhook_secret'] : '',
        ];
    }

    private function is_subscription_renewal( WC_Order $order ): bool {
        if ( $order->get_parent_id() > 0 ) {
            return true;
        }
        $renewal_meta = (string) $order->get_meta( '_subscription_renewal', true );
        return '' !== $renewal_meta;
    }

    private function schedule_slug_for_interval( int $minutes ): string {
        if ( $minutes <= 5 ) {
            return self::SCHEDULE_5_MIN;
        }
        if ( $minutes <= 10 ) {
            return self::SCHEDULE_10_MIN;
        }
        return self::SCHEDULE_15_MIN;
    }

    private function sanitize_config( array $values ): array {
        $defaults = self::defaults();
        $data     = array_merge( $defaults, $values );

        $data['enabled']             = ! empty( $data['enabled'] );
        $data['track_pending']       = ! empty( $data['track_pending'] );
        $data['track_on_hold']       = ! empty( $data['track_on_hold'] );
        $data['track_failed']        = ! empty( $data['track_failed'] );
        $data['skip_subscriptions']  = ! empty( $data['skip_subscriptions'] );

        $data['threshold_pending'] = max( 0, (int) $data['threshold_pending'] );
        $data['threshold_on_hold'] = max( 0, (int) $data['threshold_on_hold'] );
        $data['threshold_failed']  = max( 0, (int) $data['threshold_failed'] );

        $allowed_intervals = [ 5, 10, 15 ];
        $interval = (int) $data['sweep_interval_minutes'];
        if ( ! in_array( $interval, $allowed_intervals, true ) ) {
            $interval = 15;
        }
        $data['sweep_interval_minutes'] = $interval;

        return $data;
    }
}
