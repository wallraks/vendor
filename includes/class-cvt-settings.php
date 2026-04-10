<?php
defined( 'ABSPATH' ) || exit;

/**
 * Thin wrapper around wp_options for plugin settings.
 */
class CVT_Settings {

	/**
	 * Returns commission rate as a float (e.g. 12.5 for 12.5%).
	 */
	public static function commission_rate() {
		return (float) get_option( 'cvt_commission_rate', 12 );
	}

	/**
	 * Returns item categories as an array of trimmed strings.
	 */
	public static function categories() {
		$raw = get_option( 'cvt_categories', '' );
		$lines = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
		return array_values( $lines );
	}

	/**
	 * Persist settings. Validates before saving.
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
			$cats = sanitize_textarea_field( $data['categories'] );
			update_option( 'cvt_categories', $cats );
		}

		return true;
	}

	/**
	 * Human-readable label for an intake channel.
	 */
	public static function intake_channel_label( $channel ) {
		$labels = array(
			'phone'    => 'Phone Call',
			'whatsapp' => 'WhatsApp',
			'email'    => 'Email',
			'walkin'   => 'Walk-in',
		);
		return $labels[ $channel ] ?? ucfirst( $channel );
	}

	/**
	 * Human-readable label for a deal type.
	 */
	public static function deal_type_label( $type ) {
		return ucfirst( $type );
	}

	/**
	 * Human-readable label and CSS class for an item status.
	 *
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

	/**
	 * Returns an array of all valid item statuses.
	 */
	public static function all_statuses() {
		return array( 'under_review', 'posted', 'inquiry_received', 'sold', 'closed', 'withdrawn' );
	}

	/**
	 * Returns valid next statuses from a given current status.
	 * Admins may bypass this via force param in CVT_Item::update_status().
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
	 * Format a number as KES currency.
	 */
	public static function format_currency( $amount ) {
		return 'KES ' . number_format( (float) $amount, 2 );
	}
}
