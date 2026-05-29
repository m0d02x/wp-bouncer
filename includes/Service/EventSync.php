<?php

namespace Bouncer\WooCommerce\WhatsApp\Service;

use Bouncer\WooCommerce\WhatsApp\Settings\Settings;

/**
 * Pushes the plugin's hard-coded SUPPORTED_EVENTS list to Bouncer so the
 * server's enabled_events allowlist matches what this plugin actually emits.
 * Reuses the existing /woocommerce/connect endpoint as an idempotent upsert.
 */
class EventSync {
    public const VERSION_OPTION = 'wc_bouncer_whatsapp_synced_version';

    private Settings $settings;
    private ApiClient $api_client;

    public function __construct( Settings $settings, ApiClient $api_client ) {
        $this->settings   = $settings;
        $this->api_client = $api_client;
    }

    public function register(): void {
        add_action( 'init', [ $this, 'maybe_sync_on_upgrade' ], 20 );
    }

    /**
     * Called from Installer::activate on plugin activation.
     */
    public function sync_on_activation(): void {
        $this->push( 'activation' );
    }

    /**
     * Hooked on `init`. Pushes once per version bump so an upgrade
     * (e.g. 0.3.0 -> 0.4.0 with new event types) propagates without
     * the admin needing to click Reconnect.
     */
    public function maybe_sync_on_upgrade(): void {
        $current = defined( 'WC_BOUNCER_WHATSAPP_VERSION' ) ? WC_BOUNCER_WHATSAPP_VERSION : 'dev';
        $synced  = (string) get_option( self::VERSION_OPTION, '' );

        if ( $synced === $current ) {
            return;
        }

        $this->push( 'upgrade' );
        update_option( self::VERSION_OPTION, $current );
    }

    /**
     * Push the SUPPORTED_EVENTS list to Bouncer. Silent on missing
     * credentials; returns true only when the server responded 2xx.
     *
     * @param string $trigger Free-form label for logs (activation/upgrade/manual).
     */
    public function push( string $trigger = 'manual' ): bool {
        $store_url       = home_url();
        $consumer_key    = (string) $this->settings->get( 'wc_consumer_key', '' );
        $consumer_secret = (string) $this->settings->get( 'wc_consumer_secret', '' );
        $instance_id     = (string) $this->settings->get( 'instance_id', '' );

        if ( '' === $consumer_key || '' === $consumer_secret ) {
            return false;
        }

        $response = $this->api_client->connect_to_woocommerce(
            $store_url,
            $consumer_key,
            $consumer_secret,
            Events::SUPPORTED_EVENTS,
            '' !== $instance_id ? $instance_id : null
        );

        if ( empty( $response['success'] ) ) {
            error_log( sprintf(
                '[Bouncer] Event sync (%s) failed: HTTP %d %s',
                $trigger,
                (int) ( $response['response_code'] ?? 0 ),
                (string) ( $response['response_body'] ?? '' )
            ) );
            return false;
        }

        $data = $response['data'] ?? [];
        if ( ! empty( $data['integrationId'] ) ) {
            $this->settings->update( [ 'integration_id' => (string) $data['integrationId'] ] );
        }

        return true;
    }
}
