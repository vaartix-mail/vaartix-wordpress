=== Vaartix ===
Contributors: vaartix
Tags: email, smtp, transactional email, wp_mail, deliverability
Requires at least: 5.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send all WordPress email through Vaartix, the transactional email API built in India. Better deliverability, data stored in India.

== Description ==

WordPress sends email with PHP mail() by default, which is often unauthenticated and lands in spam. This plugin sends every email from WordPress, including contact forms, WooCommerce orders, password resets and notifications, through the [Vaartix](https://vaartix.com) email API instead.

* Works with every plugin that uses wp_mail(): Contact Form 7, WPForms, Elementor forms, WooCommerce, Gravity Forms and more
* Sends from your own domain with DKIM, SPF and DMARC set up in Vaartix
* Keeps attachments, HTML, CC, BCC, Reply-To and custom headers
* Optional "force From" so every email uses your verified address
* Optional fallback to WordPress's default sending if the API is unreachable
* "Send test email" button and a status box with the last email and last error
* API key can live in wp-config.php instead of the database
* Fires the standard wp_mail_succeeded and wp_mail_failed actions, so logging plugins keep working

= External service =

This plugin connects to the Vaartix API (https://api.vaartix.com) to send your site's emails. Every time WordPress sends an email, the plugin sends the email's sender, recipients, subject, body, headers and attachments to Vaartix, which delivers it. Nothing is sent when the plugin is disabled or has no API key.

Vaartix is operated by SNAP Technologies, Bengaluru, India. Email data is stored in India (AWS Mumbai).

* Terms of Service: https://vaartix.com/terms
* Privacy Policy: https://vaartix.com/privacy
* Data Processing Agreement: https://vaartix.com/dpa

== Installation ==

1. Install and activate the plugin.
2. In Vaartix (https://app.vaartix.com), verify your sending domain and create a send-only API key limited to that domain.
3. In WordPress, go to Settings → Vaartix, paste the API key and set the From email to an address on your verified domain.
4. Click "Send test email".

To keep the key out of the database, add this to wp-config.php instead of entering it on the settings page:

`define( 'VAARTIX_API_KEY', 'vx_your_api_key' );`

== Frequently Asked Questions ==

= Do I need a Vaartix account? =

Yes. Vaartix has a free plan with 5,000 emails a month. Request access at https://vaartix.com.

= Which From address should I use? =

An address on a domain you have verified in Vaartix, for example noreply@notify.yourdomain.com. Turn on "Force From" if other plugins set their own From addresses.

= What happens if Vaartix can't send an email? =

The error is shown under Settings → Vaartix → Status, and WordPress's wp_mail_failed action fires. If you turn on Fallback, WordPress sends that email the default way instead.

= Can I add tags to emails? =

Yes, with the vaartix_email_payload filter:

`add_filter( 'vaartix_email_payload', function ( $payload ) { $payload['tags'][] = array( 'name' => 'source', 'value' => 'wordpress' ); return $payload; } );`

= Does it work with SMTP plugins? =

Use either this plugin or an SMTP plugin, not both. If you prefer SMTP, see https://vaartix.com/docs/guides/wordpress-smtp/.

== Changelog ==

= 0.1.0 =
* First release: wp_mail() through the Vaartix API, settings page, test email, status, fallback and force From.
