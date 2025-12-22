# WooCommerce Bouncer WhatsApp Plugin

Automate WhatsApp notifications for WooCommerce order status changes using the Bouncer SaaS API. The plugin sends templated messages, keeps an audit log, and provides built-in testing and health tooling to minimise support overhead.

## Features

- Send WhatsApp messages automatically when selected WooCommerce order statuses are reached.
- Templating engine with placeholders for order, customer, and custom meta data (`{meta:KEY}`).
- Secure settings page for API credentials, instance configuration, and log retention.
- Test & Health console to send manual messages, verify API connectivity, and preview a template with recent orders.
- Message logging with dedicated admin screen, manual clearing, and automatic retention cleanup.
- Meta key discovery utility to surface placeholders from recent orders.

## Requirements

- WordPress 6.0+
- WooCommerce 5.0+
- PHP 7.4+
- Active Bouncer SaaS account with API credentials.

## Installation

1. Copy the plugin folder into `wp-content/plugins/bouncer-whatsapp` (or package as a zip and upload via the WordPress admin).
2. Activate **WooCommerce Bouncer WhatsApp** from the Plugins screen.
3. On first activation the plugin will create the `bouncer_logs` database table and set up a daily cron to purge expired log entries.

## Configuration

1. Navigate to `Bouncer WhatsApp → Settings` from the WordPress admin menu.
2. Provide the **API Key**, **Instance ID**, and set **Log Retention**; use the same page to run connectivity tests or send previews.
3. Switch to `Bouncer WhatsApp → Automation` to configure the **Default Message Template** and enable statuses that should trigger WhatsApp notifications. Provide per-status overrides where needed.
4. Use **Refresh Meta Keys** to scan recent orders and reveal `{meta:KEY}` placeholders.
5. Save changes on each screen as you adjust settings.

### Placeholder Reference

| Placeholder | Description |
| ----------- | ----------- |
| `{order_id}` | Internal order ID |
| `{order_number}` | Customer-facing order number |
| `{status}` | Order status slug (e.g. `completed`) |
| `{amount}` | Order total with currency |
| `{currency}` | Currency code |
| `{payment_method}` | Payment method title |
| `{shipping_method}` | Shipping method(s) |
| `{name}` | Billing full name |
| `{first_name}` | Billing first name |
| `{last_name}` | Billing last name |
| `{email}` | Billing email |
| `{phone}` | Billing phone number |
| `{meta:KEY}` | Order meta value for `KEY` (use discovery tool to surface keys) |

## Test & Health Toolkit

- Accessible via `Bouncer WhatsApp → Settings`.
- **Send Test Message** – Send an arbitrary WhatsApp message to any number for quick validation.
- **Health Check** – Call the Bouncer instance status endpoint to verify connectivity.
- **Message Preview** – Pick a recent order to preview the rendered template, inspect order meta, and optionally send the preview to the customer’s billing phone.

## Logs & Retention

- View the last 100 log entries under `Bouncer WhatsApp → Logs`.
- Each log includes order, phone, status (sent/failed), HTTP code, message, and response data.
- Clear logs manually with the provided action, or adjust the retention window in settings (cron purges older rows daily).

## Development Notes

- DB table name: `<wp_prefix>bouncer_logs`.
- Cron hook: `wc_bouncer_purge_logs` (runs daily).
- HTTP client uses WordPress HTTP API with `X-API-Key` header and 15s timeout.
- Update hooks run on activation/deactivation via `Bouncer\WooCommerce\WhatsApp\Infrastructure\Installer`.

## Roadmap Considerations

- Conditional placeholders and retry queue.
- Enhanced reporting/analytics views.
