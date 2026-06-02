<?php

/** 
 ** 
 **              ██╗ █████╗ ██████╗ ██╗██╗      █████╗ ██╗  ██╗    ███╗   ███╗██╗   ██╗███████╗██╗     ██╗███╗   ███╗             
 **              ██║██╔══██╗██╔══██╗██║██║     ██╔══██╗██║  ██║    ████╗ ████║██║   ██║██╔════╝██║     ██║████╗ ████║             
 **              ██║███████║██║  ██║██║██║     ███████║███████║    ██╔████╔██║██║   ██║███████╗██║     ██║██╔████╔██║             
 **         ██   ██║██╔══██║██║  ██║██║██║     ██╔══██║██╔══██║    ██║╚██╔╝██║██║   ██║╚════██║██║     ██║██║╚██╔╝██║             
 **         ╚█████╔╝██║  ██║██████╔╝██║███████╗██║  ██║██║  ██║    ██║ ╚═╝ ██║╚██████╔╝███████║███████╗██║██║ ╚═╝ ██║             
 **          ╚════╝ ╚═╝  ╚═╝╚═════╝ ╚═╝╚══════╝╚═╝  ╚═╝╚═╝  ╚═╝    ╚═╝     ╚═╝ ╚═════╝ ╚══════╝╚══════╝╚═╝╚═╝     ╚═╝             
 **                                                                                                                               
 **  █████╗ ███╗   ███╗ █████╗ ███╗   ██╗ █████╗ ██╗  ██╗       ██╗       ██████╗ ███████╗██████╗  █████╗ ██████╗  █████╗ ██████╗ 
 ** ██╔══██╗████╗ ████║██╔══██╗████╗  ██║██╔══██╗██║  ██║       ██║       ██╔══██╗██╔════╝██╔══██╗██╔══██╗██╔══██╗██╔══██╗██╔══██╗
 ** ███████║██╔████╔██║███████║██╔██╗ ██║███████║███████║    ████████╗    ██████╔╝█████╗  ██████╔╝███████║██║  ██║███████║██████╔╝
 ** ██╔══██║██║╚██╔╝██║██╔══██║██║╚██╗██║██╔══██║██╔══██║    ██╔═██╔═╝    ██╔══██╗██╔══╝  ██╔══██╗██╔══██║██║  ██║██╔══██║██╔══██╗
 ** ██║  ██║██║ ╚═╝ ██║██║  ██║██║ ╚████║██║  ██║██║  ██║    ██████║      ██████╔╝███████╗██║  ██║██║  ██║██████╔╝██║  ██║██████╔╝
 ** ╚═╝  ╚═╝╚═╝     ╚═╝╚═╝  ╚═╝╚═╝  ╚═══╝╚═╝  ╚═╝╚═╝  ╚═╝    ╚═════╝      ╚═════╝ ╚══════╝╚═╝  ╚═╝╚═╝  ╚═╝╚═════╝ ╚═╝  ╚═╝╚═════╝ 
 **                                                                                                                     
 ** Semoga Allah Ta'ala TIDAK memberkahi perniagaan orang yang mengutip kode program di plugin ini 
 ** "Tidak dihalalkan harta seorang muslimin kecuali yang diberikan dari ketulusan hatinya yang dalam."
 ** ( HR Al Baihaqi, Ahmad, Ad-Daraquthni - Dishahihkan oleh Al-Albani )
 ** 
***/

class BWFCO_Bouncer extends BWF_CO {
	public static $api_end_point = "https://api.bouncer.my";
	public static $headers = null;
	private static $ins = null;
	public $v2 = true;

