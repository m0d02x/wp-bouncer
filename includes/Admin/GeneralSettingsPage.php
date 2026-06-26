<?php

namespace Bouncer\WooCommerce\WhatsApp\Admin;

use Bouncer\WooCommerce\WhatsApp\Service\ApiClient;
use Bouncer\WooCommerce\WhatsApp\Service\LoggerInterface;
use Bouncer\WooCommerce\WhatsApp\Service\MetaKeyDiscovery;
use Bouncer\WooCommerce\WhatsApp\Service\PlaceholderResolver;
use Bouncer\WooCommerce\WhatsApp\Settings\Settings;
use WC_Order;

class GeneralSettingsPage {
    public const MENU_SLUG = 'wc-bouncer-whatsapp';

    private Settings $settings;
    private ApiClient $api_client;
    private PlaceholderResolver $resolver;
    private MetaKeyDiscovery $meta_discovery;
    private LoggerInterface $logger;
    private array $state = [];

    public function __construct( Settings $settings, ApiClient $api_client, PlaceholderResolver $resolver, MetaKeyDiscovery $meta_discovery, LoggerInterface $logger ) {
        $this->settings       = $settings;
        $this->api_client     = $api_client;
        $this->resolver       = $resolver;
        $this->meta_discovery = $meta_discovery;
        $this->logger         = $logger;
    }

    public function register(): void {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_wc_bouncer_fetch_instances', [ $this, 'ajax_fetch_instances' ] );
        add_action( 'wp_ajax_wc_bouncer_fetch_templates', [ $this, 'ajax_fetch_templates' ] );
        add_action( 'wp_ajax_wc_bouncer_fetch_template', [ $this, 'ajax_fetch_template' ] );
        add_action( 'wp_ajax_wc_bouncer_preview_template', [ $this, 'ajax_preview_template' ] );
    }

    public function enqueue_assets( string $hook ): void {
        if ( 'toplevel_page_' . self::MENU_SLUG !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'wc-bouncer-admin',
            WC_BOUNCER_WHATSAPP_PLUGIN_URL . 'assets/css/admin.css',
            [],
            WC_BOUNCER_WHATSAPP_VERSION
        );
    }

