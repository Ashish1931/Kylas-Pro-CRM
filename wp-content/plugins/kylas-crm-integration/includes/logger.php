<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Kylas_CRM_Logger {
	private const LOG_DIRNAME = 'kylas-crm-logs';
	private const LOG_FILENAME = 'kylas-crm-errors.log';
	private const MAX_STRING_LEN = 2000;
	private const MAX_CONTEXT_DEPTH = 6;

	public static function error( string $message, array $context = array() ): void {
		self::write( 'ERROR', $message, $context );
	}

	public static function info( string $message, array $context = array() ): void {
		self::write( 'INFO', $message, $context );
	}

	private static function write( string $level, string $message, array $context ): void {
		$path = self::get_log_path();
		if ( empty( $path ) ) {
			return;
		}

		$entry = array(
			'ts'      => current_time( 'c' ),
			'level'   => $level,
			'message' => $message,
			'context' => self::sanitize_context( $context ),
		);

		$line = wp_json_encode( $entry, JSON_UNESCAPED_SLASHES ) . PHP_EOL;

		// Best-effort logging; don't break form submissions on IO issues.
		@file_put_contents( $path, $line, FILE_APPEND | LOCK_EX );
	}

	private static function get_log_path(): string {
		$upload = wp_upload_dir();
		
		if ( empty( $upload['basedir'] ) || ! empty( $upload['error'] ) ) {
			return '';
		}

		$dir = trailingslashit( $upload['basedir'] ) . self::LOG_DIRNAME;

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		return trailingslashit( $dir ) . self::LOG_FILENAME;
	}

	private static function sanitize_context( $value, int $depth = 0 ) {
		if ( $depth > self::MAX_CONTEXT_DEPTH ) {
			return '[max_depth_reached]';
		}

		if ( is_string( $value ) ) {
			if ( strlen( $value ) > self::MAX_STRING_LEN ) {
				return substr( $value, 0, self::MAX_STRING_LEN ) . '...[truncated]';
			}
			return $value;
		}

		if ( is_numeric( $value ) || is_bool( $value ) || is_null( $value ) ) {
			return $value;
		}

		if ( is_object( $value ) ) {
			// Avoid logging object internals (may contain secrets or be huge).
			return sprintf( '[object:%s]', get_class( $value ) );
		}

		if ( ! is_array( $value ) ) {
			return '[unserializable]';
		}

		$out = array();

		foreach ( $value as $k => $v ) {
			$key = is_string( $k ) ? $k : (string) $k;
			$lower = strtolower( $key );

			if (
				str_contains( $lower, 'api-key' ) ||
				str_contains( $lower, 'api_key' ) ||
				str_contains( $lower, 'authorization' ) ||
				str_contains( $lower, 'password' ) ||
				str_contains( $lower, 'secret' ) ||
				str_contains( $lower, 'token' )
			) {
				$out[ $key ] = '[redacted]';
				continue;
			}

			$out[ $key ] = self::sanitize_context( $v, $depth + 1 );
		}

		return $out;
	}
}

