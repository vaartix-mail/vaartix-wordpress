<?php
/**
 * Tests for the mail logic. Run with: php tests/run.php
 *
 * @package Vaartix
 */

require __DIR__ . '/bootstrap.php';

$failures = 0;
$count    = 0;
function check( $name, $condition ) {
	global $failures, $count;
	$count++;
	if ( ! $condition ) {
		$failures++;
		echo "FAIL: $name\n";
	}
}

$key = array( 'api_key' => 'vx_testkey_1234' );

// Not configured: WordPress sends the email itself.
vx_reset( array() );
check( 'no key -> null (WordPress sends)', null === vx_wp_mail( 'a@example.com', 'Hi', 'Body' ) );
check( 'no key -> no API call', array() === $GLOBALS['vx_http']['requests'] );

// Disabled.
vx_reset( $key + array( 'enabled' => false ) );
check( 'disabled -> null', null === vx_wp_mail( 'a@example.com', 'Hi', 'Body' ) );

// Plain text email with defaults.
vx_reset( $key );
check( 'sends -> true', true === vx_wp_mail( 'a@example.com, B <b@example.com>', 'Hello', 'Plain body' ) );
$req  = $GLOBALS['vx_http']['requests'][0];
$body = vx_last_body();
check( 'endpoint', 'https://api.vaartix.com/emails' === $req['url'] );
check( 'bearer auth', 'Bearer vx_testkey_1234' === $req['args']['headers']['Authorization'] );
check( 'no redirects', 0 === $req['args']['redirection'] );
check( 'to list', array( 'a@example.com', 'B <b@example.com>' ) === $body['to'] );
check( 'default from is wordpress@domain', 'WordPress <wordpress@example.com>' === $body['from'] );
check( 'plain text', 'Plain body' === $body['text'] && ! isset( $body['html'] ) );
check( 'subject', 'Hello' === $body['subject'] );
check( 'success recorded', 'em_test1' === get_option( 'vaartix_status' )['last_success']['id'] );
check( 'wp_mail_succeeded fired', 'wp_mail_succeeded' === $GLOBALS['vx_actions'][0][0] );

// HTML via header, From/Cc/Bcc/Reply-To and custom headers.
vx_reset( $key );
$headers = "From: \"Acme Shop\" <orders@shop.example.com>\r\nContent-Type: text/html; charset=UTF-8\r\nCc: c@example.com\r\nBcc: d@example.com, e@example.com\r\nReply-To: help@shop.example.com\r\nX-Order-Id: 1042\r\nMIME-Version: 1.0\r\nBad Header: x";
vx_wp_mail( 'a@example.com', 'Order', '<p>Thanks</p>', $headers );
$body = vx_last_body();
check( 'from header used', 'Acme Shop <orders@shop.example.com>' === $body['from'] );
check( 'html', '<p>Thanks</p>' === $body['html'] && ! isset( $body['text'] ) );
check( 'cc', array( 'c@example.com' ) === $body['cc'] );
check( 'bcc', array( 'd@example.com', 'e@example.com' ) === $body['bcc'] );
check( 'replyTo', array( 'help@shop.example.com' ) === $body['replyTo'] );
check( 'custom header kept, reserved/invalid dropped', array( 'X-Order-Id' => '1042' ) === $body['headers'] );

// Headers as an array, and wp_mail_content_type filter (as WooCommerce uses).
vx_reset( $key );
add_filter( 'wp_mail_content_type', function () { return 'text/html'; } );
vx_wp_mail( 'a@example.com', 'S', '<b>x</b>', array( 'Cc: c@example.com' ) );
$body = vx_last_body();
check( 'content type filter -> html', '<b>x</b>' === $body['html'] );
check( 'array headers', array( 'c@example.com' ) === $body['cc'] );

// Settings From + wp_mail_from filters.
vx_reset( $key + array( 'from_email' => 'noreply@notify.example.com', 'from_name' => 'Example' ) );
vx_wp_mail( 'a@example.com', 'S', 'B' );
check( 'settings from', 'Example <noreply@notify.example.com>' === vx_last_body()['from'] );

vx_reset( $key + array( 'from_email' => 'noreply@notify.example.com' ) );
add_filter( 'wp_mail_from', function () { return 'plugin@other.example.com'; } );
vx_wp_mail( 'a@example.com', 'S', 'B' );
check( 'wp_mail_from filter respected', false !== strpos( vx_last_body()['from'], 'plugin@other.example.com' ) );

