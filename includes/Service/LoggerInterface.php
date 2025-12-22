<?php

namespace Bouncer\WooCommerce\WhatsApp\Service;

interface LoggerInterface {
    public function record(
        int $order_id,
        string $phone,
        string $message,
        string $status,
        int $response_code,
        string $response_body
    ): void;
}
