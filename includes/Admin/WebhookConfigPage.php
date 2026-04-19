<?php

namespace Bouncer\WooCommerce\WhatsApp\Admin;

use Bouncer\WooCommerce\WhatsApp\Settings\Settings;

class WebhookConfigPage {
    private Settings $settings;
    private string $option_name = 'bouncer_webhook_config';

    public function __construct( Settings $settings ) {
        $this->settings = $settings;
    }

    public function register(): void {
        add_action( 'admin_menu', [ $this, 'add_menu' ], 20 );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'wp_ajax_wc_bouncer_connect', [ $this, 'ajax_connect_to_bouncer' ] );
        add_action( 'wp_ajax_wc_bouncer_disconnect', [ $this, 'ajax_disconnect_from_bouncer' ] );
    }

    public function add_menu(): void {
        add_submenu_page(
            'bouncer-whatsapp',
            __( 'Webhook Configuration', 'wc-bouncer-whatsapp' ),
            __( 'Webhook Config', 'wc-bouncer-whatsapp' ),
            'manage_woocommerce',
            'bouncer-webhook-config',
            [ $this, 'render_page' ]
        );
    }

    public function register_settings(): void {
        register_setting( 'bouncer_webhook_config', $this->option_name );
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'wc-bouncer-whatsapp' ) );
        }

        $config = get_option( $this->option_name, [
            'webhook_url'     => '',
            'webhook_secret'  => '',
            'enabled_events'  => [],
        ] );

        // Handle form submission
        if ( isset( $_POST['bouncer_configure_webhooks'] ) ) {
            check_admin_referer( 'bouncer_configure_webhooks' );

            $config = [
                'webhook_url'    => sanitize_text_field( $_POST['webhook_url'] ?? '' ),
                'webhook_secret' => sanitize_text_field( $_POST['webhook_secret'] ?? '' ),
                'enabled_events' => isset( $_POST['enabled_events'] ) ? array_map( 'sanitize_text_field', $_POST['enabled_events'] ) : [],
            ];

            update_option( $this->option_name, $config );

            $result = $this->configure_webhooks( $config['webhook_url'], $config['webhook_secret'], $config['enabled_events'] );

            if ( $result['success'] ) {
                echo '<div class="notice notice-success is-dismissible"><p>✓ ' . sprintf( __( 'Successfully configured %d webhook(s)!', 'wc-bouncer-whatsapp' ), count( $result['webhooks'] ) ) . '</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>✗ ' . esc_html( $result['error'] ) . '</p></div>';
            }
        }

        $this->render_form( $config );
    }

    private function render_form( array $config ): void {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <p><?php _e( 'Configure automatic webhooks for Bouncer workflow triggers', 'wc-bouncer-whatsapp' ); ?></p>

            <?php
            $connection_status = $this->settings->get( 'connection_status', '' );
            $integration_id    = $this->settings->get( 'integration_id', '' );
            $is_connected      = 'connected' === $connection_status && ! empty( $integration_id );
            ?>

            <?php if ( $is_connected ) : ?>
            <div class="card" style="max-width: 800px; margin-top: 20px; background: #e7f5e7; border-left: 4px solid #46b450;">
                <h2><?php _e( 'Connected to Bouncer', 'wc-bouncer-whatsapp' ); ?> &#10003;</h2>
                <p><?php printf( __( 'Integration ID: %s', 'wc-bouncer-whatsapp' ), '<code>' . esc_html( $integration_id ) . '</code>' ); ?></p>
                <div id="bouncer-connect-result"></div>
                <p>
                    <button type="button" id="bouncer-auto-configure" class="button button-primary">
                        <?php _e( 'Reconnect', 'wc-bouncer-whatsapp' ); ?>
                    </button>
                    <button type="button" id="bouncer-disconnect" class="button" style="color: #a00; margin-left: 8px;">
                        <?php _e( 'Disconnect', 'wc-bouncer-whatsapp' ); ?>
                    </button>
                </p>
            </div>
            <?php else : ?>
            <div class="card" style="max-width: 800px; margin-top: 20px; background: #f0f6fc; border-left: 4px solid #2271b1;">
                <h2><?php _e( 'Connect to Bouncer', 'wc-bouncer-whatsapp' ); ?></h2>
                <p><?php _e( 'Automatically configure webhooks using your Bouncer API key.', 'wc-bouncer-whatsapp' ); ?></p>
                <div id="bouncer-connect-result"></div>
                <p>
                    <button type="button" id="bouncer-auto-configure" class="button button-primary button-hero">
                        <?php _e( 'Connect to Bouncer', 'wc-bouncer-whatsapp' ); ?>
                    </button>
                </p>
                <p class="description">
                    <?php _e( 'This will generate WooCommerce API keys, register your store with Bouncer, and create webhooks automatically.', 'wc-bouncer-whatsapp' ); ?>
                </p>
            </div>
            <?php endif; ?>

            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2><?php _e( 'Webhook Settings', 'wc-bouncer-whatsapp' ); ?></h2>

                <form method="post" action="">
                    <?php wp_nonce_field( 'bouncer_configure_webhooks' ); ?>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">
                                <label for="webhook_url"><?php _e( 'Webhook URL', 'wc-bouncer-whatsapp' ); ?></label>
                            </th>
                            <td>
                                <input
                                    type="url"
                                    id="webhook_url"
                                    name="webhook_url"
                                    value="<?php echo esc_attr( $config['webhook_url'] ); ?>"
                                    class="regular-text code"
                                    placeholder="https://api.bouncer.my/webhook/woocommerce/woo_xxx"
                                    required
                                />
                                <p class="description">
                                    <?php _e( '📋 Copy from: Bouncer Dashboard → Developer Settings → WooCommerce Integration', 'wc-bouncer-whatsapp' ); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="webhook_secret"><?php _e( 'Webhook Secret', 'wc-bouncer-whatsapp' ); ?></label>
                            </th>
                            <td>
                                <input
                                    type="password"
                                    id="webhook_secret"
                                    name="webhook_secret"
                                    value="<?php echo esc_attr( $config['webhook_secret'] ); ?>"
                                    class="regular-text code"
                                    placeholder="<?php _e( 'Enter secret from Bouncer', 'wc-bouncer-whatsapp' ); ?>"
                                    required
                                />
                                <p class="description">
                                    <?php _e( '🔐 Secure webhook secret from Bouncer', 'wc-bouncer-whatsapp' ); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php _e( 'Monitored Events', 'wc-bouncer-whatsapp' ); ?></th>
                            <td>
                                <fieldset>
                                    <?php
                                    $events = [
                                        'order.created'  => '📦 ' . __( 'Order Created', 'wc-bouncer-whatsapp' ),
                                        'order.updated'  => '📝 ' . __( 'Order Updated', 'wc-bouncer-whatsapp' ),
                                        'order.deleted'  => '🗑️ ' . __( 'Order Deleted', 'wc-bouncer-whatsapp' ),
                                        'product.created' => '🏷️ ' . __( 'Product Created', 'wc-bouncer-whatsapp' ),
                                        'product.updated' => '✏️ ' . __( 'Product Updated', 'wc-bouncer-whatsapp' ),
                                        'product.deleted' => '❌ ' . __( 'Product Deleted', 'wc-bouncer-whatsapp' ),
                                    ];

                                    foreach ( $events as $key => $label ) {
                                        $checked = in_array( $key, $config['enabled_events'], true ) ? 'checked' : '';
                                        ?>
                                        <label style="display: block; margin: 8px 0;">
                                            <input type="checkbox" name="enabled_events[]" value="<?php echo esc_attr( $key ); ?>" <?php echo $checked; ?>>
                                            <?php echo esc_html( $label ); ?>
                                        </label>
                                        <?php
                                    }
                                    ?>
                                    <p class="description" style="margin-top: 12px;">
                                        <?php _e( 'Select events that should trigger Bouncer workflows', 'wc-bouncer-whatsapp' ); ?>
                                    </p>
                                </fieldset>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" name="bouncer_configure_webhooks" class="button button-primary button-large">
                            ⚡ <?php _e( 'Configure Webhooks', 'wc-bouncer-whatsapp' ); ?>
                        </button>
                    </p>
                </form>
            </div>

            <?php if ( ! empty( $config['webhook_url'] ) ) : ?>
                <div class="card" style="max-width: 800px; margin-top: 20px;">
                    <h2><?php _e( 'Active Bouncer Webhooks', 'wc-bouncer-whatsapp' ); ?></h2>
                    <?php $this->display_webhooks(); ?>
                </div>
            <?php endif; ?>

            <div class="card" style="max-width: 800px; margin-top: 20px; background: #f0f6fc; border-left: 4px solid #2271b1;">
                <h2>📖 <?php _e( 'Setup Guide', 'wc-bouncer-whatsapp' ); ?></h2>
                <ol style="line-height: 1.8;">
                    <li><strong><?php _e( 'Go to Bouncer:', 'wc-bouncer-whatsapp' ); ?></strong> <?php _e( 'Dashboard → Developer Settings', 'wc-bouncer-whatsapp' ); ?></li>
                    <li><strong><?php _e( 'Add Integration:', 'wc-bouncer-whatsapp' ); ?></strong> <?php _e( 'Add or edit WooCommerce store', 'wc-bouncer-whatsapp' ); ?></li>
                    <li><strong><?php _e( 'Configure Webhook:', 'wc-bouncer-whatsapp' ); ?></strong> <?php _e( 'Expand "Webhook Configuration" section', 'wc-bouncer-whatsapp' ); ?></li>
                    <li><strong><?php _e( 'Copy Credentials:', 'wc-bouncer-whatsapp' ); ?></strong> <?php _e( 'Copy the Webhook URL and Secret', 'wc-bouncer-whatsapp' ); ?></li>
                    <li><strong><?php _e( 'Return Here:', 'wc-bouncer-whatsapp' ); ?></strong> <?php _e( 'Paste URL and Secret above', 'wc-bouncer-whatsapp' ); ?></li>
                    <li><strong><?php _e( 'Select Events:', 'wc-bouncer-whatsapp' ); ?></strong> <?php _e( 'Choose which events to monitor', 'wc-bouncer-whatsapp' ); ?></li>
                    <li><strong><?php _e( 'Save:', 'wc-bouncer-whatsapp' ); ?></strong> <?php _e( 'Click "Configure Webhooks"', 'wc-bouncer-whatsapp' ); ?></li>
                </ol>
                <p><strong>✨ <?php _e( 'Magic:', 'wc-bouncer-whatsapp' ); ?></strong> <?php _e( 'This plugin automatically creates and manages all WooCommerce webhooks for you!', 'wc-bouncer-whatsapp' ); ?></p>
            </div>

            <script>
            jQuery(document).ready(function($) {
                $('#bouncer-auto-configure').on('click', function() {
                    var $btn = $(this);
                    var $result = $('#bouncer-connect-result');
                    $btn.data('label', $btn.text());
                    $btn.prop('disabled', true).text('<?php _e( 'Connecting...', 'wc-bouncer-whatsapp' ); ?>');
                    $result.html('');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'wc_bouncer_connect',
                            nonce: '<?php echo wp_create_nonce( 'wc_bouncer_connect' ); ?>',
                            enabled_events: $('input[name="enabled_events[]"]:checked').map(function() {
                                return $(this).val();
                            }).get()
                        },
                        success: function(response) {
                            if (response.success) {
                                $result.html('<div class="notice notice-success"><p>✓ ' + response.data.message + '</p></div>');
                                // Update form fields with returned values
                                if (response.data.webhook_url) {
                                    $('#webhook_url').val(response.data.webhook_url);
                                }
                                if (response.data.webhook_secret) {
                                    $('#webhook_secret').val(response.data.webhook_secret);
                                }
                                // Reload page after 2 seconds to show updated webhooks
                                setTimeout(function() { location.reload(); }, 2000);
                            } else {
                                $result.html('<div class="notice notice-error"><p>✗ ' + response.data.message + '</p></div>');
                            }
                        },
                        error: function() {
                            $result.html('<div class="notice notice-error"><p>✗ <?php _e( 'Connection failed. Please try again.', 'wc-bouncer-whatsapp' ); ?></p></div>');
                        },
                    complete: function() {
                            $btn.prop('disabled', false).text($btn.data('label'));
                        }
                    });
                });

                $('#bouncer-disconnect').on('click', function() {
                    if (!confirm('<?php echo esc_js( __( 'Disconnect from Bouncer? This will remove all webhooks.', 'wc-bouncer-whatsapp' ) ); ?>')) return;
                    var $btn = $(this);
                    var $result = $('#bouncer-connect-result');
                    $btn.prop('disabled', true);
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'wc_bouncer_disconnect',
                            nonce: '<?php echo wp_create_nonce( 'wc_bouncer_disconnect' ); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                                setTimeout(function() { location.reload(); }, 1500);
                            } else {
                                $result.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                            }
                        },
                        error: function() {
                            $result.html('<div class="notice notice-error"><p><?php _e( 'Disconnect failed.', 'wc-bouncer-whatsapp' ); ?></p></div>');
                        },
                        complete: function() { $btn.prop('disabled', false); }
                    });
                });
            });
                });
            });
            </script>
        </div>
        <?php
    }

    private function configure_webhooks( string $url, string $secret, array $events ): array {
        // Ensure WooCommerce webhook functions are loaded (may not be during AJAX)
        if ( ! function_exists( 'wc_load_webhooks' ) ) {
            if ( defined( 'WC_ABSPATH' ) && file_exists( WC_ABSPATH . 'includes/wc-webhook-functions.php' ) ) {
                include_once WC_ABSPATH . 'includes/wc-webhook-functions.php';
            }
        }
        
        if ( ! class_exists( 'WC_Webhook' ) || ! function_exists( 'wc_load_webhooks' ) ) {
            return [ 'success' => false, 'error' => __( 'WooCommerce webhooks not available. Please ensure WooCommerce is active.', 'wc-bouncer-whatsapp' ) ];
        }

        $event_mapping = [
            'order.created'   => 'order.created',
            'order.updated'   => 'order.updated',
            'order.deleted'   => 'order.deleted',
            'product.created' => 'product.created',
            'product.updated' => 'product.updated',
            'product.deleted' => 'product.deleted',
        ];

        $created = [];

        // Delete old Bouncer webhooks
        $data_store = \WC_Data_Store::load( 'webhook' );
        $webhook_ids = array_merge(
            $data_store->get_webhooks_ids( 'active' ),
            $data_store->get_webhooks_ids( 'paused' ),
            $data_store->get_webhooks_ids( 'disabled' )
        );
        foreach ( $webhook_ids as $webhook_id ) {
            $webhook = \wc_get_webhook( $webhook_id );
            if ( ! $webhook ) {
                continue;
            }
            $delivery_url = $webhook->get_delivery_url();
            $name         = $webhook->get_name();
            if ( strpos( $delivery_url, 'bouncer.my' ) !== false || strpos( $name, 'Bouncer' ) !== false || strpos( $delivery_url, 'localhost' ) !== false ) {
                $webhook->delete( true );
            }
        }

        foreach ( $events as $event ) {
            if ( ! isset( $event_mapping[ $event ] ) ) {
                continue;
            }

            $webhook = new \WC_Webhook();
            $webhook->set_name( 'Bouncer - ' . ucwords( str_replace( '.', ' ', $event ) ) );
            $webhook->set_status( 'active' );
            $webhook->set_topic( $event_mapping[ $event ] );
            $webhook->set_delivery_url( $url );
            $webhook->set_secret( $secret );
            $webhook->set_api_version( 3 );
            // CRITICAL: Set user_id so WooCommerce can fetch order data when delivering webhooks
            // Without this, webhook delivery returns 401 "woocommerce_rest_cannot_view" error
            $webhook->set_user_id( get_current_user_id() );

            if ( $webhook->save() ) {
                $created[] = $webhook->get_id();
            }
        }

        return [ 'success' => true, 'webhooks' => $created ];
    }

    private function display_webhooks(): void {
        $data_store = \WC_Data_Store::load( 'webhook' );
        $webhook_ids = array_merge(
            $data_store->get_webhooks_ids( 'active' ),
            $data_store->get_webhooks_ids( 'paused' ),
            $data_store->get_webhooks_ids( 'disabled' )
        );
        $webhooks = array_map( function( $id ) {
            return \wc_get_webhook( $id );
        }, $webhook_ids );
        $webhooks = array_filter( $webhooks );
        $bouncer  = array_filter( $webhooks, function ( $w ) {
            return strpos( $w->get_delivery_url(), 'bouncer.my' ) !== false ||
                   strpos( $w->get_name(), 'Bouncer' ) !== false;
        } );

        if ( empty( $bouncer ) ) {
            echo '<p style="color: #666;">' . __( 'No webhooks configured yet. Click "Configure Webhooks" above to get started.', 'wc-bouncer-whatsapp' ) . '</p>';
            return;
        }

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . __( 'Name', 'wc-bouncer-whatsapp' ) . '</th>';
        echo '<th>' . __( 'Event', 'wc-bouncer-whatsapp' ) . '</th>';
        echo '<th>' . __( 'Status', 'wc-bouncer-whatsapp' ) . '</th>';
        echo '<th>' . __( 'URL', 'wc-bouncer-whatsapp' ) . '</th>';
        echo '</tr></thead><tbody>';

        foreach ( $bouncer as $w ) {
            $status = $w->get_status() === 'active'
                ? '<span style="color: #46b450;">● ' . __( 'Active', 'wc-bouncer-whatsapp' ) . '</span>'
                : '<span style="color: #999;">○ ' . esc_html( $w->get_status() ) . '</span>';

            echo '<tr>';
            echo '<td>' . esc_html( $w->get_name() ) . '</td>';
            echo '<td><code>' . esc_html( $w->get_topic() ) . '</code></td>';
            echo '<td>' . $status . '</td>';
            echo '<td><code style="font-size: 11px;">' . esc_html( substr( $w->get_delivery_url(), 0, 50 ) ) . '...</code></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    public function ajax_connect_to_bouncer(): void {
        if ( ! check_ajax_referer( 'wc_bouncer_connect', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Security check failed.', 'wc-bouncer-whatsapp' ) ] );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'wc-bouncer-whatsapp' ) ] );
        }

        $enabled_events = isset( $_POST['enabled_events'] ) ? array_map( 'sanitize_text_field', $_POST['enabled_events'] ) : [];
        $store_url      = home_url();

        $consumer_key    = $this->settings->get( 'wc_consumer_key', '' );
        $consumer_secret = $this->settings->get( 'wc_consumer_secret', '' );

        if ( empty( $consumer_key ) || empty( $consumer_secret ) ) {
            $api_keys = $this->create_wc_api_keys();
            if ( ! $api_keys ) {
                wp_send_json_error( [ 'message' => __( 'Could not create WooCommerce API keys. Ensure WooCommerce is active.', 'wc-bouncer-whatsapp' ) ] );
            }
            $consumer_key    = $api_keys['consumer_key'];
            $consumer_secret = $api_keys['consumer_secret'];
        }

        $instance_id = $this->settings->get( 'instance_id', '' );

        $api_client = new \Bouncer\WooCommerce\WhatsApp\Service\ApiClient( $this->settings );
        $result     = $api_client->connect_to_woocommerce(
            $store_url,
            $consumer_key,
            $consumer_secret,
            $enabled_events,
            $instance_id ?: null
        );

        if ( ! $result['success'] ) {
            $code = (int) ( $result['response_code'] ?? 0 );

            if ( 401 === $code ) {
                $error_message = __( 'Invalid API key. Check your Bouncer API key in Settings.', 'wc-bouncer-whatsapp' );
            } elseif ( 403 === $code ) {
                $error_message = __( 'API key missing webhooks:manage permission. Edit your key in Bouncer Dashboard > Developer to add it.', 'wc-bouncer-whatsapp' );
            } elseif ( $code >= 500 ) {
                $error_message = __( 'Bouncer server error. Please try again in a few minutes.', 'wc-bouncer-whatsapp' );
            } else {
                $error_message = __( 'Failed to connect to Bouncer.', 'wc-bouncer-whatsapp' );
                $decoded = json_decode( $result['response_body'] ?? '', true );
                if ( isset( $decoded['message'] ) ) {
                    $error_message .= ' ' . $decoded['message'];
                }
            }

            $this->settings->update( [ 'connection_status' => 'failed' ] );
            wp_send_json_error( [ 'message' => $error_message ] );
        }

        $data = $result['data'];

        $this->settings->update( [
            'integration_id'    => $data['integrationId'] ?? '',
            'connection_status' => 'connected',
        ] );

        $config = [
            'webhook_url'    => $data['webhookUrl'] ?? '',
            'webhook_secret' => $data['webhookSecret'] ?? '',
            'enabled_events' => $enabled_events,
        ];
        update_option( $this->option_name, $config );

        $webhook_result = $this->configure_webhooks(
            $config['webhook_url'],
            $config['webhook_secret'],
            $enabled_events
        );

        if ( ! $webhook_result['success'] ) {
            wp_send_json_error( [ 'message' => __( 'Connected to Bouncer but failed to create WooCommerce webhooks: ', 'wc-bouncer-whatsapp' ) . $webhook_result['error'] ] );
        }

        $message = sprintf(
            __( 'Connected to Bouncer! Created %d webhook(s).', 'wc-bouncer-whatsapp' ),
            count( $webhook_result['webhooks'] )
        );

        if ( ! empty( $data['connectionVerified'] ) && ! empty( $data['storeName'] ) ) {
            $message .= ' ' . sprintf( __( 'Store verified: %s', 'wc-bouncer-whatsapp' ), $data['storeName'] );
        } elseif ( isset( $data['connectionVerified'] ) && ! $data['connectionVerified'] ) {
            $message .= ' ' . __( 'Warning: Bouncer could not reach your store API. Ensure your site is publicly accessible.', 'wc-bouncer-whatsapp' );
        }

        wp_send_json_success( [
            'message'        => $message,
            'integration_id' => $data['integrationId'] ?? '',
            'webhook_url'    => $config['webhook_url'],
            'webhook_secret' => $config['webhook_secret'],
            'webhooks'       => $webhook_result['webhooks'],
        ] );
    }

    public function ajax_disconnect_from_bouncer(): void {
        if ( ! check_ajax_referer( 'wc_bouncer_disconnect', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Security check failed.', 'wc-bouncer-whatsapp' ) ] );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'wc-bouncer-whatsapp' ) ] );
        }

        $this->delete_bouncer_webhooks();

        $integration_id = $this->settings->get( 'integration_id', '' );
        if ( ! empty( $integration_id ) ) {
            $api_client = new \Bouncer\WooCommerce\WhatsApp\Service\ApiClient( $this->settings );
            $api_client->disconnect_woocommerce( $integration_id );
        }

        $this->settings->update( [
            'integration_id'    => '',
            'connection_status' => '',
            'wc_consumer_key'   => '',
            'wc_consumer_secret' => '',
        ] );

        update_option( $this->option_name, [
            'webhook_url'    => '',
            'webhook_secret' => '',
            'enabled_events' => [],
        ] );

        wp_send_json_success( [ 'message' => __( 'Disconnected from Bouncer.', 'wc-bouncer-whatsapp' ) ] );
    }

    private function delete_bouncer_webhooks(): void {
        if ( ! class_exists( 'WC_Data_Store' ) ) {
            return;
        }

        $data_store  = \WC_Data_Store::load( 'webhook' );
        $webhook_ids = array_merge(
            $data_store->get_webhooks_ids( 'active' ),
            $data_store->get_webhooks_ids( 'paused' ),
            $data_store->get_webhooks_ids( 'disabled' )
        );

        foreach ( $webhook_ids as $webhook_id ) {
            $webhook = \wc_get_webhook( $webhook_id );
            if ( ! $webhook ) {
                continue;
            }
            $delivery_url = $webhook->get_delivery_url();
            $name         = $webhook->get_name();
            if ( strpos( $delivery_url, 'bouncer.my' ) !== false || strpos( $name, 'Bouncer' ) !== false || strpos( $delivery_url, 'localhost' ) !== false ) {
                $webhook->delete( true );
            }
        }
    }

	private function create_wc_api_keys(): ?array {
		global $wpdb;

		// Check if we have stored raw keys from a previous creation.
		// We cannot recover raw keys from the WC table (consumer_key is hashed there),
		// so we keep the raw values in WP options.
		$stored_key    = $this->settings->get( 'wc_consumer_key', '' );
		$stored_secret = $this->settings->get( 'wc_consumer_secret', '' );

		if ( ! empty( $stored_key ) && ! empty( $stored_secret ) ) {
			return [
				'consumer_key'    => $stored_key,
				'consumer_secret' => $stored_secret,
			];
		}

		// Need WooCommerce for wc_rand_hash / wc_api_hash
		if ( ! function_exists( 'wc_rand_hash' ) ) {
			return null;
		}

		// Delete any stale Bouncer rows so we don't accumulate orphaned keys
		$wpdb->delete(
			$wpdb->prefix . 'woocommerce_api_keys',
			[ 'description' => 'Bouncer WhatsApp Integration' ],
			[ '%s' ]
		);

		// Create new API keys
		$user_id     = get_current_user_id();
		$description = 'Bouncer WhatsApp Integration';
		$permissions = 'read_write';

		$consumer_key    = 'ck_' . wc_rand_hash();
		$consumer_secret = 'cs_' . wc_rand_hash();

		$wpdb->insert(
			$wpdb->prefix . 'woocommerce_api_keys',
			[
				'user_id'         => $user_id,
				'description'     => $description,
				'permissions'     => $permissions,
				'consumer_key'    => wc_api_hash( $consumer_key ),
				'consumer_secret' => $consumer_secret,
				'truncated_key'   => substr( $consumer_key, -7 ),
			],
			[ '%d', '%s', '%s', '%s', '%s', '%s' ]
		);

		if ( ! $wpdb->insert_id ) {
			return null;
		}

		// Persist raw keys so we can reuse them on reconnect
		$this->settings->update( [
			'wc_consumer_key'    => $consumer_key,
			'wc_consumer_secret' => $consumer_secret,
		] );

		return [
			'consumer_key'    => $consumer_key,
			'consumer_secret' => $consumer_secret,
		];
	}
}
