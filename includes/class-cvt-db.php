<?php
defined( 'ABSPATH' ) || exit;

/**
 * Thin helper to centralise table name references.
 * Usage: CVT_DB::vendors(), CVT_DB::items(), etc.
 */
class CVT_DB {

	public static function vendors() {
		global $wpdb;
		return $wpdb->prefix . 'cvt_vendors';
	}

	public static function items() {
		global $wpdb;
		return $wpdb->prefix . 'cvt_items';
	}

	public static function images() {
		global $wpdb;
		return $wpdb->prefix . 'cvt_item_images';
	}

	public static function payouts() {
		global $wpdb;
		return $wpdb->prefix . 'cvt_payouts';
	}

	public static function waitlist() {
		global $wpdb;
		return $wpdb->prefix . 'cvt_waitlist';
	}

	public static function activity() {
		global $wpdb;
		return $wpdb->prefix . 'cvt_activity_log';
	}

	/**
	 * Returns a timestamp string suitable for created_at / updated_at columns.
	 */
	public static function now() {
		return current_time( 'mysql' );
	}
}