// Force From overrides plugin From.
vx_reset( $key + array( 'from_email' => 'noreply@notify.example.com', 'from_name' => 'Example', 'force_from' => true ) );
vx_wp_mail( 'a@example.com', 'S', 'B', 'From: Plugin <plugin@other.example.com>' );
check( 'force from', 'Example <noreply@notify.example.com>' === vx_last_body()['from'] );

// Name with characters that would break the address is cleaned.
vx_reset( $key + array( 'from_email' => 'noreply@notify.example.com', 'from_name' => "Evil<x>\r\nBcc: y" ) );
vx_wp_mail( 'a@example.com', 'S', 'B' );
check( 'from name cleaned', 'EvilxBcc: y <noreply@notify.example.com>' === vx_last_body()['from'] );

// Invalid From -> error, not sent.
vx_reset( $key + array( 'from_email' => '' ) );
add_filter( 'wp_mail_from', function () { return 'not-an-email'; } );
check( 'invalid from -> false', false === vx_wp_mail( 'a@example.com', 'S', 'B' ) );
check( 'invalid from -> no API call', array() === $GLOBALS['vx_http']['requests'] );
check( 'invalid from -> wp_mail_failed', 'wp_mail_failed' === $GLOBALS['vx_actions'][0][0] );

// Attachments: path list and custom file names.
$tmp = tempnam( sys_get_temp_dir(), 'vx' );
file_put_contents( $tmp, 'hello attachment' );
vx_reset( $key );
vx_wp_mail( 'a@example.com', 'S', 'B', '', array( $tmp, 'invoice.pdf' => $tmp ) );
$att = vx_last_body()['attachments'];
check( 'attachment content base64', base64_encode( 'hello attachment' ) === $att[0]['content'] );
check( 'attachment default name', basename( $tmp ) === $att[0]['filename'] );
check( 'attachment custom name', 'invoice.pdf' === $att[1]['filename'] );

vx_reset( $key );
check( 'missing attachment -> false', false === vx_wp_mail( 'a@example.com', 'S', 'B', '', array( '/no/such/file.pdf' ) ) );
unlink( $tmp );

// Multipart bodies are left to WordPress.
vx_reset( $key );
check( 'multipart -> null', null === vx_wp_mail( 'a@example.com', 'S', 'B', 'Content-Type: multipart/alternative; boundary="x"' ) );

// API error -> false, error recorded, wp_mail_failed with the API message.
vx_reset( $key, array( 'response' => array( 'code' => 403 ), 'body' => '{"error":{"type":"domain_not_verified","message":"The domain example.com is not verified."}}' ) );
check( 'api error -> false', false === vx_wp_mail( 'a@example.com', 'S', 'B' ) );
check( 'api error recorded', 'The domain example.com is not verified.' === get_option( 'vaartix_status' )['last_error']['message'] );
$failed = $GLOBALS['vx_actions'][0];
check( 'wp_mail_failed fired', 'wp_mail_failed' === $failed[0] && 'The domain example.com is not verified.' === $failed[1][0]->get_error_message() );
check( 'error never contains the key', false === strpos( get_option( 'vaartix_status' )['last_error']['message'], 'vx_testkey' ) );

// API error with fallback -> null (WordPress sends it).
vx_reset( $key + array( 'fallback' => true ), array( 'response' => array( 'code' => 500 ), 'body' => '' ) );
check( 'fallback -> null', null === vx_wp_mail( 'a@example.com', 'S', 'B' ) );

// Network error.
vx_reset( $key, new WP_Error( 'http_request_failed', 'cURL error 28: timed out' ) );
check( 'network error -> false', false === vx_wp_mail( 'a@example.com', 'S', 'B' ) );
check( 'network error message', false !== strpos( get_option( 'vaartix_status' )['last_error']['message'], 'timed out' ) );

// Another plugin already handled the email.
vx_reset( $key );
check( 'earlier pre_wp_mail result kept', true === Vaartix_Mailer::pre_wp_mail( true, array() ) );

// Constant key wins over the saved one and isn't masked out.
check( 'mask', 'vx_••••1234' === Vaartix_Settings::mask_key( 'vx_testkey_1234' ) );

// Payload filter.
vx_reset( $key );
add_filter( 'vaartix_email_payload', function ( $p ) { $p['tags'] = array( array( 'name' => 'source', 'value' => 'wordpress' ) ); return $p; } );
vx_wp_mail( 'a@example.com', 'S', 'B' );
check( 'payload filter', 'wordpress' === vx_last_body()['tags'][0]['value'] );

echo "$count checks, $failures failed\n";
exit( $failures > 0 ? 1 : 0 );
