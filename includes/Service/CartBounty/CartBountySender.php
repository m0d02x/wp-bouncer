<?php

namespace Bouncer\WooCommerce\WhatsApp\Service\CartBounty;

use Bouncer\WooCommerce\WhatsApp\Service\AbandonedOrdersScanner;
use Bouncer\WooCommerce\WhatsApp\Service\ApiClient;
use Bouncer\WooCommerce\WhatsApp\Service\LoggerInterface;
use Bouncer\WooCommerce\WhatsApp\Settings\Settings;

class CartBountySender {
    public const SENT_OPTION = 'wc_bouncer_cartbounty_sent';
    public const BATCH_LIMIT = 10;

    private Settings $settings;
    private CartBountyCartRepository $repository;
    private CartBountyPlaceholderResolver $resolver;
    private CartBountyPhoneNormalizer $phone_normalizer;
    private ApiClient $api_client;
    private LoggerInterface $logger;

    public function __construct(
        Settings $settings,
        CartBountyCartRepository $repository,
        CartBountyPlaceholderResolver $resolver,
        CartBountyPhoneNormalizer $phone_normalizer,
        ApiClient $api_client,
        LoggerInterface $logger
    ) {
        $this->settings         = $settings;
        $this->repository       = $repository;
        $this->resolver         = $resolver;
        $this->phone_normalizer = $phone_normalizer;
        $this->api_client       = $api_client;
        $this->logger           = $logger;
    }

    public function register(): void {
        if ( function_exists( 'add_action' ) ) {
            call_user_func( 'add_action', AbandonedOrdersScanner::CRON_HOOK, [ $this, 'run_sweep' ], 20 );
        }
    }

    public function run_sweep( bool $dry_run = false ): array {
        $result = [
            'sent'    => [],
            'errors'  => [],
            'skipped' => '',
        ];

        if ( empty( $this->settings->get( 'cartbounty_enabled', false ) ) ) {
            $result['skipped'] = 'disabled';
            return $result;
        }

        if ( ! $this->repository->is_available() ) {
            $result['skipped'] = 'cartbounty_unavailable';
            return $result;
        }

        $api_key = (string) $this->settings->get( 'api_key', '' );

        if ( '' === trim( $api_key ) ) {
            $result['skipped'] = 'missing_api_key';
            return $result;
        }

        $steps = (array) $this->settings->get( 'cartbounty_steps', [] );

        foreach ( $steps as $step ) {
            $step  = (int) $step;
            $carts = $this->repository->find_due_carts_for_step( $step, self::BATCH_LIMIT );

            foreach ( $carts as $cart ) {
                $cart_id = (int) ( $cart['id'] ?? 0 );

                if ( $cart_id <= 0 || $this->was_sent( $cart_id, $step ) ) {
                    continue;
                }

                if ( $dry_run ) {
                    $result['sent'][] = $cart_id;
                    continue;
                }

                $response = $this->send_single( $cart, $step );

                if ( ! empty( $response['success'] ) ) {
                    $this->mark_sent( $cart_id, $step );
                    $result['sent'][] = $cart_id;
                } elseif ( empty( $response['reason'] ) ) {
                    $result['errors'][] = $cart_id;
                }
            }
        }

        $this->prune_sent_map();

        return $result;
    }

    public function was_sent( int $cart_id, int $step ): bool {
        $sent_map = $this->get_sent_map();

        return ! empty( $sent_map[ $cart_id ][ $step ] );
    }

    public function mark_sent( int $cart_id, int $step ): void {
        $sent_map = $this->get_sent_map();

        if ( ! isset( $sent_map[ $cart_id ] ) || ! is_array( $sent_map[ $cart_id ] ) ) {
            $sent_map[ $cart_id ] = [];
        }

        $sent_map[ $cart_id ][ $step ] = $this->current_time_mysql_utc();

        $this->update_sent_map( $sent_map );
    }

    public function get_sent_map(): array {
        $sent_map = function_exists( 'get_option' ) ? call_user_func( 'get_option', self::SENT_OPTION, [] ) : [];

        return is_array( $sent_map ) ? $sent_map : [];
    }

    public function prune_sent_map( int $max_age_days = 90 ): void {
        $sent_map = $this->get_sent_map();

        if ( empty( $sent_map ) ) {
            return;
        }

        $max_age_days = max( 1, $max_age_days );
        $day_seconds  = defined( 'DAY_IN_SECONDS' ) ? (int) \constant( 'DAY_IN_SECONDS' ) : 86400;
        $cutoff       = time() - ( $max_age_days * $day_seconds );
        $check_carts  = $this->repository->is_available();
        $changed      = false;

        foreach ( $sent_map as $cart_id => $steps ) {
            $cart_id = (int) $cart_id;

            if ( ! is_array( $steps ) || ( $check_carts && null === $this->repository->get_cart( $cart_id ) ) ) {
                unset( $sent_map[ $cart_id ] );
                $changed = true;
                continue;
            }

            foreach ( $steps as $step => $timestamp ) {
                $stored_time = strtotime( (string) $timestamp );

                if ( false === $stored_time || $stored_time < $cutoff ) {
                    unset( $sent_map[ $cart_id ][ $step ] );
                    $changed = true;
                }
            }

            if ( empty( $sent_map[ $cart_id ] ) ) {
                unset( $sent_map[ $cart_id ] );
                $changed = true;
            }
        }

        if ( $changed ) {
            $this->update_sent_map( $sent_map );
        }
    }

