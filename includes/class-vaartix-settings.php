<?php
/**
 * Plugin settings and status.
 *
 * @package Vaartix
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and stores the plugin's settings (option `vaartix_settings`) and the last send status
 * (option `vaartix_status`).
 */
final class Vaartix_Settings {

	const OPTION        = 'vaartix_settings';
	const STATUS_OPTION = 'vaartix_status';

	/**
	 * Settings with defaults filled in.
	 *
	 * @return array{enabled: bool, api_key: string, from_email: string, from_name: string, force_from: bool, fallback: bool}
	 */
	public static function get() {
		$saved = get_option( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		return array(
			'enabled'    => isset( $saved['enabled'] ) ? (bool) $saved['enabled'] : true,
			'api_key'    => isset( $saved['api_key'] ) ? (string) $saved['api_key'] : '',
			'from_email' => isset( $saved['from_email'] ) ? (string) $saved['from_email'] : '',
			'from_name'  => isset( $saved['from_name'] ) ? (string) $saved['from_name'] : '',
			'force_from' => ! empty( $saved['force_from'] ),
			'fallback'   => ! empty( $saved['fallback'] ),
		);
	}

	/**
	 * True when the API key is defined in wp-config.php (VAARTIX_API_KEY), which is safer than
	 * storing it in the database.
	 */
	public static function key_from_constant() {
		return defined( 'VAARTIX_API_KEY' ) && is_string( VAARTIX_API_KEY ) && '' !== VAARTIX_API_KEY;
	}

	/**
	 * The API key to use: the VAARTIX_API_KEY constant if set, otherwise the saved setting.
	 */
	public static function api_key() {
		if ( self::key_from_constant() ) {
			return trim( (string) VAARTIX_API_KEY );
		}
		$settings = self::get();
		return trim( $settings['api_key'] );
	}

	/**
	 * The API base URL. Override with VAARTIX_API_BASE_URL (for testing only).
	 */
	public static function base_url() {
		if ( defined( 'VAARTIX_API_BASE_URL' ) && is_string( VAARTIX_API_BASE_URL ) && '' !== VAARTIX_API_BASE_URL ) {
			return untrailingslashit( VAARTIX_API_BASE_URL );
		}
		return 'https://api.vaartix.com';
	}

	/**
	 * Shows the key as `vx_••••1234` so admins can recognise it without revealing it.
	 *
	 * @param string $key API key.
	 */
	public static function mask_key( $key ) {
		$key = (string) $key;
		if ( strlen( $key ) <= 8 ) {
			return $key === '' ? '' : '••••';
		}
		return substr( $key, 0, 3 ) . '••••' . substr( $key, -4 );
	}

	/**
	 * Records a successful send.
	 *
	 * @param string $email_id Vaartix email ID (em_...).
	 */
	public static function record_success( $email_id ) {
		$status                 = self::status();
		$status['last_success'] = array(
			'time' => time(),
			'id'   => (string) $email_id,
		);
		update_option( self::STATUS_OPTION, $status, false );
	}

	/**
	 * Records a failed send.
	 *
	 * @param string $message Error message (never contains the API key).
	 */
	public static function record_error( $message ) {
		$status               = self::status();
		$status['last_error'] = array(
			'time'    => time(),
			'message' => (string) $message,
		);
		update_option( self::STATUS_OPTION, $status, false );
	}

	/**
	 * Last success and last error.
	 *
	 * @return array<string, mixed>
	 */
	public static function status() {
		$status = get_option( self::STATUS_OPTION, array() );
		return is_array( $status ) ? $status : array();
	}
}
