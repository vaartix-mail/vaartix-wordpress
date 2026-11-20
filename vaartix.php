<?php
/**
 * Plugin Name:       Vaartix
 * Plugin URI:        https://github.com/vaartix-mail/vaartix-wordpress
 * Description:       Send all WordPress email (contact forms, WooCommerce, password resets) through the Vaartix transactional email API. Data stored in India.
 * Version:           0.1.1
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Vaartix
 * Author URI:        https://vaartix.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       vaartix
 *
 * @package Vaartix
 */

defined( 'ABSPATH' ) || exit;

define( 'VAARTIX_PLUGIN_VERSION', '0.1.1' );
define( 'VAARTIX_PLUGIN_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-vaartix-settings.php';
require_once __DIR__ . '/includes/class-vaartix-api.php';
require_once __DIR__ . '/includes/class-vaartix-mailer.php';
require_once __DIR__ . '/includes/class-vaartix-admin.php';

Vaartix_Mailer::init();

if ( is_admin() ) {
	Vaartix_Admin::init();
}
