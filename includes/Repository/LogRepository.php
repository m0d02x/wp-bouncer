<?php

namespace Bouncer\WooCommerce\WhatsApp\Repository;

use wpdb;

class LogRepository {
    private const TABLE_SUFFIX = 'bouncer_logs';

    private wpdb $db;

    public function __construct( wpdb $db ) {
        $this->db = $db;
    }

    public function table_name(): string {
        return $this->db->prefix . self::TABLE_SUFFIX;
    }

    public function create_table(): void {
        $table_name      = $this->table_name();
        $charset_collate = $this->db->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            phone varchar(32) NOT NULL,
            message longtext NOT NULL,
            status varchar(20) NOT NULL,
            response_code smallint(5) unsigned NOT NULL,
            response_body longtext NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY order_id (order_id),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public function insert( array $data ): void {
        $defaults = [
            'order_id'      => 0,
            'phone'         => '',
            'message'       => '',
            'status'        => '',
            'response_code' => 0,
            'response_body' => '',
            'created_at'    => current_time( 'mysql', true ),
        ];

        $payload = array_merge( $defaults, $data );

        $this->db->insert(
            $this->table_name(),
            $payload,
            [
                '%d',
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
                '%s',
            ]
        );
    }

    public function latest( int $limit = 100 ): array {
        $table = $this->table_name();
        $limit = max( 1, $limit );

        $sql = $this->db->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", $limit );

        return (array) $this->db->get_results( $sql, ARRAY_A );
    }

    public function paginated( int $limit = 25, int $offset = 0 ): array {
        $table  = $this->table_name();
        $limit  = max( 1, $limit );
        $offset = max( 0, $offset );

        $sql = $this->db->prepare(
            "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $limit,
            $offset
        );

        return (array) $this->db->get_results( $sql, ARRAY_A );
    }

    public function count(): int {
        $table = $this->table_name();

        return (int) $this->db->get_var( "SELECT COUNT(*) FROM {$table}" );
    }

    /**
     * Retrieve aggregate counts for dashboard widgets.
     */
    public function stats(): array {
        $table = $this->table_name();

        $total = (int) $this->db->get_var( "SELECT COUNT(*) FROM {$table}" );

        $by_status = [];
        $rows      = (array) $this->db->get_results( "SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status", ARRAY_A );

        foreach ( $rows as $row ) {
            $status = (string) ( $row['status'] ?? '' );
            if ( '' === $status ) {
                continue;
            }

            $by_status[ $status ] = (int) $row['total'];
        }

        return [
            'total'     => $total,
            'by_status' => $by_status,
        ];
    }

    public function truncate(): void {
        $table = $this->table_name();
        $this->db->query( "TRUNCATE TABLE {$table}" );
    }

    public function delete_older_than( int $days ): void {
        $table = $this->table_name();
        $days  = max( 1, $days );

        $threshold = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
        $sql       = $this->db->prepare( "DELETE FROM {$table} WHERE created_at < %s", $threshold );
        $this->db->query( $sql );
    }

    public function count_older_than( int $days ): int {
        $table = $this->table_name();
        $days  = max( 1, $days );

        $threshold = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
        $sql       = $this->db->prepare( "SELECT COUNT(*) FROM {$table} WHERE created_at < %s", $threshold );

        return (int) $this->db->get_var( $sql );
    }
}
