---

# 📄 Product Requirements Document (PRD)

**Project:** WooCommerce → Bouncer WhatsApp Plugin
**Version:** v1.0
**Date:** 2025-09-19

---

## 1. Overview

This plugin integrates **WooCommerce** with the **Bouncer WhatsApp API** (`https://api.bouncer.my/swagger`).
It automatically sends WhatsApp messages to customers when their WooCommerce order status changes, using the `/sendtext` endpoint.

The plugin also provides:

* **Admin UI** for configuration (API key, instance, message template, order statuses).
* **Dynamic placeholders** to personalize messages (order info, customer info, meta fields).
* **Logging system** with retention and manual clear.
* **Test & Health page** to verify connectivity and preview messages.

---

## 2. Objectives

* Automate WhatsApp notifications for WooCommerce orders.
* Provide flexibility for merchants to customize messages.
* Ensure transparency and reliability with logs and previews.
* Minimize support issues via built-in test & health checks.

---

## 3. Scope

### In-Scope

* WooCommerce order status → WhatsApp message automation.
* Settings UI in WordPress Admin.
* Placeholder system for messages (built-in + meta).
* Logs stored in custom DB table with retention policy.
* Manual test message sending.
* API health check.
* Message preview (with placeholder resolution + meta listing).

### Out-of-Scope

* Conditional logic in templates (e.g. `{if}` syntax).
* Advanced scheduling/delayed sending.
* Multi-instance or multi-template per status (v2+).
* Bulk re-sending past notifications.

---

## 4. Functional Requirements

### 4.1 Message Trigger

* Hook into `woocommerce_order_status_changed`.
* Trigger when new status matches merchant-selected statuses.
* Collect order + customer data.
* Call `POST /sendtext` with payload:

  ```json
  {
    "instance": "INSTANCE_ID",
    "to": "60123456789",
    "message": "Resolved message"
  }
  ```

### 4.2 Message Template

* Configurable in settings.
* Supports placeholders:

| Placeholder         | Value                           |
| ------------------- | ------------------------------- |
| `{order_id}`        | Internal order ID               |
| `{order_number}`    | WooCommerce order number        |
| `{status}`          | Order status slug               |
| `{amount}`          | Total (formatted with currency) |
| `{currency}`        | Currency code                   |
| `{payment_method}`  | Payment method title            |
| `{shipping_method}` | Shipping method(s)              |
| `{name}`            | Full name                       |
| `{first_name}`      | Billing first name              |
| `{last_name}`       | Billing last name               |
| `{email}`           | Billing email                   |
| `{phone}`           | Billing phone                   |
| `{meta:KEY}`        | Any order meta value            |

### 4.3 Admin Settings

* **API Key** (text input).
* **Instance ID** (text input).
* **Message Template** (textarea with help text).
* **Trigger Statuses** (checkbox list of WooCommerce statuses).
* **Log Retention (days)** (number input).
* **Meta Key Discovery**:

  * Button: “Refresh Meta Keys” → scans last 20 orders → stores unique keys.
  * Display list of `{meta:...}` placeholders.

### 4.4 Logging

* Logs stored in `wp_bouncer_logs`.
* Fields: `id`, `order_id`, `phone`, `message`, `status`, `response_code`, `response_body`, `created_at`.
* Admin UI: WooCommerce → Bouncer Logs.

  * List last 100 logs (paginated if needed).
  * “Clear Logs” button (truncate).
* Retention: Auto-delete older than N days (cron job, default 7 days).

### 4.5 Test & Health Page

* **Send Test Message**: input phone + text → calls API.
* **Health Check**: call `/instance/{id}` → confirm API reachable.
* **Preview Message**:

  * Select recent order (dropdown).
  * Show resolved template with real data.
  * Show all meta keys + values for that order.
  * Option to “Send Preview to Customer” (uses that order’s billing phone).

---

## 5. Non-Functional Requirements

* **Performance**: API calls must be async-safe (timeout 15s).
* **Security**:

  * Store API key in WordPress options (protected by `manage_options`).
  * Use nonces for all admin forms.
* **Reliability**: Failed messages logged with full response.
* **Maintainability**: Code structured with classes, modular includes.

---

## 6. User Flows

### Order Status Change

1. Merchant updates order → status changes to "completed".
2. Plugin detects status.
3. Resolves template → fills variables.
4. Calls `/sendtext`.
5. Logs response.
6. Adds order note “WhatsApp message sent/failed”.

### Admin Setup

1. Go to Settings → Bouncer WhatsApp.
2. Enter API Key, Instance ID, template.
3. Select trigger statuses.
4. Save.

### Testing

1. Go to WooCommerce → Bouncer Test & Health.
2. Send test message manually.
3. Run health check.
4. Preview message for recent order.
5. (Optional) Send preview to real customer.

### Log Review

1. Go to WooCommerce → Bouncer Logs.
2. View last 100 logs.
3. Clear logs manually if needed.
4. Logs older than retention auto-deleted daily.

---

## 7. Future Enhancements (v2+)

