<?php

namespace Bouncer\WooCommerce\WhatsApp\Infrastructure;

use Bouncer\WooCommerce\WhatsApp\Repository\LogRepository;
use Bouncer\WooCommerce\WhatsApp\Service\AbandonedOrdersScanner;
use Bouncer\WooCommerce\WhatsApp\Service\AbandonedWebhookDispatcher;
use Bouncer\WooCommerce\WhatsApp\Service\ApiClient;
use Bouncer\WooCommerce\WhatsApp\Service\EventSync;
use Bouncer\WooCommerce\WhatsApp\Service\LogRetention;
use Bouncer\WooCommerce\WhatsApp\Service\Logger;
use Bouncer\WooCommerce\WhatsApp\Settings\Settings;

class Installer {
    public static function activate(): void {
        global $wpdb;

        $repository = new LogRepository( $wpdb );
        $repository->create_table();

        $settings = new Settings();

        $retention = new LogRetention( $repository, $settings );
        $retention->ensure_schedule();

        $scanner = new AbandonedOrdersScanner( $settings, new AbandonedWebhookDispatcher( new Logger( $repository ) ) );
        $scanner->ensure_schedule();

        ( new EventSync( $settings, new ApiClient( $settings ) ) )->sync_on_activation();
    }

    public static function deactivate(): void {
        global $wpdb;

        $repository = new LogRepository( $wpdb );
        $settings   = new Settings();

        $retention = new LogRetention( $repository, $settings );
        $retention->clear_schedule();

        $scanner = new AbandonedOrdersScanner( $settings, new AbandonedWebhookDispatcher( new Logger( $repository ) ) );
        $scanner->clear_schedule();
    }
}
