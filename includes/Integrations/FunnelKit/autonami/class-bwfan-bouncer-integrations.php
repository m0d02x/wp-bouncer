<?php

final class BWFAN_Bouncer_Integration extends BWFAN_Integration {
	private static $ins = null;
	protected $connector_slug = 'bwfco_bouncer';
	protected $need_connector = true;

	private function __construct() {
		$this->action_dir = __DIR__;
		$this->nice_name  = __( 'Bouncer', 'autonami-automations-connectors' );
		$this->group_name = __( 'Messaging', 'autonami-automations-connectors' );
		$this->group_slug = 'messaging';
		$this->priority   = 55;

		add_filter( 'bwfan_whatsapp_services', array( $this, 'add_as_whatsapp_service' ), 10, 1 );
	}

	/**
	 * Add this integration to SMS services list.
	 *
	 * @param $whatsapp_services
	 *
	 * @return array
	 */
	public function add_as_whatsapp_service( $whatsapp_services ) {
		$slug = $this->get_connector_slug();
		
		// Check if connector is connected OR if base wp-bouncer-saas plugin is configured
		$is_connected = BWFAN_Core()->connectors->is_connected( $slug );
		$is_base_configured = BWFCO_Bouncer::is_base_plugin_configured();
		
		if ( $is_connected || $is_base_configured ) {
			$whatsapp_services[] = array( 'value' => $slug, 'label' => $this->nice_name );
		}

		return $whatsapp_services;
	}

	public static function get_instance() {
		if ( null === self::$ins ) {
			self::$ins = new self();
		}

		return self::$ins;
	}

	protected function do_after_action_registration( BWFAN_Action $action_object ) {
		$action_object->connector = $this->connector_slug;
	}

}

/**
 * Register this class as an integration.
 */
BWFAN_Load_Integrations::register( 'BWFAN_Bouncer_Integration' );