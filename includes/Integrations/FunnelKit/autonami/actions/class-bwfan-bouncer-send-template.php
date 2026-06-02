<?php

class BWFAN_Bouncer_Send_Template extends BWFAN_Bouncer_Send_SMS {
	private static $instance = null;

	public function __construct() {
		$this->action_name = __( 'Send Template', 'autonami-automations-connectors' );
		$this->action_desc = __( 'This action sends an approved WhatsApp Cloud API template via Bouncer', 'autonami-automations-connectors' );
		$this->support_v2  = true;
	}

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function get_action_data_for_api() {
		if ( ! class_exists( 'BWFCO_Bouncer', false ) || ! BWFCO_Bouncer::is_cloud_api_instance() ) {
			return array();
		}

		return parent::get_action_data_for_api();
	}

	public function get_view() {
		$unique_slug        = $this->get_slug();
		$template_options   = $this->get_template_options();
		$variable_summaries = $this->get_template_variable_summaries( $template_options );
		?>
        <script type="text/html" id="tmpl-action-<?php echo esc_attr__( $unique_slug ); ?>">
            <#
            phone_merge_tag = '{{customer_phone}}';
            sms_to = (_.has(data.actionSavedData, 'data') && _.has(data.actionSavedData.data, 'sms_to')) ? _.isEmpty(data.actionSavedData.data.sms_to)?phone_merge_tag:data.actionSavedData.data.sms_to: phone_merge_tag;
            selected_template = (_.has(data.actionSavedData, 'data') && _.has(data.actionSavedData.data, 'cloud_template_name')) ? data.actionSavedData.data.cloud_template_name : '';
            #>
            <div data-element-type="bwfan-editor" class="bwfan-<?php echo esc_attr__( $unique_slug ); ?>">
                <label for="" class="bwfan-label-title"><?php echo esc_html__( 'To', 'autonami-automations-connectors' ); ?></label>
                <div class="bwfan-col-sm-12 bwfan-pl-0 bwfan-pr-0 bwfan-mb-15">
                    <input required type="text" class="bwfan-input-wrapper bwfan-field-<?php echo esc_attr__( $unique_slug ); ?>" name="bwfan[{{data.action_id}}][data][sms_to]" placeholder="E.g. 919999999999" value="{{sms_to}}"/>
                </div>

		<?php if ( empty( $template_options ) ) : ?>
                    <div class="bwfan-col-sm-12 bwfan-pl-0 bwfan-pr-0 bwfan-mb-15">
						<p class="bwfan_field_desc"><?php esc_html_e( 'Cloud API mode is active, but no approved WhatsApp templates were found. Refresh templates in Bouncer WhatsApp settings before using this action.', 'autonami-automations-connectors' ); ?></p>
                    </div>
				<?php else : ?>
                    <label for="" class="bwfan-label-title"><?php esc_html_e( 'WhatsApp Template', 'autonami-automations-connectors' ); ?></label>
                    <div class="bwfan-col-sm-12 bwfan-pl-0 bwfan-pr-0 bwfan-mb-15">
                        <select required class="bwfan-input-wrapper" name="bwfan[{{data.action_id}}][data][cloud_template_name]">
							<?php foreach ( $template_options as $template_option ) : ?>
                                <option value="<?php echo esc_attr( $template_option['value'] ); ?>" {{ selected_template == '<?php echo esc_js( $template_option['value'] ); ?>' ? 'selected' : '' }}><?php echo esc_html( $template_option['label'] ); ?></option>
							<?php endforeach; ?>
                        </select>
                    </div>
                    <div class="bwfan-col-sm-12 bwfan-pl-0 bwfan-pr-0 bwfan-mb-15 bwfan-bouncer-template-variable-summary-wrap">
						<?php foreach ( $variable_summaries as $template_value => $summary ) : ?>
                            <div class="bwfan-bouncer-template-variable-summary" data-template="<?php echo esc_attr( $template_value ); ?>" style="display:none;">
								<?php echo wp_kses_post( $summary ); ?>
                            </div>
						<?php endforeach; ?>
                    </div>
                    <script>
                        jQuery(function($) {
                            var wrap = $('.bwfan-<?php echo esc_js( $unique_slug ); ?>');
                            var select = wrap.find('select[name="bwfan[{{data.action_id}}][data][cloud_template_name]"]');
                            var summaries = wrap.find('.bwfan-bouncer-template-variable-summary');
                            var update = function() {
                                var selected = select.val();
                                summaries.hide().filter('[data-template="' + selected + '"]').show();
                            };
                            select.on('change', update);
                            update();
                        });
                    </script>
                    <div class="bwfan-col-sm-12 bwfan-pl-0 bwfan-pr-0 bwfan-mb-15">
                        <p class="bwfan_field_desc"><?php esc_html_e( 'Cloud API mode sends approved Meta templates. Edit template variables in Bouncer WhatsApp settings.', 'autonami-automations-connectors' ); ?></p>
                    </div>
				<?php endif; ?>

                <input type="hidden" name="bwfan[{{data.action_id}}][data][bwfan_sms_select]" value="template" />
                <input type="hidden" name="bwfan[{{data.action_id}}][data][sms_body_textarea]" value="" />
            </div>
        </script>
		<?php
	}

