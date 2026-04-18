<?php
defined( 'ABSPATH' ) || exit;

/**
 * Payout model — creation, marking paid, and queries.
 * Commission rate is snapshotted at payout creation time for auditability.
 */
class CVT_Payout {

	/**
	 * Auto-create a payout record when an item is marked sold.
	 * Called internally by CVT_Item::update_status().
	 *
	 * @param  int        $item_id
	 * @return int|WP_Error  New payout ID on success.
	 */
	public static function create_from_item( $item_id ) {
		global $wpdb;
		$item_id = absint( $item_id );

		$item = CVT_Item::get( $item_id );
		if ( ! $item ) {
			return new WP_Error( 'not_found', __( 'Item not found.', 'corido-vendor-tracker' ) );
		}

		// For listing items the fee is flat; for consignment/agency use the percentage rate.
		if ( $item->deal_type === 'listing' ) {
			$fee        = max( 0, (float) ( $item->listing_fee ?? 0 ) );
			$commission = round( $fee, 2 );
			$payout     = round( max( 0, (float) $item->selling_price - $commission ), 2 );
			$rate       = 0.0;
		} else {
			// Use item-level commission rate when set, otherwise fall back to global setting.
			// Either way the rate is snapshotted here for auditability.
			$rate    = ! is_null( $item->commission_rate )
				? (float) $item->commission_rate
				: CVT_Settings::commission_rate();
			$calcs      = self::calculate( (float) $item->selling_price, $rate );
			$commission = $calcs['commission'];
			$payout     = $calcs['payout'];
		}

		$wpdb->insert(
			CVT_DB::payouts(),
			array(
				'item_id'           => $item_id,
				'vendor_id'         => absint( $item->vendor_id ),
				'selling_price'     => $item->selling_price,
				'commission_rate'   => $rate,
				'commission_amount' => $commission,
				'payout_amount'     => $payout,
				'status'            => 'pending',
				'created_at'        => CVT_DB::now(),
				'updated_at'        => CVT_DB::now(),
			),
			array( '%d', '%d', '%f', '%f', '%f', '%f', '%s', '%s', '%s' )
		);

		$id = (int) $wpdb->insert_id;
		CVT_Activity_Log::log( 'payout', $id, 'payout_created', null, array(
			'item_id'           => $item_id,
			'commission_rate'   => $rate,
			'commission_amount' => $commission,
			'payout_amount'     => $payout,
		) );
		CVT_Item::bust_cache();
		return $id;
	}

