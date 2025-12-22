<?php

namespace Bouncer\WooCommerce\WhatsApp\Service;

use Bouncer\WooCommerce\WhatsApp\Repository\LogRepository;
use Bouncer\WooCommerce\WhatsApp\Settings\Settings;

class LogRetention {
    public const CRON_HOOK = 'wc_bouncer_purge_logs';

    private LogRepository $repository;
    private Settings $settings;

    public function __construct( LogRepository $repository, Settings $settings ) {
        $this->repository = $repository;
        $this->settings   = $settings;
    }

    public function register(): void {
        add_action( self::CRON_HOOK, [ $this, 'purge' ] );
        add_action( 'init', [ $this, 'ensure_schedule' ] );
    }

    public function purge(): void {
        $days = (int) $this->settings->get( 'log_retention', 7 );
        $this->repository->delete_older_than( $days );
    }

    public function ensure_schedule(): void {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
        }
    }

    public function clear_schedule(): void {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
        }
    }
}
