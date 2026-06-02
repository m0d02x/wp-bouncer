<?php

class BWFAN_Bouncer_Send_SMS extends BWFAN_Action {

	private static $instance = null;
	private $progress = false;

	public function __construct() {
		$this->action_name = __( 'Send Message', 'autonami-automations-connectors' );
		$this->action_desc = __( 'This action sends a message via Bouncer', 'autonami-automations-connectors' );
		$this->support_v2  = true;
	}

	/**
	 * @return BWFAN_Bouncer_Send_SMS|null
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function load_hooks() {
		add_filter( 'bwfan_modify_send_message_body', [ $this, 'shorten_link' ], 15, 1 );
	}

	/**
	 * Show the html fields for the current action.
	 */
	public function get_view() {
		$unique_slug = $this->get_slug();
		?>
        <script type="text/html" id="tmpl-action-<?php echo esc_attr__( $unique_slug ); ?>">
            <#
            sms_body = '';
            phone_merge_tag = '{{customer_phone}}';
            sms_body = '';
            sms_to = (_.has(data.actionSavedData, 'data') && _.has(data.actionSavedData.data, 'sms_to')) ? _.isEmpty(data.actionSavedData.data.sms_to)?phone_merge_tag:data.actionSavedData.data.sms_to: phone_merge_tag;
            sms_body_textarea = (_.has(data.actionSavedData, 'data') && _.has(data.actionSavedData.data, 'sms_body_textarea')) ? data.actionSavedData.data.sms_body_textarea : sms_body;
            sms_body_text = (_.has(data.actionSavedData, 'data') && _.has(data.actionSavedData.data, 'sms_body_text')) ? data.actionSavedData.data.sms_body_text : sms_body;
            bwfan_sms_select = (_.has(data.actionSavedData, 'data') && _.has(data.actionSavedData.data, 'bwfan_sms_select')) ? data.actionSavedData.data.bwfan_sms_select : 'text';
            sms_is_promotional = (_.has(data.actionSavedData, 'data') && _.has(data.actionSavedData.data, 'promotional_sms')) ? 'checked' : '';
            sms_is_order_first = (_.has(data.actionSavedData, 'data') && _.has(data.actionSavedData.data, 'order_first')) ? 'checked' : '';
            sms_is_append_utm = (_.has(data.actionSavedData, 'data') && _.has(data.actionSavedData.data, 'sms_append_utm')) ? 'checked' : '';
            sms_show_utm_parameters = (_.has(data.actionSavedData, 'data') && _.has(data.actionSavedData.data, 'sms_append_utm')) ? '' : 'bwfan-display-none';

            sms_entered_utm_source = (_.has(data.actionSavedData, 'data') && _.has(data.actionSavedData.data, 'sms_utm_source')) ? data.actionSavedData.data.sms_utm_source : '';
            sms_entered_utm_medium = (_.has(data.actionSavedData, 'data') && _.has(data.actionSavedData.data, 'sms_utm_medium')) ? data.actionSavedData.data.sms_utm_medium : '';
            sms_entered_utm_campaign = (_.has(data.actionSavedData, 'data') && _.has(data.actionSavedData.data, 'sms_utm_campaign')) ? data.actionSavedData.data.sms_utm_campaign : '';
            sms_entered_utm_term = (_.has(data.actionSavedData, 'data') && _.has(data.actionSavedData.data, 'sms_utm_term')) ? data.actionSavedData.data.sms_utm_term : '';
            #>
            <div data-element-type="bwfan-editor" class="bwfan-<?php echo esc_attr__( $unique_slug ); ?>">
                <label for="" class="bwfan-label-title">
					<?php
					echo esc_html__( 'To', 'autonami-automations-connectors' );
					echo $this->inline_merge_tag_invoke(); //phpcs:ignore WordPress.Security.EscapeOutput
					?>
                </label>
                <div class="bwfan-col-sm-12 bwfan-pl-0 bwfan-pr-0 bwfan-mb-15">
                    <input required type="text" class="bwfan-input-wrapper bwfan-field-<?php echo esc_attr__( $unique_slug ); ?>" name="bwfan[{{data.action_id}}][data][sms_to]" placeholder="E.g. 919999999999" value="{{sms_to}}"/>
                </div>

                <label for="" class="bwfan-label-title">
					<?php
					echo esc_html__( 'Type', 'autonami-automations-connectors' );
					?>
                </label>
                <div class="bwfan-col-sm-12 bwfan-pl-0 bwfan-pr-0">
                    <label for="bwfan_sms_option_text" class="bwfan-label-title-normal">
                        <input type="radio" name="bwfan[{{data.action_id}}][data][bwfan_sms_select]" class="bwfan_bouncer_sms_option" id="bwfan_sms_option_text" selected value="text" {{bwfan_sms_select==
                        'text' ? 'checked' : ''}}/>
						<?php esc_html_e( 'Text Only', 'autonami-automations-connectors' ); ?>
                    </label>

                    <label for="media" class="bwfan-label-title-normal">
                        <input type="radio" name="bwfan[{{data.action_id}}][data][bwfan_sms_select]" class="bwfan_bouncer_sms_option" id="bwfan_sms_option_media" selected value="media" {{bwfan_sms_select==
                        'media' ? 'checked' :
                        ''}}/>
						<?php
						esc_html_e( 'With Media', 'autonami-automations-connectors' );
						$message = __( "Please include direct URL to image, video or file", "autonami-automations-connectors" );
						echo $this->add_description( $message, 'l', 'right' );
						?>
                    </label>
                </div>
                <label for="" class="bwfan-label-title">
					<?php
					echo esc_html__( 'Text Message', 'autonami-automations-connectors' );
					?>
                </label>
                <div class="bwfan-col-sm-12 bwfan-pl-0 bwfan-pr-0 bwfan-mb-15">
                    <textarea class="bwfan-input-wrapper" id="bwfan-sms-textarea" placeholder="<?php echo esc_attr__( 'Message Body', 'autonami-automations-connectors' ); ?>" name="bwfan[{{data.action_id}}][data][sms_body_textarea]" style="{{bwfan_sms_select =='text'?'display:block;':'display:block;'}}">{{sms_body_textarea}}</textarea>
                </div>
                <label for="" id="bwfan-sms-text-label" class="bwfan-label-title" style="{{bwfan_sms_select =='media'?'display:block;':'display:none;'}}">
					<?php
					echo esc_html__( 'Media URL', 'autonami-automations-connectors' );
					?>
                </label>
                <div class="bwfan-col-sm-12 bwfan-pl-0 bwfan-pr-0 bwfan-mb-15">
                    <input type="text" name="bwfan[{{data.action_id}}][data][sms_body_text]" class="bwfan-input-wrapper" id="bwfan-sms-text" placeholder="Media File URL" value="{{sms_body_text}}" style="{{bwfan_sms_select =='media'?'display:block;':'display:none;'}}">
                </div>

                <div class="bwfan-col-sm-12 bwfan-pl-0 bwfan-pr-0 bwfan-mb-15">
                    <label for="" class="bwfan-label-title"><?php esc_html_e( 'Send Test Message', 'autonami-automations-connectors' ); ?></label>
                    <div class="bwfan_send_test_message">
                        <input type="text" name="test_message" id="bwfan_test_message">
                        <input type="button" class="button bwfan-btn-inner" id="bwfan_test_message_btn" value="<?php esc_html_e( 'Send', 'autonami-automations-connectors' ); ?>">
                    </div>
                    <div class="clearfix bwfan_field_desc">
						<?php esc_html_e( 'Enter Mobile no with country code', 'wp-marketing-automations' ); ?>
                    </div>
                </div>

                <div class="bwfan_sms_tracking bwfan-mb-15">
                    <label for="bwfan_promotional_sms" class="bwfan-label-title-normal">
                        <input type="checkbox" name="bwfan[{{data.action_id}}][data][promotional_sms]" id="bwfan_promotional_sms" value="1" {{sms_is_promotional}}/>
						<?php
						echo esc_html__( 'Mark as Promotional', 'autonami-automations-connectors' );
						$message = __( 'SMS marked as promotional will not be send to the unsubscribers.', 'autonami-automations-connectors' );
						echo $this->add_description( $message, 'xl' ); //phpcs:ignore WordPress.Security.EscapeOutput
						?>
                    </label>
                    <label for="bwfan_append_utm" class="bwfan-label-title-normal">
                        <input type="checkbox" name="bwfan[{{data.action_id}}][data][sms_append_utm]" id="bwfan_append_utm" value="1" {{sms_is_append_utm}}/>
						<?php
						echo esc_html__( 'Add UTM parameters to the links', 'autonami-automations-connectors' );
						$message = __( 'Add UTM parameters in all the links present in the sms.', 'autonami-automations-connectors' );
						echo $this->add_description( $message, 'xl' ); //phpcs:ignore WordPress.Security.EscapeOutput
						?>
                    </label>
                    <div class="bwfan_utm_sources {{sms_show_utm_parameters}}">
                        <div class="bwfan-input-form clearfix">
                            <div class="bwfan-col-sm-4 bwfan-pl-0"><span class="bwfan_label_input"><?php echo esc_html__( 'UTM Source', 'autonami-automations-connectors' ); ?></span></div>
                            <div class="bwfan-col-sm-8 bwfan-pr-0">
                                <input type="text" class="bwfan-input-wrapper" name="bwfan[{{data.action_id}}][data][sms_utm_source]" value="{{sms_entered_utm_source}}"/></div>
                        </div>
                        <div class="bwfan-input-form clearfix">
                            <div class="bwfan-col-sm-4 bwfan-pl-0"><span class="bwfan_label_input"><?php echo esc_html__( 'UTM Medium', 'autonami-automations-connectors' ); ?></span></div>
                            <div class="bwfan-col-sm-8 bwfan-pr-0">
                                <input type="text" class="bwfan-input-wrapper" name="bwfan[{{data.action_id}}][data][sms_utm_medium]" value="{{sms_entered_utm_medium}}"/></div>
                        </div>
                        <div class="bwfan-input-form clearfix">
                            <div class="bwfan-col-sm-4 bwfan-pl-0"><span class="bwfan_label_input"><?php echo esc_html__( 'UTM Campaign', 'autonami-automations-connectors' ); ?></span></div>
                            <div class="bwfan-col-sm-8 bwfan-pr-0">
                                <input type="text" class="bwfan-input-wrapper" name="bwfan[{{data.action_id}}][data][sms_utm_campaign]" value="{{sms_entered_utm_campaign}}"/></div>
                        </div>
                        <div class="bwfan-input-form clearfix">
                            <div class="bwfan-col-sm-4 bwfan-pl-0"><span class="bwfan_label_input"><?php echo esc_html__( 'UTM Term', 'autonami-automations-connectors' ); ?></span></div>
                            <div class="bwfan-col-sm-8 bwfan-pr-0">
                                <input type="text" class="bwfan-input-wrapper" name="bwfan[{{data.action_id}}][data][sms_utm_term]" value="{{sms_entered_utm_term}}"/>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </script>
        <script>
            jQuery(document).on('change', '.bwfan_bouncer_sms_option', function () {
                if (jQuery(this).val() == 'text') {
                    jQuery('#bwfan-sms-textarea').show();
                    jQuery('#bwfan-sms-text').hide();
                    jQuery('#bwfan-sms-text-label').hide();
                } else if (jQuery(this).val() == 'media') {
                    jQuery('#bwfan-sms-text').show();
                    jQuery('#bwfan-sms-textarea').show();
                    jQuery('#bwfan-sms-text-label').show();
                }
            });

            jQuery(document).on('click', '#bwfan_test_message_btn', function () {
                var smsInputElem = jQuery('#bwfan_test_message');
                var el = jQuery(this);
                el.prop('disabled', true);
                smsInputElem.prop('disabled', true);
                var sms = smsInputElem.val();
                var form_data = jQuery('#bwfan-actions-form-container').bwfan_serializeAndEncode();
                form_data = bwfan_deserialize_obj(form_data);
                var group_id = jQuery('.bwfan-selected-action').attr('data-group-id');
                var data_to_send = form_data.bwfan[group_id];
                data_to_send.source = BWFAN_Auto.uiDataDetail.trigger.source;
                data_to_send.event = BWFAN_Auto.uiDataDetail.trigger.event;
                data_to_send._wpnonce = bwfanParams.ajax_nonce;
                data_to_send.automation_id = bwfan_automation_data.automation_id;
                data_to_send.data['sms_to'] = sms;
                var ajax = new bwf_ajax();
                ajax.ajax('test_message', data_to_send);

                ajax.success = function (resp) {
                    el.prop('disabled', false);
                    smsInputElem.prop('disabled', false);

                    if (resp.status == true) {
                        var $iziWrap = jQuery("#modal_automation_success");

                        if ($iziWrap.length > 0) {
                            $iziWrap.iziModal('setTitle', resp.msg);
                            $iziWrap.iziModal('open');
                        }
                    } else {
                        swal({
                            type: 'error',
                            title: window.bwfan.texts.sync_oops_title,
                            text: resp.msg
                        });
                    }
                };
            });


        </script>
		<?php
	}

