<?php
/**
 * Bouncer Debug Helper
 *
 * Add detailed logging for debugging
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WFCO_Bouncer_Debug {

	private static $log_file = null;

	public static function init() {
		self::$log_file = WFCO_BOUNCER_PLUGIN_DIR . '/bouncer-debug.log';

		// Clear log file if it's older than 1 day
		if ( file_exists( self::$log_file ) && ( time() - filemtime( self::$log_file ) ) > 86400 ) {
			@unlink( self::$log_file );
		}
	}

	public static function log( $message, $data = null ) {
		if ( ! self::$log_file ) {
			self::init();
		}

		$timestamp = date( 'Y-m-d H:i:s' );
		$log_entry = "[{$timestamp}] {$message}";

		if ( $data !== null ) {
			$log_entry .= "\n" . print_r( $data, true );
		}

		$log_entry .= "\n" . str_repeat( '-', 80 ) . "\n";

		@file_put_contents( self::$log_file, $log_entry, FILE_APPEND );

		// Also log to WordPress debug log if enabled
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( 'BOUNCER: ' . $message );
			if ( $data !== null ) {
				error_log( 'BOUNCER DATA: ' . print_r( $data, true ) );
			}
		}
	}

	public static function get_log() {
		if ( ! self::$log_file || ! file_exists( self::$log_file ) ) {
			return "No log file found.";
		}

		return file_get_contents( self::$log_file );
	}

	public static function clear_log() {
		if ( self::$log_file && file_exists( self::$log_file ) ) {
			@unlink( self::$log_file );
		}
	}
}

// Initialize
WFCO_Bouncer_Debug::init();
