# Abandoned Order Trigger — Implementation Brief

> Status: **Spec / not yet implemented.** Hand this to the SWE picking up the work.

## Context

Bouncer SaaS (server) already exposes three abandoned-order triggers in its Workflow Builder UI:

- `order.abandoned.pending_payment`
- `order.abandoned.on_hold`
- `order.abandoned.failed`

These appear in the WooCommerce trigger dropdown
(`apps/web/src/components/workflow/config-panels/triggers/trigger-woocommerce-config.tsx:25-27`
in the `bouncer-saas-waha` repo).

**But nothing ever fires them.** WooCommerce has no native "abandoned" webhook. This
plugin currently only reacts to `woocommerce_order_status_*` hooks and pushes WhatsApp
messages directly via Bouncer's message API — it does **not** send webhooks to Bouncer's
webhook endpoint.

The Bouncer server-side webhook processor
(`apps/server/src/services/woocommerce/webhook-processor.ts`) is already 95% ready to
receive these abandoned events. It accepts any event whose normalized name
(`order.<event>`) is in the integration's `enabledEvents` allowlist. The missing piece is:
**this plugin needs to detect stale orders and POST a synthetic webhook to Bouncer.**

---

## What this plugin needs to do

### 1. New settings page: `Bouncer WhatsApp → Abandoned Orders`