	public function make_data( $integration_object, $task_meta ) {
		$this->add_action();
		$this->progress = true;
		$type           = isset( $task_meta['data']['bwfan_sms_select'] ) ? $task_meta['data']['bwfan_sms_select'] : 'text';
		$sms_body       = isset( $task_meta['data']['sms_body_textarea'] ) ? $task_meta['data']['sms_body_textarea'] : '';

		/** for media file */
		if ( 'media' == $type ) {
			$sms_file = isset( $task_meta['data']['sms_body_text'] ) ? $task_meta['data']['sms_body_text'] : '';
		}

		if ( 'image' == $type ) {
			$sms_body = isset( $task_meta['data']['sms_body_text'] ) ? $task_meta['data']['sms_body_text'] : '';
		}

		$data_to_set = array(
			'name'            => BWFAN_Common::decode_merge_tags( '{{customer_first_name}}' ),
			'promotional_sms' => ( isset( $task_meta['data']['promotional_sms'] ) ) ? 1 : 0,
			'append_utm'      => ( isset( $task_meta['data']['sms_append_utm'] ) ) ? 1 : 0,
			'number'          => ( isset( $task_meta['data']['sms_to'] ) ) ? BWFAN_Common::decode_merge_tags( $task_meta['data']['sms_to'] ) : '',
			'phone'           => ( isset( $task_meta['data']['sms_to'] ) ) ? BWFAN_Common::decode_merge_tags( $task_meta['data']['sms_to'] ) : '',
			'event'           => ( isset( $task_meta['event_data'] ) && isset( $task_meta['event_data']['event_slug'] ) ) ? $task_meta['event_data']['event_slug'] : '',
			'type'            => $type,
			'sms_body'        => BWFAN_Common::decode_merge_tags( $sms_body ),
		);
		if ( isset( $task_meta['data']['sms_utm_source'] ) && ! empty( $task_meta['data']['sms_utm_source'] ) ) {
			$data_to_set['utm_source'] = BWFAN_Common::decode_merge_tags( $task_meta['data']['sms_utm_source'] );
		}
		if ( isset( $task_meta['data']['sms_utm_medium'] ) && ! empty( $task_meta['data']['sms_utm_medium'] ) ) {
			$data_to_set['utm_medium'] = BWFAN_Common::decode_merge_tags( $task_meta['data']['sms_utm_medium'] );
		}
		if ( isset( $task_meta['data']['sms_utm_campaign'] ) && ! empty( $task_meta['data']['sms_utm_campaign'] ) ) {
			$data_to_set['utm_campaign'] = BWFAN_Common::decode_merge_tags( $task_meta['data']['sms_utm_campaign'] );
		}
		if ( isset( $task_meta['data']['sms_utm_term'] ) && ! empty( $task_meta['data']['sms_utm_term'] ) ) {
			$data_to_set['utm_term'] = BWFAN_Common::decode_merge_tags( $task_meta['data']['sms_utm_term'] );
		}

		if ( isset( $task_meta['global'] ) && isset( $task_meta['global']['order_id'] ) ) {
			$data_to_set['order_id'] = $task_meta['global']['order_id'];
		} elseif ( isset( $task_meta['global'] ) && isset( $task_meta['global']['cart_abandoned_id'] ) ) {
			$data_to_set['cart_abandoned_id'] = $task_meta['global']['cart_abandoned_id'];
		}

		/** If promotional checkbox is not checked, then empty the {{unsubscribe_link}} merge tag */
		if ( isset( $data_to_set['promotional_sms'] ) && 0 === absint( $data_to_set['promotional_sms'] ) ) {
			$data_to_set['sms_body'] = str_replace( '{{unsubscribe_link}}', '', $data_to_set['sms_body'] );
		}

		$data_to_set['sms_body'] = stripslashes( $data_to_set['sms_body'] );

		$this->remove_action();

		return $data_to_set;
	}

