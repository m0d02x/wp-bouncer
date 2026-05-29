<?php

namespace Bouncer\WooCommerce\WhatsApp;

use Bouncer\WooCommerce\WhatsApp\Admin\AbandonedOrdersPage;
use Bouncer\WooCommerce\WhatsApp\Admin\GeneralSettingsPage;
use Bouncer\WooCommerce\WhatsApp\Admin\LogsPage;
use Bouncer\WooCommerce\WhatsApp\Admin\WebhookConfigPage;
use Bouncer\WooCommerce\WhatsApp\Repository\LogRepository;
use Bouncer\WooCommerce\WhatsApp\Service\AbandonedOrdersScanner;
use Bouncer\WooCommerce\WhatsApp\Service\AbandonedWebhookDispatcher;
use Bouncer\WooCommerce\WhatsApp\Service\ApiClient;
use Bouncer\WooCommerce\WhatsApp\Service\EventSync;
use Bouncer\WooCommerce\WhatsApp\Service\LogRetention;
use Bouncer\WooCommerce\WhatsApp\Service\Logger;
use Bouncer\WooCommerce\WhatsApp\Service\MessageSender;
use Bouncer\WooCommerce\WhatsApp\Service\MetaKeyDiscovery;
use Bouncer\WooCommerce\WhatsApp\Service\PlaceholderResolver;
use Bouncer\WooCommerce\WhatsApp\Settings\Settings;

class Plugin {
    private Settings $settings;
    private GeneralSettingsPage $general_settings_page;
    private LogsPage $logs_page;
    private WebhookConfigPage $webhook_config_page;
    private AbandonedOrdersPage $abandoned_orders_page;
    private MessageSender $message_sender;
    private LogRetention $log_retention;
    private AbandonedOrdersScanner $abandoned_scanner;
    private EventSync $event_sync;

    public function __construct() {
        $this->settings = new Settings();

        global $wpdb;
        $repository = new LogRepository( $wpdb );

        $resolver               = new PlaceholderResolver();
        $meta_discovery         = new MetaKeyDiscovery();
        $api_client             = new ApiClient( $this->settings );
        $logger                      = new Logger( $repository );
        $this->general_settings_page = new GeneralSettingsPage( $this->settings, $api_client, $resolver, $meta_discovery, $logger );
        $this->webhook_config_page   = new WebhookConfigPage( $this->settings );
        $this->message_sender        = new MessageSender( $this->settings, $resolver, $api_client, $logger );
        $this->logs_page             = new LogsPage( $repository, $this->settings );
        $this->log_retention         = new LogRetention( $repository, $this->settings );

        $abandoned_dispatcher        = new AbandonedWebhookDispatcher( $logger );
        $this->abandoned_scanner     = new AbandonedOrdersScanner( $this->settings, $abandoned_dispatcher );
        $this->abandoned_orders_page = new AbandonedOrdersPage( $this->abandoned_scanner );
        $this->event_sync            = new EventSync( $this->settings, $api_client );
    }

    public function init(): void {
        add_action( 'init', [ $this, 'load_textdomain' ] );

        if ( is_admin() ) {
            $this->general_settings_page->register();
            $this->webhook_config_page->register();
            $this->logs_page->register();
            $this->abandoned_orders_page->register();
        }

        $this->message_sender->register();
        $this->log_retention->register();
        $this->abandoned_scanner->register();
        $this->event_sync->register();
    }

    public function load_textdomain(): void {
        load_plugin_textdomain( 'wc-bouncer-whatsapp', false, dirname( plugin_basename( WC_BOUNCER_WHATSAPP_PLUGIN_FILE ) ) . '/languages' );
    }

    public function settings(): Settings {
        return $this->settings;
    }
}
