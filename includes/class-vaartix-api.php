<?php
/**
 * Minimal client for the Vaartix email API.
 *
 * @package Vaartix
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sends emails through POST /emails using the WordPress HTTP API.
 */
final class Vaartix_Api {

	/**
	 * Sends one email.
	 *
	 * @param array<string, mixed> $payload Request body in the API's format.
	 * @param string               $api_key Vaartix API key.
	 * @return string|WP_Error The Vaartix email ID (em_...) or an error.
	 */
	public static function send_email( array $payload, $api_key ) {
		$response = wp_remote_post(
			Vaartix_Settings::base_url() . '/emails',
			array(
				'timeout'     => 15,
				'redirection' => 0,
				'headers'     => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
					'User-Agent'    => 'vaartix-wordpress/' . VAARTIX_PLUGIN_VERSION . '; WordPress/' . get_bloginfo( 'version' ),
				),
				'body'        => wp_json_encode( $payload ),
				'data_format' => 'body',
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'vaartix_network_error',
				/* translators: %s: error message from the HTTP request. */
				sprintf( __( 'Could not reach the Vaartix API: %s', 'vaartix' ), $response->get_error_message() )
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( $code >= 200 && $code < 300 && is_array( $body ) && isset( $body['id'] ) && is_string( $body['id'] ) ) {
			return $body['id'];
		}

		$type    = 'api_error';
		$message = '';
		if ( is_array( $body ) && isset( $body['error'] ) && is_array( $body['error'] ) ) {
			if ( isset( $body['error']['type'] ) && is_string( $body['error']['type'] ) ) {
				$type = $body['error']['type'];
			}
			if ( isset( $body['error']['message'] ) && is_string( $body['error']['message'] ) ) {
				$message = $body['error']['message'];
			}
		}
		if ( '' === $message ) {
			/* translators: %d: HTTP status code. */
			$message = sprintf( __( 'The Vaartix API returned HTTP %d.', 'vaartix' ), $code );
		}

		return new WP_Error( 'vaartix_' . sanitize_key( $type ), $message, array( 'status' => $code ) );
	}
}
