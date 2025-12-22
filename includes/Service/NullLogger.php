<?php

namespace Bouncer\WooCommerce\WhatsApp\Service;

class NullLogger implements LoggerInterface {
    public function record( int $order_id, string $phone, string $message, string $status, int $response_code, string $response_body ): void {
        // Intentionally left blank until logging subsystem is wired.
    }
}
