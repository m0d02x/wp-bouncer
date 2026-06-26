<?php

namespace Bouncer\WooCommerce\WhatsApp\Service\CartBounty;

class CartBountyCartRepository {
    private const DEFAULT_WAITING_TIME_MINUTES = 60;
    private const MIN_WAITING_TIME_MINUTES     = 20;

    private const DEFAULT_STEP_INTERVALS_MS = [
        1 => 300000,
        2 => 86400000,
        3 => 172800000,
    ];

    private $db;

    private ?bool $table_exists = null;

    public function __construct( $db = null ) {
        if ( null === $db ) {
            global $wpdb;
            $db = $wpdb;
        }

        $this->db = $db;
    }

    public function table_exists(): bool {
        if ( null !== $this->table_exists ) {
            return $this->table_exists;
        }

        $table = $this->get_table_name();

        $found = $this->db->get_var(
            $this->db->prepare(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = %s',
                $table
            )
        );

        $this->table_exists = ( (int) $found > 0 );

        return $this->table_exists;
    }

    public function is_available(): bool {
        return $this->table_exists();
    }

    /** @return array{plugin_active:bool, table_exists:bool, table:string} */
    public function get_status(): array {
        $table_exists = $this->table_exists();

        return [
            'plugin_active' => $table_exists,
            'table_exists'  => $table_exists,
            'table'         => $this->get_table_name(),
        ];
    }

    /** @return array<int, array<string,mixed>> */
    public function find_due_carts_for_step( int $admin_step, int $limit = 10 ): array {
        $admin_step = $this->normalize_admin_step( $admin_step );
        $limit      = max( 1, min( 100, $limit ) );

        if ( ! $this->table_exists() ) {
            return [];
        }

        $steps_completed = $admin_step - 1;
        $cutoff          = $this->get_due_cutoff_mysql( $admin_step );
        $date_column     = ( 1 === $admin_step ) ? 'time' : 'wp_last_sent';
        $table           = $this->get_table_name();

        $rows = $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM `{$table}`
                WHERE type = 0
                  AND phone != ''
                  AND cart_contents != ''
                  AND wp_unsubscribed != 1
                  AND wp_complete != 1
                  AND wp_steps_completed = %d
                  AND `{$date_column}` < %s
                ORDER BY time ASC
                LIMIT %d",
                $steps_completed,
                $cutoff,
                $limit
            ),
            'ARRAY_A'
        );

        if ( ! is_array( $rows ) ) {
            return [];
        }

        return $rows;
    }

    /** @return array<string,mixed>|null */
    public function get_cart( int $cart_id ): ?array {
        if ( $cart_id <= 0 || ! $this->table_exists() ) {
            return null;
        }

        $table = $this->get_table_name();

        $row = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM `{$table}` WHERE id = %d LIMIT 1",
                $cart_id
            ),
            'ARRAY_A'
        );

        if ( ! is_array( $row ) ) {
            return null;
        }

        return $row;
    }

    public function get_step_interval_seconds( int $admin_step ): int {
        $admin_step  = $this->normalize_admin_step( $admin_step );
        $interval_ms = self::DEFAULT_STEP_INTERVALS_MS[ $admin_step ];
        $steps       = $this->get_option_value( 'cartbounty_automation_steps', [] );

        if ( is_array( $steps ) && isset( $steps[ $admin_step ] ) && is_array( $steps[ $admin_step ] ) ) {
            $configured_interval = $steps[ $admin_step ]['interval'] ?? null;

            if ( is_numeric( $configured_interval ) && (int) $configured_interval > 0 ) {
                $interval_ms = (int) $configured_interval;
            }
        }

        return max( 1, (int) floor( $interval_ms / 1000 ) );
    }

    public function get_waiting_time_minutes(): int {
        $waiting_time = $this->get_option_value( 'cartbounty_waiting_time', self::DEFAULT_WAITING_TIME_MINUTES );
        $waiting_time = is_numeric( $waiting_time ) ? (int) $waiting_time : self::DEFAULT_WAITING_TIME_MINUTES;
        $waiting_time = max( self::MIN_WAITING_TIME_MINUTES, $waiting_time );

        $waiting_time = $this->apply_filter_value( 'cartbounty_waiting_time', $waiting_time );
        $waiting_time = is_numeric( $waiting_time ) ? (int) $waiting_time : self::DEFAULT_WAITING_TIME_MINUTES;

        return max( self::MIN_WAITING_TIME_MINUTES, $waiting_time );
    }

    private function get_table_name(): string {
        return $this->db->prefix . 'cartbounty';
    }

    private function normalize_admin_step( int $admin_step ): int {
        if ( $admin_step < 1 ) {
            return 1;
        }

        if ( $admin_step > 3 ) {
            return 3;
        }

        return $admin_step;
    }

    private function get_due_cutoff_mysql( int $admin_step ): string {
        $now_timestamp = strtotime( $this->get_current_utc_mysql_time() );

        if ( false === $now_timestamp ) {
            $now_timestamp = time();
        }

        $interval_seconds = $this->get_step_interval_seconds( $admin_step );

        if ( 1 === $admin_step ) {
            $interval_seconds += $this->get_waiting_time_minutes() * 60;
        }

        return gmdate( 'Y-m-d H:i:s', $now_timestamp - $interval_seconds );
    }

    private function get_option_value( string $option_name, $default ) {
        if ( ! function_exists( 'get_option' ) ) {
            return $default;
        }

        return \get_option( $option_name, $default );
    }

    private function apply_filter_value( string $filter_name, $value ) {
        if ( ! function_exists( 'apply_filters' ) ) {
            return $value;
        }

        return \apply_filters( $filter_name, $value );
    }

    private function get_current_utc_mysql_time(): string {
        if ( function_exists( 'current_time' ) ) {
            return \current_time( 'mysql', true );
        }

        return gmdate( 'Y-m-d H:i:s' );
    }
}
