<?php
/** @var array $settings */
/** @var array|null $test_result */
/** @var array|null $health_result */
/** @var array|null $preview */
/** @var array $recent_orders */
/** @var int $selected_id */

$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'connection';
$has_api_key = ! empty( $settings['api_key'] );
$has_instance = ! empty( $settings['instance_id'] );
?>
<div class="bouncer-admin-wrap">
    <!-- Page Header -->
    <div class="bouncer-page-header">
        <h1 class="bouncer-page-title">
            Bouncer WhatsApp
            <?php if ( $has_api_key && $has_instance ) : ?>
                <span class="bouncer-status-badge connected">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php esc_html_e( 'Connected', 'wc-bouncer-whatsapp' ); ?>
                </span>
            <?php elseif ( $has_api_key ) : ?>
                <span class="bouncer-status-badge unknown">
                    <span class="dashicons dashicons-warning"></span>
                    <?php esc_html_e( 'Setup Required', 'wc-bouncer-whatsapp' ); ?>
                </span>
            <?php else : ?>
                <span class="bouncer-status-badge disconnected">
                    <span class="dashicons dashicons-dismiss"></span>
                    <?php esc_html_e( 'Not Connected', 'wc-bouncer-whatsapp' ); ?>
                </span>
            <?php endif; ?>
        </h1>
        <p class="bouncer-page-description">
            <?php esc_html_e( 'Configure WhatsApp notifications for your WooCommerce orders.', 'wc-bouncer-whatsapp' ); ?>
        </p>
    </div>

    <?php if ( isset( $_GET['general_saved'] ) ) : ?>
        <div class="bouncer-alert bouncer-alert-success">
            <span class="dashicons dashicons-yes-alt"></span>
            <div class="bouncer-alert-content"><?php esc_html_e( 'Settings saved successfully.', 'wc-bouncer-whatsapp' ); ?></div>
        </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="bouncer-tabs">
        <a href="#connection" class="bouncer-tab <?php echo 'connection' === $active_tab ? 'active' : ''; ?>" data-tab="connection">
            <span class="dashicons dashicons-admin-plugins"></span>
            <?php esc_html_e( 'Connection', 'wc-bouncer-whatsapp' ); ?>
        </a>
        <a href="#automation" class="bouncer-tab <?php echo 'automation' === $active_tab ? 'active' : ''; ?>" data-tab="automation" id="automation_tab">
            <span class="dashicons dashicons-randomize"></span>
            <?php esc_html_e( 'Automation', 'wc-bouncer-whatsapp' ); ?>
        </a>
        <a href="#templates" class="bouncer-tab <?php echo 'templates' === $active_tab ? 'active' : ''; ?>" data-tab="templates" id="templates_tab" style="<?php echo 'cloud-api' !== ( $settings['instance_type'] ?? '' ) ? 'display:none;' : ''; ?>">
            <span class="dashicons dashicons-media-text"></span>
            <?php esc_html_e( 'Templates', 'wc-bouncer-whatsapp' ); ?>
        </a>
        <a href="#test" class="bouncer-tab <?php echo 'test' === $active_tab ? 'active' : ''; ?>" data-tab="test">
            <span class="dashicons dashicons-email"></span>
            <?php esc_html_e( 'Test', 'wc-bouncer-whatsapp' ); ?>
        </a>
        <a href="#tools" class="bouncer-tab <?php echo 'tools' === $active_tab ? 'active' : ''; ?>" data-tab="tools">
            <span class="dashicons dashicons-admin-tools"></span>
            <?php esc_html_e( 'Tools', 'wc-bouncer-whatsapp' ); ?>
        </a>
        <a href="#webhooks" class="bouncer-tab <?php echo 'webhooks' === $active_tab ? 'active' : ''; ?>" data-tab="webhooks">
            <span class="dashicons dashicons-rest-api"></span>
            <?php esc_html_e( 'Real-time Events', 'wc-bouncer-whatsapp' ); ?>
        </a>
    </div>

    <!-- Connection Tab -->
    <div id="connection" class="bouncer-tab-content <?php echo 'connection' === $active_tab ? 'active' : ''; ?>">
        <form method="post">
            <?php wp_nonce_field( 'wc_bouncer_save_general' ); ?>
            <input type="hidden" name="wc_bouncer_action" value="save_general" />
            <input type="hidden" name="instance_type" id="instance_type" value="<?php echo esc_attr( $settings['instance_type'] ?? '' ); ?>" />

            <div class="bouncer-card">
                <div class="bouncer-card-header">
                    <h3 class="bouncer-card-title">
                        <span class="dashicons dashicons-admin-network"></span>
                        <?php esc_html_e( 'API Configuration', 'wc-bouncer-whatsapp' ); ?>
                    </h3>
                </div>
                <div class="bouncer-card-body">
                    <div class="bouncer-form-row">
                        <div class="bouncer-form-group">
                            <label class="bouncer-form-label" for="api_key">
                                <?php esc_html_e( 'API Key', 'wc-bouncer-whatsapp' ); ?>
                                <span class="required">*</span>
                            </label>
                            <input
                                type="password"
                                name="api_key"
                                id="api_key"
                                class="bouncer-form-input"
                                value="<?php echo esc_attr( $settings['api_key'] ); ?>"
                                autocomplete="off"
                                placeholder="bnc_live_sk_..."
                            />
                            <p class="bouncer-form-description">
                                <?php esc_html_e( 'Your Bouncer API key from the dashboard.', 'wc-bouncer-whatsapp' ); ?>
                            </p>
                        </div>

                        <div class="bouncer-form-group">
                            <label class="bouncer-form-label" for="log_retention">
                                <?php esc_html_e( 'Log Retention', 'wc-bouncer-whatsapp' ); ?>
                            </label>
                            <div class="bouncer-inline-row">
                                <input
                                    type="number"
                                    name="log_retention"
                                    id="log_retention"
                                    class="bouncer-form-input small"
                                    value="<?php echo esc_attr( (string) $settings['log_retention'] ); ?>"
                                    min="1"
                                />
                                <span style="color: #6b7280; font-size: 13px;"><?php esc_html_e( 'days', 'wc-bouncer-whatsapp' ); ?></span>
                            </div>
                            <p class="bouncer-form-description">
                                <?php esc_html_e( 'Auto-delete logs older than this.', 'wc-bouncer-whatsapp' ); ?>
                            </p>
                        </div>
                    </div>

                    <div class="bouncer-divider"></div>

                    <div class="bouncer-form-group">
                        <label class="bouncer-form-label" for="instance_id">
                            <?php esc_html_e( 'WhatsApp Instance', 'wc-bouncer-whatsapp' ); ?>
                            <span class="required">*</span>
                        </label>
                        <div class="bouncer-input-group">
                            <select name="instance_id" id="instance_id" class="bouncer-form-select">
                                <option value=""><?php esc_html_e( 'Select an instance...', 'wc-bouncer-whatsapp' ); ?></option>
                                <?php if ( ! empty( $settings['instance_id'] ) ) : ?>
                                    <option value="<?php echo esc_attr( $settings['instance_id'] ); ?>" selected>
                                        <?php echo esc_html( $settings['instance_id'] ); ?>
                                    </option>
                                <?php endif; ?>
                            </select>
                            <button type="button" id="fetch_instances_btn" class="bouncer-btn bouncer-btn-secondary">
                                <span class="btn-icon"><span class="dashicons dashicons-update"></span></span>
                                <span class="btn-text"><?php esc_html_e( 'Fetch', 'wc-bouncer-whatsapp' ); ?></span>
                                <span class="bouncer-spinner" style="display:none;"></span>
                            </button>
                        </div>
                        <p class="bouncer-form-description">
                            <?php esc_html_e( 'Select the WhatsApp instance for sending messages.', 'wc-bouncer-whatsapp' ); ?>
                        </p>
                        <p id="instance_error" class="bouncer-form-error" style="display: none;"></p>

                        <div id="instance_info" class="bouncer-instance-info" style="display: none;">
                            <span class="dashicons dashicons-whatsapp"></span>
                            <div class="bouncer-instance-details">
                                <div class="bouncer-instance-name" id="instance_name"></div>
                                <div class="bouncer-instance-phone" id="instance_phone"></div>
                            </div>
                            <span id="instance_type_badge" class="bouncer-type-badge"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cloud API Templates Section -->
            <div id="templates_card" class="bouncer-card" style="display: none;">
                <div class="bouncer-card-header">
                    <h3 class="bouncer-card-title">
                        <span class="dashicons dashicons-media-text"></span>
                        <?php esc_html_e( 'Message Templates', 'wc-bouncer-whatsapp' ); ?>
                    </h3>
                    <button type="button" id="refresh_templates_btn" class="bouncer-btn bouncer-btn-ghost bouncer-btn-sm">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e( 'Refresh', 'wc-bouncer-whatsapp' ); ?>
                    </button>
                </div>
                <div class="bouncer-card-body">
                    <p class="bouncer-form-description" style="margin: 0 0 16px 0;">
                        <?php esc_html_e( 'Cloud API requires pre-approved message templates from Meta.', 'wc-bouncer-whatsapp' ); ?>
                    </p>

                    <div id="templates_loading" class="bouncer-loading-overlay" style="display: none;">
                        <span class="bouncer-spinner"></span>
                        <span><?php esc_html_e( 'Loading templates...', 'wc-bouncer-whatsapp' ); ?></span>
                    </div>

                    <div id="templates_error" class="bouncer-alert bouncer-alert-error" style="display: none; margin: 0;">
                        <span class="dashicons dashicons-dismiss"></span>
                        <div class="bouncer-alert-content"></div>
                    </div>

                    <div id="templates_list"></div>
                </div>
            </div>

            <div class="bouncer-form-actions">
                <button type="submit" class="bouncer-btn bouncer-btn-primary">
                    <span class="dashicons dashicons-saved"></span>
                    <?php esc_html_e( 'Save Settings', 'wc-bouncer-whatsapp' ); ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Automation Tab -->
    <?php
    $cloud_config        = $settings['cloud_template_config'] ?? [];
    $status_template_map = $cloud_config['status_template_map'] ?? [];
    $template_variables  = $cloud_config['template_variables'] ?? [];
    $template_languages  = $cloud_config['template_languages'] ?? [];
    $wc_statuses         = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : [];
    $instance_type       = $settings['instance_type'] ?? 'bouncer';
    $trigger_statuses    = $settings['trigger_statuses'] ?? [];
    $status_templates    = $settings['status_templates'] ?? [];
    $message_template    = $settings['message_template'] ?? '';
    ?>
    <div id="automation" class="bouncer-tab-content <?php echo 'automation' === $active_tab ? 'active' : ''; ?>">
        <form method="post" id="automation_form">
            <?php wp_nonce_field( 'wc_bouncer_save_automation' ); ?>
            <input type="hidden" name="wc_bouncer_action" value="save_automation" />
            <input type="hidden" name="instance_type" value="<?php echo esc_attr( $instance_type ); ?>" />

            <?php if ( 'bouncer' === $instance_type ) : ?>
            <!-- Bouncer: Placeholders Reference -->
            <div class="bouncer-card">
                <div class="bouncer-card-header">
                    <h3 class="bouncer-card-title">
                        <span class="dashicons dashicons-info"></span>
                        <?php esc_html_e( 'Available Placeholders', 'wc-bouncer-whatsapp' ); ?>
                    </h3>
                </div>
                <div class="bouncer-card-body">
                    <div class="bouncer-placeholder-grid">
                        <div class="bouncer-placeholder-group">
                            <div class="bouncer-section-title"><?php esc_html_e( 'Customer', 'wc-bouncer-whatsapp' ); ?></div>
                            <code>{first_name}</code> <code>{last_name}</code> <code>{name}</code> <code>{email}</code> <code>{phone}</code>
                        </div>
                        <div class="bouncer-placeholder-group">
                            <div class="bouncer-section-title"><?php esc_html_e( 'Order', 'wc-bouncer-whatsapp' ); ?></div>
                            <code>{order_number}</code> <code>{order_status}</code> <code>{order_total}</code> <code>{order_items}</code> <code>{order_date}</code>
                        </div>
                        <div class="bouncer-placeholder-group">
                            <div class="bouncer-section-title"><?php esc_html_e( 'Other', 'wc-bouncer-whatsapp' ); ?></div>
                            <code>{payment_method}</code> <code>{shipping_method}</code> <code>{billing_address}</code> <code>{meta:KEY}</code>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bouncer: Default Message Template -->
            <div class="bouncer-card">
                <div class="bouncer-card-header">
                    <h3 class="bouncer-card-title">
                        <span class="dashicons dashicons-edit"></span>
                        <?php esc_html_e( 'Default Message Template', 'wc-bouncer-whatsapp' ); ?>
                    </h3>
                </div>
                <div class="bouncer-card-body">
                    <textarea name="message_template" class="bouncer-form-textarea" rows="5" placeholder="<?php esc_attr_e( 'Hello {first_name}, your order #{order_number} is now {order_status}.', 'wc-bouncer-whatsapp' ); ?>"><?php echo esc_textarea( $message_template ); ?></textarea>
                    <p class="bouncer-form-description"><?php esc_html_e( 'Used when a status has no custom message.', 'wc-bouncer-whatsapp' ); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Status Triggers -->
            <div class="bouncer-card">
                <div class="bouncer-card-header">
                    <h3 class="bouncer-card-title">
                        <span class="dashicons dashicons-randomize"></span>
                        <?php esc_html_e( 'Order Status Triggers', 'wc-bouncer-whatsapp' ); ?>
                    </h3>
                </div>
                <div class="bouncer-card-body">
                    <p class="bouncer-form-description" style="margin: 0 0 16px 0;">
                        <?php if ( 'cloud-api' === $instance_type ) : ?>
                            <?php esc_html_e( 'Select which template to send when an order reaches each status.', 'wc-bouncer-whatsapp' ); ?>
                        <?php else : ?>
                            <?php esc_html_e( 'Enable statuses to trigger WhatsApp messages. Customize messages per status.', 'wc-bouncer-whatsapp' ); ?>
                        <?php endif; ?>
                    </p>

                    <div id="no_templates_warning" class="bouncer-alert bouncer-alert-warning" style="display: none; margin-bottom: 16px;">
                        <span class="dashicons dashicons-warning"></span>
                        <div class="bouncer-alert-content">
                            <?php esc_html_e( 'No approved templates found. Create templates in Meta Business Manager first.', 'wc-bouncer-whatsapp' ); ?>
                        </div>
                    </div>

                    <div class="bouncer-status-triggers">
                        <?php foreach ( $wc_statuses as $status_key => $status_label ) :
                            // For Bouncer
                            $is_bouncer_enabled = in_array( $status_key, $trigger_statuses, true );
                            $status_message     = $status_templates[ $status_key ] ?? '';
                            // For Cloud API
                            $mapped_template    = $status_template_map[ $status_key ] ?? '';
                            $tpl_lang           = $template_languages[ $mapped_template ] ?? 'en';
                            $has_template       = ! empty( $mapped_template );
                        ?>
                            <div class="bouncer-status-trigger-item <?php echo ( 'bouncer' === $instance_type ? $is_bouncer_enabled : $has_template ) ? 'is-enabled' : ''; ?>">
                                <div class="bouncer-status-trigger-header">
                                    <?php if ( 'cloud-api' === $instance_type ) : ?>
                                        <label class="bouncer-status-trigger-label" title="<?php echo esc_attr( $status_key ); ?>">
                                            <?php echo esc_html( $status_label ); ?>
                                        </label>
                                        <select name="cloud_template_config[status_template_map][<?php echo esc_attr( $status_key ); ?>]" class="bouncer-form-select status-template-select" data-status="<?php echo esc_attr( $status_key ); ?>">
                                            <option value="" data-language=""><?php esc_html_e( '— No template (disabled) —', 'wc-bouncer-whatsapp' ); ?></option>
                                            <?php if ( $has_template ) : ?>
                                                <option value="<?php echo esc_attr( $mapped_template ); ?>" data-language="<?php echo esc_attr( $tpl_lang ); ?>" selected><?php echo esc_html( $mapped_template ); ?></option>
                                            <?php endif; ?>
                                        </select>
                                    <?php else : ?>
                                        <label class="bouncer-checkbox-label">
                                            <input type="checkbox" name="trigger_statuses[]" value="<?php echo esc_attr( $status_key ); ?>" class="bouncer-checkbox status-trigger-checkbox" <?php checked( $is_bouncer_enabled ); ?> />
                                            <span class="bouncer-checkbox-text" title="<?php echo esc_attr( $status_key ); ?>">
                                                <strong><?php echo esc_html( $status_label ); ?></strong>
                                            </span>
                                        </label>
                                    <?php endif; ?>
                                </div>
                                <?php if ( 'bouncer' === $instance_type ) : ?>
                                <div class="bouncer-status-trigger-body" style="<?php echo $is_bouncer_enabled ? '' : 'display: none;'; ?>">
                                    <textarea name="status_templates[<?php echo esc_attr( $status_key ); ?>]" class="bouncer-form-textarea" rows="3" placeholder="<?php esc_attr_e( 'Leave blank to use default template', 'wc-bouncer-whatsapp' ); ?>"><?php echo esc_textarea( $status_message ); ?></textarea>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="template_languages_container"></div>
                </div>
            </div>

            <div class="bouncer-form-actions">
                <button type="submit" class="bouncer-btn bouncer-btn-primary">
                    <span class="dashicons dashicons-saved"></span>
                    <?php esc_html_e( 'Save Automation', 'wc-bouncer-whatsapp' ); ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Templates Tab (Cloud API only) -->
    <div id="templates" class="bouncer-tab-content <?php echo 'templates' === $active_tab ? 'active' : ''; ?>">
        <form method="post" id="templates_config_form">
            <?php wp_nonce_field( 'wc_bouncer_save_templates' ); ?>
            <input type="hidden" name="wc_bouncer_action" value="save_templates" />

            <!-- Placeholders Reference -->
            <div class="bouncer-card">
                <div class="bouncer-card-header">
                    <h3 class="bouncer-card-title">
                        <span class="dashicons dashicons-info"></span>
                        <?php esc_html_e( 'Variable Mapping Reference', 'wc-bouncer-whatsapp' ); ?>
                    </h3>
                </div>
                <div class="bouncer-card-body">
                    <p class="bouncer-form-description" style="margin: 0 0 12px 0;">
                        <?php esc_html_e( 'Map template variables ({{1}}, {{2}}) to these order data placeholders:', 'wc-bouncer-whatsapp' ); ?>
                    </p>
                    <div class="bouncer-placeholder-grid">
                        <div class="bouncer-placeholder-group">
                            <div class="bouncer-section-title"><?php esc_html_e( 'Customer', 'wc-bouncer-whatsapp' ); ?></div>
                            <code>{first_name}</code> <code>{last_name}</code> <code>{name}</code> <code>{email}</code> <code>{phone}</code>
                        </div>
                        <div class="bouncer-placeholder-group">
                            <div class="bouncer-section-title"><?php esc_html_e( 'Order', 'wc-bouncer-whatsapp' ); ?></div>
                            <code>{order_number}</code> <code>{order_status}</code> <code>{order_total}</code> <code>{order_items}</code> <code>{order_date}</code>
                        </div>
                        <div class="bouncer-placeholder-group">
                            <div class="bouncer-section-title"><?php esc_html_e( 'Other', 'wc-bouncer-whatsapp' ); ?></div>
                            <code>{payment_method}</code> <code>{billing_address}</code> <code>{shipping_address}</code> <code>{meta:KEY}</code>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Template Variable Mappings -->
            <div class="bouncer-card">
                <div class="bouncer-card-header">
                    <h3 class="bouncer-card-title">
                        <span class="dashicons dashicons-editor-code"></span>
                        <?php esc_html_e( 'Template Variable Mappings', 'wc-bouncer-whatsapp' ); ?>
                    </h3>
                </div>
                <div class="bouncer-card-body">
                    <p class="bouncer-form-description" style="margin: 0 0 16px 0;">
                        <?php esc_html_e( 'Configure which order data fills each template variable.', 'wc-bouncer-whatsapp' ); ?>
                    </p>

                    <div id="available_templates_list" class="bouncer-available-templates" style="margin-bottom: 16px; display: none;">
                        <div class="bouncer-section-title" style="margin-bottom: 8px;"><?php esc_html_e( 'Available Templates', 'wc-bouncer-whatsapp' ); ?></div>
                        <div id="available_templates_items"></div>
                    </div>

                    <div id="no_templates_to_map" class="bouncer-alert bouncer-alert-info" style="margin-bottom: 16px; display: none;">
                        <span class="dashicons dashicons-info"></span>
                        <div class="bouncer-alert-content"><?php esc_html_e( 'All templates have been mapped.', 'wc-bouncer-whatsapp' ); ?></div>
                    </div>

                    <div id="template_variable_mappings">
                        <?php if ( empty( $template_variables ) ) : ?>
                            <div class="bouncer-empty-state" id="no_mappings_placeholder">
                                <span class="dashicons dashicons-editor-code"></span>
                                <p><?php esc_html_e( 'Click "Map" on a template above to configure its variables.', 'wc-bouncer-whatsapp' ); ?></p>
                            </div>
                        <?php else : ?>
                            <?php foreach ( $template_variables as $tpl_name => $vars ) : ?>
                                <div class="bouncer-template-mapping-block" data-template="<?php echo esc_attr( $tpl_name ); ?>">
                                    <div class="bouncer-mapping-header">
                                        <button type="button" class="bouncer-collapse-toggle" aria-expanded="true"><span class="dashicons dashicons-arrow-down-alt2"></span></button>
                                        <span class="bouncer-mapping-title"><?php echo esc_html( $tpl_name ); ?></span>
                                        <span class="bouncer-mapping-summary"></span>
                                        <div class="bouncer-mapping-actions">
                                            <button type="button" class="bouncer-btn bouncer-btn-secondary bouncer-btn-sm preview-mapping-btn" data-template="<?php echo esc_attr( $tpl_name ); ?>">
                                                <span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'Preview', 'wc-bouncer-whatsapp' ); ?>
                                            </button>
                                            <button type="button" class="bouncer-btn bouncer-btn-ghost bouncer-btn-sm remove-template-mapping"><span class="dashicons dashicons-trash"></span></button>
                                        </div>
                                    </div>
                                    <div class="bouncer-mapping-content">
                                    <?php
                                    $placeholder_options = [
                                        '{first_name}' => '{first_name}', '{last_name}' => '{last_name}',
                                        '{name}' => '{name}', '{email}' => '{email}', '{phone}' => '{phone}',
                                        '{order_number}' => '{order_number}', '{order_total}' => '{order_total}',
                                        '{order_status}' => '{order_status}', '{order_date}' => '{order_date}',
                                        '{order_items}' => '{order_items}', '{payment_method}' => '{payment_method}',
                                        '{billing_address}' => '{billing_address}', '{shipping_address}' => '{shipping_address}',
                                    ];
                                    $meta_placeholders = [];
                                    if ( ! empty( $discovered_meta_keys ) ) {
                                        foreach ( $discovered_meta_keys as $mk ) {
                                            $placeholder_options[ '{meta:' . $mk . '}' ] = '{meta:' . $mk . '}';
                                            $meta_placeholders[] = '{meta:' . $mk . '}';
                                        }
                                    }
                                    $placeholder_groups = [
                                        __( 'Customer', 'wc-bouncer-whatsapp' ) => [ '{first_name}', '{last_name}', '{name}', '{email}', '{phone}' ],
                                        __( 'Order', 'wc-bouncer-whatsapp' )    => [ '{order_number}', '{order_total}', '{order_status}', '{order_date}', '{order_items}', '{payment_method}' ],
                                        __( 'Address', 'wc-bouncer-whatsapp' )  => [ '{billing_address}', '{shipping_address}' ],
                                    ];
                                    if ( ! empty( $meta_placeholders ) ) { $placeholder_groups[ __( 'Meta', 'wc-bouncer-whatsapp' ) ] = $meta_placeholders; }
                                    ?>
                                    <?php for ( $i = 1; $i <= 10; $i++ ) : ?>
                                        <div class="bouncer-variable-row">
                                            <span class="bouncer-variable-label">{{<?php echo esc_html( $i ); ?>}}</span>
                                            <select name="cloud_template_config[template_variables][<?php echo esc_attr( $tpl_name ); ?>][<?php echo esc_attr( $i ); ?>]" class="bouncer-form-select placeholder-select">
                                                <option value=""><?php esc_html_e( '— Select —', 'wc-bouncer-whatsapp' ); ?></option>
                                                <?php foreach ( $placeholder_groups as $gl => $gi ) : ?>
                                                    <optgroup label="<?php echo esc_attr( $gl ); ?>">
                                                        <?php foreach ( $gi as $pv ) : ?>
                                                            <option value="<?php echo esc_attr( $pv ); ?>" <?php selected( $vars[ $i ] ?? '', $pv ); ?>><?php echo esc_html( $pv ); ?></option>
                                                        <?php endforeach; ?>
                                                    </optgroup>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php endfor; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="bouncer-form-actions">
                <button type="submit" class="bouncer-btn bouncer-btn-primary">
                    <span class="dashicons dashicons-saved"></span>
                    <?php esc_html_e( 'Save Variable Mappings', 'wc-bouncer-whatsapp' ); ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Test Message Tab -->
    <div id="test" class="bouncer-tab-content <?php echo 'test' === $active_tab ? 'active' : ''; ?>">
        <?php if ( $test_result ) : ?>
            <div class="bouncer-alert bouncer-alert-<?php echo $test_result['success'] ? 'success' : 'error'; ?>">
                <span class="dashicons dashicons-<?php echo $test_result['success'] ? 'yes-alt' : 'dismiss'; ?>"></span>
                <div class="bouncer-alert-content">
                    <?php if ( $test_result['success'] ) : ?>
                        <div class="bouncer-alert-title"><?php esc_html_e( 'Message Sent Successfully', 'wc-bouncer-whatsapp' ); ?></div>
                        <div><?php esc_html_e( 'Your test message was delivered.', 'wc-bouncer-whatsapp' ); ?></div>
                    <?php else : ?>
                        <div class="bouncer-alert-title"><?php esc_html_e( 'Failed to Send', 'wc-bouncer-whatsapp' ); ?></div>
                        <?php if ( isset( $test_result['message'] ) && '' !== $test_result['message'] ) : ?>
                            <div><?php echo esc_html( $test_result['message'] ); ?></div>
                        <?php endif; ?>
                        <?php if ( isset( $test_result['code'] ) && $test_result['code'] ) : ?>
                            <div><?php echo esc_html( sprintf( __( 'HTTP %s', 'wc-bouncer-whatsapp' ), $test_result['code'] ) ); ?></div>
                        <?php endif; ?>
                        <?php if ( isset( $test_result['body'] ) && '' !== $test_result['body'] ) : ?>
                            <div class="bouncer-response-box"><?php echo esc_html( $test_result['body'] ); ?></div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="bouncer-card">
            <div class="bouncer-card-header">
                <h3 class="bouncer-card-title">
                    <span class="dashicons dashicons-email"></span>
                    <?php esc_html_e( 'Send Test Message', 'wc-bouncer-whatsapp' ); ?>
                </h3>
                <span id="test_instance_type_badge" class="bouncer-type-badge" style="display: none;"></span>
            </div>
            <div class="bouncer-card-body">
                <!-- Bouncer Form (free-form message) -->
                <form method="post" id="test_form_bouncer">
                    <?php wp_nonce_field( 'wc_bouncer_send_test' ); ?>
                    <input type="hidden" name="wc_bouncer_action" value="send_test" />
                    <input type="hidden" name="test_type" value="bouncer" />

                    <div class="bouncer-form-row">
                        <div class="bouncer-form-group">
                            <label class="bouncer-form-label" for="test_phone_bouncer">
                                <?php esc_html_e( 'Phone Number', 'wc-bouncer-whatsapp' ); ?>
                                <span class="required">*</span>
                            </label>
                            <input
                                type="text"
                                name="test_phone"
                                id="test_phone_bouncer"
                                class="bouncer-form-input"
                                placeholder="+60123456789"
                            />
                            <p class="bouncer-form-description">
                                <?php esc_html_e( 'Include country code.', 'wc-bouncer-whatsapp' ); ?>
                            </p>
                        </div>
                        <div></div>
                    </div>

                    <div class="bouncer-form-group">
                        <label class="bouncer-form-label" for="test_message">
                            <?php esc_html_e( 'Message', 'wc-bouncer-whatsapp' ); ?>
                            <span class="required">*</span>
                        </label>
                        <textarea
                            name="test_message"
                            id="test_message"
                            class="bouncer-form-textarea"
                            rows="4"
                            placeholder="<?php esc_attr_e( 'Enter your test message...', 'wc-bouncer-whatsapp' ); ?>"
                        ></textarea>
                    </div>

                    <div class="bouncer-form-actions">
                        <button type="submit" class="bouncer-btn bouncer-btn-primary">
                            <span class="dashicons dashicons-email-alt"></span>
                            <?php esc_html_e( 'Send Message', 'wc-bouncer-whatsapp' ); ?>
                        </button>
                    </div>
                </form>

                <!-- Cloud API Form (template selector) -->
                <form method="post" id="test_form_cloud" style="display: none;">
                    <?php wp_nonce_field( 'wc_bouncer_send_test' ); ?>
                    <input type="hidden" name="wc_bouncer_action" value="send_test" />
                    <input type="hidden" name="test_type" value="cloud-api" />

                    <div class="bouncer-form-group">
                        <label class="bouncer-form-label" for="test_template">
                            <?php esc_html_e( 'Message Template', 'wc-bouncer-whatsapp' ); ?>
                            <span class="required">*</span>
                        </label>
                        <select name="test_template" id="test_template" class="bouncer-form-select">
                            <option value="" data-language=""><?php esc_html_e( 'Select a template...', 'wc-bouncer-whatsapp' ); ?></option>
                        </select>
                        <input type="hidden" name="test_template_language" id="test_template_language" value="" />
                        <p class="bouncer-form-description">
                            <?php esc_html_e( 'Only templates with configured variable mappings are shown.', 'wc-bouncer-whatsapp' ); ?>
                        </p>
                        <div id="test_template_loading" class="bouncer-loading-overlay" style="display: none; justify-content: flex-start; padding: 12px 0;">
                            <span class="bouncer-spinner"></span>
                            <span><?php esc_html_e( 'Loading templates...', 'wc-bouncer-whatsapp' ); ?></span>
                        </div>
                    </div>

                    <div class="bouncer-form-group">
                        <label class="bouncer-form-label" for="test_order">
                            <?php esc_html_e( 'Test with Order', 'wc-bouncer-whatsapp' ); ?>
                            <span class="required">*</span>
                        </label>
                        <select name="test_order" id="test_order" class="bouncer-form-select">
                            <option value=""><?php esc_html_e( 'Select an order...', 'wc-bouncer-whatsapp' ); ?></option>
                            <?php foreach ( $recent_orders as $order ) : ?>
                                <option value="<?php echo esc_attr( $order['id'] ); ?>" data-phone="<?php echo esc_attr( $order['phone'] ?? '' ); ?>">
                                    #<?php echo esc_html( $order['id'] ); ?> - <?php echo esc_html( $order['name'] ); ?> (<?php echo esc_html( $order['status'] ); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="bouncer-form-description">
                            <?php esc_html_e( 'Message will be sent to the order\'s billing phone number.', 'wc-bouncer-whatsapp' ); ?>
                        </p>
                    </div>

                    <div id="test_order_phone_info" class="bouncer-alert bouncer-alert-info" style="display: none;">
                        <span class="dashicons dashicons-phone"></span>
                        <div class="bouncer-alert-content">
                            <?php esc_html_e( 'Will send to:', 'wc-bouncer-whatsapp' ); ?> <strong id="test_order_phone_display"></strong>
                        </div>
                    </div>

                    <!-- Live Preview -->
                    <div id="test_preview_section" style="display: none;">
                        <label class="bouncer-form-label"><?php esc_html_e( 'Message Preview', 'wc-bouncer-whatsapp' ); ?></label>
                        <div class="bouncer-test-preview">
                            <div id="test_preview_loading" class="bouncer-loading-overlay" style="display: none;">
                                <span class="bouncer-spinner"></span>
                                <span><?php esc_html_e( 'Loading preview...', 'wc-bouncer-whatsapp' ); ?></span>
                            </div>
                            <div id="test_preview_content" class="bouncer-whatsapp-bubble"></div>
                        </div>
                    </div>

                    <div class="bouncer-form-actions">
                        <button type="submit" class="bouncer-btn bouncer-btn-primary">
                            <span class="dashicons dashicons-email-alt"></span>
                            <?php esc_html_e( 'Send Template', 'wc-bouncer-whatsapp' ); ?>
                        </button>
                    </div>
                </form>

                <!-- No instance selected -->
                <div id="test_no_instance" class="bouncer-empty-state" style="display: none;">
                    <span class="dashicons dashicons-warning"></span>
                    <p><?php esc_html_e( 'Please select a WhatsApp instance in the Connection tab first.', 'wc-bouncer-whatsapp' ); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tools Tab -->
    <div id="tools" class="bouncer-tab-content <?php echo 'tools' === $active_tab ? 'active' : ''; ?>">
        <div class="bouncer-row">
            <!-- Health Check -->
            <div class="bouncer-card">
                <div class="bouncer-card-header">
                    <h3 class="bouncer-card-title">
                        <span class="dashicons dashicons-heart"></span>
                        <?php esc_html_e( 'Health Check', 'wc-bouncer-whatsapp' ); ?>
                    </h3>
                </div>
                <div class="bouncer-card-body">
                    <?php if ( $health_result ) : ?>
                        <div class="bouncer-alert bouncer-alert-<?php echo $health_result['success'] ? 'success' : 'error'; ?>" style="margin-bottom: 16px;">
                            <span class="dashicons dashicons-<?php echo $health_result['success'] ? 'yes-alt' : 'dismiss'; ?>"></span>
                            <div class="bouncer-alert-content">
                                <div class="bouncer-alert-title">
                                    <?php echo $health_result['success'] ? esc_html__( 'Connection OK', 'wc-bouncer-whatsapp' ) : esc_html__( 'Connection Failed', 'wc-bouncer-whatsapp' ); ?>
                                </div>
                                <?php if ( isset( $health_result['data'] ) && $health_result['data'] ) : ?>
                                    <div class="bouncer-response-box"><?php echo esc_html( wp_json_encode( $health_result['data'], JSON_PRETTY_PRINT ) ); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <p style="color: #6b7280; font-size: 13px; margin: 0 0 16px 0;">
                        <?php esc_html_e( 'Verify your WhatsApp instance is connected and responding.', 'wc-bouncer-whatsapp' ); ?>
                    </p>

                    <form method="post">
                        <?php wp_nonce_field( 'wc_bouncer_health_check' ); ?>
                        <input type="hidden" name="wc_bouncer_action" value="health_check" />
                        <input type="hidden" name="health_instance" value="<?php echo esc_attr( $settings['instance_id'] ?? '' ); ?>" />
                        <button type="submit" class="bouncer-btn bouncer-btn-secondary">
                            <span class="dashicons dashicons-heart"></span>
                            <?php esc_html_e( 'Run Check', 'wc-bouncer-whatsapp' ); ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Message Preview -->
            <div class="bouncer-card">
                <div class="bouncer-card-header">
                    <h3 class="bouncer-card-title">
                        <span class="dashicons dashicons-visibility"></span>
                        <?php esc_html_e( 'Message Preview', 'wc-bouncer-whatsapp' ); ?>
                    </h3>
                </div>
                <div class="bouncer-card-body">
                    <?php if ( $preview && isset( $preview['error'] ) ) : ?>
                        <div class="bouncer-alert bouncer-alert-error" style="margin-bottom: 16px;">
                            <span class="dashicons dashicons-dismiss"></span>
                            <div class="bouncer-alert-content"><?php echo esc_html( $preview['error'] ); ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ( $preview && ! isset( $preview['error'] ) ) : ?>
                        <div class="bouncer-preview-box">
                            <div class="bouncer-preview-label">
                                <?php echo esc_html( sprintf( __( 'Order #%s (%s)', 'wc-bouncer-whatsapp' ), $preview['order_number'], $preview['status'] ) ); ?>
                            </div>
                            <div class="bouncer-preview-message"><?php echo esc_html( $preview['message'] ); ?></div>
                        </div>

                        <?php if ( $preview['send_success'] !== null ) : ?>
                            <div class="bouncer-alert bouncer-alert-<?php echo $preview['send_success'] ? 'success' : 'error'; ?>" style="margin-bottom: 16px;">
                                <span class="dashicons dashicons-<?php echo $preview['send_success'] ? 'yes-alt' : 'dismiss'; ?>"></span>
                                <div class="bouncer-alert-content">
                                    <?php echo $preview['send_success'] ? esc_html__( 'Sent to customer', 'wc-bouncer-whatsapp' ) : esc_html__( 'Failed to send', 'wc-bouncer-whatsapp' ); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <form method="post">
                        <?php wp_nonce_field( 'wc_bouncer_preview' ); ?>

                        <div class="bouncer-form-group">
                            <label class="bouncer-form-label" for="preview_order_id">
                                <?php esc_html_e( 'Select Order', 'wc-bouncer-whatsapp' ); ?>
                            </label>
                            <select name="preview_order_id" id="preview_order_id" class="bouncer-form-select">
                                <option value="0"><?php esc_html_e( 'Choose an order...', 'wc-bouncer-whatsapp' ); ?></option>
                                <?php foreach ( $recent_orders as $order ) : ?>
                                    <option value="<?php echo esc_attr( $order['id'] ); ?>" <?php selected( $selected_id, $order['id'] ); ?>>
                                        #<?php echo esc_html( $order['number'] ); ?> - <?php echo esc_html( $order['status'] ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="display: flex; gap: 8px;">
                            <button type="submit" name="wc_bouncer_action" value="preview" class="bouncer-btn bouncer-btn-secondary">
                                <span class="dashicons dashicons-visibility"></span>
                                <?php esc_html_e( 'Preview', 'wc-bouncer-whatsapp' ); ?>
                            </button>
                            <button type="submit" name="wc_bouncer_action" value="send_preview" class="bouncer-btn bouncer-btn-primary">
                                <span class="dashicons dashicons-email-alt"></span>
                                <?php esc_html_e( 'Send', 'wc-bouncer-whatsapp' ); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php if ( $preview && ! isset( $preview['error'] ) && ! empty( $preview['meta'] ) ) : ?>
            <div class="bouncer-card" style="margin-top: 16px;">
                <div class="bouncer-card-header">
                    <h3 class="bouncer-card-title">
                        <span class="dashicons dashicons-database"></span>
                        <?php esc_html_e( 'Order Meta', 'wc-bouncer-whatsapp' ); ?>
                    </h3>
                </div>
                <table class="bouncer-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Key', 'wc-bouncer-whatsapp' ); ?></th>
                            <th><?php esc_html_e( 'Value', 'wc-bouncer-whatsapp' ); ?></th>
                            <th><?php esc_html_e( 'Placeholder', 'wc-bouncer-whatsapp' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $preview['meta'] as $key => $value ) : ?>
                            <tr>
                                <td><code><?php echo esc_html( $key ); ?></code></td>
                                <td><?php echo esc_html( mb_strimwidth( $value, 0, 40, '...' ) ); ?></td>
                                <td><code>{meta:<?php echo esc_html( $key ); ?>}</code></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Real-time Events Tab -->
    <div id="webhooks" class="bouncer-tab-content <?php echo 'webhooks' === $active_tab ? 'active' : ''; ?>">
        <?php
        $connection_status = $settings['connection_status'] ?? '';
        $integration_id    = $settings['integration_id'] ?? '';
        $is_connected      = 'connected' === $connection_status && ! empty( $integration_id );
        ?>

        <p class="bouncer-form-description" style="margin: 0 0 16px 0;">
            <?php esc_html_e( 'Sends order events (created, updated, deleted) to Bouncer in real time using WooCommerce webhooks. Required for any Bouncer workflow that reacts to order changes — including the Abandoned Orders feature.', 'wc-bouncer-whatsapp' ); ?>
        </p>

        <?php if ( $is_connected ) : ?>
        <div class="bouncer-card" style="background: #e7f5e7; border-left: 4px solid #46b450;">
            <div class="bouncer-card-header">
                <h3 class="bouncer-card-title">
                    <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                    <?php esc_html_e( 'Connected to Bouncer', 'wc-bouncer-whatsapp' ); ?>
                </h3>
            </div>
            <div class="bouncer-card-body">
                <p style="color: #3c434a; margin: 0 0 12px 0;">
                    <?php printf( esc_html__( 'Integration ID: %s', 'wc-bouncer-whatsapp' ), '<code>' . esc_html( $integration_id ) . '</code>' ); ?>
                </p>

                <div id="bouncer-connect-result"></div>

                <button type="button" id="bouncer-auto-configure" class="bouncer-btn bouncer-btn-primary">
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e( 'Reconnect', 'wc-bouncer-whatsapp' ); ?>
                </button>
                <button type="button" id="bouncer-disconnect" class="bouncer-btn" style="color: #a00; border-color: #a00; margin-left: 8px;">
                    <span class="dashicons dashicons-dismiss"></span>
                    <?php esc_html_e( 'Disconnect', 'wc-bouncer-whatsapp' ); ?>
                </button>
            </div>
        </div>
        <?php else : ?>
        <div class="bouncer-card" style="background: #f0f6fc; border-left: 4px solid #2271b1;">
            <div class="bouncer-card-header">
                <h3 class="bouncer-card-title">
                    <span class="dashicons dashicons-admin-links"></span>
                    <?php esc_html_e( 'Connect to Bouncer', 'wc-bouncer-whatsapp' ); ?>
                </h3>
            </div>
            <div class="bouncer-card-body">
                <p style="color: #3c434a; margin: 0 0 16px 0;">
                    <?php esc_html_e( 'Automatically configure WooCommerce webhooks to send order events to Bouncer.', 'wc-bouncer-whatsapp' ); ?>
                </p>

                <div id="bouncer-connect-result"></div>

                <button type="button" id="bouncer-auto-configure" class="bouncer-btn bouncer-btn-primary bouncer-btn-lg">
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e( 'Connect to Bouncer', 'wc-bouncer-whatsapp' ); ?>
                </button>

                <p class="description" style="margin-top: 12px; color: #6b7280;">
                    <?php esc_html_e( 'This will generate WooCommerce API keys, register your store with Bouncer, and create webhooks automatically.', 'wc-bouncer-whatsapp' ); ?>
                </p>
            </div>
        </div>
        <?php endif; ?>

        <div class="bouncer-card" style="margin-top: 16px;">
            <div class="bouncer-card-header">
                <h3 class="bouncer-card-title">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php esc_html_e( 'Webhook Events', 'wc-bouncer-whatsapp' ); ?>
                </h3>
            </div>
            <div class="bouncer-card-body">
                <p style="color: #6b7280; margin: 0 0 16px 0;">
                    <?php esc_html_e( 'Select which events should trigger Bouncer workflows:', 'wc-bouncer-whatsapp' ); ?>
                </p>
                
                <div class="bouncer-checkbox-group">
                    <label class="bouncer-checkbox-item">
                        <input type="checkbox" name="webhook_events[]" value="order.created" checked>
                        <span class="dashicons dashicons-cart"></span>
                        <?php esc_html_e( 'Order Created', 'wc-bouncer-whatsapp' ); ?>
                    </label>
                    <label class="bouncer-checkbox-item">
                        <input type="checkbox" name="webhook_events[]" value="order.updated" checked>
                        <span class="dashicons dashicons-edit"></span>
                        <?php esc_html_e( 'Order Updated', 'wc-bouncer-whatsapp' ); ?>
                    </label>
                    <label class="bouncer-checkbox-item">
                        <input type="checkbox" name="webhook_events[]" value="order.deleted">
                        <span class="dashicons dashicons-trash"></span>
                        <?php esc_html_e( 'Order Deleted', 'wc-bouncer-whatsapp' ); ?>
                    </label>
                </div>
            </div>
        </div>

        <div class="bouncer-card" style="margin-top: 16px;">
            <div class="bouncer-card-header">
                <h3 class="bouncer-card-title">
                    <span class="dashicons dashicons-admin-links"></span>
                    <?php esc_html_e( 'Active Webhooks', 'wc-bouncer-whatsapp' ); ?>
                </h3>
            </div>
            <div class="bouncer-card-body">
                <div id="bouncer-webhooks-list">
                    <?php
                    $bouncer_webhooks = [];
                    if ( class_exists( 'WC_Data_Store' ) ) {
                        $data_store  = \WC_Data_Store::load( 'webhook' );
                        $webhook_ids = array_merge(
                            $data_store->get_webhooks_ids( 'active' ),
                            $data_store->get_webhooks_ids( 'paused' ),
                            $data_store->get_webhooks_ids( 'disabled' )
                        );
                        foreach ( $webhook_ids as $wh_id ) {
                            $w = wc_get_webhook( $wh_id );
                            if ( ! $w ) continue;
                            if ( strpos( $w->get_delivery_url(), 'bouncer' ) !== false || strpos( $w->get_name(), 'Bouncer' ) !== false ) {
                                $bouncer_webhooks[] = $w;
                            }
                        }
                    }
                    
                    if ( empty( $bouncer_webhooks ) ) : ?>
                        <p style="color: #6b7280; margin: 0;">
                            <?php esc_html_e( 'No Bouncer webhooks configured yet. Click "Auto-Configure Webhooks" above to get started.', 'wc-bouncer-whatsapp' ); ?>
                        </p>
                    <?php else : ?>
                        <table class="bouncer-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Name', 'wc-bouncer-whatsapp' ); ?></th>
                                    <th><?php esc_html_e( 'Event', 'wc-bouncer-whatsapp' ); ?></th>
                                    <th><?php esc_html_e( 'Status', 'wc-bouncer-whatsapp' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $bouncer_webhooks as $webhook ) : ?>
                                    <tr>
                                        <td><?php echo esc_html( $webhook->get_name() ); ?></td>
                                        <td><code><?php echo esc_html( $webhook->get_topic() ); ?></code></td>
                                        <td>
                                            <?php if ( $webhook->get_status() === 'active' ) : ?>
                                                <span class="bouncer-status-badge connected" style="font-size: 12px;">
                                                    <?php esc_html_e( 'Active', 'wc-bouncer-whatsapp' ); ?>
                                                </span>
                                            <?php else : ?>
                                                <span class="bouncer-status-badge disconnected" style="font-size: 12px;">
                                                    <?php echo esc_html( $webhook->get_status() ); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function($) {
    // Webhook auto-configure
    $('#bouncer-auto-configure').on('click', function() {
        var $btn = $(this);
        var $result = $('#bouncer-connect-result');
        var origHtml = $btn.html();

        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spinning"></span> <?php esc_html_e( 'Connecting...', 'wc-bouncer-whatsapp' ); ?>');
        $result.html('');

        var enabledEvents = [];
        $('input[name="webhook_events[]"]:checked').each(function() {
            enabledEvents.push($(this).val());
        });

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wc_bouncer_connect',
                nonce: '<?php echo wp_create_nonce( 'wc_bouncer_connect' ); ?>',
                enabled_events: enabledEvents
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<div class="bouncer-alert bouncer-alert-success"><span class="dashicons dashicons-yes-alt"></span><div class="bouncer-alert-content">' + response.data.message + '</div></div>');
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    $result.html('<div class="bouncer-alert bouncer-alert-error"><span class="dashicons dashicons-dismiss"></span><div class="bouncer-alert-content">' + response.data.message + '</div></div>');
                }
            },
            error: function(xhr, status) {
                $result.html('<div class="bouncer-alert bouncer-alert-error"><span class="dashicons dashicons-dismiss"></span><div class="bouncer-alert-content">Connection failed: ' + status + '</div></div>');
            },
            complete: function() {
                $btn.prop('disabled', false).html(origHtml);
            }
        });
    });

    // Disconnect
    $('#bouncer-disconnect').on('click', function() {
        if (!confirm('<?php echo esc_js( __( 'Disconnect from Bouncer? This will remove all webhooks.', 'wc-bouncer-whatsapp' ) ); ?>')) return;
        var $btn = $(this);
        var $result = $('#bouncer-connect-result');
        $btn.prop('disabled', true);
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wc_bouncer_disconnect',
                nonce: '<?php echo wp_create_nonce( 'wc_bouncer_disconnect' ); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<div class="bouncer-alert bouncer-alert-success"><span class="dashicons dashicons-yes-alt"></span><div class="bouncer-alert-content">' + response.data.message + '</div></div>');
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    $result.html('<div class="bouncer-alert bouncer-alert-error"><span class="dashicons dashicons-dismiss"></span><div class="bouncer-alert-content">' + response.data.message + '</div></div>');
                }
            },
            error: function() {
                $result.html('<div class="bouncer-alert bouncer-alert-error"><span class="dashicons dashicons-dismiss"></span><div class="bouncer-alert-content"><?php esc_html_e( 'Disconnect failed.', 'wc-bouncer-whatsapp' ); ?></div></div>');
            },
            complete: function() { $btn.prop('disabled', false); }
        });
    });

    // Tab switching
    $('.bouncer-tab').on('click', function(e) {
        e.preventDefault();
        var tab = $(this).data('tab');

        $('.bouncer-tab').removeClass('active');
        $(this).addClass('active');

        $('.bouncer-tab-content').removeClass('active');
        $('#' + tab).addClass('active');

        history.replaceState(null, null, '#' + tab);

        // Update test form when switching to test tab
        if (tab === 'test') {
            updateTestForm();
        }
    });

    // Handle hash on page load
    if (window.location.hash) {
        var hash = window.location.hash.substring(1);
        if ($('#' + hash).length) {
            $('.bouncer-tab').removeClass('active');
            $('.bouncer-tab[data-tab="' + hash + '"]').addClass('active');
            $('.bouncer-tab-content').removeClass('active');
            $('#' + hash).addClass('active');
        }
    }

    // Variables
    var fetchBtn = $('#fetch_instances_btn');
    var apiKeyInput = $('#api_key');
    var instanceSelect = $('#instance_id');
    var errorP = $('#instance_error');
    var instanceInfo = $('#instance_info');
    var currentInstance = <?php echo wp_json_encode( $settings['instance_id'] ?? '' ); ?>;
    var currentInstanceType = <?php echo wp_json_encode( $settings['instance_type'] ?? '' ); ?>;
    var instancesData = [];
    var templatesData = [];

    // Connection tab elements
    var templatesCard = $('#templates_card');
    var templatesLoading = $('#templates_loading');
    var templatesError = $('#templates_error');
    var templatesList = $('#templates_list');
    var instanceTypeInput = $('#instance_type');
    var instanceTypeBadge = $('#instance_type_badge');
    var refreshTemplatesBtn = $('#refresh_templates_btn');

    // Test tab elements
    var testFormBouncer = $('#test_form_bouncer');
    var testFormCloud = $('#test_form_cloud');
    var testNoInstance = $('#test_no_instance');
    var testInstanceTypeBadge = $('#test_instance_type_badge');
    var testTemplateSelect = $('#test_template');
    var testTemplateLoading = $('#test_template_loading');
    var testOrderSelect = $('#test_order');
    var testOrderPhoneInfo = $('#test_order_phone_info');
    var testOrderPhoneDisplay = $('#test_order_phone_display');

    // Show phone number when order is selected
    testOrderSelect.on('change', function() {
        var selectedOption = $(this).find('option:selected');
        var phone = selectedOption.data('phone');
        if (phone) {
            testOrderPhoneDisplay.text(phone);
            testOrderPhoneInfo.show();
        } else {
            testOrderPhoneInfo.hide();
        }
    });

    function showLoading(show) {
        fetchBtn.find('.btn-icon').toggle(!show);
        fetchBtn.find('.btn-text').toggle(!show);
        fetchBtn.find('.bouncer-spinner').toggle(show);
        fetchBtn.prop('disabled', show);
    }

    function getSelectedInstanceType() {
        var selectedId = instanceSelect.val();
        var instance = instancesData.find(function(i) { return i.id === selectedId; });
        if (instance) return instance.type || 'bouncer';

        var selectedOption = instanceSelect.find('option:selected');
        if (selectedOption.data('type')) return selectedOption.data('type');

        return currentInstanceType || 'bouncer';
    }

    function updateTestForm() {
        var selectedId = instanceSelect.val();
        var instanceType = getSelectedInstanceType();

        // Hide all forms first
        testFormBouncer.hide();
        testFormCloud.hide();
        testNoInstance.hide();

        if (!selectedId) {
            testNoInstance.show();
            testInstanceTypeBadge.hide();
            return;
        }

        // Show type badge
        if (instanceType === 'cloud-api') {
            testInstanceTypeBadge.text('Cloud API').removeClass('type-bouncer').addClass('type-cloud').show();
            testFormCloud.show();
            loadTestTemplates();
        } else {
            testInstanceTypeBadge.text('Bouncer').removeClass('type-cloud').addClass('type-bouncer').show();
            testFormBouncer.show();
        }
    }

    function loadTestTemplates() {
        var apiKey = apiKeyInput.val().trim();
        var instanceId = instanceSelect.val();

        if (!apiKey || !instanceId) return;

        // If we already have templates from connection tab, use those
        if (templatesData.length > 0) {
            populateTestTemplateSelect(templatesData);
            return;
        }

        testTemplateLoading.show();
        testTemplateSelect.prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'wc_bouncer_fetch_templates',
                nonce: <?php echo wp_json_encode( wp_create_nonce( 'wc_bouncer_fetch_templates' ) ); ?>,
                api_key: apiKey,
                instance_id: instanceId
            },
            success: function(response) {
                testTemplateLoading.hide();
                testTemplateSelect.prop('disabled', false);

                if (response.success && response.data.templates) {
                    templatesData = response.data.templates;
                    populateTestTemplateSelect(templatesData);
                }
            },
            error: function() {
                testTemplateLoading.hide();
                testTemplateSelect.prop('disabled', false);
            }
        });
    }

    function populateTestTemplateSelect(templates) {
        testTemplateSelect.find('option:not(:first)').remove();

        templates.forEach(function(tpl) {
            if (tpl.status === 'APPROVED') {
                var label = tpl.name;
                if (tpl.language) label += ' (' + tpl.language + ')';
                var option = $('<option></option>')
                    .val(tpl.name)
                    .text(label)
                    .data('language', tpl.language || 'en');
                testTemplateSelect.append(option);
            }
        });
    }

    // Update language hidden field when template is selected
    testTemplateSelect.on('change', function() {
        var selectedOption = $(this).find('option:selected');
        var language = selectedOption.data('language') || 'en';
        $('#test_template_language').val(language);
        updateTestPreview();
    });

    // Update preview when order is selected
    testOrderSelect.on('change', function() {
        updateTestPreview();
    });

    function updateTestPreview() {
        var templateName = testTemplateSelect.val();
        var orderId = testOrderSelect.val();
        var apiKey = apiKeyInput.val().trim();
        var instanceId = instanceSelect.val();

        var previewSection = $('#test_preview_section');
        var previewLoading = $('#test_preview_loading');
        var previewContent = $('#test_preview_content');

        if (!templateName || !orderId) {
            previewSection.hide();
            return;
        }

        // Find template in cached data
        var template = templatesData.find(function(t) { return t.name === templateName; });

        // If we have cached content, use it with order data resolution
        if (template && template.content) {
            previewSection.show();
            previewLoading.show();
            previewContent.html('');

            // Fetch resolved preview from server
            fetchResolvedPreview(templateName, orderId, apiKey, instanceId, template.content);
            return;
        }

        // Need to fetch template content first
        if (!apiKey || !instanceId) {
            previewSection.hide();
            return;
        }

        previewSection.show();
        previewLoading.show();
        previewContent.html('');

        // Fetch template content first, then resolve with order
        var templateId = template ? template.id : templateName;

        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'wc_bouncer_fetch_template',
                nonce: <?php echo wp_json_encode( wp_create_nonce( 'wc_bouncer_fetch_template' ) ); ?>,
                api_key: apiKey,
                instance_id: instanceId,
                template_id: templateId
            },
            success: function(response) {
                if (response.success && response.data.template && response.data.template.content) {
                    // Cache the content
                    if (template) {
                        template.content = response.data.template.content;
                    }
                    fetchResolvedPreview(templateName, orderId, apiKey, instanceId, response.data.template.content);
                } else {
                    previewLoading.hide();
                    previewContent.html('<em><?php echo esc_js( __( 'Could not load template.', 'wc-bouncer-whatsapp' ) ); ?></em>');
                }
            },
            error: function() {
                previewLoading.hide();
                previewContent.html('<em><?php echo esc_js( __( 'Error loading template.', 'wc-bouncer-whatsapp' ) ); ?></em>');
            }
        });
    }

    function fetchResolvedPreview(templateName, orderId, apiKey, instanceId, templateContent) {
        var previewLoading = $('#test_preview_loading');
        var previewContent = $('#test_preview_content');

        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'wc_bouncer_preview_template',
                nonce: <?php echo wp_json_encode( wp_create_nonce( 'wc_bouncer_preview_template' ) ); ?>,
                template_name: templateName,
                template_content: templateContent,
                order_id: orderId
            },
            success: function(response) {
                previewLoading.hide();
                console.log('Preview response:', response);
                if (response.success && response.data.preview) {
                    previewContent.html(response.data.preview.replace(/\n/g, '<br>'));
                } else {
                    // Fallback: show template content with unresolved variables
                    previewContent.html(templateContent.replace(/\n/g, '<br>'));
                }
            },
            error: function() {
                previewLoading.hide();
                // Fallback: show template content with unresolved variables
                previewContent.html(templateContent.replace(/\n/g, '<br>'));
            }
        });
    }

    function updateInstanceInfo() {
        var selectedId = instanceSelect.val();
        var instance = instancesData.find(function(i) { return i.id === selectedId; });
        var selectedOption = instanceSelect.find('option:selected');
        var instanceType = instance ? instance.type : selectedOption.data('type');

        if (instance || (selectedId && selectedOption.length)) {
            $('#instance_name').text(instance ? (instance.name || instance.id) : selectedId);
            $('#instance_phone').text(instance ? (instance.phoneNumber || '<?php echo esc_js( __( 'No phone number', 'wc-bouncer-whatsapp' ) ); ?>') : '');
            instanceInfo.show();

            instanceTypeInput.val(instanceType || 'bouncer');
            if (instanceType === 'cloud-api') {
                instanceTypeBadge.text('Cloud API').removeClass('type-bouncer').addClass('type-cloud');
                templatesCard.show();
                fetchTemplates(selectedId);
                // Update tab label for Cloud API
                $('#templates_tab').find('.dashicons').removeClass('dashicons-randomize').addClass('dashicons-media-text');
                $('#templates_tab').contents().filter(function() { return this.nodeType === 3; }).last().replaceWith('<?php echo esc_js( __( 'Templates', 'wc-bouncer-whatsapp' ) ); ?>');
            } else {
                instanceTypeBadge.text('Bouncer').removeClass('type-cloud').addClass('type-bouncer');
                templatesCard.hide();
                templatesData = [];
                // Update tab label for Bouncer
                $('#templates_tab').find('.dashicons').removeClass('dashicons-media-text').addClass('dashicons-randomize');
                $('#templates_tab').contents().filter(function() { return this.nodeType === 3; }).last().replaceWith('<?php echo esc_js( __( 'Automation', 'wc-bouncer-whatsapp' ) ); ?>');
            }

            // Update test form if currently on test tab
            if ($('#test').hasClass('active')) {
                updateTestForm();
            }
        } else {
            instanceInfo.hide();
            templatesCard.hide();
            instanceTypeInput.val('');
            templatesData = [];
        }
    }

    // Populate status-to-template dropdowns
    function populateStatusTemplateSelects(templates) {
        var approvedTemplates = templates.filter(function(t) { return t.status === 'APPROVED'; });

        $('.status-template-select').each(function() {
            var $select = $(this);
            var currentValue = $select.val();

            $select.find('option:not(:first)').remove();

            approvedTemplates.forEach(function(tpl) {
                var label = tpl.name;
                if (tpl.language) label += ' (' + tpl.language + ')';
                var option = $('<option></option>')
                    .val(tpl.name)
                    .data('language', tpl.language || 'en')
                    .text(label);
                if (tpl.name === currentValue) {
                    option.prop('selected', true);
                }
                $select.append(option);
            });
        });

        if (approvedTemplates.length === 0) {
            $('#no_templates_warning').show();
        } else {
            $('#no_templates_warning').hide();
        }

        // Update template languages hidden inputs
        updateTemplateLanguages();
    }

    // Track template languages for Cloud API
    function updateTemplateLanguages() {
        var $container = $('#template_languages_container');
        $container.empty();

        $('.status-template-select').each(function() {
            var $select = $(this);
            var templateName = $select.val();
            if (templateName) {
                var language = $select.find('option:selected').data('language') || 'en';
                var $input = $('<input type="hidden">')
                    .attr('name', 'cloud_template_config[template_languages][' + templateName + ']')
                    .val(language);
                $container.append($input);
            }
        });
    }

    // Update languages when template selection changes
    $(document).on('change', '.status-template-select', function() {
        updateTemplateLanguages();
    });

    // Available placeholders for variable mapping
    var availablePlaceholders = [
        { value: '', label: '<?php echo esc_js( __( '— Select placeholder —', 'wc-bouncer-whatsapp' ) ); ?>' },
        { value: '{first_name}', label: '{first_name}', group: '<?php echo esc_js( __( 'Customer', 'wc-bouncer-whatsapp' ) ); ?>' },
        { value: '{last_name}', label: '{last_name}', group: '<?php echo esc_js( __( 'Customer', 'wc-bouncer-whatsapp' ) ); ?>' },
        { value: '{name}', label: '{name} (<?php echo esc_js( __( 'Full name', 'wc-bouncer-whatsapp' ) ); ?>)', group: '<?php echo esc_js( __( 'Customer', 'wc-bouncer-whatsapp' ) ); ?>' },
        { value: '{email}', label: '{email}', group: '<?php echo esc_js( __( 'Customer', 'wc-bouncer-whatsapp' ) ); ?>' },
        { value: '{phone}', label: '{phone}', group: '<?php echo esc_js( __( 'Customer', 'wc-bouncer-whatsapp' ) ); ?>' },
        { value: '{order_number}', label: '{order_number}', group: '<?php echo esc_js( __( 'Order', 'wc-bouncer-whatsapp' ) ); ?>' },
        { value: '{order_total}', label: '{order_total}', group: '<?php echo esc_js( __( 'Order', 'wc-bouncer-whatsapp' ) ); ?>' },
        { value: '{order_status}', label: '{order_status}', group: '<?php echo esc_js( __( 'Order', 'wc-bouncer-whatsapp' ) ); ?>' },
        { value: '{order_date}', label: '{order_date}', group: '<?php echo esc_js( __( 'Order', 'wc-bouncer-whatsapp' ) ); ?>' },
        { value: '{order_items}', label: '{order_items}', group: '<?php echo esc_js( __( 'Order', 'wc-bouncer-whatsapp' ) ); ?>' },
        { value: '{payment_method}', label: '{payment_method}', group: '<?php echo esc_js( __( 'Order', 'wc-bouncer-whatsapp' ) ); ?>' },
        { value: '{billing_address}', label: '{billing_address}', group: '<?php echo esc_js( __( 'Address', 'wc-bouncer-whatsapp' ) ); ?>' },
        { value: '{shipping_address}', label: '{shipping_address}', group: '<?php echo esc_js( __( 'Address', 'wc-bouncer-whatsapp' ) ); ?>' }
        <?php if ( ! empty( $discovered_meta_keys ) ) : ?>
            <?php foreach ( $discovered_meta_keys as $meta_key ) : ?>,
        { value: '{meta:<?php echo esc_js( $meta_key ); ?>}', label: '{meta:<?php echo esc_js( $meta_key ); ?>}', group: '<?php echo esc_js( __( 'Order Meta', 'wc-bouncer-whatsapp' ) ); ?>' }
            <?php endforeach; ?>
        <?php endif; ?>
    ];

    function buildPlaceholderSelect(name, selectedValue) {
        var html = '<select name="' + name + '" class="bouncer-form-select placeholder-select">';

        var currentGroup = '';
        availablePlaceholders.forEach(function(p) {
            if (p.group && p.group !== currentGroup) {
                if (currentGroup) html += '</optgroup>';
                html += '<optgroup label="' + escapeHtml(p.group) + '">';
                currentGroup = p.group;
            } else if (!p.group && currentGroup) {
                html += '</optgroup>';
                currentGroup = '';
            }

            var selected = (p.value === selectedValue) ? ' selected' : '';
            html += '<option value="' + escapeHtml(p.value) + '"' + selected + '>' + escapeHtml(p.label) + '</option>';
        });

        if (currentGroup) html += '</optgroup>';
        html += '</select>';

        return html;
    }

    // Add template mapping block
    function addTemplateMappingBlock(templateName) {
        var html = '<div class="bouncer-template-mapping-block" data-template="' + escapeHtml(templateName) + '">';
        html += '<div class="bouncer-mapping-header">';
        html += '<button type="button" class="bouncer-collapse-toggle" aria-expanded="true"><span class="dashicons dashicons-arrow-down-alt2"></span></button>';
        html += '<span class="bouncer-mapping-title">' + escapeHtml(templateName) + '</span>';
        html += '<span class="bouncer-mapping-summary"></span>';
        // Find template ID from cached data
        var tpl = templatesData.find(function(t) { return t.name === templateName; });
        var templateId = tpl ? tpl.id : '';

        html += '<div class="bouncer-mapping-actions">';
        html += '<button type="button" class="bouncer-btn bouncer-btn-secondary bouncer-btn-sm preview-mapping-btn" data-template="' + escapeHtml(templateName) + '" data-template-id="' + escapeHtml(templateId) + '"><span class="dashicons dashicons-visibility"></span> <?php echo esc_js( __( 'Preview', 'wc-bouncer-whatsapp' ) ); ?></button>';
        html += '<button type="button" class="bouncer-btn bouncer-btn-ghost bouncer-btn-sm remove-template-mapping"><span class="dashicons dashicons-trash"></span></button>';
        html += '</div>';
        html += '</div>';
        html += '<div class="bouncer-mapping-content">';

        for (var i = 1; i <= 10; i++) {
            html += '<div class="bouncer-variable-row">';
            html += '<span class="bouncer-variable-label">{{' + i + '}}</span>';
            html += buildPlaceholderSelect('cloud_template_config[template_variables][' + escapeHtml(templateName) + '][' + i + ']', '');
            html += '</div>';
        }

        html += '</div></div>';

        $('#no_mappings_placeholder').hide();
        $('#template_variable_mappings').append(html);
    }

    // Render available templates list
    function renderAvailableTemplates() {
        var approvedTemplates = templatesData.filter(function(t) { return t.status === 'APPROVED'; });

        // Find templates not yet mapped
        var existingMappings = [];
        $('.bouncer-template-mapping-block').each(function() {
            existingMappings.push($(this).data('template'));
        });

        var availableTemplates = approvedTemplates.filter(function(t) {
            return existingMappings.indexOf(t.name) === -1;
        });

        var $list = $('#available_templates_list');
        var $items = $('#available_templates_items');
        var $noTemplates = $('#no_templates_to_map');

        $items.empty();

        if (approvedTemplates.length === 0) {
            $list.hide();
            $noTemplates.hide();
            return;
        }

        if (availableTemplates.length === 0) {
            $list.hide();
            $noTemplates.show();
            return;
        }

        $noTemplates.hide();

        availableTemplates.forEach(function(tpl) {
            var label = tpl.name;
            if (tpl.language) label += ' (' + tpl.language + ')';

            var $item = $('<div class="bouncer-available-template-item"></div>');
            $item.append('<span class="bouncer-template-name">' + escapeHtml(label) + '</span>');
            $item.append('<button type="button" class="bouncer-btn bouncer-btn-secondary bouncer-btn-sm add-template-map-btn" data-template="' + escapeHtml(tpl.name) + '"><span class="dashicons dashicons-plus-alt2"></span> <?php echo esc_js( __( 'Map', 'wc-bouncer-whatsapp' ) ); ?></button>');
            $items.append($item);
        });

        $list.show();
    }

    // Add mapping when clicking on template
    $(document).on('click', '.add-template-map-btn', function() {
        var templateName = $(this).data('template');
        addTemplateMappingBlock(templateName);
        renderAvailableTemplates();
    });

    // Remove template mapping
    $(document).on('click', '.remove-template-mapping', function(e) {
        e.stopPropagation();
        $(this).closest('.bouncer-template-mapping-block').remove();
        if ($('.bouncer-template-mapping-block').length === 0) {
            $('#no_mappings_placeholder').show();
        }
        renderAvailableTemplates();
    });

    // Preview button - show template preview with mapped values
    $(document).on('click', '.preview-mapping-btn', function(e) {
        e.stopPropagation();
        var $btn = $(this);
        var templateName = $btn.data('template');
        var templateId = $btn.data('template-id');
        var $block = $btn.closest('.bouncer-template-mapping-block');

        // Get mapped values
        var mappings = {};
        $block.find('.placeholder-select').each(function(index) {
            var val = $(this).val();
            if (val) {
                mappings[index + 1] = val;
            }
        });

        // Check if we have cached content
        var template = templatesData.find(function(t) { return t.name === templateName; });
        if (template && template.content) {
            showPreviewModal(templateName, template.content, mappings);
            return;
        }

        // Get template ID from cached data if not on button
        if (!templateId && template) {
            templateId = template.id;
        }

        if (!templateId) {
            alert('<?php echo esc_js( __( 'Template ID not found. Please refresh templates.', 'wc-bouncer-whatsapp' ) ); ?>');
            return;
        }

        // Fetch template content via AJAX
        var apiKey = apiKeyInput.val().trim();
        var instanceId = instanceSelect.val();

        if (!apiKey || !instanceId) {
            alert('<?php echo esc_js( __( 'API key and instance are required.', 'wc-bouncer-whatsapp' ) ); ?>');
            return;
        }

        $btn.prop('disabled', true).find('.dashicons').removeClass('dashicons-visibility').addClass('dashicons-update bouncer-spin');

        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'wc_bouncer_fetch_template',
                nonce: <?php echo wp_json_encode( wp_create_nonce( 'wc_bouncer_fetch_template' ) ); ?>,
                api_key: apiKey,
                instance_id: instanceId,
                template_id: templateId
            },
            success: function(response) {
                $btn.prop('disabled', false).find('.dashicons').removeClass('dashicons-update bouncer-spin').addClass('dashicons-visibility');

                if (response.success && response.data.template) {
                    var content = response.data.template.content || '<?php echo esc_js( __( 'No template content available', 'wc-bouncer-whatsapp' ) ); ?>';

                    // Cache the content
                    if (template) {
                        template.content = content;
                    }

                    showPreviewModal(templateName, content, mappings);
                } else {
                    var errorMsg = response.data && response.data.message ? response.data.message : '<?php echo esc_js( __( 'Failed to fetch template.', 'wc-bouncer-whatsapp' ) ); ?>';
                    if (response.data && response.data.code) {
                        errorMsg += ' (Code: ' + response.data.code + ')';
                    }
                    console.error('Template fetch error:', response.data);
                    alert(errorMsg);
                }
            },
            error: function() {
                $btn.prop('disabled', false).find('.dashicons').removeClass('dashicons-update bouncer-spin').addClass('dashicons-visibility');
                alert('<?php echo esc_js( __( 'Network error.', 'wc-bouncer-whatsapp' ) ); ?>');
            }
        });
    });

    function showPreviewModal(templateName, content, mappings) {
        var previewContent = content;

        // Replace {{1}}, {{2}}, etc. with mapped placeholders
        for (var i = 1; i <= 10; i++) {
            var placeholder = '{{' + i + '}}';
            var mappedValue = mappings[i] || placeholder;
            previewContent = previewContent.split(placeholder).join('<span class="preview-placeholder">' + escapeHtml(mappedValue) + '</span>');
        }

        // Show preview modal
        var modalHtml = '<div class="bouncer-preview-modal-overlay">';
        modalHtml += '<div class="bouncer-preview-modal">';
        modalHtml += '<div class="bouncer-preview-modal-header">';
        modalHtml += '<h3><span class="dashicons dashicons-whatsapp"></span> ' + escapeHtml(templateName) + '</h3>';
        modalHtml += '<button type="button" class="bouncer-preview-modal-close"><span class="dashicons dashicons-no-alt"></span></button>';
        modalHtml += '</div>';
        modalHtml += '<div class="bouncer-preview-modal-body">';
        modalHtml += '<div class="bouncer-whatsapp-preview">';
        modalHtml += '<div class="bouncer-whatsapp-bubble">' + previewContent.replace(/\n/g, '<br>') + '</div>';
        modalHtml += '</div>';
        modalHtml += '</div>';
        modalHtml += '<div class="bouncer-preview-modal-footer">';
        modalHtml += '<p class="bouncer-form-description"><?php echo esc_js( __( 'Highlighted values show your placeholder mappings. Actual values will be filled from order data.', 'wc-bouncer-whatsapp' ) ); ?></p>';
        modalHtml += '</div>';
        modalHtml += '</div>';
        modalHtml += '</div>';

        $('body').append(modalHtml);
    }

    // Close preview modal
    $(document).on('click', '.bouncer-preview-modal-close, .bouncer-preview-modal-overlay', function(e) {
        if (e.target === this || $(e.target).closest('.bouncer-preview-modal-close').length) {
            $('.bouncer-preview-modal-overlay').remove();
        }
    });

    // Collapse/expand template mapping - click on header (excluding action buttons)
    $(document).on('click', '.bouncer-mapping-header', function(e) {
        // Don't toggle if clicking action buttons
        if ($(e.target).closest('.bouncer-mapping-actions').length) {
            return;
        }

        var $block = $(this).closest('.bouncer-template-mapping-block');
        var $content = $block.find('.bouncer-mapping-content');
        var $summary = $block.find('.bouncer-mapping-summary');
        var $btn = $block.find('.bouncer-collapse-toggle');
        var isExpanded = $btn.attr('aria-expanded') === 'true';

        if (isExpanded) {
            // Collapse - generate summary
            var mappings = [];
            $content.find('.placeholder-select').each(function() {
                var val = $(this).val();
                if (val) mappings.push(val);
            });
            var summaryText = mappings.length > 0 ? mappings.slice(0, 3).join(', ') : '<?php echo esc_js( __( 'No mappings', 'wc-bouncer-whatsapp' ) ); ?>';
            if (mappings.length > 3) summaryText += ' +' + (mappings.length - 3);
            $summary.text(summaryText);

            $content.slideUp(200);
            $btn.attr('aria-expanded', 'false');
            $btn.find('.dashicons').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-right-alt2');
            $block.addClass('collapsed');
        } else {
            // Expand
            $summary.text('');
            $content.slideDown(200);
            $btn.attr('aria-expanded', 'true');
            $btn.find('.dashicons').removeClass('dashicons-arrow-right-alt2').addClass('dashicons-arrow-down-alt2');
            $block.removeClass('collapsed');
        }
    });

    function fetchTemplates(instanceId) {
        var apiKey = apiKeyInput.val().trim();
        if (!apiKey || !instanceId) return;

        templatesLoading.show();
        templatesError.hide();
        templatesList.empty();

        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'wc_bouncer_fetch_templates',
                nonce: <?php echo wp_json_encode( wp_create_nonce( 'wc_bouncer_fetch_templates' ) ); ?>,
                api_key: apiKey,
                instance_id: instanceId
            },
            success: function(response) {
                templatesLoading.hide();

                if (response.success && response.data.templates) {
                    templatesData = response.data.templates;
                    renderTemplates(templatesData);
                    populateStatusTemplateSelects(templatesData);
                    populateTestTemplateSelect(templatesData);
                    renderAvailableTemplates();
                } else {
                    var msg = response.data && response.data.message ? response.data.message : '<?php echo esc_js( __( 'Failed to fetch templates.', 'wc-bouncer-whatsapp' ) ); ?>';
                    templatesError.find('.bouncer-alert-content').text(msg);
                    templatesError.show();
                }
            },
            error: function() {
                templatesLoading.hide();
                templatesError.find('.bouncer-alert-content').text('<?php echo esc_js( __( 'Network error.', 'wc-bouncer-whatsapp' ) ); ?>');
                templatesError.show();
            }
        });
    }

    function renderTemplates(templates) {
        if (!templates.length) {
            templatesList.html('<div class="bouncer-empty-state"><span class="dashicons dashicons-media-text"></span><p><?php echo esc_js( __( 'No templates found.', 'wc-bouncer-whatsapp' ) ); ?></p></div>');
            return;
        }

        var html = '<table class="bouncer-table"><thead><tr>';
        html += '<th><?php echo esc_js( __( 'Name', 'wc-bouncer-whatsapp' ) ); ?></th>';
        html += '<th><?php echo esc_js( __( 'Category', 'wc-bouncer-whatsapp' ) ); ?></th>';
        html += '<th><?php echo esc_js( __( 'Language', 'wc-bouncer-whatsapp' ) ); ?></th>';
        html += '<th><?php echo esc_js( __( 'Status', 'wc-bouncer-whatsapp' ) ); ?></th>';
        html += '</tr></thead><tbody>';

        templates.forEach(function(tpl) {
            var statusClass = tpl.status === 'APPROVED' ? 'status-approved' : (tpl.status === 'PENDING' ? 'status-pending' : 'status-rejected');
            html += '<tr>';
            html += '<td><code>' + escapeHtml(tpl.name) + '</code></td>';
            html += '<td>' + escapeHtml(tpl.category || '-') + '</td>';
            html += '<td>' + escapeHtml(tpl.language || '-') + '</td>';
            html += '<td><span class="bouncer-template-status ' + statusClass + '">' + escapeHtml(tpl.status || 'unknown') + '</span></td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        templatesList.html(html);
    }

    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    refreshTemplatesBtn.on('click', function() {
        var selectedId = instanceSelect.val();
        if (selectedId) {
            fetchTemplates(selectedId);
        }
    });

    instanceSelect.on('change', updateInstanceInfo);

    fetchBtn.on('click', function() {
        var apiKey = apiKeyInput.val().trim();

        if (!apiKey) {
            errorP.text(<?php echo wp_json_encode( __( 'Enter an API key first.', 'wc-bouncer-whatsapp' ) ); ?>).show();
            return;
        }

        errorP.hide();
        showLoading(true);

        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'wc_bouncer_fetch_instances',
                nonce: <?php echo wp_json_encode( wp_create_nonce( 'wc_bouncer_fetch_instances' ) ); ?>,
                api_key: apiKey
            },
            success: function(response) {
                showLoading(false);

                if (response.success && response.data.instances) {
                    instancesData = response.data.instances;
                    instanceSelect.find('option:not(:first)').remove();

                    response.data.instances.forEach(function(inst) {
                        var label = inst.name || inst.id;
                        if (inst.phoneNumber) {
                            label += ' - ' + inst.phoneNumber;
                        }
                        var option = $('<option></option>')
                            .val(inst.id)
                            .text(label)
                            .data('type', inst.type || 'bouncer');

                        if (inst.id === currentInstance) {
                            option.prop('selected', true);
                        }

                        instanceSelect.append(option);
                    });

                    setTimeout(function() {
                        updateInstanceInfo();
                        // Also update test form on initial load
                        updateTestForm();
                    }, 50);

                    if (response.data.instances.length === 0) {
                        errorP.text(<?php echo wp_json_encode( __( 'No instances found.', 'wc-bouncer-whatsapp' ) ); ?>).show();
                    }
                } else {
                    var msg = response.data && response.data.message ? response.data.message : <?php echo wp_json_encode( __( 'Failed to fetch instances.', 'wc-bouncer-whatsapp' ) ); ?>;
                    errorP.text(msg).show();
                }
            },
            error: function() {
                showLoading(false);
                errorP.text(<?php echo wp_json_encode( __( 'Network error.', 'wc-bouncer-whatsapp' ) ); ?>).show();
            }
        });
    });

    // Initialize test form based on saved instance type
    function initTestForm() {
        if (currentInstance && currentInstanceType) {
            if (currentInstanceType === 'cloud-api') {
                testInstanceTypeBadge.text('Cloud API').removeClass('type-bouncer').addClass('type-cloud').show();
                testFormCloud.show();
                testFormBouncer.hide();
            } else {
                testInstanceTypeBadge.text('Bouncer').removeClass('type-cloud').addClass('type-bouncer').show();
                testFormBouncer.show();
                testFormCloud.hide();
            }
        } else if (!currentInstance) {
            testFormBouncer.hide();
            testNoInstance.show();
        }
    }

    // Auto-collapse template mappings that have values
    function autoCollapseConfiguredMappings() {
        $('.bouncer-template-mapping-block').each(function() {
            var $block = $(this);
            var $content = $block.find('.bouncer-mapping-content');
            var $summary = $block.find('.bouncer-mapping-summary');
            var $btn = $block.find('.bouncer-collapse-toggle');

            // Check if any placeholders are selected
            var mappings = [];
            $content.find('.placeholder-select').each(function() {
                var val = $(this).val();
                if (val) mappings.push(val);
            });

            // If has mappings, auto-collapse
            if (mappings.length > 0) {
                var summaryText = mappings.slice(0, 3).join(', ');
                if (mappings.length > 3) summaryText += ' +' + (mappings.length - 3);
                $summary.text(summaryText);

                $content.hide();
                $btn.attr('aria-expanded', 'false');
                $btn.find('.dashicons').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-right-alt2');
                $block.addClass('collapsed');
            }
        });
    }

    // Run auto-collapse on page load
    autoCollapseConfiguredMappings();

    // Auto-fetch if API key exists
    if (apiKeyInput.val().trim()) {
        fetchBtn.trigger('click');
    } else {
        initTestForm();
    }

    // Status trigger checkbox toggle (Bouncer automation)
    $('.status-trigger-checkbox').on('change', function() {
        var $item = $(this).closest('.bouncer-status-trigger-item');
        var $body = $item.find('.bouncer-status-trigger-body');

        if ($(this).is(':checked')) {
            $item.addClass('is-enabled');
            $body.slideDown(200);
        } else {
            $item.removeClass('is-enabled');
            $body.slideUp(200);
        }
    });
})(jQuery);
</script>
