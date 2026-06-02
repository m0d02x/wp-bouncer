<?php

class WFCO_Bouncer_Send_Group extends WFCO_Call {

	private static $instance = null;
	private $api_end_point = null;

	public function __construct() {

		$this->required_fields = array( 'api_key', 'instance_id', 'number', 'type', 'sms_body' );

	}

	/**
	 * @return WFCO_Bouncer_Send_Group|null
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function process() {
		$is_required_fields_present = $this->check_fields( $this->data, $this->required_fields );
		if ( false === $is_required_fields_present ) {
				return $this->show_fields_error();
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
		if ( 'text' === strval( $this->data['type']) ) {
			$this->data['sms_body'] = apply_filters( 'bwfan_modify_send_message_body', $this->data['sms_body'], $this->data );
		}

		$res = [];
		foreach ( $numbers as $group_id ) {
			// Group ID is in format: 120363xxxxxxxxxx@g.us
			$group_jid = trim( $group_id );

			// Prepare request based on message type
			if ( 'text' === strval( $this->data['type'] ) ){
				// Send text message to group
				$url = $base_url . '/api/v1/message/sendText';
				$body = array(
					'phoneNumber' => $group_jid,  // Use group JID as phoneNumber
					'instanceId'  => $this->data['instance_id'],
					'text'        => $this->data['sms_body'],
				);
			} elseif ( 'media' === strval( $this->data['type'] ) || 'image' === strval( $this->data['type'] ) ){
				// Send media message to group
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
					'phoneNumber' => $group_jid,  // Use group JID as phoneNumber
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
					'phoneNumber' => $group_jid,  // Use group JID as phoneNumber
					'instanceId'  => $this->data['instance_id'],
					'text'        => $this->data['sms_body'],
				);
			}

			$res = $this->make_wp_requests( $url, json_encode( $body ), $headers, BWF_CO::$POST );
		}

		return $res;
	}


}

return 'WFCO_Bouncer_Send_Group';
