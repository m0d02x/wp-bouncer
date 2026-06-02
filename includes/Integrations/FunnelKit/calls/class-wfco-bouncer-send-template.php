<?php

class WFCO_Bouncer_Send_Template extends WFCO_Call {

	private static $instance = null;

	public function __construct() {
		$this->required_fields = array( 'api_key', 'instance_id', 'number', 'template_name', 'variables' );
	}

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function process() {
		WFCO_Bouncer_Debug::log( 'WFCO_Bouncer_Send_Template::process() called', $this->data );

		$is_required_fields_present = $this->check_fields( $this->data, $this->required_fields );
		if ( false === $is_required_fields_present ) {
			WFCO_Bouncer_Debug::log( 'ERROR: Required fields missing', $this->required_fields );
			return $this->show_fields_error();
		}

		$base_url = "https://api.bouncer.my";
		$url = $base_url . '/api/v1/cloud/sendTemplate';

		$headers = array(
			'Content-Type' => 'application/json',
			'X-API-Key'    => $this->data['api_key']
		);

		// Clean phone number
		$str = array( '+', '-', ' ' );
		$clean_number = str_replace( $str, '', trim( $this->data['number'] ) );

		// Add country code if provided
		if ( isset( $this->data['country_code'] ) && ! empty( $this->data['country_code'] ) ) {
			$clean_number = Phone_Numbers::add_country_code( $this->data['number'], $this->data['country_code'] );
			$clean_number = str_replace( $str, '', $clean_number );
		}

		$body = array(
			'phoneNumber'  => $clean_number,
			'instanceId'   => $this->data['instance_id'],
			'templateName' => $this->data['template_name'],
			'language'     => isset( $this->data['language'] ) ? $this->data['language'] : 'en',
		);

		// Add variables if not empty
		if ( ! empty( $this->data['variables'] ) && is_array( $this->data['variables'] ) ) {
			$body['variables'] = $this->data['variables'];
		}

		$res = $this->make_wp_requests( $url, json_encode( $body ), $headers, BWF_CO::$POST );

		WFCO_Bouncer_Debug::log( 'Cloud template sent to ' . $clean_number, array(
			'url'      => $url,
			'body'     => $body,
			'response' => $res
		) );

		return $res;
	}
}

return 'WFCO_Bouncer_Send_Template';
