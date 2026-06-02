<?php

namespace Bouncer\WooCommerce\WhatsApp\Integrations\FunnelKit;

use Bouncer\WooCommerce\WhatsApp\Settings\Settings;

class FunnelKitIntegration {
    private Settings $settings;
    private string $base_dir;

    public function __construct( Settings $settings ) {
        $this->settings = $settings;
        $this->base_dir = trailingslashit( WC_BOUNCER_WHATSAPP_PLUGIN_DIR . 'includes/Integrations/FunnelKit' );
    }

    public function register(): void {
        if ( ! $this->is_enabled() ) {
            return;
        }

        if ( class_exists( 'WFCO_Bouncer', false ) || class_exists( 'BWFCO_Bouncer', false ) ) {
            add_action( 'admin_notices', [ $this, 'render_standalone_notice' ] );
            return;
        }

        $this->define_constants();

        add_action( 'wfco_load_connectors', [ $this, 'load_connector_classes' ] );
        add_action( 'bwfan_before_automations_loaded', [ $this, 'load_autonami_classes' ] );
        add_action( 'bwfan_automations_loaded', [ $this, 'load_autonami_classes' ] );
        add_action( 'bwfan_loaded', [ $this, 'init_bouncer' ] );
    }

    public function render_standalone_notice(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        echo '<div class="notice notice-warning"><p>' . esc_html__( 'Bouncer FunnelKit integration is enabled in the base plugin, but the standalone FunnelKit Bouncer connector is also active. Disable the standalone connector plugin to use the built-in integration.', 'wc-bouncer-whatsapp' ) . '</p></div>';
    }

    public function init_bouncer(): void {
        $this->require_debug_helper();

        if ( ! class_exists( 'WFCO_Bouncer_Call', false ) && class_exists( 'WFCO_Call', false ) ) {
            require_once $this->base_dir . 'includes/class-wfco-bouncer-call.php';
        }

        $this->ensure_base_connector_saved_data();

        if ( class_exists( 'WFCO_Bouncer_Debug', false ) ) {
            \WFCO_Bouncer_Debug::log( 'Built-in FunnelKit integration initialized', [
                'version' => WC_BOUNCER_WHATSAPP_VERSION,
            ] );
        }
    }

    public function load_connector_classes(): void {
        if ( class_exists( 'BWFCO_Bouncer', false ) ) {
            return;
        }

        $this->require_debug_helper();

        if ( ! class_exists( 'WFCO_Bouncer_Call', false ) && class_exists( 'WFCO_Call', false ) ) {
            require_once $this->base_dir . 'includes/class-wfco-bouncer-call.php';
        }

        require_once $this->base_dir . 'connector.php';

        $this->ensure_base_connector_saved_data();

        if ( class_exists( 'WFCO_Bouncer_Debug', false ) ) {
            \WFCO_Bouncer_Debug::log( 'Built-in FunnelKit connector classes loaded' );
        }
    }

    public function load_autonami_classes(): void {
        if ( class_exists( 'BWFAN_Bouncer_Integration', false ) ) {
            return;
        }

        $this->load_connector_classes();
        $this->ensure_base_connector_saved_data();

        foreach ( glob( $this->base_dir . 'autonami/class-*.php' ) as $filename ) {
            require_once $filename;
        }
    }

    private function ensure_base_connector_saved_data(): void {
        if ( ! class_exists( '\WFCO_Common', false ) || ! class_exists( '\BWFCO_Bouncer', false ) ) {
            return;
        }

        if ( ! \BWFCO_Bouncer::is_base_plugin_configured() ) {
            return;
        }

        if ( empty( \WFCO_Common::$connectors_saved_data ) ) {
            \WFCO_Common::get_connectors_data();
        }

        if ( isset( \WFCO_Common::$connectors_saved_data['bwfco_bouncer'] ) ) {
            return;
        }

        \WFCO_Common::$connectors_saved_data['bwfco_bouncer'] = [
            'id'            => 0,
            'last_sync'     => current_time( 'mysql', 1 ),
            'status'        => 1,
            'base_settings' => 'wc_bouncer_whatsapp_settings',
        ];
    }

    private function is_enabled(): bool {
        return (bool) $this->settings->get( 'funnelkit_enabled', false );
    }

    private function define_constants(): void {
        $constants = [
            'WFCO_BOUNCER_VERSION'         => WC_BOUNCER_WHATSAPP_VERSION,
            'WFCO_BOUNCER_FULL_NAME'       => 'WooCommerce Bouncer WhatsApp: FunnelKit Integration',
            'WFCO_BOUNCER_PLUGIN_FILE'     => WC_BOUNCER_WHATSAPP_PLUGIN_FILE,
            'WFCO_BOUNCER_PLUGIN_DIR'      => untrailingslashit( $this->base_dir ),
            'WFCO_BOUNCER_PLUGIN_URL'      => untrailingslashit( WC_BOUNCER_WHATSAPP_PLUGIN_URL . 'includes/Integrations/FunnelKit' ),
            'WFCO_BOUNCER_PLUGIN_BASENAME' => plugin_basename( WC_BOUNCER_WHATSAPP_PLUGIN_FILE ),
            'WFCO_BOUNCER_MAIN'            => 'wc-bouncer-whatsapp',
            'WFCO_BOUNCER_ENCODE'          => sha1( plugin_basename( WC_BOUNCER_WHATSAPP_PLUGIN_FILE ) . ':funnelkit' ),
        ];

        foreach ( $constants as $name => $value ) {
            if ( ! defined( $name ) ) {
                define( $name, $value );
            }
        }
    }

	private function require_debug_helper(): void {
		if ( ! class_exists( 'WFCO_Bouncer_Debug', false ) || ! class_exists( 'WFCO_Bouncer_Base_Log', false ) ) {
			require_once $this->base_dir . 'includes/debug-helper.php';
		}
	}
}
