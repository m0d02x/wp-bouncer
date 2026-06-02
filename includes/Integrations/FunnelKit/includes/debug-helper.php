<?php
/**
 * Bouncer Debug Helper
 *
 * Add detailed logging for debugging
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFCO_Bouncer_Debug', false ) ) {
class WFCO_Bouncer_Debug {

	private static $log_file = null;

	public static function init() {
		self::$log_file = WFCO_BOUNCER_PLUGIN_DIR . '/bouncer-debug.log';

		// Clear log file if it's older than 1 day
		if ( file_exists( self::$log_file ) && ( time() - filemtime( self::$log_file ) ) > 86400 ) {
			@unlink( self::$log_file );
		}
	}

	public static function log( $message, $data = null ) {
		if ( ! self::$log_file ) {
			self::init();
		}

		$timestamp = date( 'Y-m-d H:i:s' );
		$log_entry = "[{$timestamp}] {$message}";

		if ( $data !== null ) {
			$log_entry .= "\n" . print_r( $data, true );
		}

		$log_entry .= "\n" . str_repeat( '-', 80 ) . "\n";

		@file_put_contents( self::$log_file, $log_entry, FILE_APPEND );

		// Also log to WordPress debug log if enabled
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( 'BOUNCER: ' . $message );
			if ( $data !== null ) {
				error_log( 'BOUNCER DATA: ' . print_r( $data, true ) );
			}
		}
	}

	public static function get_log() {
		if ( ! self::$log_file || ! file_exists( self::$log_file ) ) {
			return "No log file found.";
		}

		return file_get_contents( self::$log_file );
	}

	public static function clear_log() {
		if ( self::$log_file && file_exists( self::$log_file ) ) {
			@unlink( self::$log_file );
		}
	}
}
}

class WFCO_Bouncer_Base_Log {

	public static function record( $data, $response, $label = 'Message' ) {
		if ( ! class_exists( '\\Bouncer\\WooCommerce\\WhatsApp\\Service\\Logger' ) || ! class_exists( '\\Bouncer\\WooCommerce\\WhatsApp\\Repository\\LogRepository' ) ) {
			return;
		}

		global $wpdb;
		if ( ! $wpdb ) {
			return;
		}

		try {
			$logger = new \Bouncer\WooCommerce\WhatsApp\Service\Logger(
				new \Bouncer\WooCommerce\WhatsApp\Repository\LogRepository( $wpdb )
			);

			$logger->record(
				self::get_order_id( $data ),
				self::get_phone( $data ),
				self::get_message( $data, $label, $response ),
				self::is_success( $response ) ? 'success' : 'failed',
				self::get_response_code( $response ),
				self::get_response_body( $response )
			);
		} catch ( \Throwable $exception ) {
			WFCO_Bouncer_Debug::log( 'Base log bridge failed', array( 'message' => $exception->getMessage() ) );
		}
	}

	private static function get_order_id( $data ) {
		return ! empty( $data['order_id'] ) ? absint( $data['order_id'] ) : 0;
	}

	private static function get_phone( $data ) {
		$candidates = array( 'number', 'phone', 'recipient', 'to', 'mobile', 'billing_phone', 'contact_no' );
		foreach ( $candidates as $key ) {
			if ( ! empty( $data[ $key ] ) && is_scalar( $data[ $key ] ) ) {
				return (string) $data[ $key ];
			}
		}

		// Fall back to order billing phone for WC order automations.
		if ( ! empty( $data['order_id'] ) && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( absint( $data['order_id'] ) );
			if ( $order && method_exists( $order, 'get_billing_phone' ) ) {
				$phone = (string) $order->get_billing_phone();
				if ( '' !== $phone ) {
					return $phone;
				}
			}
		}

		// Fall back to abandoned cart checkout data when present.
		if ( ! empty( $data['cart_abandoned_id'] ) && class_exists( 'BWFAN_Merge_Tag_Loader' ) ) {
			$cart_details = BWFAN_Merge_Tag_Loader::get_data( 'cart_details' );
			if ( is_array( $cart_details ) && ! empty( $cart_details['checkout_data'] ) ) {
				$checkout = json_decode( (string) $cart_details['checkout_data'], true );
				if ( is_array( $checkout ) && isset( $checkout['fields']['billing_phone'] ) && '' !== $checkout['fields']['billing_phone'] ) {
					return (string) $checkout['fields']['billing_phone'];
				}
			}
		}

		return '';
	}

	private static function get_message( $data, $label, $response = null ) {
		$rendered_content = self::get_rendered_content( $response );
		if ( '' !== $rendered_content ) {
			return $rendered_content;
		}

		if ( ! empty( $data['template_name'] ) ) {
			return sprintf( '[FunnelKit] Template: %s', $data['template_name'] );
		}

		if ( 'Group' === $label ) {
			return '[FunnelKit] Group: ' . ( ! empty( $data['sms_body'] ) ? $data['sms_body'] : $label );
		}

		return '[FunnelKit] ' . ( ! empty( $data['sms_body'] ) ? $data['sms_body'] : $label );
	}

	private static function get_rendered_content( $response ) {
		$body = is_array( $response ) && array_key_exists( 'body', $response ) ? $response['body'] : null;
		if ( is_array( $body ) && ! empty( $body['renderedContent'] ) && is_string( $body['renderedContent'] ) ) {
			return $body['renderedContent'];
		}

		return '';
	}

	private static function is_success( $response ) {
		$response_code = is_array( $response ) && isset( $response['response'] ) ? (int) $response['response'] : 0;

		return is_array( $response ) && 200 === $response_code && isset( $response['body']['success'] ) && true === $response['body']['success'];
	}

	private static function get_response_code( $response ) {
		return is_array( $response ) && isset( $response['response'] ) ? (int) $response['response'] : 0;
	}

	private static function get_response_body( $response ) {
		$body = is_array( $response ) && array_key_exists( 'body', $response ) ? $response['body'] : $response;

		if ( is_scalar( $body ) || null === $body ) {
			return (string) $body;
		}

		return function_exists( 'wp_json_encode' ) ? wp_json_encode( $body ) : json_encode( $body );
	}
}

// Initialize
if ( class_exists( 'WFCO_Bouncer_Debug', false ) ) {
	WFCO_Bouncer_Debug::init();
}
