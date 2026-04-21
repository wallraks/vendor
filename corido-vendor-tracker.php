<?php
/**
 * Plugin Name:       CR Business Suite
 * Plugin URI:        https://corido.co.ke
 * Description:       Internal business management suite for Corido Marketplace — manage vendors, items, deal tracking, commissions, payouts, and waiting lists from WordPress admin.
 * Version:           1.7.3
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Corido Marketplace
 * Author URI:        https://corido.co.ke
 * Text Domain:       corido-vendor-tracker
 * License:           GPL-2.0+
 */

defined( 'ABSPATH' ) || exit;

define( 'CVT_VERSION',     '1.7.3' );
define( 'CVT_DB_VERSION',  '9' );
define( 'CVT_PLUGIN_FILE', __FILE__ );
define( 'CVT_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'CVT_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

// Core includes — order matters for class dependencies.
require_once CVT_PLUGIN_DIR . 'includes/class-cvt-activator.php';
require_once CVT_PLUGIN_DIR . 'includes/class-cvt-db.php';
require_once CVT_PLUGIN_DIR . 'includes/class-cvt-settings.php';
require_once CVT_PLUGIN_DIR . 'includes/class-cvt-activity-log.php';
require_once CVT_PLUGIN_DIR . 'includes/class-cvt-vendor.php';
require_once CVT_PLUGIN_DIR . 'includes/class-cvt-item.php';
require_once CVT_PLUGIN_DIR . 'includes/class-cvt-payout.php';
require_once CVT_PLUGIN_DIR . 'includes/class-cvt-roles.php';
require_once CVT_PLUGIN_DIR . 'includes/class-cvt-waitlist.php';

register_activation_hook( __FILE__, array( 'CVT_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CVT_Activator', 'deactivate' ) );

add_action( 'init',           array( 'CVT_Roles',     'register' ) );
add_action( 'plugins_loaded', array( 'CVT_Activator', 'maybe_upgrade' ) );

if ( is_admin() ) {
	require_once CVT_PLUGIN_DIR . 'admin/class-cvt-admin.php';
	require_once CVT_PLUGIN_DIR . 'admin/class-cvt-ajax.php';
	add_action( 'plugins_loaded', function () {
		static $booted = false;
		if ( $booted ) {
			return;
		}
		$booted = true;
		new CVT_Admin();
		new CVT_Ajax();
	} );
}