	public function make_v2_data( $automation_data, $step_data ) {
		$this->add_action();
		$this->progress = true;
		$type           = isset( $step_data['bwfan_sms_select'] ) ? $step_data['bwfan_sms_select'] : 'text';
		$sms_body       = isset( $step_data['sms_body_textarea'] ) ? $step_data['sms_body_textarea'] : '';
		$sms_file       = '';

		/** for media file */
		if ( 'media' == $type ) {
			$sms_file = isset( $step_data['sms_body_text'] ) ? $step_data['sms_body_text'] : '';
		}

		if ( 'image' == $type ) {
			$sms_body = isset( $step_data['sms_body_text'] ) ? $step_data['sms_body_text'] : '';
		}

		/** for image and file */
		$data_to_set = array(
			'name'            => BWFAN_Common::decode_merge_tags( '{{customer_first_name}}' ),
			'promotional_sms' => ( isset( $step_data['promotional_sms'] ) ) ? 1 : 0,
			'append_utm'      => ( isset( $step_data['bwfan_bg_add_utm_params'] ) ) ? 1 : 0,
			'number'          => ( isset( $step_data['sms_to'] ) ) ? BWFAN_Common::decode_merge_tags( $step_data['sms_to'] ) : '',
			'phone'           => ( isset( $step_data['sms_to'] ) ) ? BWFAN_Common::decode_merge_tags( $step_data['sms_to'] ) : '',
			'event'           => ( isset( $step_data['event_data'] ) && isset( $step_data['event_data']['event_slug'] ) ) ? $step_data['event_data']['event_slug'] : '',
			'type'            => $type,
			'sms_body'        => BWFAN_Common::decode_merge_tags( $sms_body ),
			'sms_file'        => $sms_file,
		);

		// Cloud API template (if selected) - parse template_name|language format
		if ( isset( $step_data['cloud_template_name'] ) && ! empty( $step_data['cloud_template_name'] ) ) {
			$template_value = $step_data['cloud_template_name'];
			// Parse template_name|language format
			if ( strpos( $template_value, '|' ) !== false ) {
				$parts = explode( '|', $template_value, 2 );
				$data_to_set['template_name'] = $parts[0];
				$data_to_set['template_language'] = isset( $parts[1] ) ? $parts[1] : 'en';
			} else {
				// Fallback for old format (just template name)
				$data_to_set['template_name'] = $template_value;
			}
			$data_to_set['type'] = 'template';
		}
		
		// Also detect Cloud API from base plugin settings
		if ( BWFCO_Bouncer::is_cloud_api_instance() && 'template' !== $data_to_set['type'] ) {
			$data_to_set['type'] = 'template';
		}


		$data_to_set['api_key']      = isset( $step_data['connector_data']['api_key'] ) ? $step_data['connector_data']['api_key'] : '';
		$data_to_set['instance_id']  = isset( $step_data['connector_data']['instance_id'] ) ? $step_data['connector_data']['instance_id'] : '';


		if ( isset( $step_data['sms_utm_source'] ) && ! empty( $step_data['sms_utm_source'] ) ) {
			$data_to_set['utm_source'] = BWFAN_Common::decode_merge_tags( $step_data['sms_utm_source'] );
		}
		if ( isset( $step_data['sms_utm_medium'] ) && ! empty( $step_data['sms_utm_medium'] ) ) {
			$data_to_set['utm_medium'] = BWFAN_Common::decode_merge_tags( $step_data['sms_utm_medium'] );
		}
		if ( isset( $step_data['sms_utm_campaign'] ) && ! empty( $step_data['sms_utm_campaign'] ) ) {
			$data_to_set['utm_campaign'] = BWFAN_Common::decode_merge_tags( $step_data['sms_utm_campaign'] );
		}
		if ( isset( $step_data['sms_utm_term'] ) && ! empty( $step_data['sms_utm_term'] ) ) {
			$data_to_set['utm_term'] = BWFAN_Common::decode_merge_tags( $step_data['sms_utm_term'] );
		}

		if ( isset( $automation_data['global'] ) && isset( $automation_data['global']['order_id'] ) ) {
			$data_to_set['order_id'] = $automation_data['global']['order_id'];
		} elseif ( isset( $automation_data['global'] ) && isset( $automation_data['global']['cart_abandoned_id'] ) ) {
			$data_to_set['cart_abandoned_id'] = $automation_data['global']['cart_abandoned_id'];
		}

		/** If promotional checkbox is not checked, then empty the {{unsubscribe_link}} merge tag */
		if ( isset( $data_to_set['promotional_sms'] ) && 0 === absint( $data_to_set['promotional_sms'] ) ) {
			$data_to_set['sms_body'] = str_replace( '{{unsubscribe_link}}', '', $data_to_set['sms_body'] );
		}

		$data_to_set['sms_body'] = stripslashes( $data_to_set['sms_body'] );

		/** Append UTM and Create Conversation (Engagement Tracking) */
		$data_to_set['sms_body'] = BWFAN_Connectors_Common::modify_sms_body( $data_to_set['sms_body'], $data_to_set );

		$this->remove_action();

		return $data_to_set;
	}

