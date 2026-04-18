<?php
defined( 'ABSPATH' ) || exit;

/**
 * Thin wrapper around wp_options for plugin settings.
 *
 * v1.1: Added Listivo taxonomy integration for categories.
 * Categories are now sourced live from the configured Listivo taxonomy when
 * it exists, with a transparent fallback to the manual textarea list.
 */
class CVT_Settings {

	// -------------------------------------------------------------------------
	// Commission
	// -------------------------------------------------------------------------

	/**
	 * Returns commission rate as a float (e.g. 12.5 for 12.5%).
	 */
	public static function commission_rate() {
		return (float) get_option( 'cvt_commission_rate', 12 );
	}

	// -------------------------------------------------------------------------
	// Categories — Listivo taxonomy integration
	// -------------------------------------------------------------------------

	/**
	 * Returns the configured Listivo taxonomy slug.
	 * Defaults to 'listivo_category', the slug used by Listivo's CT framework.
	 */
	public static function get_listivo_taxonomy() {
		return sanitize_key( get_option( 'cvt_listivo_taxonomy', 'listivo_category' ) );
	}

	/**
	 * Returns the configured Listivo listing post type slug.
	 * Defaults to 'listivo1_listing', the CPT registered by the Listivo theme.
	 */
	public static function get_listivo_post_type() {
		return sanitize_key( get_option( 'cvt_listivo_post_type', 'listivo1_listing' ) );
	}

	// -------------------------------------------------------------------------
	// Agent Role Configuration
	// -------------------------------------------------------------------------

	/**
	 * Returns the role slugs whose users appear in "Assigned Agent" dropdowns.
	 * Falls back to the four built-in CVT roles + administrator if nothing is saved.
	 *
	 * @return string[]
	 */
	public static function get_assignable_roles() {
		$saved = get_option( 'cvt_assignable_roles' );
		if ( ! is_array( $saved ) || empty( $saved ) ) {
			return array( 'administrator', 'cvt_admin', 'cvt_senior_agent', 'cvt_junior_agent' );
		}
		return array_values( array_filter( array_map( 'sanitize_key', $saved ) ) );
	}

