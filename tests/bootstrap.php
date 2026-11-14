<?php
/**
 * Minimal stand-ins for the WordPress functions the plugin uses, so the mail logic can be tested
 * with plain PHP (no WordPress install or database needed).
 *
 * @package Vaartix
 */

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['vx_filters'] = array();
$GLOBALS['vx_actions'] = array();
$GLOBALS['vx_options'] = array();
$GLOBALS['vx_http']    = array( 'requests' => array(), 'response' => null );

class WP_Error {
	private $code;
	private $message;
	private $data;
	public function __construct( $code = '', $message = '', $data = '' ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}

function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
function add_filter( $hook, $cb, $priority = 10, $args = 1 ) { $GLOBALS['vx_filters'][ $hook ][] = $cb; return true; }
function add_action( $hook, $cb, $priority = 10, $args = 1 ) { return add_filter( $hook, $cb, $priority, $args ); }
function apply_filters( $hook, $value, ...$args ) {
	foreach ( $GLOBALS['vx_filters'][ $hook ] ?? array() as $cb ) {
		$value = $cb( $value, ...$args );
	}
	return $value;
}
function do_action( $hook, ...$args ) { $GLOBALS['vx_actions'][] = array( $hook, $args ); }
function get_option( $name, $default = false ) { return $GLOBALS['vx_options'][ $name ] ?? $default; }
function update_option( $name, $value, $autoload = null ) { $GLOBALS['vx_options'][ $name ] = $value; return true; }
function is_email( $email ) { return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function network_home_url() { return 'https://www.example.com'; }
function wp_basename( $path ) { return basename( $path ); }
function untrailingslashit( $s ) { return rtrim( $s, '/\\' ); }
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) ); }
function wp_json_encode( $data ) { return json_encode( $data ); }
function get_bloginfo( $what = '' ) { return 'version' === $what ? '6.8' : 'Example'; }
function __( $text, $domain = 'default' ) { return $text; }
function wp_remote_post( $url, $args ) {
	$GLOBALS['vx_http']['requests'][] = array( 'url' => $url, 'args' => $args );
	return $GLOBALS['vx_http']['response'];
}
function wp_remote_retrieve_response_code( $r ) { return $r['response']['code'] ?? ''; }
function wp_remote_retrieve_body( $r ) { return $r['body'] ?? ''; }

/** Resets state between tests and sets the API's reply. */
function vx_reset( $settings = array(), $response = null ) {
	$GLOBALS['vx_filters'] = array();
	$GLOBALS['vx_actions'] = array();
	$GLOBALS['vx_options'] = array( 'vaartix_settings' => $settings );
	$GLOBALS['vx_http']    = array(
		'requests' => array(),
		'response' => $response ?? array( 'response' => array( 'code' => 200 ), 'body' => '{"id":"em_test1"}' ),
	);
	Vaartix_Mailer::init();
}

/** The JSON body of the last request to the API. */
function vx_last_body() {
	$requests = $GLOBALS['vx_http']['requests'];
	$last     = end( $requests );
	return $last ? json_decode( $last['args']['body'], true ) : null;
}

/** Calls the pre_wp_mail filter like wp_mail() does. */
function vx_wp_mail( $to, $subject, $message, $headers = '', $attachments = array() ) {
	return apply_filters( 'pre_wp_mail', null, compact( 'to', 'subject', 'message', 'headers', 'attachments' ) );
}

define( 'VAARTIX_PLUGIN_VERSION', 'test' );
require __DIR__ . '/../includes/class-vaartix-settings.php';
require __DIR__ . '/../includes/class-vaartix-api.php';
require __DIR__ . '/../includes/class-vaartix-mailer.php';