	/**
	 * Mark a payout as paid and auto-close the linked item.
	 *
	 * @param  int    $payout_id
	 * @param  string $reference  M-Pesa code, bank ref, etc.
	 * @param  string $notes
	 * @return true|WP_Error
	 */
	public static function mark_paid( $payout_id, $reference = '', $notes = '' ) {
		global $wpdb;
		$payout_id = absint( $payout_id );

		if ( ! current_user_can( 'cvt_mark_payouts' ) ) {
			return new WP_Error( 'permission', __( 'You do not have permission to mark payouts.', 'corido-vendor-tracker' ) );
		}

		$payout = self::get( $payout_id );
		if ( ! $payout ) {
			return new WP_Error( 'not_found', __( 'Payout not found.', 'corido-vendor-tracker' ) );
		}
		if ( $payout->status === 'paid' ) {
			return new WP_Error( 'already_paid', __( 'This payout is already marked as paid.', 'corido-vendor-tracker' ) );
		}

		$wpdb->update(
			CVT_DB::payouts(),
			array(
				'status'           => 'paid',
				'payout_date'      => current_time( 'Y-m-d' ),
				'reference_number' => sanitize_text_field( $reference ),
				'notes'            => sanitize_textarea_field( $notes ),
				'processed_by'     => get_current_user_id(),
				'updated_at'       => CVT_DB::now(),
			),
			array( 'id' => $payout_id ),
			array( '%s', '%s', '%s', '%s', '%d', '%s' ),
			array( '%d' )
		);

		CVT_Activity_Log::log( 'payout', $payout_id, 'payout_marked_paid',
			array( 'status' => 'pending' ),
			array( 'status' => 'paid', 'reference' => $reference )
		);

		// Auto-close the item — done as a direct DB update to bypass the
		// normal transition validation (sold → closed is valid, but mark_paid
		// is the trigger, not an agent status change).
		$wpdb->update(
			CVT_DB::items(),
			array( 'status' => 'closed', 'updated_at' => CVT_DB::now() ),
			array( 'id' => absint( $payout->item_id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		CVT_Activity_Log::log( 'item', absint( $payout->item_id ), 'status_changed',
			array( 'status' => 'sold' ),
			array( 'status' => 'closed' ),
			'Auto-closed after payout marked paid.'
		);

		CVT_Item::bust_cache();
		return true;
	}

	/**
	 * Fetch a single payout by ID.
	 *
	 * @param  int        $id
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT p.*, v.name AS vendor_name, i.title AS item_title,
				 u.display_name AS processed_by_name
				 FROM %i p
				 LEFT JOIN {$wpdb->prefix}cvt_vendors v ON v.id = p.vendor_id
				 LEFT JOIN {$wpdb->prefix}cvt_items i ON i.id = p.item_id
				 LEFT JOIN {$wpdb->users} u ON u.ID = p.processed_by
				 WHERE p.id = %d",
				CVT_DB::payouts(),
				absint( $id )
			)
		);
	}

	/**
	 * Fetch a paginated list of payouts.
	 *
	 * @param  array $args { status, vendor_id, orderby, order, per_page, paged }
	 * @return array { items, total }
	 */
	public static function get_all( array $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'    => '',
			'vendor_id' => 0,
			'orderby'   => 'created_at',
			'order'     => 'DESC',
			'per_page'  => 20,
			'paged'     => 1,
		);
		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'p.status = %s';
			$params[] = $args['status'];
		}
		if ( ! empty( $args['vendor_id'] ) ) {
			$where[]  = 'p.vendor_id = %d';
			$params[] = absint( $args['vendor_id'] );
		}

		$allowed_orderby = array( 'created_at', 'payout_amount', 'status', 'id' );
		$orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
		$offset  = ( absint( $args['paged'] ) - 1 ) * absint( $args['per_page'] );
		$limit   = absint( $args['per_page'] );

		$where_sql = implode( ' AND ', $where );
		$tables    = "{$wpdb->prefix}cvt_payouts p
			LEFT JOIN {$wpdb->prefix}cvt_vendors v ON v.id = p.vendor_id
			LEFT JOIN {$wpdb->prefix}cvt_items i ON i.id = p.item_id
			LEFT JOIN {$wpdb->users} u ON u.ID = p.processed_by";

		$count_sql = "SELECT COUNT(*) FROM $tables WHERE $where_sql";
		$total     = (int) ( $params
			? $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) )
			: $wpdb->get_var( $count_sql ) );

		$select_sql = "SELECT p.*, v.name AS vendor_name, i.title AS item_title, u.display_name AS processed_by_name
			FROM $tables
			WHERE $where_sql
			ORDER BY p.$orderby $order
			LIMIT %d OFFSET %d";

		$query_params = array_merge( $params, array( $limit, $offset ) );
		$items        = $wpdb->get_results( $wpdb->prepare( $select_sql, ...$query_params ) );

		return array( 'items' => $items, 'total' => $total );
	}

	/**
	 * Calculate commission and vendor payout from a selling price.
	 *
	 * @param  float $selling_price
	 * @param  float $rate           Percentage, e.g. 12.5 for 12.5%.
	 * @return array { commission, payout }
	 */
	public static function calculate( $selling_price, $rate ) {
		$selling_price = (float) $selling_price;
		$rate          = (float) $rate;
		$commission    = round( $selling_price * ( $rate / 100 ), 2 );
		$payout        = round( $selling_price - $commission, 2 );
		return array( 'commission' => $commission, 'payout' => $payout );
	}

	/**
	 * Pending payout total count — for dashboard badge.
	 */
	public static function pending_count() {
		global $wpdb;
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}cvt_payouts WHERE status = 'pending'"
		);
	}
}
