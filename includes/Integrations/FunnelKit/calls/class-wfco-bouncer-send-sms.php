<?php

class WFCO_Bouncer_Send_SMS extends WFCO_Call {

	private static $instance = null;
	private $api_end_point = null;

	public function __construct() {
		// Note: sms_body is only required for text/media types, not for template type
		$this->required_fields = array( 'api_key', 'instance_id', 'number', 'type' );
	}

	/**
	 * @return WFCO_Bouncer_Send_SMS|null
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function process() {
		WFCO_Bouncer_Debug::log( 'WFCO_Bouncer_Send_SMS::process() called', $this->data );

		$is_required_fields_present = $this->check_fields( $this->data, $this->required_fields );
		if ( false === $is_required_fields_present ) {
			WFCO_Bouncer_Debug::log( 'ERROR: Required fields missing', $this->required_fields );
			return $this->show_fields_error();
		}

		// For text/media types, sms_body is required
		$type = isset( $this->data['type'] ) ? strval( $this->data['type'] ) : 'text';
		if ( in_array( $type, array( 'text', 'media', 'image' ), true ) ) {
			if ( empty( $this->data['sms_body'] ) && empty( $this->data['sms_file'] ) ) {
				WFCO_Bouncer_Debug::log( 'ERROR: sms_body is required for type: ' . $type );
				return array(
					'response' => 400,
					'body' => array(
						'success' => false,
						'error' => array(
							'message' => __( 'Message body is required for text/media messages.', 'autonami-automations-connectors' )
						)
					),
					'bwfan_response' => __( 'Message body is required.', 'autonami-automations-connectors' )
				);
			}
		}

		// For template type, send via Cloud API template endpoint
		if ( 'template' === $type ) {
			return $this->send_template_message();
		}

		$base_url = "https://api.bouncer.my";

		$headers = array(
			'Content-Type' => 'application/json',
			'X-API-Key' => $this->data['api_key']
		);

		$numbers = trim( stripslashes( $this->data['number'] ) );
		$numbers = explode( ',', $numbers );

		$this->data['type']     = $this->data['type'];
		$this->data['sms_body'] = BWFAN_Common::decode_merge_tags( $this->data['sms_body'] );

		/** only allow link shorting for message type text */
		if ( 'text' === strval( $this->data['type']) || 'media' === strval( $this->data['type']) ) {
			$this->data['sms_body'] = apply_filters( 'bwfan_modify_send_message_body', $this->data['sms_body'], $this->data );
		}

