<?php

namespace Bouncer\WooCommerce\WhatsApp\Service;

use WC_Order;
use WC_REST_Orders_Controller;
use WP_REST_Request;

/**
 * Serializes a WooCommerce order using the REST controller and POSTs it to the
 * Bouncer webhook endpoint as a synthetic `abandoned.<slug>` event.
 */
class AbandonedWebhookDispatcher {
    /**
     * Map of WooCommerce status slug => Bouncer abandoned event slug.
     */
    public const STATUS_EVENT_MAP = [
        'pending' => 'pending_payment',
        'on-hold' => 'on_hold',
        'failed'  => 'failed',
    ];

    private LoggerInterface $logger;

    public function __construct( LoggerInterface $logger ) {
        $this->logger = $logger;
    }

    /**
     * Build the webhook payload from a WC_Order. Public for testability.
     *
     * @return array{payload:array,event_slug:string}|null
     */
    public function build_payload( WC_Order $order, string $status ): ?array {
        if ( ! isset( self::STATUS_EVENT_MAP[ $status ] ) ) {
            return null;
        }

        if ( ! class_exists( 'WC_REST_Orders_Controller' ) ) {
            // The class is autoloaded by WooCommerce when the REST API boots.
            // Force-load it for cron contexts where REST has not initialised.
            if ( defined( 'WC_ABSPATH' ) && file_exists( WC_ABSPATH . 'includes/rest-api/Controllers/Version3/class-wc-rest-orders-controller.php' ) ) {
                require_once WC_ABSPATH . 'includes/rest-api/Controllers/Version2/class-wc-rest-orders-v2-controller.php';
                require_once WC_ABSPATH . 'includes/rest-api/Controllers/Version3/class-wc-rest-orders-controller.php';
            }
            if ( ! class_exists( 'WC_REST_Orders_Controller' ) ) {
                return null;
            }
        }

        $event_slug = self::STATUS_EVENT_MAP[ $status ];

        $controller = new WC_REST_Orders_Controller();
        $request    = new WP_REST_Request( 'GET' );
        $request->set_param( 'id', $order->get_id() );

        // Use prepare_object_for_response directly so we do not hit REST permission
        // callbacks (which assume a logged-in user with `read_shop_orders`).
        try {
            $response = $controller->prepare_object_for_response( $order, $request );
        } catch ( \Throwable $e ) {
            return null;
        }

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $payload = $response->get_data();
        if ( ! is_array( $payload ) ) {
            return null;
        }

        $payload['event'] = 'abandoned.' . $event_slug;

        return [
            'payload'    => $payload,
            'event_slug' => $event_slug,
        ];
    }

    /**
     * POST the abandoned event to the configured webhook URL.
     *
     * @return array{success:bool,code:int,body:string,event_slug:string}
     */
    public function dispatch( WC_Order $order, string $status, string $webhook_url, string $webhook_secret, bool $write_log = true ): array {
        $built = $this->build_payload( $order, $status );
        if ( null === $built ) {
            return [
                'success'    => false,
                'code'       => 0,
                'body'       => 'invalid_status_or_serialization_failed',
                'event_slug' => '',
            ];
        }

        $event_slug = $built['event_slug'];
        $payload    = $built['payload'];
        $body       = wp_json_encode( $payload );

        if ( false === $body ) {
            return [
                'success'    => false,
                'code'       => 0,
                'body'       => 'json_encode_failed',
                'event_slug' => $event_slug,
            ];
        }

        $signature = base64_encode( hash_hmac( 'sha256', $body, $webhook_secret, true ) );

        $response = wp_remote_post(
            $webhook_url,
            [
                'timeout' => 15,
                'headers' => [
                    'Content-Type'             => 'application/json',
                    'X-WC-Webhook-Event'       => 'abandoned.' . $event_slug,
                    'X-WC-Webhook-Topic'       => 'order.abandoned.' . $event_slug,
                    'X-WC-Webhook-Signature'   => $signature,
                    'X-WC-Webhook-Source'      => home_url( '/' ),
                    'X-WC-Webhook-Delivery-ID' => wp_generate_uuid4(),
                    'User-Agent'               => 'WP-Bouncer/' . ( defined( 'WC_BOUNCER_WHATSAPP_VERSION' ) ? \WC_BOUNCER_WHATSAPP_VERSION : 'dev' ),
                ],
                'body'    => $body,
            ]
        );

        if ( is_wp_error( $response ) ) {
            $result = [
                'success'    => false,
                'code'       => 0,
                'body'       => $response->get_error_message(),
                'event_slug' => $event_slug,
            ];
        } else {
            $code = (int) wp_remote_retrieve_response_code( $response );
            $body_resp = (string) wp_remote_retrieve_body( $response );
            $result = [
                'success'    => $code >= 200 && $code < 300,
                'code'       => $code,
                'body'       => $body_resp,
                'event_slug' => $event_slug,
            ];
        }

        if ( $write_log ) {
            // LogsPage view treats 'success' as the only "Sent" status; anything
            // else renders as failed. Match that convention here.
            $log_status = $result['success'] ? 'success' : 'failed';
            $this->logger->record(
                $order->get_id(),
                (string) $order->get_billing_phone(),
                'abandoned.' . $event_slug . ' webhook',
                $log_status,
                $result['code'],
                $result['body']
            );
        }

        return $result;
    }
}
