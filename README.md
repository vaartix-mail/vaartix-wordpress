# Vaartix for WordPress

Send all WordPress email (contact forms, WooCommerce, password resets) through the
[Vaartix](https://vaartix.com) transactional email API. Data stored in India.

The plugin hooks `pre_wp_mail`, so every `wp_mail()` call, from core, themes and any plugin, goes
through `POST https://api.vaartix.com/emails` instead of PHP `mail()`.

## Install

1. Download [vaartix.zip](https://github.com/vaartix-mail/vaartix-wordpress/releases/latest/download/vaartix.zip)
   (always the latest release) and upload it in **Plugins → Add New → Upload Plugin**, or copy
   this folder to `wp-content/plugins/vaartix`.
2. Activate it, then open **Settings → Vaartix**.
3. Paste a send-only API key from [app.vaartix.com](https://app.vaartix.com), set the From email
   to an address on your verified domain, and click **Send test email**.

Keep the key out of the database by adding it to `wp-config.php`:

```php
define( 'VAARTIX_API_KEY', 'vx_your_api_key' );
```

## Settings

| Setting | What it does |
|---|---|
| Send with Vaartix | Turn sending through Vaartix on or off |
| API key | Your Vaartix key (or `VAARTIX_API_KEY` in `wp-config.php`) |
| From email / name | Used when an email doesn't set its own From |
| Force From | Always use the From above, even when a plugin sets its own |
| Fallback | If Vaartix can't send, let WordPress send that email itself (off by default) |

## Hooks

- `vaartix_email_payload` filter: change the request body, for example to add tags.
- Standard `wp_mail_succeeded` and `wp_mail_failed` actions fire as in core.
- Core's `wp_mail`, `wp_mail_from`, `wp_mail_from_name` and `wp_mail_content_type` filters are
  respected.

Messages whose caller builds its own `multipart/*` body are left to WordPress, because only
PHPMailer can send those unchanged.

## Development

```bash
php tests/run.php          # mail logic tests, no WordPress needed
composer install && vendor/bin/phpcs   # WordPress security, i18n and PHP compatibility checks
```

## License

GPL-2.0-or-later. Vaartix is a product of NAP Designs, Bengaluru.
