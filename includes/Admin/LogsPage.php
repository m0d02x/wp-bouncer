<?php

namespace Bouncer\WooCommerce\WhatsApp\Admin;

use Bouncer\WooCommerce\WhatsApp\Repository\LogRepository;
use Bouncer\WooCommerce\WhatsApp\Settings\Settings;

class LogsPage {
    private const MENU_SLUG = 'wc-bouncer-whatsapp-logs';

    private LogRepository $repository;
    private Settings $settings;

    public function __construct( LogRepository $repository, Settings $settings ) {
        $this->repository = $repository;
        $this->settings   = $settings;
    }

    public function register(): void {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_post_wc_bouncer_clear_logs', [ $this, 'handle_clear_logs' ] );
    }

    public function add_menu(): void {
        add_submenu_page(
            GeneralSettingsPage::MENU_SLUG,
            __( 'Bouncer Logs', 'wc-bouncer-whatsapp' ),
            __( 'Logs', 'wc-bouncer-whatsapp' ),
            'manage_woocommerce',
            self::MENU_SLUG,
            [ $this, 'render_page' ]
        );
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'wc-bouncer-whatsapp' ) );
        }

        $logs        = $this->repository->latest( 100 );
        $retention   = (int) $this->settings->get( 'log_retention', 7 );
        $table_name  = $this->repository->table_name();

        include WC_BOUNCER_WHATSAPP_PLUGIN_DIR . 'views/logs-page.php';
    }

    public function handle_clear_logs(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'wc-bouncer-whatsapp' ) );
        }

        check_admin_referer( 'wc_bouncer_clear_logs' );

        $this->repository->truncate();

        wp_safe_redirect( add_query_arg( 'cleared', 'true', wp_get_referer() ?: admin_url( 'admin.php?page=' . self::MENU_SLUG ) ) );
        exit;
    }
}