	public function get_fields_schema() {
		$fields = array(
			array(
				'id'          => 'sms_to',
				'label'       => __( 'To', 'wp-marketing-automations' ),
				'type'        => 'text',
				'placeholder' => '',
				'class'       => 'bwfan-input-wrapper',
				'tip'         => '',
				'description' => '',
				'required'    => true,
			),
		);

		$template_options = $this->get_template_options();
		if ( empty( $template_options ) ) {
			$fields[] = array(
				'id'      => 'cloud_api_templates_missing_notice',
				'type'    => 'notice',
				'class'   => '',
				'status'  => 'error',
				'message' => __( 'Cloud API mode is active, but no approved templates were found. Refresh templates in Bouncer WhatsApp settings before using this action.', 'autonami-automations-connectors' ),
				'dismiss' => false,
			);
		} else {
			$fields[] = array(
				'id'          => 'cloud_template_name',
				'label'       => __( 'WhatsApp Template', 'wp-marketing-automations' ),
				'type'        => 'select',
				'options'     => $template_options,
				'class'       => 'bwfan-input-wrapper',
				'tip'         => __( 'Select the Cloud API template to send. Templates must be configured in Bouncer WhatsApp settings.', 'autonami-automations-connectors' ),
				'description' => __( 'Cloud API requires pre-approved templates from Meta.', 'autonami-automations-connectors' ),
				'required'    => true,
			);
			foreach ( $this->get_template_variable_summaries( $template_options ) as $template_value => $summary ) {
				$fields[] = array(
					'id'      => 'cloud_template_variables_' . md5( $template_value ),
					'type'    => 'notice',
					'class'   => '',
					'status'  => 'info',
					'message' => $summary,
					'isHtml'  => true,
					'toggler' => array(
						'fields'   => array(
							array(
								'id'    => 'cloud_template_name',
								'value' => $template_value,
							),
						),
						'relation' => 'AND',
					),
				);
			}
			$fields[] = array(
				'id'      => 'cloud_api_notice',
				'type'    => 'notice',
				'class'   => '',
				'status'  => 'info',
				'message' => __( 'Message will be sent using the selected approved template. Variable mappings are managed in Bouncer WhatsApp settings.', 'autonami-automations-connectors' ),
			);
		}

		$fields[] = array(
			'id'    => 'bwfan_sms_select',
			'type'  => 'hidden',
			'value' => 'template',
		);
		$fields[] = array(
			'id'    => 'sms_body_textarea',
			'type'  => 'hidden',
			'value' => '',
		);

		return $fields;
	}

	private function get_template_options() {
		$cloud_config     = BWFCO_Bouncer::get_cloud_template_config();
		$template_options = array();

		foreach ( BWFCO_Bouncer::fetch_cloud_templates() as $template ) {
			if ( empty( $template['name'] ) ) {
				continue;
			}

			if ( isset( $template['status'] ) && 'APPROVED' !== strtoupper( $template['status'] ) ) {
				continue;
			}

			$language = ! empty( $template['language'] ) ? $template['language'] : BWFCO_Bouncer::get_template_language( $template['name'] );
			$template_options[ $template['name'] . '|' . $language ] = array(
				'label' => $template['name'] . ' (' . $language . ')',
				'value' => $template['name'] . '|' . $language,
			);
		}

		foreach ( $this->get_saved_template_names( $cloud_config ) as $template_name ) {
			$language = BWFCO_Bouncer::get_template_language( $template_name );
			$template_options[ $template_name . '|' . $language ] = array(
				'label' => $template_name . ' (' . $language . ')',
				'value' => $template_name . '|' . $language,
			);
		}

		return array_values( $template_options );
	}

	private function get_saved_template_names( $cloud_config ) {
		$template_names = array();

		foreach ( array( 'template_variables', 'template_languages', 'status_template_map' ) as $config_key ) {
			if ( empty( $cloud_config[ $config_key ] ) || ! is_array( $cloud_config[ $config_key ] ) ) {
				continue;
			}

			$names = 'status_template_map' === $config_key ? array_values( $cloud_config[ $config_key ] ) : array_keys( $cloud_config[ $config_key ] );
			foreach ( $names as $template_name ) {
				if ( is_scalar( $template_name ) && '' !== trim( (string) $template_name ) ) {
					$template_names[] = (string) $template_name;
				}
			}
		}

		return array_values( array_unique( $template_names ) );
	}

	private function get_template_variable_summaries( $template_options ) {
		$cloud_config       = BWFCO_Bouncer::get_cloud_template_config();
		$template_variables = isset( $cloud_config['template_variables'] ) && is_array( $cloud_config['template_variables'] ) ? $cloud_config['template_variables'] : array();
		$summaries          = array();

		foreach ( $template_options as $template_option ) {
			$template_value = $template_option['value'];
			$parts          = explode( '|', $template_value, 2 );
			$template_name  = $parts[0];
			$variables      = isset( $template_variables[ $template_name ] ) ? $template_variables[ $template_name ] : array();
			$html           = '<strong>' . esc_html( sprintf( __( 'Template variables for %s', 'autonami-automations-connectors' ), $template_name ) ) . '</strong>';

			if ( empty( $variables ) || ! is_array( $variables ) ) {
				$summaries[ $template_value ] = $html . '<br />' . esc_html__( 'No variable mappings are configured for this template in Bouncer WhatsApp settings.', 'autonami-automations-connectors' );
				continue;
			}

			ksort( $variables, SORT_NUMERIC );
			$html .= '<ul style="margin:8px 0 0 18px; list-style:disc;">';
			foreach ( $variables as $index => $placeholder ) {
				$html .= '<li>' . esc_html( '{{' . absint( $index ) . '}}' ) . ' &rarr; ' . esc_html( $placeholder ) . '</li>';
			}
			$html .= '</ul>';

			$summaries[ $template_value ] = $html;
		}

		return $summaries;
	}
}

return 'BWFAN_Bouncer_Send_Template';
