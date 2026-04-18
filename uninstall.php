<?php
/**
 * Plugin uninstall handler.
 *
 * Runs only when WordPress deletes the plugin via Plugins → Delete.
 * Drops all CVT tables and removes all plugin options and custom roles.
 *
 * Deactivation alone does NOT trigger this file, so data is safe on
 * temporary deactivation.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$tables = array(
	'cvt_activity_log',
	'cvt_item_images',
	'cvt_payouts',
	'cvt_waitlist',
	'cvt_items',
	'cvt_vendors',
);
foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" );
}

delete_option( 'cvt_db_version' );
delete_option( 'cvt_commission_rate' );
delete_option( 'cvt_listing_fee_default' );
delete_option( 'cvt_categories' );
delete_option( 'cvt_listivo_taxonomy' );
delete_option( 'cvt_listivo_post_type' );
delete_option( 'cvt_assignable_roles' );

remove_role( 'cvt_admin' );
remove_role( 'cvt_senior_agent' );
remove_role( 'cvt_junior_agent' );
