<?php

class WFCO_Bouncer_Verify extends \WFCO_Call {
    private static $instance = null;
	private $api_end_point = null;

	public function __construct() {
		$this->required_fields = array( 'instance_id', 'api_key' );
	}

    /**
	 * @return WFCO_Bouncer_Verify|null
	 */

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

    /**
	 * Get call slug
	 *
	 * @return string
	 */
	public function get_slug() {
		return 'wfco_bouncer_verify';
	}

    public function process() {
		$is_required_fields_present = $this->check_fields( $this->data, $this->required_fields );
		if ( false === $is_required_fields_present ) {
			return $this->show_fields_error();
		}

		$instance_id = $this->data['instance_id'];
		$url = "https://api.bouncer.my/api/v1/instances/{$instance_id}";

		$headers = array(
			'Content-Type' => 'application/json',
			'X-API-Key' => $this->data['api_key']
		);

		$res = $this->make_wp_requests( $url, array(), $headers, \BWF_CO::$GET );

		return $res;
	}
}

return 'WFCO_Bouncer_Verify';