<?php

namespace Bouncer\WooCommerce\WhatsApp\Service;

use WC_Order;

class PlaceholderResolver {
    /**
     * Resolve a single placeholder to its value.
     */
    public function resolve_placeholder( string $placeholder, WC_Order $order ): string {
        // Handle meta placeholders
        if ( preg_match( '/^\{meta:([^}]+)\}$/', $placeholder, $matches ) ) {
            $meta_key = $matches[1];
            $value    = $order->get_meta( $meta_key, true );

            if ( is_array( $value ) || is_object( $value ) ) {
                $value = wp_json_encode( $value );
            }

            return sanitize_text_field( (string) ( $value ?? '' ) );
        }

        // Handle standard placeholders
        $replacements = $this->build_replacements( $order );

        return $replacements[ $placeholder ] ?? $placeholder;
    }

    public function resolve( string $template, WC_Order $order ): string {
        $placeholders = $this->build_replacements( $order );
        $message      = strtr( $template, $placeholders );

        return preg_replace_callback(
            '/\{meta:([^}]+)\}/',
            static function ( array $matches ) use ( $order ) {
                $meta_key = $matches[1];
                $value    = $order->get_meta( $meta_key, true );

                if ( is_array( $value ) || is_object( $value ) ) {
                    $value = wp_json_encode( $value );
                }

                if ( null === $value || '' === $value ) {
                    return '';
                }

                return sanitize_text_field( (string) $value );
            },
            $message
        );
    }

    private function build_replacements( WC_Order $order ): array {
        $currency = $order->get_currency();
        $total    = $order->get_total();
        $price    = function_exists( 'wc_price' )
            ? wp_strip_all_tags( html_entity_decode( wc_price( $total, [ 'currency' => $currency ] ) ) )
            : number_format_i18n( (float) $total, 2 );

        $shipping_methods = $this->format_shipping_methods( $order );
        $order_items      = $this->format_order_items( $order );

        $first_name = $order->get_billing_first_name();
        $last_name  = $order->get_billing_last_name();
        $full_name  = trim( sprintf( '%s %s', $first_name, $last_name ) );

        $order_date = $order->get_date_created();
        $order_date_formatted = $order_date ? $order_date->date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) : '';

        $billing_address  = $this->format_address( $order, 'billing' );
        $shipping_address = $this->format_address( $order, 'shipping' );

        return [
            '{order_id}'         => (string) $order->get_id(),
            '{order_number}'     => (string) $order->get_order_number(),
            '{status}'           => (string) $order->get_status(),
            '{order_status}'     => (string) wc_get_order_status_name( $order->get_status() ),
            '{order_date}'       => $order_date_formatted,
            '{amount}'           => $price,
            '{order_total}'      => $price,
            '{currency}'         => (string) $currency,
            '{order_items}'      => $order_items,
            '{payment_method}'   => (string) $order->get_payment_method_title(),
            '{shipping_method}'  => $shipping_methods,
            '{billing_address}'  => $billing_address,
            '{shipping_address}' => $shipping_address,
            '{name}'             => $full_name ?: trim( $first_name ),
            '{first_name}'       => (string) $first_name,
            '{last_name}'        => (string) $last_name,
            '{email}'            => (string) $order->get_billing_email(),
            '{phone}'            => (string) $order->get_billing_phone(),
        ];
    }

    private function format_address( WC_Order $order, string $type = 'billing' ): string {
        if ( 'shipping' === $type ) {
            $parts = array_filter( [
                $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
                $order->get_shipping_address_1(),
                $order->get_shipping_address_2(),
                $order->get_shipping_city(),
                $order->get_shipping_state(),
                $order->get_shipping_postcode(),
                $order->get_shipping_country(),
            ] );
        } else {
            $parts = array_filter( [
                $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                $order->get_billing_address_1(),
                $order->get_billing_address_2(),
                $order->get_billing_city(),
                $order->get_billing_state(),
                $order->get_billing_postcode(),
                $order->get_billing_country(),
            ] );
        }

        return implode( ', ', array_map( 'trim', $parts ) );
    }

    private function format_order_items( WC_Order $order ): string {
        $items = $order->get_items();
        $lines = [];

        foreach ( $items as $item ) {
            $name = $item->get_name();
            $qty  = $item->get_quantity();
            if ( $name ) {
                $lines[] = $qty > 1 ? "{$qty}x {$name}" : $name;
            }
        }

        return implode( "\n", $lines );
    }

    private function format_shipping_methods( WC_Order $order ): string {
        $items   = $order->get_items( 'shipping' );
        $methods = [];

        foreach ( $items as $item ) {
            $name = $item->get_name();
            if ( $name ) {
                $methods[] = $name;
            }
        }

        if ( empty( $methods ) ) {
            $methods[] = __( 'Default shipping', 'wc-bouncer-whatsapp' );
        }

        return implode( ', ', array_unique( $methods ) );
    }
}
