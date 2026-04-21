<?php
defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin activation, deactivation, and database table creation.
 */
class CVT_Activator {

	public static function activate() {
		self::create_tables();
		update_option( 'cvt_db_version', CVT_DB_VERSION );
		CVT_Roles::register();
		CVT_Roles::sync_external_roles();
		// Set defaults only on first activation.
		if ( false === get_option( 'cvt_commission_rate' ) ) {
			update_option( 'cvt_commission_rate', '12' );
		}
		if ( false === get_option( 'cvt_categories' ) ) {
			update_option( 'cvt_categories', implode( "\n", array(
				'Furniture',
				'Electronics',
				'Home Appliances',
				'Décor',
				'Kitchenware',
				'Automobiles',
				'General / Mixed Goods',
			) ) );
		}
		flush_rewrite_rules();
	}

	/**
	 * Runs on every plugins_loaded. Uses dbDelta to add any missing columns
	 * for existing installs when CVT_DB_VERSION is bumped.
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'cvt_db_version' ) === CVT_DB_VERSION ) {
			return;
		}
		self::create_tables();

		// dbDelta cannot modify existing enum definitions — do it explicitly.
		global $wpdb;
		$col = $wpdb->get_row( "SHOW COLUMNS FROM {$wpdb->prefix}cvt_items LIKE 'deal_type'" );
		if ( $col && strpos( $col->Type, 'listing' ) === false ) {
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}cvt_items MODIFY deal_type enum('consignment','agency','listing') NOT NULL DEFAULT 'consignment'" );
		}

		// Add tags column to waitlist table if not present.
		$tags_col = $wpdb->get_row( "SHOW COLUMNS FROM {$wpdb->prefix}cvt_waitlist LIKE 'tags'" );
		if ( ! $tags_col ) {
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}cvt_waitlist ADD COLUMN tags text NOT NULL DEFAULT '' AFTER notes" );
		}

		CVT_Roles::sync_external_roles();
		update_option( 'cvt_db_version', CVT_DB_VERSION );
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Creates all plugin database tables using dbDelta for safe upgrades.
	 */
	private static function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Vendors table.
		$sql[] = "CREATE TABLE {$wpdb->prefix}cvt_vendors (
			id              bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name            varchar(200) NOT NULL,
			phone_primary   varchar(50) NOT NULL DEFAULT '',
			phone_secondary varchar(50) NOT NULL DEFAULT '',
			email           varchar(200) NOT NULL DEFAULT '',
			location        varchar(300) NOT NULL DEFAULT '',
			apartment_name  varchar(200) NOT NULL DEFAULT '',
			house_number    varchar(100) NOT NULL DEFAULT '',
			intake_channel  enum('phone','whatsapp','email','walkin') NOT NULL DEFAULT 'phone',
			notes           text,
			assigned_agent_id bigint(20) UNSIGNED DEFAULT NULL,
			created_by      bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			created_at      datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at      datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY assigned_agent_id (assigned_agent_id),
			KEY created_at (created_at)
		) $charset_collate;";

		// Items table.
		$sql[] = "CREATE TABLE {$wpdb->prefix}cvt_items (
			id               bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			vendor_id        bigint(20) UNSIGNED NOT NULL,
			title            varchar(500) NOT NULL DEFAULT '',
			description      text,
			category         varchar(100) NOT NULL DEFAULT '',
			market_value     decimal(12,2) DEFAULT NULL,
			selling_price    decimal(12,2) NOT NULL DEFAULT 0.00,
			commission_rate  decimal(5,2) DEFAULT NULL,
			listing_fee      decimal(10,2) DEFAULT NULL,
			deal_type        enum('consignment','agency','listing') NOT NULL DEFAULT 'consignment',
			status           enum('under_review','posted','inquiry_received','sold','closed','withdrawn') NOT NULL DEFAULT 'under_review',
			assigned_agent_id bigint(20) UNSIGNED DEFAULT NULL,
			agreement_attachment_id bigint(20) UNSIGNED DEFAULT NULL,
			listivo_listing_url varchar(500) NOT NULL DEFAULT '',
			date_received    date DEFAULT NULL,
			date_posted      date DEFAULT NULL,
			notes            text,
			created_by       bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			created_at       datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at       datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY vendor_id (vendor_id),
			KEY status (status),
			KEY assigned_agent_id (assigned_agent_id),
			KEY created_at (created_at)
		) $charset_collate;";

		// Item images table.
		$sql[] = "CREATE TABLE {$wpdb->prefix}cvt_item_images (
			id            bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			item_id       bigint(20) UNSIGNED NOT NULL,
			attachment_id bigint(20) UNSIGNED NOT NULL,
			sort_order    tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
			created_at    datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY item_id (item_id)
		) $charset_collate;";

		// Payouts table.
		$sql[] = "CREATE TABLE {$wpdb->prefix}cvt_payouts (
			id                bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			item_id           bigint(20) UNSIGNED NOT NULL,
			vendor_id         bigint(20) UNSIGNED NOT NULL,
			selling_price     decimal(12,2) NOT NULL DEFAULT 0.00,
			commission_rate   decimal(5,2) NOT NULL DEFAULT 0.00,
			commission_amount decimal(12,2) NOT NULL DEFAULT 0.00,
			payout_amount     decimal(12,2) NOT NULL DEFAULT 0.00,
			status            enum('pending','paid') NOT NULL DEFAULT 'pending',
			payout_date       date DEFAULT NULL,
			reference_number  varchar(200) NOT NULL DEFAULT '',
			notes             text,
			processed_by      bigint(20) UNSIGNED DEFAULT NULL,
			created_at        datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at        datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY item_id (item_id),
			KEY vendor_id (vendor_id),
			KEY status (status)
		) $charset_collate;";

		// Waiting list table.
		$sql[] = "CREATE TABLE {$wpdb->prefix}cvt_waitlist (
			id               bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			client_name      varchar(200) NOT NULL DEFAULT '',
			phone            varchar(50)  NOT NULL DEFAULT '',
			email            varchar(200) NOT NULL DEFAULT '',
			description      text,
			category         varchar(100) NOT NULL DEFAULT '',
			budget_min       decimal(12,2) DEFAULT NULL,
			budget_max       decimal(12,2) DEFAULT NULL,
			quantity         smallint(5) UNSIGNED NOT NULL DEFAULT 1,
			timeframe        varchar(200) NOT NULL DEFAULT '',
			notes            text,
			tags             text NOT NULL DEFAULT '',
			status           enum('open','matched','fulfilled','cancelled') NOT NULL DEFAULT 'open',
			matched_item_id  bigint(20) UNSIGNED DEFAULT NULL,
			assigned_agent_id bigint(20) UNSIGNED DEFAULT NULL,
			created_by       bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			created_at       datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at       datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY status (status),
			KEY category (category),
			KEY created_at (created_at),
			KEY assigned_agent_id (assigned_agent_id)
		) $charset_collate;";

		// Activity log table.
		$sql[] = "CREATE TABLE {$wpdb->prefix}cvt_activity_log (
			id          bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			entity_type enum('vendor','item','payout') NOT NULL,
			entity_id   bigint(20) UNSIGNED NOT NULL,
			action      varchar(100) NOT NULL DEFAULT '',
			old_value   longtext,
			new_value   longtext,
			note        text,
			user_id     bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			created_at  datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY entity (entity_type, entity_id),
			KEY created_at (created_at),
			KEY user_id (user_id)
		) $charset_collate;";

		foreach ( $sql as $query ) {
			dbDelta( $query );
		}
	}
}