	private function add_action() {
		add_filter( 'bwfan_order_billing_address_separator', [ $this, 'change_br_to_slash_n' ] );
		add_filter( 'bwfan_order_shipping_address_separator', [ $this, 'change_br_to_slash_n' ] );
	}

	private function remove_action() {
		remove_filter( 'bwfan_order_billing_address_params', [ $this, 'change_br_to_slash_n' ] );
		remove_filter( 'bwfan_order_shipping_address_separator', [ $this, 'change_br_to_slash_n' ] );
	}

	public function shorten_link( $body ) {
		if ( true === $this->progress ) {
			$body = preg_replace_callback( '/((\w+:\/\/\S+)|(\w+[\.:]\w+\S+))[^\s,\.]/i', array( $this, 'shorten_urls' ), $body );
		}

		return preg_replace_callback( '/((\w+:\/\/\S+)|(\w+[\.:]\w+\S+))[^\s,\.]/i', array( $this, 'unsubscribe_url_with_mode' ), $body );
	}

	public function execute_action( $action_data ) {
		global $wpdb;
		$this->set_data( $action_data['processed_data'] );
		$this->data['task_id'] = $action_data['task_id'];

		// Ensure Cloud API detection for old saved automations
		if ( BWFCO_Bouncer::is_cloud_api_instance() && ( ! isset( $this->data['type'] ) || 'template' !== $this->data['type'] ) ) {
			$this->data['type'] = 'template';
			// Try to get template name from saved data or config
			if ( empty( $this->data['template_name'] ) ) {
				$cloud_config = BWFCO_Bouncer::get_cloud_template_config();
				$template_variables = isset( $cloud_config['template_variables'] ) ? $cloud_config['template_variables'] : array();
				if ( ! empty( $template_variables ) ) {
					$this->data['template_name'] = array_key_first( $template_variables );
					$this->data['template_language'] = BWFCO_Bouncer::get_template_language( $this->data['template_name'] );
				}
			}
		}

		/** Attaching track id */
		$sql_query         = 'Select meta_value FROM {table_name} WHERE bwfan_task_id = %d AND meta_key = %s';
		$sql_query         = $wpdb->prepare( $sql_query, $this->data['task_id'], 't_track_id' ); //phpcs:ignore WordPress.DB.PreparedSQL
		$gids              = BWFAN_Model_Taskmeta::get_results( $sql_query );
		$this->data['gid'] = '';
		if ( ! empty( $gids ) && is_array( $gids ) ) {
			foreach ( $gids as $gid ) {
				$this->data['gid'] = $gid['meta_value'];
			}
		}

		/** Validating promotional sms */
		$recipient = ! empty( $this->data['phone'] ) ? $this->data['phone'] : ( $this->data['number'] ?? '' );
		if ( 1 === absint( $this->data['promotional_sms'] ) && ( false === apply_filters( 'bwfan_force_promotional_sms', false, $this->data ) ) ) {
			$where             = array(
				'recipient' => $recipient,
				'mode'      => 2,
			);
			$check_unsubscribe = BWFAN_Model_Message_Unsubscribe::get_message_unsubscribe_row( $where );

			if ( ! empty( $check_unsubscribe ) ) {
				$this->progress = false;

				return array(
					'status'  => 4,
					'message' => __( 'User is already unsubscribed', 'autonami-automations-connectors' ),
				);
			}
		}

		/** Validating connector */
		WFCO_Bouncer_Debug::log( 'BWFAN_Bouncer_Send_SMS::process() - Automation triggered', $this->data );

		$load_connector = WFCO_Load_Connectors::get_instance();
		$call_class     = $load_connector->get_call( 'wfco_bouncer_send_sms' );
		if ( is_null( $call_class ) ) {
			$this->progress = false;
			WFCO_Bouncer_Debug::log( 'ERROR: Send SMS call class not found' );

			return array(
				'status'  => 4,
				'message' => __( 'Send SMS call not found', 'autonami-automations-connectors' ),
			);
		}

		$integration             = BWFAN_Bouncer_Integration::get_instance();
		$this->data['api_key']   = $integration->get_settings( 'api_key' );
		$this->data['instance_id']  = $integration->get_settings( 'instance_id' );

		// Fall back to base wp-bouncer-saas plugin credentials if needed
		if ( empty( $this->data['api_key'] ) || empty( $this->data['instance_id'] ) ) {
			$base_credentials = BWFCO_Bouncer::get_base_plugin_credentials();
			if ( empty( $this->data['api_key'] ) && ! empty( $base_credentials['api_key'] ) ) {
				$this->data['api_key'] = $base_credentials['api_key'];
			}
			if ( empty( $this->data['instance_id'] ) && ! empty( $base_credentials['instance_id'] ) ) {
				$this->data['instance_id'] = $base_credentials['instance_id'];
			}
		}

		WFCO_Bouncer_Debug::log( 'Integration settings retrieved', array(
			'has_api_key' => ! empty( $this->data['api_key'] ),
			'has_instance_id' => ! empty( $this->data['instance_id'] ),
			'connector_slug' => $integration->get_connector_slug()
		) );

		/** WC order case */
		$order_details = null;
		if ( ! empty( $this->data['order_id'] ) ) {
			$order_details = wc_get_order( $this->data['order_id'] );

			/** Appending country code */
			if ( $order_details ) {
				$country = $order_details->get_billing_country();
				if ( ! empty( $country ) ) {
					$this->data['country_code'] = $country;
				}
			}
		} elseif ( ! empty( $this->data['cart_abandoned_id'] ) ) {
			/** Cart abandonment case */
			$cart_details = BWFAN_Merge_Tag_Loader::get_data( 'cart_details' );

			/** Appending country code in case available */
			$checkout_data = json_decode( $cart_details['checkout_data'], true );
			if ( is_array( $checkout_data ) && isset( $checkout_data['fields'] ) && isset( $checkout_data['fields']['billing_country'] ) && ! empty( $checkout_data['fields']['billing_country'] ) ) {
				$this->data['country_code'] = $checkout_data['fields']['billing_country'];
			}
		}

		/** Check if Cloud API instance - use template sending */
		$is_cloud_api = BWFCO_Bouncer::is_cloud_api_instance();
		
		// Also check if type is 'template' as a backup detection
		$is_template_type = isset( $this->data['type'] ) && 'template' === $this->data['type'];
		
		WFCO_Bouncer_Debug::log( 'Cloud API detection', array(
			'is_cloud_api_instance' => $is_cloud_api,
			'is_template_type' => $is_template_type,
			'data_type' => isset( $this->data['type'] ) ? $this->data['type'] : 'not_set',
			'template_name' => isset( $this->data['template_name'] ) ? $this->data['template_name'] : 'not_set'
		) );
		
		if ( $is_cloud_api || $is_template_type ) {
			$response = $this->send_cloud_template( $order_details );
		} else {
			/** Create Conversation */
			$automation_id = ! empty( $this->data['automation_id'] ) ? $this->data['automation_id'] : 0;
			$conversation  = $this->create_engagement( $this->data['number'], $automation_id, $this->data['sms_body'] );
			if ( $conversation instanceof BWFAN_Engagement_Tracking ) {
				$this->data['sms_body'] = $this->add_tracking_code( $conversation, $this->data['sms_body'] );
			}

			$call_class->set_data( $this->data );
			$response = $call_class->process();
		}
		if ( class_exists( 'WFCO_Bouncer_Base_Log', false ) ) {
			WFCO_Bouncer_Base_Log::record( $this->data, $response );
		}

		if ( is_array( $response ) && 200 === $response['response'] && ( isset( $response['body']['success'] ) && $response['body']['success'] === true ) ) {
			$this->progress = false;

			return array(
				'status'  => 3,
				'message' => __( 'Message sent successfully.', 'autonami-automations-connectors' ),
			);
		}

		$message = __( 'Message could not be sent. ', 'autonami-automations-connectors' );
		$status  = 4;

		if ( isset( $response['body']['error'] ) && isset( $response['body']['error']['message'] ) ) {
			$message = $response['body']['error']['message'];
		} elseif ( isset( $response['body']['message'] ) ) {
			$message = $response['body']['message'];
		} elseif ( isset( $response['bwfan_response'] ) && ! empty( $response['bwfan_response'] ) ) {
			$message = $response['bwfan_response'];
		} elseif ( is_array( $response['body'] ) && isset( $response['body'][0] ) && is_string( $response['body'][0] ) ) {
			$message = $message . $response['body'][0];
		}
		$this->progress = false;

		if ( $conversation instanceof BWFAN_Engagement_Tracking ) {
			BWFCRM_Core()->conversation->fail_the_conversation( $conversation->get_id(), $message );
		}

		return array(
			'status'  => $status,
			'message' => $message,
		);
	}

