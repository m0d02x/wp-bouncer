<?php

namespace Bouncer\WooCommerce\WhatsApp\Service;

use Bouncer\WooCommerce\WhatsApp\Settings\Settings;

class ApiClient {
    private Settings $settings;
    private string $base_url;

    public function __construct( Settings $settings, string $base_url = 'https://api.bouncer.my/api/v1' ) {
        $this->settings = $settings;

        // Use localhost API for local development
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && isset( $_SERVER['HTTP_HOST'] ) && 'local.local' === $_SERVER['HTTP_HOST'] ) {
            $base_url = 'http://localhost:3000/api/v1';
        }

        $this->base_url = rtrim( $base_url, '/' );
    }

    /**
     * Send WhatsApp text message through Bouncer API.
     */
    public function send_text( string $number, string $message, ?string $instance = null ): array {
        $endpoint = $this->base_url . '/message/sendText';
        $payload  = [
            'number' => $number,
            'text'   => $message,
        ];

        if ( ! empty( $instance ) ) {
            $payload['instance'] = $instance;
        }

        $headers = $this->build_headers();

        $response = wp_remote_post(
            $endpoint,
            [
                'headers' => $headers,
                'body'    => wp_json_encode( $payload ),
                'timeout' => 15,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return [
                'success'        => false,
                'response_code'  => 0,
                'response_body'  => $response->get_error_message(),
                'raw'            => $response,
            ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        return [
            'success'        => $code >= 200 && $code < 300,
            'response_code'  => $code,
            'response_body'  => $body,
            'raw'            => $response,
        ];
    }

    /**
     * Send WhatsApp Cloud API template message.
     */
    public function send_cloud_template( string $phone_number, string $template_name, array $variables = [], ?string $instance = null, string $language = 'en' ): array {
        $endpoint = $this->base_url . '/cloud/sendTemplate';

        $payload = [
            'phoneNumber'  => $phone_number,
            'templateName' => $template_name,
            'language'     => $language,
        ];

        if ( ! empty( $instance ) ) {
            $payload['instanceId'] = $instance;
        }

        if ( ! empty( $variables ) ) {
            $payload['variables'] = $variables;
        }

        $headers = $this->build_headers();

        $response = wp_remote_post(
            $endpoint,
            [
                'headers' => $headers,
                'body'    => wp_json_encode( $payload ),
                'timeout' => 15,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return [
                'success'        => false,
                'response_code'  => 0,
                'response_body'  => $response->get_error_message(),
                'raw'            => $response,
            ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        return [
            'success'        => $code >= 200 && $code < 300,
            'response_code'  => $code,
            'response_body'  => $body,
            'raw'            => $response,
        ];
    }

    /**
     * Fetch all instances for the authenticated organization.
     */
    public function get_instances( ?string $api_key = null ): array {
        $endpoint = $this->base_url . '/instances/';

        $headers = [
            'Content-Type' => 'application/json',
        ];

        $key = $api_key ?? $this->settings->get( 'api_key' );
        if ( $key ) {
            $headers['X-API-Key'] = $key;
        }

        $response = wp_remote_get(
            $endpoint,
            [
                'headers' => $headers,
                'timeout' => 10,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return [
                'success'        => false,
                'response_code'  => 0,
                'response_body'  => $response->get_error_message(),
                'raw'            => $response,
                'data'           => null,
            ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        return [
            'success'        => $code >= 200 && $code < 300,
            'response_code'  => $code,
            'response_body'  => $body,
            'raw'            => $response,
            'data'           => $data,
        ];
    }

    /**
     * Fetch Cloud API templates for a specific instance.
     */
    public function get_cloud_templates( string $instance_id, ?string $api_key = null ): array {
        $endpoint = $this->base_url . '/cloud/templates?' . http_build_query( [
            'instanceId' => $instance_id,
        ] );

        $headers = [
            'Content-Type' => 'application/json',
        ];

        $key = $api_key ?? $this->settings->get( 'api_key' );
        if ( $key ) {
            $headers['X-API-Key'] = $key;
        }

        $response = wp_remote_get(
            $endpoint,
            [
                'headers' => $headers,
                'timeout' => 15,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return [
                'success'        => false,
                'response_code'  => 0,
                'response_body'  => $response->get_error_message(),
                'raw'            => $response,
                'data'           => null,
            ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        return [
            'success'        => $code >= 200 && $code < 300,
            'response_code'  => $code,
            'response_body'  => $body,
            'raw'            => $response,
            'data'           => $data,
        ];
    }

    /**
     * Fetch a single Cloud API template by ID.
     */
    public function get_cloud_template( string $template_id, string $instance_id, ?string $api_key = null ): array {
        $endpoint = $this->base_url . '/cloud/templates/' . rawurlencode( $template_id ) . '?' . http_build_query( [
            'instanceId' => $instance_id,
        ] );

        $headers = [
            'Content-Type' => 'application/json',
        ];

        $key = $api_key ?? $this->settings->get( 'api_key' );
        if ( $key ) {
            $headers['X-API-Key'] = $key;
        }

        $response = wp_remote_get(
            $endpoint,
            [
                'headers' => $headers,
                'timeout' => 15,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return [
                'success'        => false,
                'response_code'  => 0,
                'response_body'  => $response->get_error_message(),
                'raw'            => $response,
                'data'           => null,
            ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        return [
            'success'        => $code >= 200 && $code < 300,
            'response_code'  => $code,
            'response_body'  => $body,
            'raw'            => $response,
            'data'           => $data,
        ];
    }

    public function get_instance_status( string $instance_id ): array {
        if ( '' === $instance_id ) {
            return [
                'success'        => false,
                'response_code'  => 0,
                'response_body'  => __( 'Instance ID is not configured.', 'wc-bouncer-whatsapp' ),
                'raw'            => null,
                'data'           => null,
            ];
        }

        $endpoint = $this->base_url . '/instances/' . rawurlencode( $instance_id );

        $response = wp_remote_get(
            $endpoint,
            [
                'headers' => $this->build_headers(),
                'timeout' => 10,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return [
                'success'        => false,
                'response_code'  => 0,
                'response_body'  => $response->get_error_message(),
                'raw'            => $response,
                'data'           => null,
            ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        return [
            'success'        => $code >= 200 && $code < 300,
            'response_code'  => $code,
            'response_body'  => $body,
            'raw'            => $response,
            'data'           => $data,
        ];
    }

    private function build_headers(): array {
        $headers = [
            'Content-Type' => 'application/json',
        ];

        $api_key = $this->settings->get( 'api_key' );
        if ( $api_key ) {
            $headers['X-API-Key'] = $api_key;
        }

        return $headers;
    }
}
