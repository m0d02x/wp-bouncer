<?php

namespace Bouncer\WooCommerce\WhatsApp\Service;

use Bouncer\WooCommerce\WhatsApp\Repository\LogRepository;

class Logger implements LoggerInterface {
    private LogRepository $repository;

    public function __construct( LogRepository $repository ) {
        $this->repository = $repository;
    }

    public function record( int $order_id, string $phone, string $message, string $status, int $response_code, string $response_body ): void {
        try {
            $this->repository->insert(
                [
                    'order_id'      => $order_id,
                    'phone'         => $phone,
                    'message'       => $message,
                    'status'        => $status,
                    'response_code' => $response_code,
                    'response_body' => $response_body,
                ]
            );
        } catch ( \Throwable $exception ) {
            // Avoid breaking order flow if logging fails.
            error_log( sprintf( 'Bouncer WhatsApp logging failed: %s', $exception->getMessage() ) );
        }
    }
}
