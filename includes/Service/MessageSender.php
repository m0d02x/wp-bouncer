<?php

namespace Bouncer\WooCommerce\WhatsApp\Service;

use Bouncer\WooCommerce\WhatsApp\Settings\Settings;
use WC_Order;

class MessageSender {
    private Settings $settings;
    private PlaceholderResolver $resolver;
    private ApiClient $api_client;
    private LoggerInterface $logger;

    public function __construct(
        Settings $settings,
        PlaceholderResolver $resolver,
        ApiClient $api_client,
        LoggerInterface $logger
    ) {
        $this->settings  = $settings;
        $this->resolver  = $resolver;
        $this->api_client = $api_client;
        $this->logger    = $logger;
    }

    public function register(): void {
        add_action( 'woocommerce_order_status_changed', [ $this, 'maybe_send_message' ], 10, 4 );
    }

    public function maybe_send_message( int $order_id, string $old_status, string $new_status, $order ): void { // phpcs:ignore Squiz.Commenting.FunctionComment.InvalidNoReturn
        $instance_type = (string) $this->settings->get( 'instance_type', 'bouncer' );

        if ( 'cloud-api' === $instance_type ) {
            $this->send_cloud_template( $order_id, $new_status, $order );
        } else {
            $this->send_bouncer_text( $order_id, $new_status, $order );
        }
    }

    private function send_bouncer_text( int $order_id, string $new_status, $order ): void {
        $selected_statuses = (array) $this->settings->get( 'trigger_statuses', [] );
        $status_key        = 'wc-' . $new_status;

        if ( empty( $selected_statuses ) || ! in_array( $status_key, $selected_statuses, true ) ) {
            return;
        }

        if ( ! $order instanceof WC_Order ) {
            $order = wc_get_order( $order_id );
        }

        if ( ! $order ) {
            return;
        }

        $api_key = $this->settings->get( 'api_key', '' );
        if ( empty( $api_key ) ) {
            $order->add_order_note( __( 'Bouncer WhatsApp message skipped: API key not configured.', 'wc-bouncer-whatsapp' ) );
            return;
        }

        $phone = $this->normalize_phone( $order->get_billing_phone() );
        if ( empty( $phone ) ) {
            $order->add_order_note( __( 'Bouncer WhatsApp message skipped: customer phone number missing.', 'wc-bouncer-whatsapp' ) );
            return;
        }

        $templates = (array) $this->settings->get( 'status_templates', [] );
        $template  = $templates[ $status_key ] ?? '';

        if ( '' === trim( $template ) ) {
            $template = (string) $this->settings->get( 'message_template', '' );
        }

        if ( '' === trim( $template ) ) {
            $order->add_order_note( __( 'Bouncer WhatsApp message skipped: message template is empty.', 'wc-bouncer-whatsapp' ) );
            return;
        }

        $message = trim( $this->resolver->resolve( $template, $order ) );

        if ( '' === $message ) {
            $order->add_order_note( __( 'Bouncer WhatsApp message skipped: resolved message is empty.', 'wc-bouncer-whatsapp' ) );
            return;
        }

        $instance = (string) $this->settings->get( 'instance_id', '' );

        $response = $this->api_client->send_text( $phone, $message, $instance );

        $status        = $response['success'] ? 'sent' : 'failed';
        $response_code = (int) ( $response['response_code'] ?? 0 );
        $response_body = (string) ( $response['response_body'] ?? '' );

        $this->logger->record( $order->get_id(), $phone, $message, $status, $response_code, $response_body );

        if ( $response['success'] ) {
            $order->add_order_note( sprintf( /* translators: %s: phone number */ __( 'Bouncer WhatsApp message sent to %s.', 'wc-bouncer-whatsapp' ), $phone ) );
        } else {
            $order->add_order_note( sprintf( /* translators: 1: phone, 2: response code */ __( 'Bouncer WhatsApp message failed to %1$s (HTTP %2$s).', 'wc-bouncer-whatsapp' ), $phone, $response_code ?: 'n/a' ) );
        }
    }

    private function send_cloud_template( int $order_id, string $new_status, $order ): void {
        $cloud_config      = (array) $this->settings->get( 'cloud_template_config', [] );
        $status_template_map = $cloud_config['status_template_map'] ?? [];
        $template_variables  = $cloud_config['template_variables'] ?? [];
        $status_key        = 'wc-' . $new_status;

        // Check if this status has a template mapped
        $template_name = $status_template_map[ $status_key ] ?? '';
        if ( empty( $template_name ) ) {
            return;
        }

        if ( ! $order instanceof WC_Order ) {
            $order = wc_get_order( $order_id );
        }

        if ( ! $order ) {
            return;
        }

        $api_key = $this->settings->get( 'api_key', '' );
        if ( empty( $api_key ) ) {
            $order->add_order_note( __( 'Bouncer WhatsApp template skipped: API key not configured.', 'wc-bouncer-whatsapp' ) );
            return;
        }

        $phone = $this->normalize_phone( $order->get_billing_phone() );
        if ( empty( $phone ) ) {
            $order->add_order_note( __( 'Bouncer WhatsApp template skipped: customer phone number missing.', 'wc-bouncer-whatsapp' ) );
            return;
        }

        $instance = (string) $this->settings->get( 'instance_id', '' );

        // Build variables from mappings
        $variables     = [];
        $var_mappings  = $template_variables[ $template_name ] ?? [];
        foreach ( $var_mappings as $index => $placeholder ) {
            if ( ! empty( $placeholder ) ) {
                $resolved                   = $this->resolver->resolve_placeholder( $placeholder, $order );
                $variables[ (string) $index ] = $resolved;
            }
        }

        // Get template language (default to 'en')
        $language = $cloud_config['template_languages'][ $template_name ] ?? 'en';

        $response = $this->api_client->send_cloud_template( $phone, $template_name, $variables, $instance, $language );

        $status        = $response['success'] ? 'sent' : 'failed';
        $response_code = (int) ( $response['response_code'] ?? 0 );
        $response_body = (string) ( $response['response_body'] ?? '' );

        $log_message = sprintf( 'Template: %s', $template_name );
        $this->logger->record( $order->get_id(), $phone, $log_message, $status, $response_code, $response_body );

        if ( $response['success'] ) {
            $order->add_order_note( sprintf( /* translators: 1: template name, 2: phone number */ __( 'Bouncer WhatsApp template "%1$s" sent to %2$s.', 'wc-bouncer-whatsapp' ), $template_name, $phone ) );
        } else {
            $order->add_order_note( sprintf( /* translators: 1: template name, 2: phone, 3: response code */ __( 'Bouncer WhatsApp template "%1$s" failed to %2$s (HTTP %3$s).', 'wc-bouncer-whatsapp' ), $template_name, $phone, $response_code ?: 'n/a' ) );
        }
    }

    private function normalize_phone( string $phone ): string {
        $digits = preg_replace( '/[^0-9+]/', '', $phone );

        if ( ! $digits ) {
            return '';
        }

        if ( strpos( $digits, '+' ) !== 0 && strpos( $digits, '00' ) === 0 ) {
            $digits = '+' . substr( $digits, 2 );
        }

        return $digits;
    }
}