	public function __construct() {

		/** Connector.php initialization */
		$this->slug = 'bwfco_bouncer';
		$this->keys_to_track = [
			'base_settings',
		];
		$this->form_req_keys = [];

		$this->sync          = false;
		$this->connector_url = WFCO_BOUNCER_PLUGIN_URL;
		$this->dir           = __DIR__;
		$this->nice_name     = __( 'Bouncer', 'autonami-automations-connectors' );

		$this->autonami_int_slug = 'BWFAN_Bouncer_Integration';

		add_filter( 'wfco_connectors_loaded', array( $this, 'add_card' ) );
		add_action( 'wp_ajax_bwf_test_message', array( __CLASS__, 'send_message_via_ajax_call' ) );
		add_action( 'wp_ajax_bwf_test_message_group', array( __CLASS__, 'send_message_group_via_ajax_call' ) );

		WFCO_Bouncer_Debug::log( 'BWFCO_Bouncer constructor called', array(
			'slug' => $this->slug,
			'integration_slug' => $this->autonami_int_slug,
			'keys_to_track' => $this->keys_to_track
		) );
	}

	public function get_fields_schema() {
		return array(
			array(
				'id'       => 'bouncer_base_settings_notice',
				'type'     => 'notice',
				'class'    => '',
				'status'   => self::is_base_plugin_configured() ? 'info' : 'error',
				'message'  => self::is_base_plugin_configured()
					? __( 'Bouncer is configured in Bouncer WhatsApp. FunnelKit uses that connection automatically; credentials and instance details are not stored or shown here.', 'autonami-automations-connectors' )
					: __( 'Configure your API key and WhatsApp instance in Bouncer WhatsApp before using this FunnelKit connector.', 'autonami-automations-connectors' ),
				'dismiss'  => false,
				'required' => false,
				'toggler'  => array(),
			),
		);
	}

	public function get_settings_fields_values() {
		return array();
	}

	/**
	 * Get credentials from the base wp-bouncer-saas plugin.
	 *
	 * @return array{api_key: string, instance_id: string}
	 */
	public static function get_base_plugin_credentials() {
		$credentials = array(
			'api_key'     => '',
			'instance_id' => '',
		);

		// Try to get from wp-bouncer-saas plugin option
		$bouncer_saas_settings = get_option( 'wc_bouncer_whatsapp_settings', array() );
		if ( is_array( $bouncer_saas_settings ) ) {
			if ( ! empty( $bouncer_saas_settings['api_key'] ) ) {
				$credentials['api_key'] = $bouncer_saas_settings['api_key'];
			}
			if ( ! empty( $bouncer_saas_settings['instance_id'] ) ) {
				$credentials['instance_id'] = $bouncer_saas_settings['instance_id'];
			}
		}

		return $credentials;
	}

	/**
	 * Check if base wp-bouncer-saas plugin is configured.
	 *
	 * @return bool
	 */
	public static function is_base_plugin_configured() {
		$credentials = self::get_base_plugin_credentials();
		return ! empty( $credentials['api_key'] ) && ! empty( $credentials['instance_id'] );
	}

	/**
	 * Let FunnelKit treat the built-in integration as connected when the base
	 * Bouncer WhatsApp plugin already has valid connection settings.
	 *
	 * FunnelKit's connector listing first checks its own WFCO saved connector
	 * rows. The built-in integration intentionally uses the base plugin as the
	 * single source of truth, so there may be no separate WFCO row.
	 *
	 * @return bool
	 */
	public function is_connected() {
		return self::is_base_plugin_configured();
	}

	/**
	 * Get full settings from the base wp-bouncer-saas plugin.
	 *
	 * @return array
	 */
	public static function get_base_plugin_settings() {
		$bouncer_saas_settings = get_option( 'wc_bouncer_whatsapp_settings', array() );
		return is_array( $bouncer_saas_settings ) ? $bouncer_saas_settings : array();
	}

	/**
	 * Check if base plugin is using Cloud API instance type.
	 *
	 * @return bool
	 */
	public static function is_cloud_api_instance() {
		$settings = self::get_base_plugin_settings();
		return isset( $settings['instance_type'] ) && 'cloud-api' === $settings['instance_type'];
	}