		$res = [];
		foreach ( $numbers as $number ) {
			// Clean phone number
			$str = ['+','-',' '];
			$clean_number = str_replace( $str, '', $number );

			/** User 2 digit country code passed */
			if ( isset( $this->data['country_code'] ) && ! empty( $this->data['country_code'] ) ) {
				$clean_number = Phone_Numbers::add_country_code( $number, $this->data['country_code'] );
				$clean_number = str_replace( $str, '', $clean_number );
			}

			// Prepare request based on message type
			if ( 'text' === strval( $this->data['type'] ) ){
				// Send text message
				$url = $base_url . '/api/v1/message/sendText';
				$body = array(
					'phoneNumber' => $clean_number,
					'instanceId'  => $this->data['instance_id'],
					'text'        => $this->data['sms_body'],
				);
			} elseif ( 'media' === strval( $this->data['type'] ) || 'image' === strval( $this->data['type'] ) ){
				// Send media message
				$url = $base_url . '/api/v1/message/sendMedia';

				$media_url = isset( $this->data['sms_file'] ) ? $this->data['sms_file'] : $this->data['sms_body'];
				$caption = '';

				if ( 'media' === strval( $this->data['type'] ) && isset( $this->data['sms_body'] ) ) {
					$caption = $this->data['sms_body'];
				}

				// Determine media type based on file extension or default to image
				$mediatype = 'image';
				if ( isset( $this->data['sms_file'] ) ) {
					$extension = strtolower( pathinfo( $this->data['sms_file'], PATHINFO_EXTENSION ) );
					if ( in_array( $extension, array( 'mp4', 'avi', 'mov', 'wmv' ) ) ) {
						$mediatype = 'video';
					} elseif ( in_array( $extension, array( 'mp3', 'wav', 'ogg', 'm4a' ) ) ) {
						$mediatype = 'audio';
					} elseif ( in_array( $extension, array( 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx' ) ) ) {
						$mediatype = 'document';
					}
				}

				$body = array(
					'phoneNumber' => $clean_number,
					'instanceId'  => $this->data['instance_id'],
					'mediatype'   => $mediatype,
					'media'       => $media_url,
				);

				if ( ! empty( $caption ) ) {
					$body['caption'] = $caption;
				}
			} else {
				// Default to text if unknown type
				$url = $base_url . '/api/v1/message/sendText';
				$body = array(
					'phoneNumber' => $clean_number,
					'instanceId'  => $this->data['instance_id'],
					'text'        => $this->data['sms_body'],
				);
			}

			$res = $this->make_wp_requests( $url, json_encode( $body ), $headers, BWF_CO::$POST );

			WFCO_Bouncer_Debug::log( 'Message sent to ' . $clean_number, array(
				'url' => $url,
				'body' => $body,
				'response' => $res
			) );
		}

		return $res;
	}

	/**
	 * Send Cloud API template message.
	 * Handles template type by getting config from base plugin and sending via Cloud API.
	 *
	 * @return array API response
	 */
	protected function send_template_message() {
		$base_url = "https://api.bouncer.my";

		// Get template configuration from base plugin
		$cloud_config = BWFCO_Bouncer::get_cloud_template_config();
		$template_variables = isset( $cloud_config['template_variables'] ) ? $cloud_config['template_variables'] : array();
		$template_languages = isset( $cloud_config['template_languages'] ) ? $cloud_config['template_languages'] : array();

		// Determine template name - from data or first configured template
		$template_name = '';
		if ( ! empty( $this->data['template_name'] ) ) {
			$template_name = $this->data['template_name'];
		} elseif ( ! empty( $template_variables ) ) {
			$template_name = array_key_first( $template_variables );
		}

		if ( empty( $template_name ) ) {
			WFCO_Bouncer_Debug::log( 'ERROR: No template configured' );
			return array(
				'response' => 400,
				'body' => array(
					'success' => false,
					'error' => array(
						'message' => __( 'No template configured. Please configure templates in Bouncer WhatsApp settings.', 'autonami-automations-connectors' )
					)
				),
				'bwfan_response' => __( 'No template configured.', 'autonami-automations-connectors' )
			);
		}

		// Get template language - prefer from action data, fallback to config, then 'en'
		$template_language = 'en';
		if ( ! empty( $this->data['template_language'] ) ) {
			$template_language = $this->data['template_language'];
		} elseif ( isset( $template_languages[ $template_name ] ) ) {
			$template_language = $template_languages[ $template_name ];
		}

		// Build variables from mappings
		$variables = array();
		$var_mappings = isset( $template_variables[ $template_name ] ) ? $template_variables[ $template_name ] : array();
		
		// Get order for variable resolution
		$order = null;
		if ( ! empty( $this->data['order_id'] ) ) {
			$order = wc_get_order( $this->data['order_id'] );
		}

		foreach ( $var_mappings as $index => $placeholder ) {
			if ( ! empty( $placeholder ) ) {
				$resolved = $this->resolve_template_placeholder( $placeholder, $order );
				$variables[ (string) $index ] = $resolved;
			}
		}

		WFCO_Bouncer_Debug::log( 'Template config resolved', array(
			'template_name' => $template_name,
			'language' => $template_language,
			'variables' => $variables
		) );

		// Prepare request
		$headers = array(
			'Content-Type' => 'application/json',
			'X-API-Key'    => $this->data['api_key']
		);

		// Clean phone number
		$str = array( '+', '-', ' ' );
		$clean_number = str_replace( $str, '', trim( $this->data['number'] ) );

		if ( isset( $this->data['country_code'] ) && ! empty( $this->data['country_code'] ) ) {
			$clean_number = Phone_Numbers::add_country_code( $this->data['number'], $this->data['country_code'] );
			$clean_number = str_replace( $str, '', $clean_number );
		}

		$url = $base_url . '/api/v1/cloud/sendTemplate';
		$body = array(
			'phoneNumber'  => $clean_number,
			'instanceId'   => $this->data['instance_id'],
			'templateName' => $template_name,
			'language'     => $template_language,
		);

		if ( ! empty( $variables ) ) {
			$body['variables'] = $variables;
		}

		$res = $this->make_wp_requests( $url, json_encode( $body ), $headers, BWF_CO::$POST );

		return $res;
	}

	/**
	 * Resolve template placeholder to actual value.
	 *
	 * @param string $placeholder Placeholder like {first_name}, {order_number}
	 * @param WC_Order|null $order Order object for data resolution
	 * @return string Resolved value
	 */
	protected function resolve_template_placeholder( $placeholder, $order = null ) {
		$key = trim( $placeholder, '{}' );

		// Resolve from order first (plain text, no HTML) - BEFORE checking $this->data
		if ( $order instanceof WC_Order ) {
			switch ( $key ) {
				case 'first_name':
					return $order->get_billing_first_name();
				case 'last_name':
					return $order->get_billing_last_name();
				case 'name':
				case 'full_name':
					$name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
					return $name ?: $order->get_billing_first_name();
				case 'order_number':
				case 'order_id':
					return (string) $order->get_order_number();
				case 'order_total':
				case 'amount':
					// Strip HTML for plain text
					$currency = $order->get_currency();
					$total = $order->get_total();
					return wp_strip_all_tags( html_entity_decode( wc_price( $total, array( 'currency' => $currency ) ) ) );
				case 'order_status':
				case 'status':
					return wc_get_order_status_name( $order->get_status() );
				case 'order_date':
					$date = $order->get_date_created();
					return $date ? $date->date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) : '';
				case 'billing_email':
				case 'email':
					return $order->get_billing_email();
				case 'billing_phone':
				case 'phone':
					return $order->get_billing_phone();
				case 'order_items':
					// Plain text format: "1x Product\n2x Other"
					$lines = array();
					foreach ( $order->get_items() as $item ) {
						$name = $item->get_name();
						$qty = $item->get_quantity();
						$lines[] = $qty > 1 ? "{$qty}x {$name}" : $name;
					}
					return implode( "\n", $lines );
				case 'shipping_method':
					$methods = array();
					foreach ( $order->get_items( 'shipping' ) as $item ) {
						$methods[] = $item->get_name();
					}
					return implode( ', ', array_unique( $methods ) ) ?: 'Default shipping';
				case 'payment_method':
					return $order->get_payment_method_title();
				case 'billing_address':
					return $this->format_address_plain( $order, 'billing' );
				case 'shipping_address':
					return $this->format_address_plain( $order, 'shipping' );
				case 'currency':
					return $order->get_currency();
			}
			
			// Check for meta:KEY pattern
			if ( 0 === strpos( $key, 'meta:' ) ) {
				$meta_key = substr( $key, 5 );
				$value = $order->get_meta( $meta_key, true );
				return is_array( $value ) || is_object( $value ) ? wp_json_encode( $value ) : sanitize_text_field( (string) $value );
			}
		}

		// Check simple data keys (but NOT order_items/order_total which need plain text)
		$skip_data_keys = array( 'order_items', 'order_total', 'amount', 'billing_address', 'shipping_address' );
		if ( isset( $this->data[ $key ] ) && ! in_array( $key, $skip_data_keys, true ) ) {
			return $this->data[ $key ];
		}

		// Return placeholder as-is if unresolved
		return $placeholder;
	}

	/**
	 * Format address as plain text.
	 */
	protected function format_address_plain( $order, $type = 'billing' ) {
		if ( 'shipping' === $type ) {
			$parts = array_filter( array(
				trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() ),
				$order->get_shipping_address_1(),
				$order->get_shipping_address_2(),
				$order->get_shipping_city(),
				$order->get_shipping_state(),
				$order->get_shipping_postcode(),
				$order->get_shipping_country(),
			) );
		} else {
			$parts = array_filter( array(
				trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
				$order->get_billing_address_1(),
				$order->get_billing_address_2(),
				$order->get_billing_city(),
				$order->get_billing_state(),
				$order->get_billing_postcode(),
				$order->get_billing_country(),
			) );
		}
		return implode( ', ', array_map( 'trim', $parts ) );
	}

}

return 'WFCO_Bouncer_Send_SMS';
