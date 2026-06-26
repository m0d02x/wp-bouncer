<?php

namespace Bouncer\WooCommerce\WhatsApp\Service\CartBounty;

class CartBountyPhoneNormalizer {
    public function normalize( string $raw_phone, string $country_code = '' ): string {
        try {
            $phone = trim( $raw_phone );

            if ( '' === $phone ) {
                return '';
            }

            if ( 0 === strpos( $phone, '+' ) ) {
                $digits = preg_replace( '/\D+/', '', substr( $phone, 1 ) );

                return $digits ? '+' . $digits : '';
            }

            $digits = preg_replace( '/\D+/', '', $phone );

            if ( ! $digits ) {
                return '';
            }

            if ( 0 === strpos( $digits, '00' ) ) {
                $international_digits = substr( $digits, 2 );

                return '' !== $international_digits ? '+' . $international_digits : '';
            }

            $calling_code = $this->calling_code_for_country( $country_code );

            if ( '' === $calling_code ) {
                return '';
            }

            $local_digits = ltrim( $digits, '0' );

            if ( '' === $local_digits ) {
                return '';
            }

            return '+' . $calling_code . $local_digits;
        } catch ( \Throwable $e ) {
            unset( $e );

            return '';
        }
    }

    public function calling_code_for_country( string $country_code ): string {
        try {
            $country_code = strtoupper( trim( $country_code ) );

            if ( '' === $country_code ) {
                return '';
            }

            if ( class_exists( '\WC_Countries' ) ) {
                $countries    = new \WC_Countries();
                $calling_code = $countries->get_country_calling_code( $country_code );

                if ( is_string( $calling_code ) && '' !== trim( $calling_code ) ) {
                    return ltrim( preg_replace( '/\D+/', '', $calling_code ), '0' );
                }
            }

            $fallback_codes = [
                'MY' => '60',
                'US' => '1',
                'GB' => '44',
                'ID' => '62',
                'SG' => '65',
                'IN' => '91',
                'AU' => '61',
                'PH' => '63',
            ];

            return $fallback_codes[ $country_code ] ?? '';
        } catch ( \Throwable $e ) {
            unset( $e );

            return '';
        }
    }
}
