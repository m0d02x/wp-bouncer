<?php

namespace Bouncer\WooCommerce\WhatsApp\Settings;

class Settings {
    public const OPTION_NAME = 'wc_bouncer_whatsapp_settings';

    public function get_all(): array {
        $defaults = $this->defaults();
        $stored   = get_option( self::OPTION_NAME, [] );

        if ( ! is_array( $stored ) ) {
            $stored = [];
        }

        return array_merge( $defaults, $stored );
    }

    public function get( string $key, $default = null ) {
        $settings = $this->get_all();

        return $settings[ $key ] ?? $default;
    }

    public function update( array $values ): void {
        // Merge with current values first, so partial updates don't wipe existing data
        $current = $this->get_all();
        $merged = array_merge( $current, $values );
        $sanitized = $this->sanitize( wp_unslash( $merged ) );
        update_option( self::OPTION_NAME, $sanitized );
    }

    public function defaults(): array {
        return [
            'api_key'               => '',
            'instance_id'           => '',
            'instance_type'         => '',
            'integration_id'        => '',
            'wc_consumer_key'       => '',
            'wc_consumer_secret'    => '',
            'connection_status'     => '',
            'message_template'      => __( 'Hello {name}, your order {order_number} is now {status}. Total: {amount}.', 'wc-bouncer-whatsapp' ),
            'trigger_statuses'      => [],
            'log_retention'         => 7,
            'meta_keys'             => [],
            'status_templates'      => [],
            'funnelkit_enabled'     => false,
            'cloud_template_config' => [
                'status_template_map' => [],
                'template_variables'  => [],
                'template_languages'  => [],
            ],
        ];
    }

    private function sanitize( array $values ): array {
        $defaults = $this->defaults();

        $data = wp_parse_args( $values, $defaults );

        $data['api_key']            = sanitize_text_field( $data['api_key'] );
        $data['instance_id']        = sanitize_text_field( $data['instance_id'] );
        $data['instance_type']      = sanitize_key( $data['instance_type'] ?? '' );
        $data['integration_id']     = sanitize_text_field( $data['integration_id'] ?? '' );
        $data['wc_consumer_key']    = sanitize_text_field( $data['wc_consumer_key'] ?? '' );
        $data['wc_consumer_secret'] = sanitize_text_field( $data['wc_consumer_secret'] ?? '' );
        $data['connection_status']  = sanitize_key( $data['connection_status'] ?? '' );
        $data['message_template'] = wp_kses_post( $data['message_template'] );
        $data['log_retention']    = max( 1, absint( $data['log_retention'] ) );
        $data['funnelkit_enabled'] = ! empty( $data['funnelkit_enabled'] );

        $statuses = $values['trigger_statuses'] ?? [];
        $statuses = is_array( $statuses ) ? array_map( 'sanitize_key', $statuses ) : [];
        $data['trigger_statuses'] = array_values( array_unique( $statuses ) );

        $meta_keys = $values['meta_keys'] ?? [];
        $meta_keys = is_array( $meta_keys ) ? array_map( [ $this, 'sanitize_meta_key' ], $meta_keys ) : [];
        $data['meta_keys'] = array_values( array_unique( $meta_keys ) );

        $status_templates = [];
        if ( isset( $values['status_templates'] ) && is_array( $values['status_templates'] ) ) {
            foreach ( $values['status_templates'] as $status => $template ) {
                $status_key = sanitize_key( $status );
                if ( '' === $status_key ) {
                    continue;
                }

                $status_templates[ $status_key ] = wp_kses_post( $template );
            }
        }

        $data['status_templates'] = $status_templates;

        // Sanitize cloud template config
        $cloud_config = [
            'status_template_map' => [],
            'template_variables'  => [],
            'template_languages'  => [],
        ];

        if ( isset( $values['cloud_template_config'] ) && is_array( $values['cloud_template_config'] ) ) {
            // Status to template mapping
            if ( isset( $values['cloud_template_config']['status_template_map'] ) && is_array( $values['cloud_template_config']['status_template_map'] ) ) {
                foreach ( $values['cloud_template_config']['status_template_map'] as $status => $template_name ) {
                    $status_key = sanitize_key( $status );
                    if ( '' !== $status_key && '' !== trim( $template_name ) ) {
                        $cloud_config['status_template_map'][ $status_key ] = sanitize_text_field( $template_name );
                    }
                }
            }

            // Template variables mapping
            if ( isset( $values['cloud_template_config']['template_variables'] ) && is_array( $values['cloud_template_config']['template_variables'] ) ) {
                foreach ( $values['cloud_template_config']['template_variables'] as $template_name => $variables ) {
                    $safe_name = sanitize_text_field( $template_name );
                    if ( '' === $safe_name || ! is_array( $variables ) ) {
                        continue;
                    }

                    $cloud_config['template_variables'][ $safe_name ] = [];
                    foreach ( $variables as $index => $placeholder ) {
                        $idx = absint( $index );
                        if ( $idx > 0 && '' !== trim( $placeholder ) ) {
                            $cloud_config['template_variables'][ $safe_name ][ $idx ] = sanitize_text_field( $placeholder );
                        }
                    }
                }
            }

            if ( isset( $values['cloud_template_config']['template_languages'] ) && is_array( $values['cloud_template_config']['template_languages'] ) ) {
                foreach ( $values['cloud_template_config']['template_languages'] as $template_name => $language ) {
                    $safe_name     = sanitize_text_field( $template_name );
                    $safe_language = sanitize_text_field( $language );

                    if ( '' === $safe_name || '' === $safe_language ) {
                        continue;
                    }

                    $cloud_config['template_languages'][ $safe_name ] = $safe_language;
                }
            }
        }

        $data['cloud_template_config'] = $cloud_config;

        return $data;
    }

    private function sanitize_meta_key( string $key ): string {
        $key = sanitize_text_field( $key );

        return preg_replace( '/[^A-Za-z0-9_:\-]/', '', $key );
    }
}