* Conditional placeholders (`{if:meta:tracking_number}`).
* Multi-instance support.
* Multiple templates per order status.
* Retry queue for failed messages.
* Per-order tab for WhatsApp history.

---

✅ This PRD covers **all current requirements**:

* Auto WhatsApp messaging on Woo order status.
* Configurable settings + variables.
* Logging + retention.
* Test & Health + Preview.
* Meta key discovery + order-specific meta listing.

---

Based on the Swagger API documentation, here's a comprehensive description of the available endpoints for your WordPress plugin PRD:

  Bouncer SaaS API Endpoints for WordPress Plugin Integration

  Authentication

  All endpoints require X-API-Key header with organization-specific API keys:
  - Live keys: bnc_live_sk_... (production)
  - Test keys: bnc_test_sk_... (development)

  Message Sending Endpoints

  1. Send Text Messages

  POST /api/v1/message/sendText
  - Purpose: Send WhatsApp text messages
  - Parameters:
    - number (required): Phone number in E.164 format
    - text (required): Message content (1-4096 chars)
    - instance (optional): WhatsApp instance name (default: "default")
    - options.delay (optional): Delay before sending (0-30000ms)
    - options.presence (optional): "composing", "recording", "paused"

  2. Send Media Messages

  POST /api/v1/message/sendMedia
  - Purpose: Send images, videos, audio, documents
  - Parameters:
    - number (required): Phone number in E.164 format
    - mediatype (required): "image", "video", "audio", "document"
    - media (required): URL or base64 data
    - caption (optional): Media caption (max 1024 chars)
    - instance (optional): WhatsApp instance name
    - options.delay & options.presence (same as text)

  3. Send Location Messages

  POST /api/v1/message/sendLocation
  - Purpose: Share geographic coordinates
  - Parameters:
    - number (required): Phone number
    - latitude (required): Geographic latitude
    - longitude (required): Geographic longitude
    - name (optional): Location name
    - address (optional): Location address
    - instance (optional): WhatsApp instance

  4. Send Contact Messages

  POST /api/v1/message/sendContact
  - Purpose: Send vCard contact information
  - Parameters:
    - number (required): Phone number
    - contact (required): Contact object with name, phone, etc.
    - instance (optional): WhatsApp instance

  Conversation State Management

  5. Update Conversation State

  POST /api/v1/conversations/state
  - Purpose: Control bot-to-human handover workflows
  - Parameters:
    - phoneNumber (required): Customer phone number
    - state (required): "bot", "human_transfer", "closed"
    - webhookConfigId (required): Webhook endpoint ID
    - assignedTo (optional): Agent email for human_transfer
    - metadata (optional): Custom context data
    - expiresIn (optional): TTL in seconds

  6. Get Conversation State

  GET /api/v1/conversations/state/{phoneNumber}?webhookConfigId={id}
  - Purpose: Check current conversation state
  - Returns: State, assignment, metadata, expiration status

  7. List Conversation States

  GET /api/v1/conversations/states
  - Purpose: Get paginated list with filtering
  - Query Parameters:
    - state: Filter by conversation state
    - webhookConfigId: Filter by webhook endpoint
    - assignedTo: Filter by assigned agent
    - includeExpired: Include expired conversations
    - page & pageSize: Pagination

  8. Bulk Update Conversation States

  POST /api/v1/conversations/states/bulk
  - Purpose: Update multiple conversations atomically
  - Use Cases: Shift management, bulk escalation
  - Parameters:
    - phoneNumbers (required): Array of phone numbers
    - updates (required): State changes to apply
    - webhookConfigId (required): Webhook endpoint ID

  9. Delete Conversation State

  DELETE /api/v1/conversations/state/{phoneNumber}?webhookConfigId={id}
  - Purpose: Remove conversation state record

  Instance Management

  10. Get Instance Status

  GET /api/v1/instance/status/{instanceId}
  - Purpose: Check WhatsApp connection status
  - Returns: Status ("open", "connecting", "close", "timeout", "unknown")

  11. List All Instances

  GET /api/v1/instances/status
  - Purpose: Get status of all organization instances
  - Returns: Array of instances with connection status

  12. Get Instance by Name

  GET /api/v1/instance/status/name/{instanceName}
  - Purpose: Check specific instance by name instead of ID


  Response Format

  All endpoints return standardized responses:
  {
    "success": true,
    "messageId": "msg_123...",
    "timestamp": "2024-01-01T00:00:00Z",
    "credits_used": 1,
    "credits_remaining": 999
  }

  Key WordPress Plugin Features to Implement

  1. Message Broadcasting - Send bulk messages to subscriber lists
  2. Conversation Management - Handle customer service workflows
  3. Instance Monitoring - Display WhatsApp connection status
  4. Contact Integration - Sync with WordPress users/customers
  5. Media Handling - Upload and send WordPress media files
  6. Location Sharing - Send business location or event locations
  7. Contact Cards - Share business or team member contacts
  8. Analytics Integration - Track message delivery and conversation metrics

  This API provides comprehensive WhatsApp messaging capabilities that would integrate perfectly with WordPress for customer communication, marketing automation, and support workflows.