| Field | Type | Notes |
|---|---|---|
| Enable abandoned tracking | toggle | Master switch |
| Bouncer Webhook URL | text | e.g. `https://api.bouncer.my/api/v1/webhook/woocommerce/{integrationId}` (path is `/webhook/`, singular — copy verbatim from Bouncer's Developer settings) |
| Bouncer Webhook Secret | password | Used to compute HMAC signature (see §4) |
| Track `pending` orders | toggle + threshold (minutes) | Default 30 min |
| Track `on-hold` orders | toggle + threshold (minutes) | Default 1440 (24h) |
| Track `failed` orders | toggle + threshold (minutes) | Default 60 min |
| Sweep interval | select: 5 / 10 / 15 min | Default 15 min |
| Test sweep now | button | Dry-run, shows candidate count |

Persist in `wp_options` under a single keyed array, e.g. `wc_bouncer_abandoned_settings`.

### 2. WP-Cron job: `wc_bouncer_abandoned_sweep`

Register on plugin activation; unschedule on deactivation. Interval comes from settings.

**Pseudocode:**

```php
function wc_bouncer_abandoned_sweep() {
  $settings = get_option('wc_bouncer_abandoned_settings');
  if (empty($settings['enabled'])) return;

  foreach (['pending', 'on-hold', 'failed'] as $status) {
    if (empty($settings["track_$status"])) continue;

    $threshold_minutes = (int) $settings["threshold_$status"];
    if ($threshold_minutes <= 0) continue; // 0 = disabled, prevent thundering herd

    $cutoff = (new DateTimeImmutable("-{$threshold_minutes} minutes"))->format('Y-m-d H:i:s');

    $orders = wc_get_orders([
      'status'        => $status,
      'date_modified' => '<' . $cutoff,
      'limit'         => 50,                     // batch cap per tick
      'meta_query'    => [[
        'key'     => "_bouncer_abandoned_notified_$status",
        'compare' => 'NOT EXISTS',
      ]],
      'return'        => 'objects',
    ]);

    foreach ($orders as $order) {
      $ok = wc_bouncer_post_abandoned_event($order, $status, $settings);
      if ($ok) {
        $order->update_meta_data("_bouncer_abandoned_notified_$status", current_time('mysql'));
        $order->save();
      }
    }
  }
}
```

**Important details:**

- **Dedup via meta flag** so an order is notified at most once per status entry.
- **Reset the meta flag** when the order leaves that status. Hook into
  `woocommerce_order_status_changed`:

  ```php
  add_action('woocommerce_order_status_changed', function($order_id, $from, $to, $order) {
    foreach (['pending', 'on-hold', 'failed'] as $s) {
      if ($from === $s && $to !== $s) {
        $order->delete_meta_data("_bouncer_abandoned_notified_$s");
        $order->save();
      }
    }
  }, 10, 4);
  ```

  This lets a re-failed order re-trigger later.
- Use `date_modified` (not `date_created`) so editing an order resets the clock —
  matches user intuition. Confirm with product before flipping.
- Batch limit (e.g. 50) prevents one tick from timing out on backfill.

### 3. Payload shape (CRITICAL — must match WooCommerce webhook format)

Bouncer's `extractOrderData` (`webhook-processor.ts:34-110`) expects the standard
WooCommerce order JSON. Reuse WooCommerce's own REST serializer to guarantee shape
compatibility:

```php
function wc_bouncer_serialize_order(WC_Order $order): array {
  $controller = new WC_REST_Orders_Controller();
  $request = new WP_REST_Request('GET');
  $request->set_param('id', $order->get_id());
  $response = $controller->get_item($request);
  return $response->get_data();
}
```

Then build the webhook body:

```php
$payload = wc_bouncer_serialize_order($order);
$payload['event'] = "abandoned.$status_slug"; // e.g. "abandoned.pending_payment"
```

**Status slug mapping** (must match the trigger values Bouncer's UI uses):

| WooCommerce status | Bouncer event slug |
|---|---|
| `pending` | `pending_payment` |
| `on-hold` | `on_hold` |
| `failed` | `failed` |

Bouncer's `normalizeWooCommerceEvent()` will prefix `order.` automatically, producing
`order.abandoned.pending_payment` etc.

### 4. HTTP request

```php
function wc_bouncer_post_abandoned_event(WC_Order $order, string $status, array $settings): bool {
  $map = ['pending' => 'pending_payment', 'on-hold' => 'on_hold', 'failed' => 'failed'];
  $event_slug = $map[$status];

  $payload = wc_bouncer_serialize_order($order);
  $payload['event'] = "abandoned.$event_slug";

  $body      = wp_json_encode($payload);
  $signature = base64_encode(hash_hmac('sha256', $body, $settings['webhook_secret'], true));

  $response = wp_remote_post($settings['webhook_url'], [
    'timeout' => 15,
    'headers' => [
      'Content-Type'             => 'application/json',
      'X-WC-Webhook-Event'       => "abandoned.$event_slug",    // fallback if payload.event ignored
      'X-WC-Webhook-Topic'       => "order.abandoned.$event_slug",
      'X-WC-Webhook-Signature'   => $signature,
      'X-WC-Webhook-Source'      => home_url('/'),
      'X-WC-Webhook-Delivery-ID' => wp_generate_uuid4(),
      'User-Agent'               => 'WP-Bouncer/' . WC_BOUNCER_VERSION,
    ],
    'body' => $body,
  ]);

  $code = wp_remote_retrieve_response_code($response);
  $ok   = !is_wp_error($response) && $code >= 200 && $code < 300;

  // Log via the existing bouncer_logs infrastructure
  wc_bouncer_log([
    'order_id'  => $order->get_id(),
    'phone'     => $order->get_billing_phone(),
    'status'    => $ok ? 'sent' : 'failed',
    'http_code' => $code,
    'message'   => "abandoned.$event_slug webhook",
    'response'  => wp_remote_retrieve_body($response),
  ]);

  return $ok;
}
```

The HMAC signature uses the same algorithm WooCommerce core uses
(`base64(hmac_sha256(body, secret))`). Bouncer's `verifyWooCommerceSignature`
(`apps/server/src/lib/woocommerce-signature.ts`) already validates this format.

### 5. Webhook secret distribution

This plugin already stores an API Key. Add a **separate** "Webhook Secret" field — this
is the secret stored on Bouncer's `woocommerceIntegrations` row, not the API key. The
user copies it from the Bouncer dashboard
(Developer Settings → WooCommerce integration → Signing Secret) into the plugin
settings page.

### 6. Testing UX

In the new settings page, add:

- **Run Sweep Now** — executes one cron tick synchronously, returns count of orders
  notified per status. Useful for ops.
- **Send Test Webhook** — picks the most recent `pending` order (or any order ID input)
  and POSTs it as `order.abandoned.pending_payment`. Shows HTTP response. Does **not**
  set the dedup meta (so it can be re-tested).

### 7. Edge cases to handle explicitly

1. **No billing phone on order** — Bouncer's webhook processor will accept the webhook
   but log it as `processed` with `error: "Invalid phone number"` and **will not fire any
   workflow** (see `webhook-processor.ts:411-453`). Recommended behaviour: still POST
   the event (so it appears in Bouncer's audit log) but also skip-and-mark dedup so the
   plugin doesn't re-attempt every sweep. Consider adding a setting "Skip orders without
   billing phone" to short-circuit before the HTTP call when the user wants quieter
   plugin logs.
   Note: Bouncer normalises the phone using a hardcoded default country code of `60`
   (Malaysia) in `webhook-processor.ts:413`. International stores will need either
   E.164-formatted phones in WooCommerce billing, or a server-side fix to make the
   default configurable per integration. Flag this to the Bouncer team if you serve
   non-MY merchants.
2. **Order paid via late callback (race)** — the meta flag still prevents re-fire even
   if status changed mid-sweep. Acceptable.
3. **Plugin reactivation** — re-register the cron. Don't wipe existing
   `_bouncer_abandoned_notified_*` meta on deactivation; users may pause/resume.
4. **Threshold = 0** — treat as disabled (see pseudocode in §2).
5. **WooCommerce HPOS (custom order tables)** — `wc_get_orders()` and
   `WC_Order::update_meta_data()` are HPOS-safe. Test on a HPOS-enabled store.
6. **Trashed / cancelled orders** — `wc_get_orders` excludes by default; verify.
7. **Subscription renewal orders** — usually start as `pending` then auto-pay. Consider
   a setting "Skip subscription renewals" that filters orders with `parent_id` or
   `_subscription_renewal` meta.

---

## What changes on Bouncer server (not this repo's responsibility)

Coordinate these with the backend owner before this plugin ships:

1. **Default `enabledEvents` on new integrations**: include the three abandoned events
   so the early-exit in `webhook-processor.ts:336` doesn't drop them. File:
   `apps/server/src/db/operations/woocommerce.ts` (createIntegration defaults) or the
   integration-creation UI.
2. **Surface the signing secret** in the WooCommerce integration UI
   (`apps/web/src/components/developer/woocommerce-webhook-config.tsx`) with a copy
   button — needed for §5 above.
3. **Documentation page** explaining how to install this plugin and paste the secret.

---

## Acceptance criteria

- [ ] New settings page renders, persists, and validates inputs.
- [ ] `wc_bouncer_abandoned_sweep` cron registers on activation, runs at configured
      interval.
- [ ] An order stuck in `pending` past its threshold produces exactly **one** POST to
      Bouncer's webhook URL.
- [ ] HTTP 2xx response sets the dedup meta; non-2xx leaves it unset (retries next tick).
- [ ] Order moving out of `pending` clears the dedup meta.
- [ ] Payload validated against Bouncer staging: workflow with
      `order.abandoned.pending_payment` trigger fires and resolves variables
      (`{{order.id}}`, `{{customer.phone}}`, `{{order.total}}`, etc.).
- [ ] HMAC signature passes Bouncer's `verifyWooCommerceSignature` check.
- [ ] All three statuses (`pending`, `on-hold`, `failed`) verified end-to-end on staging.
- [ ] Logs appear in `Bouncer WhatsApp → Logs` with
      `message = "abandoned.<slug> webhook"`.
- [ ] Plugin works on a HPOS-enabled WooCommerce store.

---

## Reference files in `bouncer-saas-waha` (for SWE to read)

- `apps/server/src/services/woocommerce/webhook-processor.ts` — webhook entry point,
  event normalization, signature check, workflow dispatch.
- `apps/server/src/services/woocommerce/workflow-trigger-utils.ts` — event matching +
  execution variable shape (so this plugin sends data the workflow can consume).
- `apps/server/src/lib/woocommerce-signature.ts` — HMAC algorithm to mirror.
- `apps/server/src/routes/webhooks/woocommerce.ts` — HTTP route, URL pattern
  `/api/v1/webhook/woocommerce/:integrationId` (singular `webhook`).
- `apps/web/src/components/workflow/config-panels/triggers/trigger-woocommerce-config.tsx:25-27`
  — the three trigger slugs this plugin must emit.

---

## Out of scope

- Real cart-abandonment (visitor adds to cart but never checks out). That requires
  session tracking and a different data model — separate roadmap item.
- Recovery coupon generation inside the plugin. Bouncer already has a
  `woocommerceCreateCoupon` workflow node; the workflow handles that downstream.
