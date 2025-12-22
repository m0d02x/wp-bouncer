<?php

namespace Bouncer\WooCommerce\WhatsApp\Service;

use WC_Order;

class MetaKeyDiscovery {
    public function discover( int $limit = 20 ): array {
        if ( ! function_exists( 'wc_get_orders' ) ) {
            return [];
        }

        $orders = wc_get_orders(
            [
                'limit'   => $limit,
                'orderby' => 'date',
                'order'   => 'DESC',
            ]
        );

        $keys = [];
        foreach ( $orders as $order ) {
            if ( ! $order instanceof WC_Order ) {
                continue;
            }

            foreach ( $order->get_meta_data() as $meta ) {
                $key = (string) $meta->key;
                if ( '' === $key ) {
                    continue;
                }

                $keys[] = $key;
            }
        }

        $keys = array_unique( $keys );
        sort( $keys );

        return $keys;
    }
}
