<?php

namespace Bouncer\WooCommerce\WhatsApp\Service\CartBounty;

class CartBountyPlaceholderResolver {
    /**
     * Resolve all placeholders in a template string using cart data.
     *
     * @param string $template Template text with {placeholder} tokens
     * @param array  $cart     CartBounty cart row (associative array from DB)
     * @return string          Template with all placeholders resolved
     */
    public function resolve( string $template, array $cart ): string {
        $placeholders = $this->build_replacements( $cart );
        $message      = strtr( $template, $placeholders );

        // Handle dynamic meta placeholders
        $message = preg_replace_callback(
            '/\{cart_meta:([^}]+)\}/',
            static function ( array $matches ) use ( $cart ) {
                $key   = $matches[1];
                $meta  = maybe_unserialize( $cart['cart_meta'] ?? '' );

                if ( ! is_array( $meta ) ) {
                    return '';
                }

                $value = $meta[ $key ] ?? '';

                if ( is_array( $value ) || is_object( $value ) ) {
                    $value = wp_json_encode( $value );
                }

                return sanitize_text_field( (string) ( $value ?? '' ) );
            },
            $message
        );

        $message = preg_replace_callback(
            '/\{other_field:([^}]+)\}/',
            static function ( array $matches ) use ( $cart ) {
                $key    = $matches[1];
                $fields = maybe_unserialize( $cart['other_fields'] ?? '' );

                if ( ! is_array( $fields ) ) {
                    return '';
                }

                $value = $fields[ $key ] ?? '';

                if ( is_array( $value ) || is_object( $value ) ) {
                    $value = wp_json_encode( $value );
                }

                return sanitize_text_field( (string) ( $value ?? '' ) );
            },
            $message
        );

        $message = preg_replace_callback(
            '/\{meta:([^}]+)\}/',
            static function ( array $matches ) use ( $cart ) {
                $key   = $matches[1];
                $meta  = maybe_unserialize( $cart['cart_meta'] ?? '' );

                // Fallback to other_fields if not found in cart_meta
                if ( ! is_array( $meta ) || ! array_key_exists( $key, $meta ) ) {
                    $fields = maybe_unserialize( $cart['other_fields'] ?? '' );
                    if ( is_array( $fields ) && array_key_exists( $key, $fields ) ) {
                        $meta = $fields;
                    }
                }

                if ( ! is_array( $meta ) ) {
                    return '';
                }

                $value = $meta[ $key ] ?? '';

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

        return $message;
    }

    /**
     * Resolve a single placeholder token.
     *
     * @param string $placeholder e.g. "first_name" (without braces) or "cart_meta:custom_key"
     * @param array  $cart
     * @return string
     */
    public function resolve_placeholder( string $placeholder, array $cart ): string {
        // Handle dynamic meta placeholders
        if ( preg_match( '/^\{cart_meta:([^}]+)\}$/', $placeholder, $matches ) ) {
            $key  = $matches[1];
            $meta = maybe_unserialize( $cart['cart_meta'] ?? '' );

            if ( ! is_array( $meta ) ) {
                return '';
            }

            $value = $meta[ $key ] ?? '';

            if ( is_array( $value ) || is_object( $value ) ) {
                $value = wp_json_encode( $value );
            }

            return sanitize_text_field( (string) ( $value ?? '' ) );
        }

        if ( preg_match( '/^\{other_field:([^}]+)\}$/', $placeholder, $matches ) ) {
            $key    = $matches[1];
            $fields = maybe_unserialize( $cart['other_fields'] ?? '' );

            if ( ! is_array( $fields ) ) {
                return '';
            }

            $value = $fields[ $key ] ?? '';

            if ( is_array( $value ) || is_object( $value ) ) {
                $value = wp_json_encode( $value );
            }

            return sanitize_text_field( (string) ( $value ?? '' ) );
        }

        if ( preg_match( '/^\{meta:([^}]+)\}$/', $placeholder, $matches ) ) {
            $key   = $matches[1];
            $meta  = maybe_unserialize( $cart['cart_meta'] ?? '' );

            if ( ! is_array( $meta ) || ! array_key_exists( $key, $meta ) ) {
                $fields = maybe_unserialize( $cart['other_fields'] ?? '' );
                if ( is_array( $fields ) && array_key_exists( $key, $fields ) ) {
                    $meta = $fields;
                }
            }

            if ( ! is_array( $meta ) ) {
                return '';
            }

            $value = $meta[ $key ] ?? '';

            if ( is_array( $value ) || is_object( $value ) ) {
                $value = wp_json_encode( $value );
            }

            if ( null === $value || '' === $value ) {
                return '';
            }

            return sanitize_text_field( (string) $value );
        }

        // Handle standard placeholders
        $replacements = $this->build_replacements( $cart );

        return $replacements[ $placeholder ] ?? $placeholder;
    }

    /**
     * Build the replacements map from cart data.
     *
     * @param array $cart CartBounty cart row
     * @return array Associative array of placeholder => value
     */
    private function build_replacements( array $cart ): array {
        $currency  = $cart['currency'] ?? '';
        $cart_total = $cart['cart_total'] ?? 0;

        $price = $this->format_price( $cart_total, $currency );

        $first_name = $cart['name'] ?? '';
        $last_name  = $cart['surname'] ?? '';
        $full_name  = trim( sprintf( '%s %s', $first_name, $last_name ) );

        $cart_date = $this->format_cart_date( $cart );

        $cart_items   = $this->format_cart_items( $cart );
        $location     = $this->get_location( $cart );

        return [
            '{cart_id}'        => (string) ( $cart['id'] ?? '' ),
            '{cart_total}'     => $price,
            '{amount}'         => $price,
            '{order_total}'    => $price,
            '{currency}'       => (string) $currency,
            '{cart_items}'     => $cart_items,
            '{order_items}'    => $cart_items,
            '{cart_hash}'      => (string) ( $cart['cart_hash'] ?? '' ),
            '{cart_date}'      => $cart_date,
            '{order_date}'     => $cart_date,
            '{abandoned_at}'   => $cart_date,
            '{country}'        => $location['country'] ?? '',
            '{city}'           => $location['city'] ?? '',
            '{postcode}'       => $location['postcode'] ?? '',
            '{name}'           => $full_name ?: trim( $first_name ),
            '{first_name}'     => (string) $first_name,
            '{last_name}'      => (string) $last_name,
            '{email}'          => (string) ( $cart['email'] ?? '' ),
            '{phone}'          => (string) ( $cart['phone'] ?? '' ),
        ];
    }

    /**
     * Format price using WooCommerce wc_price if available.
     *
     * @param float  $total
     * @param string $currency
     * @return string
     */
    private function format_price( float $total, string $currency ): string {
        if ( function_exists( 'wc_price' ) ) {
            return wp_strip_all_tags( html_entity_decode( wc_price( $total, [ 'currency' => $currency ] ) ) );
        }

        return number_format_i18n( (float) $total, 2 ) . ' ' . $currency;
    }

    /**
     * Format cart items from serialized cart_contents.
     *
     * @param array $cart
     * @return string
     */
    private function format_cart_items( array $cart ): string {
        $contents = maybe_unserialize( $cart['cart_contents'] ?? '' );

        if ( ! is_array( $contents ) || empty( $contents ) ) {
            return '';
        }

        $lines = [];

        foreach ( $contents as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $qty  = $item['quantity'] ?? 1;
            $name = $item['name'] ?? '';

            if ( empty( $name ) ) {
                $product_id = $item['product_id'] ?? '';
                $name       = $product_id ? sprintf( 'Product #%s', $product_id ) : '';
            }

            if ( $name ) {
                $lines[] = $qty > 1 ? "{$qty}x {$name}" : $name;
            }
        }

        return implode( ', ', $lines );
    }

    /**
     * Get location data from serialized location column.
     *
     * @param array $cart
     * @return array ['country' => '', 'city' => '', 'postcode' => '']
     */
    private function get_location( array $cart ): array {
        $location = maybe_unserialize( $cart['location'] ?? '' );

        if ( ! is_array( $location ) ) {
            return [];
        }

        return [
            'country'  => $location['country'] ?? '',
            'city'     => $location['city'] ?? '',
            'postcode' => $location['postcode'] ?? '',
        ];
    }

    /**
     * Format cart timestamp to localized date string.
     *
     * @param array $cart
     * @return string
     */
    private function format_cart_date( array $cart ): string {
        $time = $cart['time'] ?? '';

        if ( empty( $time ) ) {
            return '';
        }

        $timestamp = is_numeric( $time ) ? (int) $time : strtotime( $time );

        if ( false === $timestamp ) {
            return '';
        }

        return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
    }
}
