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
delete_option( 'cvt_sold_sync_enabled' );
delete_option( 'cvt_sold_sync_meta_key' );
delete_option( 'cvt_sold_sync_meta_value' );
delete_option( 'cvt_sold_sync_taxonomy' );
delete_option( 'cvt_sold_sync_term' );
delete_transient( 'cvt_sold_sync_pending' );

// Remove our own sync markers from listings. The sold flag itself is left in
// place — it is the theme's own field, and those items really did sell.
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_cvt_sold_synced' ) );

remove_role( 'cvt_admin' );
remove_role( 'cvt_senior_agent' );
remove_role( 'cvt_junior_agent' );