	/**
	 * Returns item categories as an array of strings, pulled from:
	 *   1. The configured Listivo taxonomy (if it exists and has terms), or
	 *   2. The manual categories textarea as a fallback.
	 *
	 * @return string[]
	 */
	public static function categories() {
		$taxonomy = self::get_listivo_taxonomy();

		if ( $taxonomy && taxonomy_exists( $taxonomy ) ) {
			$terms = get_terms( array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
				'fields'     => 'names',
			) );
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				return array_values( $terms );
			}
		}

		// Fallback: manual list from plugin settings.
		$raw   = get_option( 'cvt_categories', '' );
		$lines = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
		return array_values( $lines );
	}

	/**
	 * Returns the current category source status for the Settings UI.
	 *
	 * @return array { source: 'listivo'|'listivo_empty'|'manual', taxonomy: string, count: int }
	 */
	public static function categories_source_info() {
		$taxonomy = self::get_listivo_taxonomy();

		if ( $taxonomy && taxonomy_exists( $taxonomy ) ) {
			$count = wp_count_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
			$count = is_wp_error( $count ) ? 0 : (int) $count;
			return array(
				'source'   => $count > 0 ? 'listivo' : 'listivo_empty',
				'taxonomy' => $taxonomy,
				'count'    => $count,
			);
		}

		$manual = self::categories();
		return array(
			'source'   => 'manual',
			'taxonomy' => $taxonomy,
			'count'    => count( $manual ),
		);
	}

	// -------------------------------------------------------------------------
	// Settings persistence
	// -------------------------------------------------------------------------

	/**
	 * Persist settings. All values are validated before saving.
	 *
	 * @param  array $data  Associative array of setting key => value.
	 * @return true|WP_Error
	 */
	public static function save( array $data ) {
		if ( ! current_user_can( 'cvt_manage_settings' ) ) {
			return new WP_Error( 'permission', __( 'You do not have permission to change settings.', 'corido-vendor-tracker' ) );
		}

		if ( isset( $data['commission_rate'] ) ) {
			$rate = (float) $data['commission_rate'];
			if ( $rate < 0 || $rate > 100 ) {
				return new WP_Error( 'invalid_rate', __( 'Commission rate must be between 0 and 100.', 'corido-vendor-tracker' ) );
			}
			update_option( 'cvt_commission_rate', $rate );
		}

		if ( isset( $data['categories'] ) ) {
			update_option( 'cvt_categories', sanitize_textarea_field( $data['categories'] ) );
		}

		if ( isset( $data['cvt_listivo_taxonomy'] ) ) {
			update_option( 'cvt_listivo_taxonomy', sanitize_key( $data['cvt_listivo_taxonomy'] ) );
		}

		if ( isset( $data['cvt_listivo_post_type'] ) ) {
			update_option( 'cvt_listivo_post_type', sanitize_key( $data['cvt_listivo_post_type'] ) );
		}

		// Roles form submits a sentinel field so we can detect "all unchecked".
		if ( isset( $data['cvt_roles_submitted'] ) ) {
			$roles = array_values( array_filter(
				array_map( 'sanitize_key', (array) ( $data['cvt_assignable_roles'] ?? array() ) )
			) );
			if ( empty( $roles ) ) {
				return new WP_Error(
					'no_roles',
					__( 'Please select at least one role for agent assignment.', 'corido-vendor-tracker' )
				);
			}
			update_option( 'cvt_assignable_roles', $roles );
		}

		return true;
	}

	// -------------------------------------------------------------------------
	// Labels & helpers
	// -------------------------------------------------------------------------

	public static function intake_channel_label( $channel ) {
		$labels = array(
			'phone'    => 'Phone Call',
			'whatsapp' => 'WhatsApp',
			'email'    => 'Email',
			'walkin'   => 'Walk-in',
		);
		return $labels[ $channel ] ?? ucfirst( $channel );
	}

	public static function deal_type_label( $type ) {
		return ucfirst( $type );
	}

	/**
	 * Human-readable label and CSS class for an item status.
	 * @return array { label, class }
	 */
	public static function status_info( $status ) {
		$map = array(
			'under_review'     => array( 'label' => 'Under Review',     'class' => 'cvt-badge--review' ),
			'posted'           => array( 'label' => 'Posted',           'class' => 'cvt-badge--posted' ),
			'inquiry_received' => array( 'label' => 'Inquiry Received', 'class' => 'cvt-badge--inquiry' ),
			'sold'             => array( 'label' => 'Sold',             'class' => 'cvt-badge--sold' ),
			'closed'           => array( 'label' => 'Closed',           'class' => 'cvt-badge--closed' ),
			'withdrawn'        => array( 'label' => 'Withdrawn',        'class' => 'cvt-badge--withdrawn' ),
		);
		return $map[ $status ] ?? array( 'label' => ucfirst( $status ), 'class' => '' );
	}

	/** Returns all valid item status slugs. */
	public static function all_statuses() {
		return array( 'under_review', 'posted', 'inquiry_received', 'sold', 'closed', 'withdrawn' );
	}

	/**
	 * The main linear pipeline stages (excludes 'withdrawn' which is a side branch).
	 * Used by the status stepper on the item detail page.
	 */
	public static function pipeline_stages() {
		return array( 'under_review', 'posted', 'inquiry_received', 'sold', 'closed' );
	}

	/**
	 * Returns valid next statuses from a given current status.
	 * Admins may bypass this via the force flag in CVT_Item::update_status().
	 */
	public static function valid_transitions( $from ) {
		$map = array(
			'under_review'     => array( 'posted', 'withdrawn' ),
			'posted'           => array( 'inquiry_received', 'withdrawn' ),
			'inquiry_received' => array( 'posted', 'sold', 'withdrawn' ),
			'sold'             => array( 'closed' ),
			'closed'           => array(),
			'withdrawn'        => array(),
		);
		return $map[ $from ] ?? array();
	}

	/**
	 * Reverse transitions allowed only for users with cvt_manage_settings.
	 * Used when a deal falls through (sold → back on market) or a vendor
	 * changes their mind after withdrawal.
	 *
	 * Reversing from 'sold' automatically voids any pending payout record.
	 * Reversing from 'closed' is never allowed — payout was already paid out.
	 */
	public static function reverse_transitions( $from ) {
		$map = array(
			'sold'      => array( 'inquiry_received', 'posted', 'withdrawn' ),
			'withdrawn' => array( 'under_review' ),
		);
		return $map[ $from ] ?? array();
	}

	/** Format a number as KES currency. */
	public static function format_currency( $amount ) {
		return 'KES ' . number_format( (float) $amount, 2 );
	}

	/**
	 * Maximum allowed lengths for text fields — enforced in sanitize() methods
	 * to prevent oversized input from reaching the DB.
	 */
	public static function max_lengths() {
		return array(
			'name'                => 200,
			'phone'               => 50,
			'email'               => 200,
			'location'            => 300,
			'apartment_name'      => 200,
			'house_number'        => 100,
			'title'               => 500,
			'category'            => 100,
			'listivo_listing_url' => 500,
		);
	}
}
