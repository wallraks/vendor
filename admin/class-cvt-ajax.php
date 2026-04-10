<?php
defined( 'ABSPATH' ) || exit;

/**
 * AJAX endpoint handlers.
 * All endpoints require a 'cvt_ajax' nonce and logged-in user.
 */
class CVT_Ajax {

	public function __construct() {
		add_action( 'wp_ajax_cvt_vendor_search',     array( $this, 'vendor_search' ) );
		add_action( 'wp_ajax_cvt_payout_preview',    array( $this, 'payout_preview' ) );
		add_action( 'wp_ajax_cvt_remove_item_image', array( $this, 'remove_item_image' ) );
	}

	/**
	 * Typeahead search for vendors — used in item add/edit form.
	 * Returns JSON array of { id, name, phone_primary }.
	 */
	public function vendor_search() {
		check_ajax_referer( 'cvt_ajax', 'nonce' );

		if ( ! current_user_can( 'cvt_add_items' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
		}

		$query   = sanitize_text_field( $_GET['q'] ?? '' );
		$results = CVT_Vendor::search( $query, 10 );

		wp_send_json_success( $results );
	}

	/**
	 * Live payout preview when agent changes the selling price in item form.
	 * Returns { commission_rate, commission_amount, payout_amount }.
	 */
	public function payout_preview() {
		check_ajax_referer( 'cvt_ajax', 'nonce' );

		$price = (float) ( $_GET['price'] ?? 0 );
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
	 */
	public function remove_item_image() {
		check_ajax_referer( 'cvt_ajax', 'nonce' );

		if ( ! current_user_can( 'cvt_edit_own_item' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
		}

		$item_id      = absint( $_POST['item_id'] ?? 0 );
		$image_row_id = absint( $_POST['image_row_id'] ?? 0 );

		$result = CVT_Item::remove_image( $item_id, $image_row_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success();
	}
}