    public function ajax_fetch_instances(): void {
        check_ajax_referer( 'wc_bouncer_fetch_instances', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'wc-bouncer-whatsapp' ) ] );
        }

        $api_key = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );

        if ( '' === $api_key ) {
            wp_send_json_error( [ 'message' => __( 'API key is required.', 'wc-bouncer-whatsapp' ) ] );
        }

        $response = $this->api_client->get_instances( $api_key );

        if ( ! $response['success'] ) {
            wp_send_json_error( [
                'message' => __( 'Failed to fetch instances.', 'wc-bouncer-whatsapp' ),
                'code'    => $response['response_code'],
                'body'    => $response['response_body'],
            ] );
        }

        $instances = [];
        $data      = $response['data'];

        $items = $data['instances'] ?? $data;

        if ( is_array( $items ) ) {
            foreach ( $items as $instance ) {
                if ( isset( $instance['id'] ) ) {
                    $instances[] = [
                        'id'          => $instance['id'],
                        'name'        => $instance['name'] ?? $instance['id'],
                        'phoneNumber' => $instance['phoneNumber'] ?? null,
                        'type'        => $instance['type'] ?? 'bouncer',
                        'status'      => $instance['status'] ?? 'unknown',
                    ];
                }
            }
        }

        wp_send_json_success( [ 'instances' => $instances ] );
    }

    public function ajax_fetch_templates(): void {
        check_ajax_referer( 'wc_bouncer_fetch_templates', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'wc-bouncer-whatsapp' ) ] );
        }

        $api_key     = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );
        $instance_id = sanitize_text_field( wp_unslash( $_POST['instance_id'] ?? '' ) );

        if ( '' === $api_key ) {
            wp_send_json_error( [ 'message' => __( 'API key is required.', 'wc-bouncer-whatsapp' ) ] );
        }

        if ( '' === $instance_id ) {
            wp_send_json_error( [ 'message' => __( 'Instance ID is required.', 'wc-bouncer-whatsapp' ) ] );
        }

        $response = $this->api_client->get_cloud_templates( $instance_id, $api_key );

        if ( ! $response['success'] ) {
            wp_send_json_error( [
                'message' => __( 'Failed to fetch templates.', 'wc-bouncer-whatsapp' ),
                'code'    => $response['response_code'],
                'body'    => $response['response_body'],
            ] );
        }

        $templates = [];
        $data      = $response['data'];

        $items = $data['templates'] ?? $data;

        if ( is_array( $items ) ) {
            foreach ( $items as $template ) {
                if ( isset( $template['name'] ) ) {
                    // Extract body text from components
                    $body_text = '';
                    if ( ! empty( $template['components'] ) && is_array( $template['components'] ) ) {
                        foreach ( $template['components'] as $component ) {
                            if ( isset( $component['type'] ) && 'BODY' === $component['type'] ) {
                                $body_text = $component['text'] ?? '';
                                break;
                            }
                        }
                    }

                    $templates[] = [
                        'id'       => $template['id'] ?? $template['name'],
                        'name'     => $template['name'],
                        'status'   => $template['status'] ?? 'unknown',
                        'category' => $template['category'] ?? '',
                        'language' => $template['language'] ?? '',
                        'content'  => $body_text,
                    ];
                }
            }
        }

        wp_send_json_success( [ 'templates' => $templates ] );
    }

    public function ajax_fetch_template(): void {
        check_ajax_referer( 'wc_bouncer_fetch_template', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'wc-bouncer-whatsapp' ) ] );
        }

        $api_key     = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );
        $instance_id = sanitize_text_field( wp_unslash( $_POST['instance_id'] ?? '' ) );
        $template_id = sanitize_text_field( wp_unslash( $_POST['template_id'] ?? '' ) );

        if ( '' === $api_key || '' === $instance_id || '' === $template_id ) {
            wp_send_json_error( [ 'message' => __( 'Missing required parameters.', 'wc-bouncer-whatsapp' ) ] );
        }

        $response = $this->api_client->get_cloud_template( $template_id, $instance_id, $api_key );

        if ( ! $response['success'] ) {
            wp_send_json_error( [
                'message' => __( 'Failed to fetch template.', 'wc-bouncer-whatsapp' ),
                'code'    => $response['response_code'],
                'body'    => $response['response_body'],
            ] );
        }

        $data = $response['data'];

        // The API might return the template directly or nested in a 'template' key
        $template_data = $data['template'] ?? $data;

        // Extract body text from components
        $body_text = '';
        if ( ! empty( $template_data['components'] ) && is_array( $template_data['components'] ) ) {
            foreach ( $template_data['components'] as $component ) {
                if ( isset( $component['type'] ) && 'BODY' === $component['type'] ) {
                    $body_text = $component['text'] ?? '';
                    break;
                }
            }
        }

        wp_send_json_success( [
            'template' => [
                'id'      => $template_data['id'] ?? $template_data['name'] ?? $template_id,
                'name'    => $template_data['name'] ?? $template_id,
                'content' => $body_text,
            ],
        ] );
    }

    public function ajax_preview_template(): void {
        check_ajax_referer( 'wc_bouncer_preview_template', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized', 'wc-bouncer-whatsapp' ) ] );
        }

        $template_name    = sanitize_text_field( wp_unslash( $_POST['template_name'] ?? '' ) );
        $template_content = wp_kses_post( wp_unslash( $_POST['template_content'] ?? '' ) );
        $order_id         = absint( wp_unslash( $_POST['order_id'] ?? 0 ) );

        if ( '' === $template_name || '' === $template_content || 0 === $order_id ) {
            wp_send_json_error( [ 'message' => __( 'Missing required parameters.', 'wc-bouncer-whatsapp' ) ] );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_send_json_error( [ 'message' => __( 'Order not found.', 'wc-bouncer-whatsapp' ) ] );
        }

        // Get variable mappings from settings
        $cloud_config       = $this->settings->get( 'cloud_template_config', [] );
        $template_variables = $cloud_config['template_variables'][ $template_name ] ?? [];

        // Resolve variables and replace in template
        $preview_text  = $template_content;
        $resolved_vars = [];
        foreach ( $template_variables as $index => $placeholder ) {
            if ( ! empty( $placeholder ) ) {
                $resolved                = $this->resolver->resolve_placeholder( $placeholder, $order );
                $resolved_vars[ $index ] = [
                    'placeholder' => $placeholder,
                    'resolved'    => $resolved,
                ];
                $preview_text = str_replace( '{{' . $index . '}}', $resolved, $preview_text );
            }
        }

        wp_send_json_success( [
            'preview'       => $preview_text,
            'original'      => $template_content,
            'template_name' => $template_name,
            'mappings'      => $template_variables,
            'resolved'      => $resolved_vars,
        ] );
    }

    public function add_menu(): void {
        add_menu_page(
            __( 'Bouncer WhatsApp', 'wc-bouncer-whatsapp' ),
            __( 'Bouncer WhatsApp', 'wc-bouncer-whatsapp' ),
            'manage_woocommerce',
            self::MENU_SLUG,
            [ $this, 'render_page' ],
            'dashicons-format-chat',
            56
        );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'Bouncer WhatsApp Settings', 'wc-bouncer-whatsapp' ),
            __( 'Settings', 'wc-bouncer-whatsapp' ),
            'manage_woocommerce',
            self::MENU_SLUG,
            [ $this, 'render_page' ]
        );
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'wc-bouncer-whatsapp' ) );
        }

        if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
            $this->handle_post();
        }

        $settings       = $this->settings->get_all();
        $recent_orders  = $this->get_recent_orders();
        $selected_id    = isset( $_POST['preview_order_id'] ) ? absint( wp_unslash( $_POST['preview_order_id'] ) ) : 0;
        $preview        = $this->state['preview'] ?? null;
        $test_result    = $this->state['test_result'] ?? null;
        $health_result  = $this->state['health_result'] ?? null;
        $discovered_meta_keys = $this->meta_discovery->discover( 50 );
        $cartbounty_status    = $this->get_cartbounty_status();

        include WC_BOUNCER_WHATSAPP_PLUGIN_DIR . 'views/general-settings-page.php';
    }

    private function get_cartbounty_status(): array {
        global $wpdb;

        $table  = $wpdb->prefix . 'cartbounty';
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = %s',
                $table
            )
        );

        return [
            'available'     => (int) $exists > 0,
            'table'         => $table,
            'plugin_active' => class_exists( 'CartBounty', false ) || defined( 'CARTBOUNTY_VERSION' ),
        ];
    }

    private function handle_post(): void {
        $action = $_POST['wc_bouncer_action'] ?? '';

        switch ( $action ) {
            case 'save_general':
                $this->handle_save_general();
                break;
            case 'send_test':
                $this->handle_send_test();
                break;
            case 'health_check':
                $this->handle_health_check();
                break;
            case 'preview':
                $this->handle_preview( false );
                break;
            case 'send_preview':
                $this->handle_preview( true );
                break;
            case 'save_templates':
                $this->handle_save_templates();
                break;
            case 'save_automation':
                $this->handle_save_automation();
                break;
            case 'save_integrations':
                $this->handle_save_integrations();
                break;
        }
    }

    private function handle_save_integrations(): void {
        check_admin_referer( 'wc_bouncer_save_integrations' );

        $current = $this->settings->get_all();
        $current['funnelkit_enabled'] = ! empty( $_POST['funnelkit_enabled'] );
        $current['cartbounty_enabled'] = ! empty( $_POST['cartbounty_enabled'] );

        $this->settings->update( $current );

        $redirect = menu_page_url( self::MENU_SLUG, false );
        if ( empty( $redirect ) ) {
            $redirect = admin_url( 'admin.php?page=' . self::MENU_SLUG );
        }

        $redirect = add_query_arg( 'general_saved', 'true', $redirect );
        $redirect .= '#integrations';
        wp_safe_redirect( $redirect );
        exit;
    }

    private function handle_save_templates(): void {
        check_admin_referer( 'wc_bouncer_save_templates' );

        $posted_cloud_config   = $_POST['cloud_template_config'] ?? [];
        $current               = $this->settings->get_all();
        $current_cloud_config  = $current['cloud_template_config'] ?? [];

        if ( isset( $posted_cloud_config['template_variables'] ) ) {
            $current_cloud_config['template_variables'] = $posted_cloud_config['template_variables'];
        }

        $current['cloud_template_config'] = $current_cloud_config;

        $this->settings->update( $current );

        $redirect = menu_page_url( self::MENU_SLUG, false );
        if ( empty( $redirect ) ) {
            $redirect = admin_url( 'admin.php?page=' . self::MENU_SLUG );
        }

        $redirect = add_query_arg( 'general_saved', 'true', $redirect );
        $redirect .= '#templates';
        wp_safe_redirect( $redirect );
        exit;
    }

    private function handle_save_automation(): void {
        check_admin_referer( 'wc_bouncer_save_automation' );

        $current       = $this->settings->get_all();
        $instance_type = sanitize_key( $_POST['instance_type'] ?? '' );

        if ( 'cloud-api' === $instance_type ) {
            // Save Cloud API status-to-template mapping
            $cloud_config = $current['cloud_template_config'] ?? [];

            $status_template_map = [];
            if ( ! empty( $_POST['cloud_template_config']['status_template_map'] ) && is_array( $_POST['cloud_template_config']['status_template_map'] ) ) {
                foreach ( $_POST['cloud_template_config']['status_template_map'] as $status => $template_name ) {
                    $status_key = sanitize_key( $status );
                    if ( '' !== $status_key && '' !== trim( $template_name ) ) {
                        $status_template_map[ $status_key ] = sanitize_text_field( $template_name );
                    }
                }
            }
            $cloud_config['status_template_map'] = $status_template_map;

            // Save template languages
            if ( ! empty( $_POST['cloud_template_config']['template_languages'] ) && is_array( $_POST['cloud_template_config']['template_languages'] ) ) {
                $template_languages = [];
                foreach ( $_POST['cloud_template_config']['template_languages'] as $tpl_name => $lang ) {
                    $template_languages[ sanitize_text_field( $tpl_name ) ] = sanitize_text_field( $lang );
                }
                $cloud_config['template_languages'] = $template_languages;
            }

            $current['cloud_template_config'] = $cloud_config;
        } else {
            // Save Bouncer-type settings
            $current['message_template'] = wp_kses_post( wp_unslash( $_POST['message_template'] ?? '' ) );
            $current['trigger_statuses'] = array_map( 'sanitize_text_field', (array) ( $_POST['trigger_statuses'] ?? [] ) );

            $status_templates = [];
            if ( ! empty( $_POST['status_templates'] ) && is_array( $_POST['status_templates'] ) ) {
                foreach ( $_POST['status_templates'] as $status => $template ) {
                    $status_templates[ sanitize_key( $status ) ] = wp_kses_post( wp_unslash( $template ) );
                }
            }
            $current['status_templates'] = $status_templates;
        }

        // CartBounty step templates (bouncer instance type)
        $cartbounty_status_templates = [];
        if ( ! empty( $_POST['cartbounty_status_templates'] ) && is_array( $_POST['cartbounty_status_templates'] ) ) {
            foreach ( $_POST['cartbounty_status_templates'] as $step => $template ) {
                $step_key = absint( $step );
                if ( $step_key >= 1 && $step_key <= 3 ) {
                    $cartbounty_status_templates[ $step_key ] = wp_kses_post( wp_unslash( $template ) );
                }
            }
        }
        $current['cartbounty_status_templates'] = $cartbounty_status_templates;

        // CartBounty cloud config (cloud-api instance type)
        $cartbounty_cloud_config = $current['cartbounty_cloud_config'] ?? [];
        if ( ! empty( $_POST['cartbounty_cloud_config']['step_template_map'] ) && is_array( $_POST['cartbounty_cloud_config']['step_template_map'] ) ) {
            $step_map = [];
            foreach ( $_POST['cartbounty_cloud_config']['step_template_map'] as $step => $tpl ) {
                $step_key = absint( $step );
                if ( $step_key >= 1 && $step_key <= 3 && '' !== trim( $tpl ) ) {
                    $step_map[ $step_key ] = sanitize_text_field( $tpl );
                }
            }
            $cartbounty_cloud_config['step_template_map'] = $step_map;
        }
        if ( ! empty( $_POST['cartbounty_cloud_config']['template_languages'] ) && is_array( $_POST['cartbounty_cloud_config']['template_languages'] ) ) {
            $langs = [];
            foreach ( $_POST['cartbounty_cloud_config']['template_languages'] as $tpl => $lang ) {
                $langs[ sanitize_text_field( $tpl ) ] = sanitize_text_field( $lang );
            }
            $cartbounty_cloud_config['template_languages'] = $langs;
        }
        $current['cartbounty_cloud_config'] = $cartbounty_cloud_config;

        $this->settings->update( $current );

        $redirect = menu_page_url( self::MENU_SLUG, false );
        if ( empty( $redirect ) ) {
            $redirect = admin_url( 'admin.php?page=' . self::MENU_SLUG );
        }

        $redirect = add_query_arg( 'general_saved', 'true', $redirect );
        $redirect .= '#automation';
        wp_safe_redirect( $redirect );
        exit;
    }

    private function handle_save_general(): void {
        check_admin_referer( 'wc_bouncer_save_general' );

        $api_key       = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );
        $instance_id   = sanitize_text_field( wp_unslash( $_POST['instance_id'] ?? '' ) );
        $instance_type = sanitize_key( wp_unslash( $_POST['instance_type'] ?? '' ) );
        $log_retention = absint( wp_unslash( $_POST['log_retention'] ?? 7 ) );
        $log_retention = max( 1, $log_retention );

        $current                     = $this->settings->get_all();
        $current['api_key']          = $api_key;
        $current['instance_id']      = $instance_id;
        $current['instance_type']    = $instance_type;
        $current['log_retention']    = $log_retention;

        $this->settings->update( $current );

        $redirect = menu_page_url( self::MENU_SLUG, false );
        if ( empty( $redirect ) ) {
            $redirect = admin_url( 'admin.php?page=' . self::MENU_SLUG );
        }

        $redirect = add_query_arg( 'general_saved', 'true', $redirect );
        wp_safe_redirect( $redirect );
        exit;
    }

    private function handle_send_test(): void {
        check_admin_referer( 'wc_bouncer_send_test' );

        $test_type = sanitize_key( wp_unslash( $_POST['test_type'] ?? 'bouncer' ) );
        $instance  = (string) $this->settings->get( 'instance_id', '' );

        if ( 'cloud-api' === $test_type ) {
            // Cloud API - send template
            $template_name     = sanitize_text_field( wp_unslash( $_POST['test_template'] ?? '' ) );
            $template_language = sanitize_text_field( wp_unslash( $_POST['test_template_language'] ?? 'en' ) );
            $order_id          = absint( wp_unslash( $_POST['test_order'] ?? 0 ) );

            if ( '' === $template_name ) {
                $this->state['test_result'] = [
                    'success' => false,
                    'message' => __( 'Template is required for Cloud API.', 'wc-bouncer-whatsapp' ),
                ];
                return;
            }

            if ( ! $order_id ) {
                $this->state['test_result'] = [
                    'success' => false,
                    'message' => __( 'Order is required to test template with real data.', 'wc-bouncer-whatsapp' ),
                ];
                return;
            }

            $order = wc_get_order( $order_id );
            if ( ! $order ) {
                $this->state['test_result'] = [
                    'success' => false,
                    'message' => __( 'Order not found.', 'wc-bouncer-whatsapp' ),
                ];
                return;
            }

            // Get phone from order
            $phone = $order->get_billing_phone();
            if ( '' === $phone ) {
                $this->state['test_result'] = [
                    'success' => false,
                    'message' => __( 'Order has no billing phone number.', 'wc-bouncer-whatsapp' ),
                ];
                return;
            }

            // Get template variable mappings from settings
            $cloud_config       = $this->settings->get( 'cloud_template_config', [] );
            $template_variables = $cloud_config['template_variables'][ $template_name ] ?? [];

            // Build variables object (keys are "1", "2", etc.)
            $variables = [];
            foreach ( $template_variables as $index => $placeholder ) {
                if ( ! empty( $placeholder ) ) {
                    $resolved              = $this->resolver->resolve_placeholder( $placeholder, $order );
                    $variables[ (string) $index ] = $resolved;
                }
            }

            $response = $this->api_client->send_cloud_template( $phone, $template_name, $variables, $instance, $template_language );

            // Log test message
            $status        = $response['success'] ? 'success' : 'failed';
            $response_code = (int) ( $response['response_code'] ?? 0 );
            $response_body = (string) ( $response['response_body'] ?? '' );
            $log_message   = sprintf( '[TEST] Template: %s', $template_name );
            $this->logger->record( $order_id, $phone, $log_message, $status, $response_code, $response_body );

            $this->state['test_result'] = [
                'success' => $response['success'],
                'code'    => $response_code,
                'body'    => $response_body,
            ];
        } else {
            // Bouncer - send text message
            $phone   = sanitize_text_field( wp_unslash( $_POST['test_phone'] ?? '' ) );
            $message = wp_kses_post( wp_unslash( $_POST['test_message'] ?? '' ) );

            if ( '' === $phone ) {
                $this->state['test_result'] = [
                    'success' => false,
                    'message' => __( 'Phone number is required.', 'wc-bouncer-whatsapp' ),
                ];
                return;
            }

            if ( '' === trim( $message ) ) {
                $this->state['test_result'] = [
                    'success' => false,
                    'message' => __( 'Message is required.', 'wc-bouncer-whatsapp' ),
                ];
                return;
            }

            $response = $this->api_client->send_text( $phone, wp_strip_all_tags( $message ), $instance );

            // Log test message
            $status        = $response['success'] ? 'success' : 'failed';
            $response_code = (int) ( $response['response_code'] ?? 0 );
            $response_body = (string) ( $response['response_body'] ?? '' );
            $log_message   = '[TEST] ' . mb_strimwidth( $message, 0, 100, '...' );
            $this->logger->record( 0, $phone, $log_message, $status, $response_code, $response_body );

            $this->state['test_result'] = [
                'success' => $response['success'],
                'code'    => $response_code,
                'body'    => $response_body,
            ];
        }
    }

    private function handle_health_check(): void {
        check_admin_referer( 'wc_bouncer_health_check' );

        $instance = sanitize_text_field( wp_unslash( $_POST['health_instance'] ?? '' ) );
        if ( '' === $instance ) {
            $instance = (string) $this->settings->get( 'instance_id', '' );
        }

        $response = $this->api_client->get_instance_status( $instance );

        $this->state['health_result'] = [
            'success' => $response['success'],
            'code'    => $response['response_code'] ?? 0,
            'body'    => $response['response_body'] ?? '',
            'data'    => $response['data'] ?? null,
        ];
    }

    private function handle_preview( bool $send_to_customer ): void {
        check_admin_referer( 'wc_bouncer_preview' );

        $order_id = absint( wp_unslash( $_POST['preview_order_id'] ?? 0 ) );
        if ( ! $order_id ) {
            $this->state['preview'] = [
                'error' => __( 'Select an order to preview.', 'wc-bouncer-whatsapp' ),
            ];

            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order instanceof WC_Order ) {
            $this->state['preview'] = [
                'error' => __( 'The selected order could not be loaded.', 'wc-bouncer-whatsapp' ),
            ];

            return;
        }

        $status_key = 'wc-' . $order->get_status();
        $templates  = (array) $this->settings->get( 'status_templates', [] );
        $template   = $templates[ $status_key ] ?? (string) $this->settings->get( 'message_template', '' );
        $message  = trim( $this->resolver->resolve( $template, $order ) );
        $meta     = $this->collect_meta( $order );

        $preview_data = [
            'order_id'      => $order_id,
            'order_number'  => $order->get_order_number(),
            'message'       => $message,
            'meta'          => $meta,
            'phone'         => $order->get_billing_phone(),
            'status'        => $order->get_status(),
            'send_success'  => null,
            'send_response' => null,
        ];

        if ( $send_to_customer ) {
            $phone = $order->get_billing_phone();
            if ( '' === $phone ) {
                $preview_data['send_success']  = false;
                $preview_data['send_response'] = __( 'Customer phone number is missing.', 'wc-bouncer-whatsapp' );
            } elseif ( '' === $message ) {
                $preview_data['send_success']  = false;
                $preview_data['send_response'] = __( 'Resolved message is empty.', 'wc-bouncer-whatsapp' );
            } else {
                $instance  = (string) $this->settings->get( 'instance_id', '' );
                $response  = $this->api_client->send_text( $phone, $message, $instance );
                $preview_data['send_success']  = $response['success'];
                $preview_data['send_response'] = $response['response_body'] ?? '';
            }
        }

        $this->state['preview'] = $preview_data;
    }

    private function get_recent_orders(): array {
        if ( ! function_exists( 'wc_get_orders' ) ) {
            return [];
        }

        $orders = wc_get_orders(
            [
                'limit'   => 20,
                'orderby' => 'date',
                'order'   => 'DESC',
            ]
        );

        $list = [];
        foreach ( $orders as $order ) {
            if ( ! $order instanceof WC_Order ) {
                continue;
            }

            $first_name = $order->get_billing_first_name();
            $last_name  = $order->get_billing_last_name();
            $full_name  = trim( $first_name . ' ' . $last_name );

            $list[] = [
                'id'           => $order->get_id(),
                'number'       => $order->get_order_number(),
                'status'       => $order->get_status(),
                'name'         => $full_name ?: __( 'Guest', 'wc-bouncer-whatsapp' ),
                'phone'        => $order->get_billing_phone(),
                'date_created' => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'Y-m-d H:i' ) : '',
            ];
        }

        return $list;
    }

    private function collect_meta( WC_Order $order ): array {
        $meta_data = [];

        foreach ( $order->get_meta_data() as $meta ) {
            $value = $meta->value;
            if ( is_array( $value ) || is_object( $value ) ) {
                $value = wp_json_encode( $value );
            }

            $meta_data[ $meta->key ] = (string) $value;
        }

        return $meta_data;
    }
}
