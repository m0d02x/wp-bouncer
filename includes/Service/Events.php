<?php

namespace Bouncer\WooCommerce\WhatsApp\Service;

/**
 * Single source of truth for every webhook event topic this plugin emits.
 * Pushed to Bouncer on activation, plugin upgrade, and reconnect so the
 * server's per-integration `enabled_events` allowlist stays in sync without
 * manual admin toggling.
 */
class Events {
    public const SUPPORTED_EVENTS = [
        'order.created',
        'order.updated',
        'order.deleted',
        'order.abandoned.pending_payment',
        'order.abandoned.on_hold',
        'order.abandoned.failed',
    ];
}
