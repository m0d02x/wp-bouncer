<?php

namespace Bouncer\WooCommerce\WhatsApp\Admin;

use Bouncer\WooCommerce\WhatsApp\Service\AbandonedOrdersScanner;
use Bouncer\WooCommerce\WhatsApp\Service\CartBounty\CartBountySender;
use Bouncer\WooCommerce\WhatsApp\Settings\Settings;

class AbandonedOrdersPage {
    public const MENU_SLUG = 'wc-bouncer-abandoned-orders';

    private AbandonedOrdersScanner $scanner;
    private Settings $settings;
    private CartBountySender $cartbounty_sender;
    private array $state = [];

    public function __construct( AbandonedOrdersScanner $scanner, Settings $settings, CartBountySender $cartbounty_sender ) {
        $this->scanner           = $scanner;
        $this->settings          = $settings;
        $this->cartbounty_sender = $cartbounty_sender;
    }

    public function register(): void {
        add_action( 'admin_menu', [ $this, 'add_menu' ], 20 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function enqueue_assets( string $hook ): void {
        if ( 'bouncer-whatsapp_page_' . self::MENU_SLUG !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'wc-bouncer-admin',
            WC_BOUNCER_WHATSAPP_PLUGIN_URL . 'assets/css/admin.css',
            [],
            WC_BOUNCER_WHATSAPP_VERSION
        );
    }

    public function add_menu(): void {
        add_submenu_page(
            GeneralSettingsPage::MENU_SLUG,
            __( 'Abandoned Orders', 'wc-bouncer-whatsapp' ),
            __( 'Abandoned Orders', 'wc-bouncer-whatsapp' ),
            'manage_woocommerce',
            self::MENU_SLUG,
            [ $this, 'render_page' ]
        );
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'wc-bouncer-whatsapp' ) );
        }

        if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === strtoupper( (string) wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) {
            $this->handle_post();
        }

        $config             = $this->scanner->get_config();
        $webhook_config     = get_option( 'bouncer_webhook_config', [] );
        $webhook_configured = ! empty( $webhook_config['webhook_url'] ) && ! empty( $webhook_config['webhook_secret'] );
        $next_scheduled     = wp_next_scheduled( AbandonedOrdersScanner::CRON_HOOK );
        $sweep_result       = $this->state['sweep_result'] ?? null;
        $test_result        = $this->state['test_result'] ?? null;
        $pipeline = $this->scanner->get_pipeline( 25 );

        $cartbounty_settings = [
            'enabled' => (bool) $this->settings->get( 'cartbounty_enabled', false ),
            'steps'   => (array) $this->settings->get( 'cartbounty_steps', [] ),
        ];
        global $wpdb;
        $cartbounty_table_exists = (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = %s',
                $wpdb->prefix . 'cartbounty'
            )
        ) > 0;

        $active_tab = $this->state['active_tab']
            ?? ( isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'activity' );
        $allowed_tabs = [ 'activity', 'settings', 'sweep', 'test' ];
        if ( ! in_array( $active_tab, $allowed_tabs, true ) ) {
            $active_tab = 'activity';
        }

        include WC_BOUNCER_WHATSAPP_PLUGIN_DIR . 'views/abandoned-orders-page.php';
    }

    private function handle_post(): void {
        $action = isset( $_POST['wc_bouncer_abandoned_action'] )
            ? sanitize_key( wp_unslash( $_POST['wc_bouncer_abandoned_action'] ) )
            : '';

        switch ( $action ) {
            case 'save':
                $this->handle_save();
                break;
            case 'run_sweep':
                $this->handle_run_sweep( false );
                break;
            case 'dry_run':
                $this->handle_run_sweep( true );
                break;
            case 'send_test':
                $this->handle_send_test();
                break;
        }
    }

    private function handle_save(): void {
        check_admin_referer( 'wc_bouncer_abandoned_save' );

        $values = [
            'enabled'                => ! empty( $_POST['enabled'] ),
            'track_pending'          => ! empty( $_POST['track_pending'] ),
            'threshold_pending'      => absint( $_POST['threshold_pending'] ?? 0 ),
            'track_on_hold'          => ! empty( $_POST['track_on_hold'] ),
            'threshold_on_hold'      => absint( $_POST['threshold_on_hold'] ?? 0 ),
            'track_failed'           => ! empty( $_POST['track_failed'] ),
            'threshold_failed'       => absint( $_POST['threshold_failed'] ?? 0 ),
            'sweep_interval_minutes' => absint( $_POST['sweep_interval_minutes'] ?? 15 ),
            'skip_subscriptions'     => ! empty( $_POST['skip_subscriptions'] ),
        ];

        $this->scanner->update_config( $values );

        // Save CartBounty polling settings to the main Bouncer settings option.
        $bouncer_current = $this->settings->get_all();
        $bouncer_current['cartbounty_enabled'] = ! empty( $_POST['cartbounty_polling_enabled'] );
        $bouncer_current['cartbounty_steps'] = array_filter(
            array_map( 'absint', (array) ( $_POST['cartbounty_steps'] ?? [] ) ),
            static fn( $step ) => $step >= 1 && $step <= 3
        );
        $this->settings->update( $bouncer_current );

        // PRG to prevent re-submit on refresh.
        $redirect = add_query_arg(
            [ 'page' => self::MENU_SLUG, 'abandoned_saved' => '1' ],
            admin_url( 'admin.php' )
        );
        wp_safe_redirect( $redirect );
        exit;
    }

    private function handle_run_sweep( bool $dry_run ): void {
        check_admin_referer( 'wc_bouncer_abandoned_sweep' );

        $this->state['sweep_result'] = [
            'dry_run'           => $dry_run,
            'result'            => $this->scanner->run_sweep( $dry_run ),
            'cartbounty_result' => $this->cartbounty_sender->run_sweep( $dry_run ),
            'cartbounty_preview' => $this->cartbounty_sender->get_due_cart_preview(),
        ];
        $this->state['active_tab'] = 'sweep';
    }

    private function handle_send_test(): void {
        check_admin_referer( 'wc_bouncer_abandoned_test' );

        $order_id = isset( $_POST['test_order_id'] ) ? absint( wp_unslash( $_POST['test_order_id'] ) ) : 0;
        $status   = isset( $_POST['test_status'] ) ? sanitize_key( wp_unslash( $_POST['test_status'] ) ) : 'pending';

        if ( ! in_array( $status, AbandonedOrdersScanner::TRACKED_STATUSES, true ) ) {
            $status = 'pending';
        }

        if ( $order_id <= 0 ) {
            // Pick the most recent order of that status.
            $orders = function_exists( 'wc_get_orders' )
                ? wc_get_orders( [ 'status' => $status, 'limit' => 1, 'orderby' => 'date', 'order' => 'DESC', 'return' => 'objects' ] )
                : [];

            if ( ! empty( $orders ) && is_array( $orders ) ) {
                $order_id = (int) $orders[0]->get_id();
            }
        }

        if ( $order_id <= 0 ) {
            $this->state['test_result'] = [
                'success'    => false,
                'code'       => 0,
                'body'       => __( 'No order ID provided and no recent orders found in that status.', 'wc-bouncer-whatsapp' ),
                'event_slug' => '',
                'order_id'   => 0,
                'status'     => $status,
            ];
            return;
        }

        $result             = $this->scanner->send_test( $order_id, $status );
        $result['order_id'] = $order_id;
        $result['status']   = $status;

        $this->state['test_result'] = $result;
        $this->state['active_tab']  = 'test';
    }
}
