<?php
/**
 * Routes wp_mail() through Vaartix.
 *
 * @package Vaartix
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hooks `pre_wp_mail` (WordPress 5.7+) so every wp_mail() call, from core, WooCommerce, contact
 * form plugins and themes, is sent through the Vaartix API instead of PHP mail().
 */
final class Vaartix_Mailer {

	/** Headers Vaartix builds from the message itself, so they are never passed through. */
	const RESERVED_HEADERS = array(
		'from',
		'to',
		'cc',
		'bcc',
		'reply-to',
		'sender',
		'subject',
		'date',
		'message-id',
		'content-type',
		'content-transfer-encoding',
		'mime-version',
		'return-path',
		'x-mailer',
	);

	/** Largest total attachment size accepted before sending (bytes). */
	const MAX_ATTACHMENT_BYTES = 30000000;

	/**
	 * Registers the hook.
	 */
	public static function init() {
		add_filter( 'pre_wp_mail', array( __CLASS__, 'pre_wp_mail' ), 10, 2 );
	}

	/**
	 * Sends the email through Vaartix.
	 *
	 * Returns null to let WordPress send the email itself (plugin disabled, not configured, or
	 * fallback after an error), true when Vaartix accepted it, false when sending failed.
	 *
	 * @param null|bool            $result Result from an earlier `pre_wp_mail` filter.
	 * @param array<string, mixed> $atts   wp_mail() arguments, after the `wp_mail` filter.
	 * @return null|bool
	 */
	public static function pre_wp_mail( $result, $atts ) {
		if ( null !== $result ) {
			return $result;
		}

		$settings = Vaartix_Settings::get();
		$api_key  = Vaartix_Settings::api_key();
		if ( ! $settings['enabled'] || '' === $api_key ) {
			return null;
		}

		$mail_data = self::mail_data( $atts );
		$payload   = self::build_payload( $mail_data, $settings );
		if ( null === $payload ) {
			return null;
		}
		if ( is_wp_error( $payload ) ) {
			return self::fail( $payload, $mail_data, $settings );
		}

		$sent = Vaartix_Api::send_email( $payload, $api_key );
		if ( is_wp_error( $sent ) ) {
			return self::fail( $sent, $mail_data, $settings );
		}

		Vaartix_Settings::record_success( $sent );
		/** Fires after an email is sent successfully, as WordPress core does. */
		do_action( 'wp_mail_succeeded', $mail_data ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WordPress hook, fired as wp_mail() would.
		return true;
	}

	/**
	 * Normalises wp_mail() arguments the same way core does.
	 *
	 * @param array<string, mixed> $atts wp_mail() arguments.
	 * @return array{to: mixed, subject: string, message: string, headers: mixed, attachments: mixed}
	 */
	private static function mail_data( $atts ) {
		$atts = is_array( $atts ) ? $atts : array();
		return array(
			'to'          => isset( $atts['to'] ) ? $atts['to'] : array(),
			'subject'     => isset( $atts['subject'] ) ? (string) $atts['subject'] : '',
			'message'     => isset( $atts['message'] ) ? (string) $atts['message'] : '',
			'headers'     => isset( $atts['headers'] ) ? $atts['headers'] : array(),
			'attachments' => isset( $atts['attachments'] ) ? $atts['attachments'] : array(),
		);
	}

	/**
	 * Builds the POST /emails body.
	 *
	 * @param array<string, mixed> $mail     Normalised wp_mail() arguments.
	 * @param array<string, mixed> $settings Plugin settings.
	 * @return array<string, mixed>|WP_Error|null Null when the message must be left to WordPress
	 *                                            (multipart bodies built by the caller).
	 */
	public static function build_payload( array $mail, array $settings ) {
		$headers      = self::parse_headers( $mail['headers'] );
		$content_type = isset( $headers['content_type'] ) ? $headers['content_type'] : 'text/plain';
		/** This filter is documented in wp-includes/pluggable.php. */
		$content_type = (string) apply_filters( 'wp_mail_content_type', $content_type ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WordPress hook, fired as wp_mail() would.

		if ( false !== stripos( $content_type, 'multipart/' ) ) {
			// The caller built its own MIME body; only PHPMailer can send that unchanged.
			return null;
		}

		$from = self::from_address( $headers, $settings );
		if ( is_wp_error( $from ) ) {
			return $from;
		}

		$to = self::address_list( $mail['to'] );
		if ( array() === $to ) {
			return new WP_Error( 'vaartix_no_recipient', __( 'The email has no recipient.', 'vaartix' ) );
		}

		$payload = array(
			'from'    => $from,
			'to'      => $to,
			'subject' => $mail['subject'],
		);

		if ( false !== stripos( $content_type, 'text/html' ) ) {
			$payload['html'] = $mail['message'];
		} else {
			$payload['text'] = $mail['message'];
		}

		foreach ( array(
			'cc'       => 'cc',
			'bcc'      => 'bcc',
			'reply_to' => 'replyTo',
		) as $key => $api_name ) {
			if ( ! empty( $headers[ $key ] ) ) {
				$payload[ $api_name ] = $headers[ $key ];
			}
		}
		if ( ! empty( $headers['custom'] ) ) {
			$payload['headers'] = $headers['custom'];
		}

		$attachments = self::attachments( $mail['attachments'] );
		if ( is_wp_error( $attachments ) ) {
			return $attachments;
		}
		if ( array() !== $attachments ) {
			$payload['attachments'] = $attachments;
		}

		/**
		 * Filters the request body sent to the Vaartix API, for example to add tags.
		 *
		 * @param array<string, mixed> $payload Request body for POST /emails.
		 * @param array<string, mixed> $mail    wp_mail() arguments.
		 */
		return apply_filters( 'vaartix_email_payload', $payload, $mail );
	}

	/**
	 * Parses wp_mail() headers (a string or an array of "Name: value" lines).
	 *
	 * @param mixed $headers Headers.
	 * @return array<string, mixed>
	 */
	public static function parse_headers( $headers ) {
		if ( ! is_array( $headers ) ) {
			$headers = explode( "\n", str_replace( "\r\n", "\n", (string) $headers ) );
		}
		$out = array(
			'cc'       => array(),
			'bcc'      => array(),
			'reply_to' => array(),
			'custom'   => array(),
		);
		foreach ( $headers as $line ) {
			if ( ! is_string( $line ) || false === strpos( $line, ':' ) ) {
				continue;
			}
			list( $name, $value ) = explode( ':', $line, 2 );
			$name                 = trim( $name );
			$value                = trim( $value );
			$lower                = strtolower( $name );
			if ( '' === $value ) {
				continue;
			}
			switch ( $lower ) {
				case 'from':
					$out['from'] = $value;
					break;
				case 'content-type':
					$parts               = explode( ';', $value );
					$out['content_type'] = trim( $parts[0] );
					break;
				case 'cc':
					$out['cc'] = array_merge( $out['cc'], self::address_list( $value ) );
					break;
				case 'bcc':
					$out['bcc'] = array_merge( $out['bcc'], self::address_list( $value ) );
					break;
				case 'reply-to':
					$out['reply_to'] = array_merge( $out['reply_to'], self::address_list( $value ) );
					break;
				default:
					if ( ! in_array( $lower, self::RESERVED_HEADERS, true ) && preg_match( '/^[A-Za-z0-9-]+$/', $name ) ) {
						$out['custom'][ $name ] = $value;
					}
			}
		}
		return $out;
	}

	/**
	 * Splits a recipient string or array into a clean list.
	 *
	 * @param mixed $value "a@x.com, Name <b@y.com>" or an array of addresses.
	 * @return list<string>
	 */
	public static function address_list( $value ) {
		if ( ! is_array( $value ) ) {
			$value = explode( ',', (string) $value );
		}
		$out = array();
		foreach ( $value as $address ) {
			$address = trim( (string) $address );
			if ( '' !== $address ) {
				$out[] = $address;
			}
		}
		return $out;
	}

	/**
	 * Works out the From address like core does, then applies the plugin's settings.
	 *
	 * @param array<string, mixed> $headers  Parsed headers.
	 * @param array<string, mixed> $settings Plugin settings.
	 * @return string|WP_Error
	 */
	private static function from_address( array $headers, array $settings ) {
		$from_email = '';
		$from_name  = '';

		if ( ! empty( $headers['from'] ) ) {
			$from = (string) $headers['from'];
			if ( preg_match( '/^(.*)<\s*([^>]+)\s*>\s*$/', $from, $match ) ) {
				$from_name  = trim( trim( $match[1] ), '"\'' );
				$from_email = trim( $match[2] );
			} else {
				$from_email = trim( $from );
			}
		}

		if ( '' === $from_email ) {
			$from_email = '' !== $settings['from_email'] ? $settings['from_email'] : self::default_from();
		}
		if ( '' === $from_name ) {
			$from_name = '' !== $settings['from_name'] ? $settings['from_name'] : 'WordPress';
		}

		/** These filters are documented in wp-includes/pluggable.php. */
		$from_email = (string) apply_filters( 'wp_mail_from', $from_email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WordPress hook, fired as wp_mail() would.
		$from_name  = (string) apply_filters( 'wp_mail_from_name', $from_name ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WordPress hook, fired as wp_mail() would.

		if ( $settings['force_from'] && '' !== $settings['from_email'] ) {
			$from_email = $settings['from_email'];
			if ( '' !== $settings['from_name'] ) {
				$from_name = $settings['from_name'];
			}
		}

		if ( ! is_email( $from_email ) ) {
			return new WP_Error(
				'vaartix_invalid_from',
				/* translators: %s: the From address. */
				sprintf( __( 'The From address "%s" is not a valid email address.', 'vaartix' ), $from_email )
			);
		}

		$from_name = trim( str_replace( array( '"', '<', '>', "\r", "\n" ), '', $from_name ) );
		return '' === $from_name ? $from_email : $from_name . ' <' . $from_email . '>';
	}

	/**
	 * WordPress's own default sender: wordpress@ the site's domain.
	 */
	private static function default_from() {
		$host = wp_parse_url( network_home_url(), PHP_URL_HOST );
		$host = is_string( $host ) ? strtolower( $host ) : '';
		if ( 0 === strpos( $host, 'www.' ) ) {
			$host = substr( $host, 4 );
		}
		return 'wordpress@' . $host;
	}

	/**
	 * Reads attachment files into the API's base64 format.
	 *
	 * @param mixed $attachments File paths, newline-separated or an array (string keys are file names).
	 * @return list<array{filename: string, content: string}>|WP_Error
	 */
	private static function attachments( $attachments ) {
		if ( empty( $attachments ) ) {
			return array();
		}
		if ( ! is_array( $attachments ) ) {
			$attachments = explode( "\n", str_replace( "\r\n", "\n", (string) $attachments ) );
		}
		$out   = array();
		$total = 0;
		foreach ( $attachments as $name => $path ) {
			$path = trim( (string) $path );
			if ( '' === $path ) {
				continue;
			}
			if ( ! is_readable( $path ) || ! is_file( $path ) ) {
				return new WP_Error(
					'vaartix_attachment_missing',
					/* translators: %s: file name. */
					sprintf( __( 'Could not read the attachment %s.', 'vaartix' ), wp_basename( $path ) )
				);
			}
			$size   = (int) filesize( $path );
			$total += $size;
			if ( $total > self::MAX_ATTACHMENT_BYTES ) {
				return new WP_Error( 'vaartix_attachments_too_large', __( 'Attachments are larger than 30 MB in total.', 'vaartix' ) );
			}
			// Reading local files the caller passed to wp_mail(); WP_Filesystem is for writing.
			$content = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false === $content ) {
				return new WP_Error(
					'vaartix_attachment_missing',
					/* translators: %s: file name. */
					sprintf( __( 'Could not read the attachment %s.', 'vaartix' ), wp_basename( $path ) )
				);
			}
			$out[] = array(
				'filename' => is_string( $name ) && '' !== $name ? $name : wp_basename( $path ),
				'content'  => base64_encode( $content ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- the API takes base64 file content.
			);
		}
		return $out;
	}

	/**
	 * Records an error, fires wp_mail_failed like core, and decides whether WordPress should retry.
	 *
	 * @param WP_Error             $error     What went wrong.
	 * @param array<string, mixed> $mail_data wp_mail() arguments.
	 * @param array<string, mixed> $settings  Plugin settings.
	 * @return null|false Null to fall back to WordPress's own sending, false otherwise.
	 */
	private static function fail( WP_Error $error, array $mail_data, array $settings ) {
		Vaartix_Settings::record_error( $error->get_error_message() );
		if ( $settings['fallback'] ) {
			return null;
		}
		/** This action is documented in wp-includes/pluggable.php. */
		do_action( 'wp_mail_failed', new WP_Error( 'wp_mail_failed', $error->get_error_message(), $mail_data ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WordPress hook, fired as wp_mail() would.
		return false;
	}
}
