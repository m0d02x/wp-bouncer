<?php
/**
 * Plugin Name: WooCommerce Bouncer WhatsApp
 * Plugin URI: https://bouncer.my
 * Description: Automates WhatsApp notifications for WooCommerce orders using the Bouncer API.
 * Version: 0.4.0
 * Author: Bouncer
 * Author URI: https://bouncer.my
 * Text Domain: wc-bouncer-whatsapp
 * Domain Path: /languages
 */

define( 'WC_BOUNCER_WHATSAPP_VERSION', '0.4.0' );
define( 'WC_BOUNCER_WHATSAPP_MIN_PHP', '7.4' );
define( 'WC_BOUNCER_WHATSAPP_PLUGIN_FILE', __FILE__ );
define( 'WC_BOUNCER_WHATSAPP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WC_BOUNCER_WHATSAPP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

autoloader();

register_activation_hook( __FILE__, [ 'Bouncer\\WooCommerce\\WhatsApp\\Infrastructure\\Installer', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Bouncer\\WooCommerce\\WhatsApp\\Infrastructure\\Installer', 'deactivate' ] );

function autoloader() {
    spl_autoload_register( function ( $class ) {
        $prefix   = 'Bouncer\\WooCommerce\\WhatsApp\\';
        $base_dir = WC_BOUNCER_WHATSAPP_PLUGIN_DIR . 'includes/';
        $len      = strlen( $prefix );

        if ( 0 !== strncmp( $prefix, $class, $len ) ) {
            return;
        }

        $relative_class = substr( $class, $len );
        $file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

        if ( file_exists( $file ) ) {
            require_once $file;
        }
    } );
}

if ( version_compare( PHP_VERSION, WC_BOUNCER_WHATSAPP_MIN_PHP, '<' ) ) {
    add_action( 'admin_notices', function () {
        echo '<div class="notice notice-error"><p>' . esc_html__( 'WooCommerce Bouncer WhatsApp requires PHP 7.4 or higher.', 'wc-bouncer-whatsapp' ) . '</p></div>';
    } );
    return;
}

add_action( 'plugins_loaded', function () {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-warning"><p>' . esc_html__( 'WooCommerce Bouncer WhatsApp requires WooCommerce to be active.', 'wc-bouncer-whatsapp' ) . '</p></div>';
        } );

        return;
    }

    $plugin = new Bouncer\WooCommerce\WhatsApp\Plugin();
    $plugin->init();
} );
