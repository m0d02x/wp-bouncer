<?php
$saved_data    = WFCO_Common::$connectors_saved_data;
$old_data      = ( isset( $saved_data[ $this->get_slug() ] ) && is_array( $saved_data[ $this->get_slug() ] ) && count( $saved_data[ $this->get_slug() ] ) > 0 ) ? $saved_data[ $this->get_slug() ] : array();
$base_settings = get_option( 'wc_bouncer_whatsapp_settings', array() );
$has_api_key   = ! empty( $base_settings['api_key'] );
$has_instance  = ! empty( $base_settings['instance_id'] );
?>

<div class="wfco-form-group featured field-input">
    <label><?php echo esc_html__( 'Bouncer WhatsApp Settings', 'autonami-automations-connectors' ); ?></label>
    <div class="field-wrap">
        <div class="wrapper">
            <?php if ( $has_api_key && $has_instance ) : ?>
                <p><?php echo esc_html__( 'This connector uses the connection configured in Bouncer WhatsApp. Credentials are not shown or stored in FunnelKit.', 'autonami-automations-connectors' ); ?></p>
                <p>
                    <strong><?php echo esc_html__( 'Connection:', 'autonami-automations-connectors' ); ?></strong>
                    <?php echo esc_html__( 'Configured', 'autonami-automations-connectors' ); ?>
                </p>
            <?php else : ?>
                <p><?php echo esc_html__( 'Configure your API key and WhatsApp instance in Bouncer WhatsApp before using this FunnelKit connector.', 'autonami-automations-connectors' ); ?></p>
            <?php endif; ?>
            <p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-bouncer-whatsapp#connection' ) ); ?>" target="_blank" rel="noopener noreferrer">
                    <?php echo esc_html__( 'Open Bouncer WhatsApp settings', 'autonami-automations-connectors' ); ?>
                </a>
            </p>
        </div>
    </div>
</div>

<div class="wfco-form-groups wfco_form_submit wfco_bouncer_main_submit">
	<?php
	if ( isset( $old_data['id'] ) && (int) $old_data['id'] > 0 ) {
		?>
        <input type="hidden" name="edit_nonce" value="<?php echo esc_attr( wp_create_nonce( 'wfco-connector-edit' ) ); ?>"/>
        <input type="hidden" name="id" value="<?php echo esc_attr( $old_data['id'] ); ?>"/>
        <input type="hidden" name="wfco_connector" value="<?php echo esc_attr( $this->get_slug() ); ?>"/>
        <input type="submit" class="wfco_update_btn_style wfco_save_btn_style" name="autoresponderSubmit" value="<?php echo esc_attr__( 'Update', 'autonami-automations-connectors' ); ?>">
	<?php } else { ?>
        <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'wfco-connector' ) ); ?>">
        <input type="hidden" name="wfco_connector" value="<?php echo esc_attr( $this->get_slug() ); ?>"/>
        <input type="submit" class="wfco_save_btn_style" name="autoresponderSubmit" value="<?php echo esc_attr__( 'Save', 'autonami-automations-connectors' ); ?>">
	<?php } ?>
</div>
<div class="wfco_form_response" style="text-align: center;font-size: 15px;margin-top: 10px;"></div>
