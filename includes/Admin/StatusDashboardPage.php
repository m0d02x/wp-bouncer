<?php

namespace Bouncer\WooCommerce\WhatsApp\Admin;

use Bouncer\WooCommerce\WhatsApp\Repository\LogRepository;
use Bouncer\WooCommerce\WhatsApp\Service\ApiClient;
use Bouncer\WooCommerce\WhatsApp\Settings\Settings;

class StatusDashboardPage {
    private const MENU_SLUG = 'wc-bouncer-whatsapp-status';

    private Settings $settings;
    private ApiClient $api_client;
    private LogRepository $logs;

    public function __construct( Settings $settings, ApiClient $api_client, LogRepository $logs ) {
        $this->settings   = $settings;
        $this->api_client = $api_client;
        $this->logs       = $logs;
    }

    public function register(): void {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
    }

    public function add_menu(): void {
        add_submenu_page(
            GeneralSettingsPage::MENU_SLUG,
            __( 'Bouncer WhatsApp Status', 'wc-bouncer-whatsapp' ),
            __( 'Status', 'wc-bouncer-whatsapp' ),
            'manage_woocommerce',
            self::MENU_SLUG,
            [ $this, 'render_page' ]
        );
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'wc-bouncer-whatsapp' ) );
        }

        $instance_id   = (string) $this->settings->get( 'instance_id', '' );
        $instance_data = null;

        if ( '' !== $instance_id ) {
            $instance_data = $this->api_client->get_instance_status( $instance_id );
        }

        $log_stats = $this->logs->stats();

        include WC_BOUNCER_WHATSAPP_PLUGIN_DIR . 'views/status-dashboard-page.php';
    }
}
