<?php
/**
 * Removes the plugin's options when it is deleted.
 *
 * @package Vaartix
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'vaartix_settings' );
delete_option( 'vaartix_status' );