	public function add_unsubscribe_query_args( $link ) {
		if ( empty( $this->data ) ) {
			return $link;
		}
		if ( isset( $this->data['number'] ) ) {
			$link = add_query_arg( array(
				'subscriber_recipient' => $this->data['number'],
			), $link );
		}
		if ( isset( $this->data['name'] ) ) {
			$link = add_query_arg( array(
				'subscriber_name' => $this->data['name'],
			), $link );
		}

		return $link;
	}

	public function skip_name_email() {
		return true;
	}

	public function before_executing_task() {
		add_filter( 'bwfan_change_tasks_retry_limit', [ $this, 'modify_retry_limit' ], 99 );
		add_filter( 'bwfan_unsubscribe_link', array( $this, 'add_unsubscribe_query_args' ) );
		add_filter( 'bwfan_skip_name_email_from_unsubscribe_link', array( $this, 'skip_name_email' ) );
	}

	public function after_executing_task() {
		remove_filter( 'bwfan_change_tasks_retry_limit', [ $this, 'modify_retry_limit' ], 99 );
		remove_filter( 'bwfan_unsubscribe_link', array( $this, 'add_unsubscribe_query_args' ) );
		remove_filter( 'bwfan_skip_name_email_from_unsubscribe_link', array( $this, 'skip_name_email' ) );
	}