    public function get_status_summary(): array {
        return [
            'enabled'    => ! empty( $this->settings->get( 'cartbounty_enabled', false ) ),
            'available'  => $this->repository->is_available(),
            'last_run'   => '',
            'sent_count' => $this->count_sent_markers(),
        ];
    }

    private function send_single( array $cart, int $step ): array {
        $instance_type = (string) $this->settings->get( 'instance_type', 'bouncer' );

        if ( 'cloud-api' === $instance_type ) {
            return $this->send_cloud_template( $cart, $step );
        }

        return $this->send_bouncer_text( $cart, $step );
    }

    private function send_bouncer_text( array $cart, int $step ): array {
        $step_templates = (array) $this->settings->get( 'cartbounty_status_templates', [] );
        $template       = $step_templates[ $step ] ?? '';

        if ( '' === trim( (string) $template ) ) {
            return $this->skipped_response( 'no_template' );
        }

        $phone = $this->normalize_cart_phone( $cart );

        if ( '' === $phone ) {
            return $this->skipped_response( 'invalid_phone' );
        }

        $message = trim( $this->resolver->resolve( (string) $template, $cart ) );

        if ( '' === $message ) {
            return $this->skipped_response( 'empty_message' );
        }

        $instance = (string) $this->settings->get( 'instance_id', '' );
        $response = $this->api_client->send_text( $phone, $message, $instance );

        $status        = ! empty( $response['success'] ) ? 'success' : 'failed';
        $response_code = (int) ( $response['response_code'] ?? 0 );
        $response_body = (string) ( $response['response_body'] ?? '' );
        $log_message   = sprintf( '[CartBounty cart #%d step %d] %s', (int) ( $cart['id'] ?? 0 ), $step, $message );

        $this->logger->record( 0, $phone, $log_message, $status, $response_code, $response_body );

        return $response;
    }

    private function send_cloud_template( array $cart, int $step ): array {
        // Step→template mapping is CartBounty-specific.
        $cartbounty_config = (array) $this->settings->get( 'cartbounty_cloud_config', [] );
        $template_name     = $cartbounty_config['step_template_map'][ $step ] ?? '';

        if ( '' === trim( (string) $template_name ) ) {
            return $this->skipped_response( 'no_template' );
        }

        $phone = $this->normalize_cart_phone( $cart );

        if ( '' === $phone ) {
            return $this->skipped_response( 'invalid_phone' );
        }

        // Variable mappings and languages are template-level, shared with order
        // triggers via the Templates tab. The cart resolver handles the same
        // placeholder syntax ({first_name}, {order_total}, etc.) using cart data.
        $shared_config = (array) $this->settings->get( 'cloud_template_config', [] );

        $instance     = (string) $this->settings->get( 'instance_id', '' );
        $variables    = [];
        $var_mappings = $shared_config['template_variables'][ $template_name ] ?? [];

        if ( is_array( $var_mappings ) ) {
            foreach ( $var_mappings as $index => $placeholder ) {
                if ( '' === trim( (string) $placeholder ) ) {
                    continue;
                }

                $variables[ (string) $index ] = $this->resolver->resolve_placeholder( (string) $placeholder, $cart );
            }
        }

        $language = (string) ( $shared_config['template_languages'][ $template_name ] ?? 'en' );

        if ( '' === trim( $language ) ) {
            $language = 'en';
        }

        $response = $this->api_client->send_cloud_template( $phone, (string) $template_name, $variables, $instance, $language );

        $status        = ! empty( $response['success'] ) ? 'success' : 'failed';
        $response_code = (int) ( $response['response_code'] ?? 0 );
        $response_body = (string) ( $response['response_body'] ?? '' );
        $log_message   = sprintf( '[CartBounty cart #%d step %d] Template: %s', (int) ( $cart['id'] ?? 0 ), $step, $template_name );

        $this->logger->record( 0, $phone, $log_message, $status, $response_code, $response_body );

        return $response;
    }

    private function normalize_cart_phone( array $cart ): string {
        $location = $this->maybe_unserialize_value( $cart['location'] ?? '' );
        $country  = is_array( $location ) ? (string) ( $location['country'] ?? '' ) : '';

        return $this->phone_normalizer->normalize( (string) ( $cart['phone'] ?? '' ), $country );
    }

    private function skipped_response( string $reason ): array {
        return [
            'success'       => false,
            'reason'        => $reason,
            'response_code' => 0,
            'response_body' => '',
        ];
    }

    private function count_sent_markers(): int {
        $count = 0;

        foreach ( $this->get_sent_map() as $steps ) {
            if ( is_array( $steps ) ) {
                $count += count( array_filter( $steps ) );
            }
        }

        return $count;
    }

    private function update_sent_map( array $sent_map ): void {
        if ( function_exists( 'update_option' ) ) {
            call_user_func( 'update_option', self::SENT_OPTION, $sent_map, false );
        }
    }

    private function maybe_unserialize_value( $value ) {
        if ( function_exists( 'maybe_unserialize' ) ) {
            return call_user_func( 'maybe_unserialize', $value );
        }

        if ( ! is_string( $value ) || ! $this->looks_serialized( $value ) ) {
            return $value;
        }

        return unserialize( $value, [ 'allowed_classes' => false ] );
    }

    private function looks_serialized( string $value ): bool {
        $value = trim( $value );

        if ( 'N;' === $value ) {
            return true;
        }

        return 1 === preg_match( '/^(?:a|O|s|i|d|b):/', $value );
    }

    private function current_time_mysql_utc(): string {
        if ( function_exists( 'current_time' ) ) {
            return (string) call_user_func( 'current_time', 'mysql', true );
        }

        return gmdate( 'Y-m-d H:i:s' );
    }
}
