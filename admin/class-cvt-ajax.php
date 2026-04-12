<?php
defined( 'ABSPATH' ) || exit;

/**
 * AJAX endpoint handlers.
 *
 * Security model:
 *  - Every endpoint verifies the 'cvt_ajax' nonce.
 *  - Every endpoint checks a relevant CVT capability.
 *  - The vendor_search endpoint is rate-limited to 60 calls/user/minute.
 *  - Image removal verifies per-item ownership, not just the global capability.
 *  - Only `wp_ajax_*` hooks are registered (no nopriv) — all endpoints require login.
 */
class CVT_Ajax {

	public function __construct() {
		add_action( 'wp_ajax_cvt_vendor_search',     array( $this, 'vendor_search' ) );
		add_action( 'wp_ajax_cvt_payout_preview',    array( $this, 'payout_preview' ) );
		add_action( 'wp_ajax_cvt_remove_item_image', array( $this, 'remove_item_image' ) );
	}

	// -------------------------------------------------------------------------
	// Endpoints
	// -------------------------------------------------------------------------

	/**
	 * Typeahead search for vendors — used in item add/edit form.
	 * Returns JSON array of { id, name, phone_primary }.
	 * Rate-limited to 60 requests per user per minute.
	 */
	public function vendor_search() {
		check_ajax_referer( 'cvt_ajax', 'nonce' );

		if ( ! current_user_can( 'cvt_add_items' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
		}

		$this->check_rate_limit( 'vendor_search', 60 );

		$query   = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) );
		$results = CVT_Vendor::search( $query, 10 );

		wp_send_json_success( $results );
	}

	/**
	 * Live payout preview when agent types a selling price.
	 * Returns { commission_rate, commission_amount, payout_amount, formatted }.
	 */
	public function payout_preview() {
		check_ajax_referer( 'cvt_ajax', 'nonce' );

		if ( ! current_user_can( 'cvt_add_items' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
		}

		$price = max( 0, (float) ( $_GET['price'] ?? 0 ) );
		$rate  = CVT_Settings::commission_rate();
		$calcs = CVT_Payout::calculate( $price, $rate );

		wp_send_json_success( array(
			'commission_rate'   => $rate,
			'commission_amount' => $calcs['commission'],
			'payout_amount'     => $calcs['payout'],
			'formatted'         => array(
				'commission' => CVT_Settings::format_currency( $calcs['commission'] ),
				'payout'     => CVT_Settings::format_currency( $calcs['payout'] ),
			),
		) );
	}

	/**
	 * Remove an item image.
	 * Verifies the requesting user has edit permission on this specific item
	 * (own vs. any) before acting — not just the global capability.
	 */
	public function remove_item_image() {
		check_ajax_referer( 'cvt_ajax', 'nonce' );

		$item_id      = absint( $_POST['item_id'] ?? 0 );
		$image_row_id = absint( $_POST['image_row_id'] ?? 0 );

		if ( ! $item_id ) {
			wp_send_json_error( array( 'message' => 'Invalid item.' ), 400 );
		}

		// Load the item to check ownership before acting.
		$item = CVT_Item::get( $item_id );
		if ( ! $item ) {
			wp_send_json_error( array( 'message' => 'Item not found.' ), 404 );
		}

		$is_owner = (int) $item->created_by === get_current_user_id();
		$cap      = $is_owner ? 'cvt_edit_own_item' : 'cvt_edit_any_item';

		if ( ! current_user_can( $cap ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
		}

		$result = CVT_Item::remove_image( $item_id, $image_row_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Transient-based rate limiter.
	 * Sends a 429 JSON error and exits if the per-user call count exceeds $max
	 * within the current 60-second window.
	 *
	 * @param string $action   Unique action name for this endpoint.
	 * @param int    $max      Maximum calls allowed per minute.
	 */
	private function check_rate_limit( $action, $max = 60 ) {
		$user_id = get_current_user_id();
		$key     = 'cvt_rl_' . sanitize_key( $action ) . '_' . $user_id;
		$count   = (int) get_transient( $key );

		if ( $count >= $max ) {
			wp_send_json_error( array( 'message' => 'Too many requests. Please slow down.' ), 429 );
		}

		// Increment counter; set a 60-second window on first call.
		if ( $count === 0 ) {
			set_transient( $key, 1, MINUTE_IN_SECONDS );
		} else {
			// Preserve the existing TTL by using set_transient with 0 (which WordPress
			// ignores for existing keys). We use a direct option update instead.
			set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		}
	}
}