	public function modify_retry_limit( $retry_data ) {
		$retry_data[] = DAY_IN_SECONDS;

		return $retry_data;
	}

	public function change_br_to_slash_n() {
		return "\n";
	}

	protected function shorten_urls( $matches ) {
		$string = $matches[0];

		/**
		 * method exist check is required here as it is outside the connector plugin
		 * same is not required for the connector inside the connector plugin
		 */
		if ( method_exists( 'BWFAN_Connectors_Common', 'get_shorten_url' ) ) {
			return BWFAN_Connectors_Common::get_shorten_url( $string );
		}

		return do_shortcode( '[bwfan_bitly_shorten]' . $string . '[/bwfan_bitly_shorten]' );
	}

	/**
	 * Send Cloud API template message using base plugin configuration.
	 *
	 * @param WC_Order|null $order The order object for resolving variables.
	 * @return array API response.
	 */
	protected function send_cloud_template( $order = null ) {
		WFCO_Bouncer_Debug::log( 'Cloud API detected - attempting template send', array(
			'has_order' => ! is_null( $order ),
			'phone' => $this->data['number']
		) );

		// Get template configuration from base plugin
		$cloud_config = BWFCO_Bouncer::get_cloud_template_config();
		$template_variables = isset( $cloud_config['template_variables'] ) ? $cloud_config['template_variables'] : array();
		
		// Find the first configured template (or use template_name if specified in action data)
		$template_name = '';
		$template_language = 'en';
		
		if ( ! empty( $this->data['template_name'] ) ) {
			$template_name = $this->data['template_name'];
		} elseif ( ! empty( $template_variables ) ) {
			// Use the first configured template
			$template_name = array_key_first( $template_variables );
		}

		if ( empty( $template_name ) ) {
			WFCO_Bouncer_Debug::log( 'ERROR: No template configured for Cloud API' );
			return array(
				'response' => 400,
				'body' => array(
					'success' => false,
					'error' => array(
						'message' => __( 'No template configured. Please configure templates in Bouncer WhatsApp settings.', 'autonami-automations-connectors' )
					)
				)
			);
		}

		// Get template language if available
		$template_languages = isset( $cloud_config['template_languages'] ) ? $cloud_config['template_languages'] : array();
		if ( isset( $template_languages[ $template_name ] ) ) {
			$template_language = $template_languages[ $template_name ];
		}
		$this->data['template_name']     = $template_name;
		$this->data['template_language'] = $template_language;

		// Build variables from mappings
		$variables = array();
		$var_mappings = isset( $template_variables[ $template_name ] ) ? $template_variables[ $template_name ] : array();
		
		foreach ( $var_mappings as $index => $placeholder ) {
			if ( ! empty( $placeholder ) && $order ) {
				$resolved = $this->resolve_cloud_placeholder( $placeholder, $order );
				$variables[ (string) $index ] = $resolved;
			}
		}

		WFCO_Bouncer_Debug::log( 'Sending Cloud API template', array(
			'template_name' => $template_name,
			'language' => $template_language,
			'variables' => $variables
		) );

		// Load the template call class
		$load_connector = WFCO_Load_Connectors::get_instance();
		$call_class = $load_connector->get_call( 'wfco_bouncer_send_template' );
		
		if ( is_null( $call_class ) ) {
			WFCO_Bouncer_Debug::log( 'ERROR: Send Template call class not found' );
			return array(
				'response' => 500,
				'body' => array(
					'success' => false,
					'error' => array(
						'message' => __( 'Template call class not found', 'autonami-automations-connectors' )
					)
				)
			);
		}

		$template_data = array(
			'api_key'       => $this->data['api_key'],
			'instance_id'   => $this->data['instance_id'],
			'number'        => $this->data['number'],
			'template_name' => $template_name,
			'language'      => $template_language,
			'variables'     => $variables,
			'type'          => 'template', // Ensure type is set for call class
		);

		if ( isset( $this->data['country_code'] ) ) {
			$template_data['country_code'] = $this->data['country_code'];
		}

		$call_class->set_data( $template_data );
		return $call_class->process();
	}

