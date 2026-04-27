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
        add_action( 'admin_post_wc_bouncer_purge_old_logs', [ $this, 'handle_purge_old_logs' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function enqueue_assets( string $hook ): void {
        if ( 'bouncer-whatsapp_page_wc-bouncer-whatsapp-logs' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'wc-bouncer-whatsapp-admin',
            WC_BOUNCER_WHATSAPP_PLUGIN_URL . 'assets/css/admin.css',
            [],
            WC_BOUNCER_WHATSAPP_VERSION
        );
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

        $retention      = (int) $this->settings->get( 'log_retention', 7 );
        $per_page       = 25;
        $total_logs     = $this->repository->count();
        $total_pages    = max( 1, (int) ceil( $total_logs / $per_page ) );
        $current_page   = min( max( 1, absint( $_GET['paged'] ?? 1 ) ), $total_pages );
        $offset         = ( $current_page - 1 ) * $per_page;
        $logs           = $this->repository->paginated( $per_page, $offset );
        $stats          = $this->repository->stats();
        $expired_count  = $this->repository->count_older_than( $retention );
        $table_name     = $this->repository->table_name();

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

    public function handle_purge_old_logs(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'wc-bouncer-whatsapp' ) );
        }

        check_admin_referer( 'wc_bouncer_purge_old_logs' );

        $retention = (int) $this->settings->get( 'log_retention', 7 );
        $this->repository->delete_older_than( $retention );

        wp_safe_redirect( add_query_arg( 'purged', 'true', wp_get_referer() ?: admin_url( 'admin.php?page=' . self::MENU_SLUG ) ) );
        exit;
    }
}