	/**
	 * Get Cloud API template configuration from base plugin.
	 *
	 * @return array
	 */
	public static function get_cloud_template_config() {
		$settings = self::get_base_plugin_settings();
		return isset( $settings['cloud_template_config'] ) ? $settings['cloud_template_config'] : array();
	}

	/**
	 * Get template variables mapping for a specific template.
	 *
	 * @param string $template_name The template name.
	 * @return array
	 */
	public static function get_template_variables( $template_name ) {
		$config = self::get_cloud_template_config();
		$template_variables = isset( $config['template_variables'] ) ? $config['template_variables'] : array();
		return isset( $template_variables[ $template_name ] ) ? $template_variables[ $template_name ] : array();
	}

	/**
	 * Fetch Cloud API templates from the API.
	 * Returns array of templates with name, language, status, etc.
	 *
	 * @return array
	 */
	public static function fetch_cloud_templates() {
		$credentials = self::get_base_plugin_credentials();
		if ( empty( $credentials['api_key'] ) || empty( $credentials['instance_id'] ) ) {
			WFCO_Bouncer_Debug::log( 'fetch_cloud_templates: No credentials' );
			return array();
		}

		// Check for cached templates (cache for 5 minutes)
		$cache_key = 'bouncer_cloud_templates_' . md5( $credentials['instance_id'] );
		$cached = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) && ! empty( $cached ) ) {
			WFCO_Bouncer_Debug::log( 'fetch_cloud_templates: Using cached data', $cached );
			return $cached;
		}

		// Fetch from API
		$url = 'https://api.bouncer.my/api/v1/cloud/templates?' . http_build_query( array(
			'instanceId' => $credentials['instance_id'],
		) );

		$response = wp_remote_get( $url, array(
			'headers' => array(
				'Content-Type' => 'application/json',
				'X-API-Key'    => $credentials['api_key'],
			),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$raw_body = wp_remote_retrieve_body( $response );
		$body = json_decode( $raw_body, true );
		$code = wp_remote_retrieve_response_code( $response );
		
		WFCO_Bouncer_Debug::log( 'fetch_cloud_templates: API response', array(
			'code' => $code,
			'body_keys' => is_array( $body ) ? array_keys( $body ) : 'not_array',
		) );

		if ( $code < 200 || $code >= 300 ) {
			WFCO_Bouncer_Debug::log( 'fetch_cloud_templates: Bad response code', $code );
			return array();
		}

		// Parse response - templates can be in $body['templates'] or $body directly
		$items = $body['templates'] ?? $body;
		if ( ! is_array( $items ) ) {
			WFCO_Bouncer_Debug::log( 'fetch_cloud_templates: Items not array' );
			return array();
		}

		$templates = array();
		foreach ( $items as $template ) {
			if ( isset( $template['name'] ) ) {
				$templates[] = array(
					'name'     => $template['name'],
					'language' => $template['language'] ?? 'en',
					'status'   => $template['status'] ?? 'unknown',
				);
			}
		}

		WFCO_Bouncer_Debug::log( 'fetch_cloud_templates: Parsed templates', $templates );

		// Cache for 5 minutes
		set_transient( $cache_key, $templates, 5 * MINUTE_IN_SECONDS );

		return $templates;
	}

	/**
	 * Get template language from API-fetched templates.
	 *
	 * @param string $template_name Template name.
	 * @return string Language code (defaults to 'en').
	 */
	public static function get_template_language( $template_name ) {
		// First check config (for templates mapped to order statuses)
		$config = self::get_cloud_template_config();
		$template_languages = isset( $config['template_languages'] ) ? $config['template_languages'] : array();
		if ( isset( $template_languages[ $template_name ] ) ) {
			return $template_languages[ $template_name ];
		}

		// Fallback: fetch from API
		$templates = self::fetch_cloud_templates();
		foreach ( $templates as $template ) {
			if ( $template['name'] === $template_name ) {
				return $template['language'];
			}
		}

		return 'en';
	}

	/**
	 * Get data from the API call, must required function otherwise call
	 *
	 * @param $data
	 *
	 * @return array
	 */
	protected function get_api_data( $data ) {
		WFCO_Bouncer_Debug::log( 'get_api_data called', $data );

		$base_credentials = self::get_base_plugin_credentials();
		if ( empty( $data['api_key'] ) && ! empty( $base_credentials['api_key'] ) ) {
			$data['api_key'] = $base_credentials['api_key'];
		}
		if ( empty( $data['instance_id'] ) && ! empty( $base_credentials['instance_id'] ) ) {
			$data['instance_id'] = $base_credentials['instance_id'];
		}

		$load_connector = WFCO_Load_Connectors::get_instance();
		$call_class     = $load_connector->get_call( 'wfco_bouncer_verify' );

		$resp_array = array(
			'api_data' => $data,
			'status'   => 'failed',
			'message'  => __( 'There is problem verifying your credentials. Confirm entered details.', 'autonami-automations-connectors' ),
		);

		if ( is_null( $call_class ) ) {
			WFCO_Bouncer_Debug::log( 'ERROR: Verify call class is null' );
			return $resp_array;
		}

		$payload = array(
			'api_key'     => isset( $data['api_key'] ) ? $data['api_key'] : '',
			'instance_id' => isset( $data['instance_id'] ) ? $data['instance_id'] : '',
		);

		$call_class->set_data( $payload );
		$request = $call_class->process();

		WFCO_Bouncer_Debug::log( 'API verification response', $request );

		if ( is_array( $request ) && (200 === $request['response'] || 304 === $request['response']) && isset( $request['body'] ) ) {
			if ( ! isset( $request['body']['success'] ) ) {
				$resp_array['status']   = 'failed';
				$resp_array['message']  = __( 'Undefined API Error', 'autonami-automations-connectors' );
				$resp_array['api_data'] = array();

				return $resp_array;
			}

			if ( $request['body']['success'] === false ) {
				$resp_array['status']   = 'failed';
				$resp_array['message']  = isset( $request['body']['error']['message'] ) ? $request['body']['error']['message'] : __( 'Undefined API Error', 'autonami-automations-connectors' );
				$resp_array['api_data'] = array();

				return $resp_array;
			}

		} else {
			$resp_array['status']   = 'failed';
			$resp_array['message']  = __( 'Unable to verify credentials', 'autonami-automations-connectors' );
			$resp_array['api_data'] = array();

			return $resp_array;
		}

		$response							= [];
		$response['status']					= 'success';
		$response['api_data']['base_settings'] = 'wc_bouncer_whatsapp_settings';


		return $response;
	}

	public static function get_instance() {
		if ( null === self::$ins ) {
			self::$ins = new self();
		}

		return self::$ins;
	}

	public static function set_headers( $api_key ) {

		$headers = array(
			'Content-Type' => 'application/json',
			'X-API-Key' => $api_key
		);

		self::$headers = $headers;
	}

	public function add_card( $available_connectors ) {
		$available_connectors['autonami']['connectors']['bwfco_bouncer'] = array(
			'name'            => 'Bouncer',
			'desc'            => __( 'Engage your customers via WhatsApp using Bouncer API, send messages and broadcast.', 'autonami-automations-connectors' ),
			'connector_class' => 'BWFCO_Bouncer',
			'image'           => $this->get_image(),
			'source'          => '',
			'file'            => '',
		);

		return $available_connectors;
	}

	/**
	 * Sending message by ajax request
	 */
	public static function send_message_via_ajax_call() {
		BWFAN_Common::check_nonce();
		$response = self::send_message( true );
		wp_send_json( $response );
	}

	public static function send_message_group_via_ajax_call() {
		BWFAN_Common::check_nonce();
		$response = self::send_message_group( true );
		wp_send_json( $response );
	}

	public static function send_message_via_broadcast( $phone, $messages, $utm = [] ) {
		$response = [
			'status' => false,
		];
		foreach ( $messages as $message ) {
			$data                   = [ 'data' => [] ];
			$data['data']['sms_to'] = $phone;
			$data['data']['type']   = $data['data']['bwfan_sms_select'] = $message['type'];
			if ( $message['type'] == 'text' ) {
				$data['data']['sms_body_textarea'] = $message['data'];
			} else {
				$data['data']['sms_body_text'] = $message['data'];
			}
			if ( ! empty( $utm ) ) {
				if ( isset( $utm['utm_source'] ) && ! empty( $utm['utm_source'] ) ) {
					$data['data']['sms_utm_source'] = $utm['utm_source'];
				}
				if ( isset( $utm['utm_medium'] ) && ! empty( $utm['utm_medium'] ) ) {
					$data['data']['sms_utm_medium'] = $utm['utm_medium'];
				}
				if ( isset( $utm['utm_name'] ) && ! empty( $utm['utm_name'] ) ) {
					$data['data']['sms_utm_campaign'] = $utm['utm_name'];
				}
				if ( isset( $utm['utm_term'] ) && ! empty( $utm['utm_term'] ) ) {
					$data['data']['sms_utm_term'] = $utm['utm_term'];
				}
			}
			$response = self::send_message( false, $data );
		}

		return $response;
	}

	/**
	 * sending test message
	 */
	public static function send_message( $is_ajax, $data = [] ) {
		// phpcs:disable WordPress.Security.NonceVerification
		$result = array(
			'status' => false,
			'msg'    => __( 'Error', 'wp-marketing-automations' ),
		);

		if ( $is_ajax ) {
			$post = $_POST;
		} else {
			$post = $data;
		}
		
		if ( isset( $post['v'] ) && 2 === absint( $post['v'] ) ) {
			if ( ! isset( $post['sms_to'] ) ) {
				$result['msg'] = __( 'Phone number can\'t be blank', 'wp-marketing-automations' );

				return $result;
			}

			$sms_to   = $post['sms_to'];
			$sms_body = isset( $post['sms_body_textarea'] ) ? stripslashes( $post['sms_body_textarea'] ) : '';
			$type     = isset( $post['type'] ) ? sanitize_text_field( $post['type'] ) : 'text';
			$sms_file = isset( $post['sms_body_text'] ) ? stripslashes( $post['sms_body_text'] ) : '';

			$data_to_set['number']   = $sms_to;
			$data_to_set['sms_body'] = $sms_body;
			$data_to_set['type']     = $type;
			$data_to_set['sms_file'] = $sms_file;

		} else {
			if ( ! isset( $post['data']['sms_to'] ) ) {
				$result['msg'] = __( 'Phone number can\'t be blank', 'wp-marketing-automations' );

				return $result;
			}
	
			$type     = isset( $post['data']['type'] ) ? sanitize_text_field( $post['data']['type'] ) : 'text';
			$sms_body = isset( $post['data']['sms_body_textarea'] ) ? stripslashes( $post['data']['sms_body_textarea'] ) : '';
	
			/** for image and file */
			if ( 'text' !== $type ) {
				$sms_body = isset( $post['data']['sms_body_text'] ) ? sanitize_text_field( $post['data']['sms_body_text'] ) : '';
			}

			$data_to_set['number']   = $post['data']['sms_to'];
			$data_to_set['sms_body'] = $sms_body;
			$data_to_set['type']     = $type;
	
		}
	
		$data_to_set['test'] = true;

		/** @var  $global_settings */
		$global_settings = WFCO_Common::$connectors_saved_data;
		$bouncer_settings = array();

		// First try connector saved data
		if ( array_key_exists( 'bwfco_bouncer', $global_settings ) ) {
			$bouncer_settings = $global_settings['bwfco_bouncer'];
		}

		// Fall back to base wp-bouncer-saas plugin credentials if needed
		if ( empty( $bouncer_settings['api_key'] ) || empty( $bouncer_settings['instance_id'] ) ) {
			$base_credentials = self::get_base_plugin_credentials();
			if ( empty( $bouncer_settings['api_key'] ) && ! empty( $base_credentials['api_key'] ) ) {
				$bouncer_settings['api_key'] = $base_credentials['api_key'];
			}
			if ( empty( $bouncer_settings['instance_id'] ) && ! empty( $base_credentials['instance_id'] ) ) {
				$bouncer_settings['instance_id'] = $base_credentials['instance_id'];
			}
		}

		// Check if we have valid credentials from either source
		if ( empty( $bouncer_settings['api_key'] ) || empty( $bouncer_settings['instance_id'] ) ) {
			return array(
				'msg'    => __( 'Bouncer is not connected. Please configure credentials in Bouncer WhatsApp settings.', 'wp-marketing-automations' ),
				'status' => false,
			);
		}

		$load_connector = WFCO_Load_Connectors::get_instance();
		$call_class     = $load_connector->get_call( 'wfco_bouncer_send_sms' );

		$data_to_set['api_key']      = $bouncer_settings['api_key'];
		$data_to_set['instance_id']  = $bouncer_settings['instance_id'];

		// is_preview set to true for merge tag before sending data for sms;
		BWFAN_Merge_Tag_Loader::set_data( array(
			'is_preview' => true,
		) );
		
		$call_class->set_data( $data_to_set );
		$response = $call_class->process();

		if ( is_array( $response ) && 200 === $response['response'] && ( isset( $response['body']['success'] ) && $response['body']['success'] === true ) ) {
			return array(
				'status' => true,
				'msg'    => __( 'Message sent successfully.', 'wp-marketing-automations' ),
			);
		}

		$message = __( 'Message could not be sent. ', 'autonami-automations-connectors' );
		$status  = false;

		if ( isset( $response['body']['success'] ) && $response['body']['success'] === false && isset( $response['body']['error']['message'] ) ) {
			$message = $response['body']['error']['message'];
		} elseif ( isset( $response['body']['error']['message'] ) ) {
			$message = $response['body']['error']['message'];
		} elseif ( isset( $response['bwfan_response'] ) && ! empty( $response['bwfan_response'] ) ) {
			$message = $response['bwfan_response'];
		} elseif ( is_array( $response['body'] ) && isset( $response['body'][0] ) && is_string( $response['body'][0] ) ) {
			$message = $message . $response['body'][0];
		}

		return array(
			'status' => $status,
			'msg'    => $message,
		);
	}

	public static function send_message_group( $is_ajax, $data = [] ) {
		// phpcs:disable WordPress.Security.NonceVerification
		$result = array(
			'status' => false,
			'msg'    => __( 'Error', 'wp-marketing-automations' ),
		);

		if ( $is_ajax ) {
			$post = $_POST;
		} else {
			$post = $data;
		}
		if ( isset( $post['v'] ) && 2 === absint( $post['v'] ) ) {
			if ( ! isset( $post['sms_to'] ) ) {
				$result['msg'] = __( 'Phone number can\'t be blank', 'wp-marketing-automations' );

				return $result;
			}

			$sms_to   = $post['sms_to'];
			$sms_body = isset( $post['sms_body_textarea'] ) ? stripslashes( $post['sms_body_textarea'] ) : '';
			$type     = isset( $post['type'] ) ? sanitize_text_field( $post['type'] ) : 'text';
			$sms_file = isset( $post['sms_body_text'] ) ? stripslashes( $post['sms_body_text'] ) : '';

			$data_to_set['number']   = $sms_to;
			$data_to_set['sms_body'] = $sms_body;
			$data_to_set['type']     = $type;
			$data_to_set['sms_file'] = $sms_file;

		} else {
			if ( ! isset( $post['data']['sms_to'] ) ) {
				$result['msg'] = __( 'Phone number can\'t be blank', 'wp-marketing-automations' );

				return $result;
			}

			$type     = isset( $post['data']['type'] ) ? sanitize_text_field( $post['data']['type'] ) : 'text';
			$sms_body = isset( $post['data']['sms_body_textarea'] ) ? sanitize_text_field( $post['data']['sms_body_textarea'] ) : '';
			$sms_file = isset( $post['data']['sms_body_text'] ) ? stripslashes( $post['data']['sms_body_text'] ) : '';

			$data_to_set['number']   = $post['data']['sms_to'];
			$data_to_set['sms_body'] = $sms_body;
			$data_to_set['type']     = $type;
			$data_to_set['sms_file'] = $sms_file;
		}

		$data_to_set['test'] = true;

		/** @var  $global_settings */
		$global_settings = WFCO_Common::$connectors_saved_data;
		$bouncer_settings = array();

		// First try connector saved data
		if ( array_key_exists( 'bwfco_bouncer', $global_settings ) ) {
			$bouncer_settings = $global_settings['bwfco_bouncer'];
		}

		// Fall back to base wp-bouncer-saas plugin credentials if needed
		if ( empty( $bouncer_settings['api_key'] ) || empty( $bouncer_settings['instance_id'] ) ) {
			$base_credentials = self::get_base_plugin_credentials();
			if ( empty( $bouncer_settings['api_key'] ) && ! empty( $base_credentials['api_key'] ) ) {
				$bouncer_settings['api_key'] = $base_credentials['api_key'];
			}
			if ( empty( $bouncer_settings['instance_id'] ) && ! empty( $base_credentials['instance_id'] ) ) {
				$bouncer_settings['instance_id'] = $base_credentials['instance_id'];
			}
		}

		// Check if we have valid credentials from either source
		if ( empty( $bouncer_settings['api_key'] ) || empty( $bouncer_settings['instance_id'] ) ) {
			return array(
				'msg'    => __( 'Bouncer is not connected. Please configure credentials in Bouncer WhatsApp settings.', 'wp-marketing-automations' ),
				'status' => false,
			);
		}

		$load_connector = WFCO_Load_Connectors::get_instance();
		$call_class     = $load_connector->get_call( 'wfco_bouncer_send_group' );

		$data_to_set['api_key']      = $bouncer_settings['api_key'];
		$data_to_set['instance_id']  = $bouncer_settings['instance_id'];

		// is_preview set to true for merge tag before sending data for sms;
		BWFAN_Merge_Tag_Loader::set_data( array(
			'is_preview' => true,
		) );
		$call_class->set_data( $data_to_set );
		$response = $call_class->process();

		if ( is_array( $response ) && 200 === $response['response'] && ( isset( $response['body']['success'] ) && $response['body']['success'] === true ) ) {
			return array(
				'status' => true,
				'msg'    => __( 'Message sent successfully.', 'wp-marketing-automations' ),
			);
		}

		$message = __( 'Message could not be sent. ', 'autonami-automations-connectors' );
		$status  = false;

		if ( isset( $response['body']['success'] ) && $response['body']['success'] === false && isset( $response['body']['error']['message'] ) ) {
			$message = $response['body']['error']['message'];
		} elseif ( isset( $response['body']['error']['message'] ) ) {
			$message = $response['body']['error']['message'];
		} elseif ( isset( $response['bwfan_response'] ) && ! empty( $response['bwfan_response'] ) ) {
			$message = $response['bwfan_response'];
		} elseif ( is_array( $response['body'] ) && isset( $response['body'][0] ) && is_string( $response['body'][0] ) ) {
			$message = $message . $response['body'][0];
		}

		return array(
			'status' => $status,
			'msg'    => $message,
		);
	}	
}

WFCO_Load_Connectors::register( 'BWFCO_Bouncer' );