	/**
	 * Resolve Cloud API placeholder to actual value from order.
	 *
	 * @param string $placeholder The placeholder like {first_name}, {order_number}.
	 * @param WC_Order $order The order object.
	 * @return string Resolved value.
	 */
	protected function resolve_cloud_placeholder( $placeholder, $order ) {
		// Remove curly braces
		$key = trim( $placeholder, '{}' );

		switch ( $key ) {
			case 'first_name':
				return $order->get_billing_first_name();
			case 'last_name':
				return $order->get_billing_last_name();
			case 'name':
				$name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
				return $name ?: $order->get_billing_first_name();
			case 'email':
				return $order->get_billing_email();
			case 'phone':
				return $order->get_billing_phone();
			case 'order_number':
			case 'order_id':
				return (string) $order->get_order_number();
			case 'order_total':
			case 'amount':
				// Strip HTML and decode entities for plain text
				$currency = $order->get_currency();
				$total = $order->get_total();
				return wp_strip_all_tags( html_entity_decode( wc_price( $total, array( 'currency' => $currency ) ) ) );
			case 'order_status':
			case 'status':
				return wc_get_order_status_name( $order->get_status() );
			case 'order_date':
				$date = $order->get_date_created();
				return $date ? $date->date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) : '';
			case 'payment_method':
				return $order->get_payment_method_title();
			case 'billing_address':
				return $this->format_address_plain( $order, 'billing' );
			case 'shipping_address':
				return $this->format_address_plain( $order, 'shipping' );
			case 'order_items':
				// Plain text format: "1x Product\n2x Other Product"
				$lines = array();
				foreach ( $order->get_items() as $item ) {
					$name = $item->get_name();
					$qty = $item->get_quantity();
					$lines[] = $qty > 1 ? "{$qty}x {$name}" : $name;
				}
				return implode( "\n", $lines );
			case 'currency':
				return $order->get_currency();
			case 'shipping_method':
				$methods = array();
				foreach ( $order->get_items( 'shipping' ) as $item ) {
					$methods[] = $item->get_name();
				}
				return implode( ', ', array_unique( $methods ) ) ?: __( 'Default shipping', 'autonami-automations-connectors' );
			default:
				// Check for meta:KEY pattern
				if ( 0 === strpos( $key, 'meta:' ) ) {
					$meta_key = substr( $key, 5 );
					$value = $order->get_meta( $meta_key, true );
					return is_array( $value ) || is_object( $value ) ? wp_json_encode( $value ) : sanitize_text_field( (string) $value );
				}
				return $placeholder;
		}
	}

	/**
	 * Format address as plain text (no HTML).
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

	protected function create_engagement( $phone, $automation_id, $body ) {
		if ( empty( $body ) || empty( $automation_id ) ) {
			return false;
		}

		$contact = BWFCRM_Common::get_contact_by_email_or_phone( $phone );
		if ( ! $contact instanceof BWFCRM_Contact || ! $contact->is_contact_exists() ) {
			return false;
		}

		/** 1 for Text Only */
		$template_id = BWFCRM_Core()->conversation->get_or_create_template( 1, '', $body );

		$conversation = new BWFAN_Engagement_Tracking();
		$conversation->set_oid( absint( $automation_id ) );
		$conversation->set_mode( BWFAN_Email_Conversations::$MODE_WHATSAPP );
		$conversation->set_contact( $contact );
		$conversation->set_send_to( $contact->contact->get_contact_no() );
		$conversation->enable_tracking();
		$conversation->set_type( BWFAN_Email_Conversations::$TYPE_AUTOMATION );
		$conversation->set_template_id( $template_id );
		$conversation->set_status( BWFAN_Email_Conversations::$STATUS_SEND );
		$conversation->add_merge_tags_from_string( $body, array() );

		if ( ! $conversation->save() ) {
			return false;
		}

		return $conversation;
	}

	/**
	 * @param BWFAN_Engagement_Tracking $conversation
	 * @param string $body
	 */
	protected function add_tracking_code( $conversation, $body ) {
		$utm  = BWFAN_UTM_Tracking::get_instance();
		$body = $utm->maybe_add_utm_parameters( $body, $this->data );
		$body = BWFAN_Core()->conversations->add_tracking_code( $body, $this->data, $conversation->get_hash(), $conversation->get_oid(), true, BWFAN_Email_Conversations::$MODE_SMS );

		return $body;
	}

	public function handle_response_v2( $response ) {
		do_action( 'bwfan_sendsms_action_response', $response, $this->data );
		if ( class_exists( 'WFCO_Bouncer_Base_Log', false ) ) {
			WFCO_Bouncer_Base_Log::record( $this->data, $response );
		}

		if ( is_array( $response ) && 200 === $response['response'] && ( isset( $response['body']['success'] ) && $response['body']['success'] === true ) ) {
			$this->progress = false;

			return $this->success_message( __( 'Message sent successfully.', 'autonami-automations-connectors' ) );
		}

		$message = __( 'Message could not be sent. ', 'autonami-automations-connectors' );

		if ( isset( $response['body']['error'] ) && isset( $response['body']['error']['message'] ) ) {
			$message = $response['body']['error']['message'];
		} elseif ( isset( $response['body']['message'] ) ) {
			$message = $response['body']['message'];
		} elseif ( isset( $response['bwfan_response'] ) && ! empty( $response['bwfan_response'] ) ) {
			$message = $response['bwfan_response'];
		} elseif ( is_array( $response['body'] ) && isset( $response['body'][0] ) && is_string( $response['body'][0] ) ) {
			$message = $message . $response['body'][0];
		}
		$this->progress = false;

		return $this->error_response( $message );
	}

	/**
	 * adding mode in unsubscribe link
	 *
	 * @param $matches
	 *
	 * @return string
	 */
	protected function unsubscribe_url_with_mode( $matches ) {
		$string = $matches[0];

		/** if its a unsubscriber link then pass the mode in url */
		if ( strpos( $string, 'unsubscribe' ) !== false ) {
			$string = add_query_arg( array(
				'mode' => 2,
			), $string );
		}

		return $string;
	}

	/**
	 * v2 Method: Get field Schema
	 *
	 * @return array[]
	 */
	public function get_fields_schema() {
		$fields = [
			[
				'id'          => 'sms_to',
				'label'       => __( "To", 'wp-marketing-automations' ),
				'type'        => 'text',
				'placeholder' => "",
				"class"       => 'bwfan-input-wrapper',
				'tip'         => __( '', 'autonami-automations-connectors' ),
				"description" => '',
				"required"    => true,
			],
		];

			$fields[] = [
				'id'          => 'bwfan_sms_select',
				'label'       => __( "Type", 'wp-marketing-automations' ),
				'type'        => 'radio',
				'options'     => [
					[
						'label' => __( "Text", 'wp-marketing-automations' ),
						'value' => 'text',
					],
					[
						'label'   => __( "Media", 'wp-marketing-automations' ),
						'value'   => 'media',
						'tooltip' => __( "Direct link to image / file", 'wp-marketing-automations' )
					],
				],
				"description" => '',
				"required"    => true,
			];

			$fields[] = [
				'id'          => 'sms_body_textarea',
				'label'       => __( "Text Message", 'wp-marketing-automations' ),
				'type'        => 'textarea',
				'placeholder' => "Message Body",
				"class"       => 'bwfan-input-wrapper',
				'tip'         => __( '', 'autonami-automations-connectors' ),
				"description" => '',
				"required"    => true,
			];

			$fields[] = [
				'id'          => 'sms_body_text',
				'label'       => __( "Image / File URL", 'wp-marketing-automations' ),
				'type'        => 'text',
				'placeholder' => "Direct link to image / file",
				"class"       => 'bwfan-input-wrapper',
				'tip'         => __( '', 'autonami-automations-connectors' ),
				"description" => '',
				"required"    => true,
				'toggler'     => [
					'fields'   => [
						[
							'id'    => 'bwfan_sms_select',
							'value' => 'media',
						],
					],
					'relation' => 'OR',
				],
			];
			$fields[] = [
				'id'          => 'test_sms',
				'label'       => __( "Send Test Message", 'wp-marketing-automations' ),
				'type'        => 'text',
				'placeholder' => "",
				"class"       => 'bwfan-input-wrapper',
				'tip'         => '',
				"description" => __( 'Enter Mobile no with country code', 'autonami-automations-connectors' ),
				"required"    => false,
			];
			$fields[] = [
				'id'          => 'send_test_sms',
				'type'        => 'send_data',
				'label'       => __( '', 'wp-marketing-automations' ),
				'send_action' => 'bwf_test_message',
				'send_field'  => [
					'sms_to'            => 'test_sms',
					'sms_body_textarea' => 'sms_body_textarea',
					'type'				=> 'bwfan_sms_select',
					'sms_body_text' 	=> 'sms_body_text',
				],
				"hint"        => __( "", 'wp-marketing-automations' )
			];

		$fields[] = [
			'id'            => 'promotional_sms',
			'checkboxlabel' => __( "Mark as Promotional", 'wp-marketing-automations' ),
			'type'          => 'checkbox',
			"class"         => '',
			'hint'          => __( 'SMS marked as promotional will not be send to the unsubscribers.', 'wp-marketing-automations' ),
			'description'   => __( 'SMS marked as promotional will not be send to the unsubscribers.', 'autonami-automations-connectors' ),
			"required"      => false,
		];

			$fields[] = [
				'id'            => 'bwfan_bg_add_utm_params',
				'checkboxlabel' => __( " Add UTM parameters to the links", 'wp-marketing-automations' ),
				'type'          => 'checkbox',
				"class"         => '',
				'hint'          => 'Add UTM parameters in all the links present in the sms.',
				'description'   => __( 'Add UTM parameters in all the links present in the sms.', 'autonami-automations-connectors' ),
				"required"      => false,
			];
			$fields[] = [
				'id'          => 'sms_utm_source',
				'label'       => __( "UTM Source", 'wp-marketing-automations' ),
				'type'        => 'text',
				'placeholder' => "",
				"class"       => 'bwfan-input-wrapper',
				'tip'         => '',
				"description" => __( '', 'autonami-automations-connectors' ),
				"required"    => false,
				'toggler'     => array(
					'fields'   => array(
						array(
							'id'    => 'bwfan_bg_add_utm_params',
							'value' => true,
						),
					),
					'relation' => 'AND',
				),
			];
			$fields[] = [
				'id'          => 'sms_utm_medium',
				'label'       => __( "UTM Medium", 'wp-marketing-automations' ),
				'type'        => 'text',
				'placeholder' => "",
				"class"       => 'bwfan-input-wrapper',
				'tip'         => '',
				"description" => __( '', 'autonami-automations-connectors' ),
				"required"    => false,
				'toggler'     => array(
					'fields'   => array(
						array(
							'id'    => 'bwfan_bg_add_utm_params',
							'value' => true,
						),
					),
					'relation' => 'AND',
				),
			];
			$fields[] = [
				'id'          => 'sms_utm_campaign',
				'label'       => __( "UTM Campaign", 'wp-marketing-automations' ),
				'type'        => 'text',
				'placeholder' => "",
				"class"       => 'bwfan-input-wrapper',
				'tip'         => '',
				"description" => __( '', 'autonami-automations-connectors' ),
				"required"    => false,
				'toggler'     => array(
					'fields'   => array(
						array(
							'id'    => 'bwfan_bg_add_utm_params',
							'value' => true,
						),
					),
					'relation' => 'AND',
				),
			];
			$fields[] = [
				'id'          => 'utm_utm_term',
				'label'       => __( "UTM Term", 'wp-marketing-automations' ),
				'type'        => 'text',
				'placeholder' => "",
				"class"       => 'bwfan-input-wrapper',
				'tip'         => '',
				"description" => __( '', 'autonami-automations-connectors' ),
				"required"    => false,
				'toggler'     => array(
					'fields'   => array(
						array(
							'id'    => 'bwfan_bg_add_utm_params',
							'value' => true,
						),
					),
					'relation' => 'AND',
				),
			];

		return $fields;
	}
}

return 'BWFAN_Bouncer_Send_SMS';
