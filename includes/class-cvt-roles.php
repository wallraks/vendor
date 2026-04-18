<?php
defined( 'ABSPATH' ) || exit;

/**
 * Registers custom roles and capabilities for the plugin.
 *
 * Three tiers:
 *  - cvt_admin        Full plugin access, no WordPress admin access beyond what's needed.
 *  - cvt_senior_agent Can add/edit all records, mark payouts, view full logs; cannot change settings.
 *  - cvt_junior_agent Can add vendors/items and update limited statuses; cannot delete or mark payouts.
 *
 * WordPress 'administrator' role also receives all CVT capabilities.
 */
class CVT_Roles {

	/**
	 * All capabilities, keyed by tier (highest grants all below it).
	 */
	private static function capability_map() {
		return array(
			// Settings & administration.
			'cvt_manage_settings'          => array( 'admin' ),
			'cvt_manage_users'             => array( 'admin' ),

			// Reporting.
			'cvt_view_reports'             => array( 'admin', 'senior' ),
			'cvt_view_all_logs'            => array( 'admin', 'senior' ),

			// Vendor CRUD.
			'cvt_add_vendors'              => array( 'admin', 'senior', 'junior' ),
			'cvt_edit_own_vendor'          => array( 'admin', 'senior', 'junior' ),
			'cvt_edit_any_vendor'          => array( 'admin', 'senior' ),
			'cvt_delete_vendors'           => array( 'admin' ),

			// Item CRUD.
			'cvt_add_items'                => array( 'admin', 'senior', 'junior' ),
			'cvt_edit_own_item'            => array( 'admin', 'senior', 'junior' ),
			'cvt_edit_any_item'            => array( 'admin', 'senior' ),
			'cvt_delete_items'             => array( 'admin' ),

			// Status transitions.
			'cvt_update_status_posted'     => array( 'admin', 'senior', 'junior' ),
			'cvt_update_status_sold'       => array( 'admin', 'senior' ),
			'cvt_update_status_withdrawn'  => array( 'admin', 'senior' ),

			// Payouts.
			'cvt_view_payouts'             => array( 'admin', 'senior' ),
			'cvt_mark_payouts'             => array( 'admin', 'senior' ),
		);
	}

	/**
	 * Register roles and assign capabilities.
	 * Called on 'init' and on plugin activation.
	 */
	public static function register() {
		$map = self::capability_map();

		$tiers = array(
			'admin'  => array(),
			'senior' => array(),
			'junior' => array(),
		);

		foreach ( $map as $cap => $allowed_tiers ) {
			foreach ( $allowed_tiers as $tier ) {
				$tiers[ $tier ][ $cap ] = true;
			}
		}

		// Create or refresh custom roles.
		self::add_or_update_role(
			'cvt_admin',
			__( 'CR Admin', 'corido-vendor-tracker' ),
			array_merge( array( 'read' => true ), $tiers['admin'] )
		);
		self::add_or_update_role(
			'cvt_senior_agent',
			__( 'CR Senior Agent', 'corido-vendor-tracker' ),
			array_merge( array( 'read' => true ), $tiers['senior'] )
		);
		self::add_or_update_role(
			'cvt_junior_agent',
			__( 'CR Junior Agent', 'corido-vendor-tracker' ),
			array_merge( array( 'read' => true ), $tiers['junior'] )
		);

		// Grant all CVT capabilities to WordPress administrators.
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			foreach ( array_keys( $map ) as $cap ) {
				$admin_role->add_cap( $cap );
			}
		}
	}

	/**
	 * Remove CVT roles. Called on plugin uninstall.
	 */
	public static function remove() {
		remove_role( 'cvt_admin' );
		remove_role( 'cvt_senior_agent' );
		remove_role( 'cvt_junior_agent' );

		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			foreach ( array_keys( self::capability_map() ) as $cap ) {
				$admin_role->remove_cap( $cap );
			}
		}
	}

	/**
	 * Return WordPress users eligible to be assigned as agents.
	 * The roles queried are driven by Settings → Agent Role Configuration.
	 * Falls back to CVT roles + administrator if nothing is configured.
	 *
	 * @return WP_User[]
	 */
	public static function get_agents() {
		$roles = CVT_Settings::get_assignable_roles();
		return get_users( array(
			'role__in' => $roles,
			'orderby'  => 'display_name',
			'order'    => 'ASC',
		) );
	}

	/**
	 * Helper to create or update a role's capabilities without removing it first.
	 */
	private static function add_or_update_role( $role_slug, $display_name, array $caps ) {
		$role = get_role( $role_slug );
		if ( $role ) {
			// Sync capabilities: add new, remove stale.
			foreach ( $caps as $cap => $granted ) {
				if ( $granted ) {
					$role->add_cap( $cap );
				} else {
					$role->remove_cap( $cap );
				}
			}
		} else {
			add_role( $role_slug, $display_name, $caps );
		}
	}
}
