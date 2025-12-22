# Repository Guidelines

## Project Structure & Module Organization

```
wp-bouncer-saas/
├── bouncer-whatsapp.php      # Plugin bootstrap, constants, autoloader
├── includes/
│   ├── Plugin.php            # Main orchestrator, hooks registration
│   ├── Admin/
│   │   └── GeneralSettingsPage.php  # Admin UI, AJAX handlers, form processing
│   ├── Service/
│   │   ├── ApiClient.php          # Bouncer API communication
│   │   ├── MessageSender.php      # Order status trigger handler
│   │   ├── PlaceholderResolver.php # Template variable resolution
│   │   ├── MetaKeyDiscovery.php   # Auto-discover order meta keys
│   │   └── Logger.php             # Message logging
│   ├── Repository/
│   │   └── LogRepository.php      # Database persistence for logs
│   ├── Settings/
│   │   └── Settings.php           # WordPress options wrapper
│   └── Infrastructure/
│       └── Activator.php          # Plugin activation hooks
├── views/
│   └── general-settings-page.php  # Admin template with tabs UI
└── assets/
    └── css/
        └── admin.css              # Modern shadcn/ui inspired styling
```

## Instance Types

The plugin supports two Bouncer instance types:

### Bouncer Instance (Standard)
- Sends free-form text messages
- Uses `ApiClient::send_text()` method
- Endpoint: `POST /message/sendText`

### Cloud API Instance
- Sends pre-approved WhatsApp templates only
- Uses `ApiClient::send_cloud_template()` method
- Endpoint: `POST /api/v1/cloud/sendTemplate`
- Payload structure:
  ```json
  {
    "phoneNumber": "+60123456789",
    "templateName": "order_notification",
    "language": "en",
    "instanceId": "instance-uuid",
    "variables": { "1": "John", "2": "12345" }
  }
  ```

## Bouncer API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/instances/` | List all instances |
| GET | `/cloud/templates?instanceId=` | List Cloud API templates |
| GET | `/cloud/templates/{templateId}?instanceId=` | Get single template with content |
| POST | `/message/sendText` | Send text message (Bouncer) |
| POST | `/api/v1/cloud/sendTemplate` | Send template message (Cloud API) |

## Settings Storage

Settings stored in `wp_options` table under `wc_bouncer_whatsapp_settings`:

```php
[
    'api_key'       => 'bnc_live_sk_...',
    'instance_id'   => 'uuid',
    'instance_type' => 'cloud-api', // or 'bouncer'
    'message_template' => 'Hello {first_name}...', // For Bouncer type
    'cloud_template_config' => [
        'status_template_map' => [
            'processing' => 'order_confirmation',
            'completed'  => 'order_shipped',
        ],
        'template_variables' => [
            'order_confirmation' => [
                '1' => '{first_name}',
                '2' => '{order_number}',
                '3' => '{order_total}',
            ],
        ],
    ],
]
```

## PlaceholderResolver

Resolves template variables to order data. Supported placeholders:

**Customer:**
- `{first_name}`, `{last_name}`, `{name}` (full name)
- `{email}`, `{phone}`

**Order:**
- `{order_id}`, `{order_number}`
- `{status}` (key), `{order_status}` (label)
- `{order_date}` (formatted)
- `{amount}`, `{order_total}` (formatted price)
- `{currency}`
- `{order_items}` (formatted list: "2x Product Name")
- `{payment_method}`, `{shipping_method}`

**Address:**
- `{billing_address}`, `{shipping_address}` (formatted)

**Custom Meta:**
- `{meta:_custom_field}` - any order meta key

## Admin UI Tabs

1. **Connection** - API key, instance selector (dropdown), instance type badge
2. **Templates** (Cloud API only) - Status-to-template mapping, variable configuration with preview
3. **Test** - Send test messages with live preview
4. **Tools** - Health check, logs

## AJAX Endpoints

| Action | Nonce | Description |
|--------|-------|-------------|
| `wc_bouncer_fetch_instances` | `wc_bouncer_fetch_instances` | Get instances list |
| `wc_bouncer_fetch_templates` | `wc_bouncer_fetch_templates` | Get Cloud API templates |
| `wc_bouncer_fetch_template` | `wc_bouncer_fetch_template` | Get single template content |
| `wc_bouncer_preview_template` | `wc_bouncer_preview_template` | Resolve template with order data |

## Build, Test, and Development Commands

This repository targets a WordPress/WooCommerce environment. Spin up a local site (e.g., with `wp-env` or a Docker-based stack), drop the plugin folder into `wp-content/plugins/`, then run `wp plugin activate bouncer-whatsapp`. Run `php -l $(find . -name '*.php')` to lint all PHP files before committing. When WooCommerce fixtures are needed, use `wp wc` CLI commands to create orders for preview/testing flows.

## Coding Style & Naming Conventions

Follow WordPress PHP coding standards: 4-space indentation, snake_case for option keys/table names, PascalCase for classes in the `Bouncer\WooCommerce\WhatsApp` namespace. Keep translatable strings wrapped in `__()`/`esc_html__()` with the `wc-bouncer-whatsapp` text domain. Admin templates should echo escaped values and prefer helper functions (e.g., `esc_html`, `wp_nonce_field`).

## Testing Guidelines

At minimum, lint PHP (`php -l`) and exercise key flows manually: saving settings, sending test messages, order-status triggers, and log retention. If adding automated tests, prefer PHPUnit with WordPress integration (place suites under `tests/` and mirror the namespace). Name test classes with the `*Test.php` suffix and document required fixtures in the test README.

## Commit & Pull Request Guidelines

Use imperative commit messages (`Add cron retention purge`, `Fix placeholder resolver`). Each PR should describe scope, testing performed, and any configuration changes. Link relevant issues and attach screenshots/GIFs for admin UI tweaks. Ensure migrations/installers remain idempotent and highlight any backward-compatibility risks in the PR description.

## Security & Configuration Tips

Never hard-code API credentials; rely on WordPress options secured by `manage_woocommerce`. Validate and sanitize all admin inputs, and escape output in views. When testing against live Bouncer endpoints, use sandbox keys (`bnc_test_sk_*`) to avoid unexpected production messages.
