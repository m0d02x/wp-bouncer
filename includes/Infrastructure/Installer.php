<?php

namespace Bouncer\WooCommerce\WhatsApp\Infrastructure;

use Bouncer\WooCommerce\WhatsApp\Repository\LogRepository;
use Bouncer\WooCommerce\WhatsApp\Service\LogRetention;
use Bouncer\WooCommerce\WhatsApp\Settings\Settings;

class Installer {
    public static function activate(): void {
        global $wpdb;

        $repository = new LogRepository( $wpdb );
        $repository->create_table();

        $retention = new LogRetention( $repository, new Settings() );
        $retention->ensure_schedule();
    }

    public static function deactivate(): void {
        global $wpdb;

        $repository = new LogRepository( $wpdb );
        $retention  = new LogRetention( $repository, new Settings() );
        $retention->clear_schedule();
    }
}
