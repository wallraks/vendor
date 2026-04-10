<?php
defined( 'ABSPATH' ) || exit;

/**
 * Item model — CRUD, status transitions, and image management.
 */
class CVT_Item {

	/**
	 * Create a new item.
	 *
	 * @param  array      $data
	 * @return int|WP_Error  New item ID on success.
	 */
	public static function create( array $data ) {
		global $wpdb;

		if ( ! current_user_can( 'cvt_add_items' ) ) {
			return new WP_Error( 'permission', __( 'You do not have permission to add items.', 'corido-vendor-tracker' ) );
		}

		$insert             = self::sanitize( $data );
		$insert['status']   = 'under_review';
		$insert['created_by'] = get_current_user_id();
		$insert['created_at'] = CVT_DB::now();
		$insert['updated_at'] = CVT_DB::now();

		if ( empty( $insert['date_received'] ) ) {
			$insert['date_received'] = current_time( 'Y-m-d' );
		}

		$result = $wpdb->insert( CVT_DB::items(), $insert );
		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Could not save item.', 'corido-vendor-tracker' ) );
		}

		$id = (int) $wpdb->insert_id;
		CVT_Activity_Log::log( 'item', $id, 'created', null, $insert );
		return $id;
	}

	/**
	 * Update an existing item's details (not status — use update_status() for that).
	 *
	 * @param  int        $id
	 * @param  array      $data
	 * @return true|WP_Error
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		$id = absint( $id );

		$item = self::get( $id );
		if ( ! $item ) {
			return new WP_Error( 'not_found', __( 'Item not found.', 'corido-vendor-tracker' ) );
		}

		$is_owner = (int) $item->created_by === get_current_user_id();
		$cap      = $is_owner ? 'cvt_edit_own_item' : 'cvt_edit_any_item';
		if ( ! current_user_can( $cap ) ) {
			return new WP_Error( 'permission', __( 'You do not have permission to edit this item.', 'corido-vendor-tracker' ) );
		}

		// Prevent editing terminal items without admin override.
		if ( in_array( $item->status, array( 'closed', 'withdrawn' ), true ) && ! current_user_can( 'cvt_manage_settings' ) ) {
			return new WP_Error( 'terminal', __( 'This item is closed and cannot be edited.', 'corido-vendor-tracker' ) );
		}

		$update               = self::sanitize( $data );
		$update['updated_at'] = CVT_DB::now();

		// Don't allow status to be changed via the general update path.
		unset( $update['status'] );

		$result = $wpdb->update( CVT_DB::items(), $update, array( 'id' => $id ) );
		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Could not update item.', 'corido-vendor-tracker' ) );
		}

		CVT_Activity_Log::log( 'item', $id, 'updated', (array) $item, $update );
		return true;
	}

	/**
	 * Transition an item's status.
	 *
	 * @param  int    $id
	 * @param  string $new_status
	 * @param  string $note        Optional agent note attached to this transition.
	 * @param  bool   $force       Admin bypass for invalid transitions.
	 * @return true|WP_Error
	 */
	public static function update_status( $id, $new_status, $note = '', $force = false ) {
		global $wpdb;
		$id = absint( $id );

		$item = self::get( $id );
		if ( ! $item ) {
			return new WP_Error( 'not_found', __( 'Item not found.', 'corido-vendor-tracker' ) );
		}

		$old_status = $item->status;

		// Validate status value.
		if ( ! in_array( $new_status, CVT_Settings::all_statuses(), true ) ) {
			return new WP_Error( 'invalid_status', __( 'Invalid status.', 'corido-vendor-tracker' ) );
		}

		// Validate transition unless admin is forcing it.
		$valid = CVT_Settings::valid_transitions( $old_status );
		if ( ! in_array( $new_status, $valid, true ) ) {
			if ( ! $force || ! current_user_can( 'cvt_manage_settings' ) ) {
				/* translators: 1: old status label, 2: new status label */
				return new WP_Error( 'invalid_transition', sprintf(
					__( 'Cannot transition from %1$s to %2$s.', 'corido-vendor-tracker' ),
					CVT_Settings::status_info( $old_status )['label'],
					CVT_Settings::status_info( $new_status )['label']
				) );
			}
		}

		// Capability checks for privileged transitions.
		if ( in_array( $new_status, array( 'sold' ), true ) && ! current_user_can( 'cvt_update_status_sold' ) ) {
			return new WP_Error( 'permission', __( 'You do not have permission to mark items as sold.', 'corido-vendor-tracker' ) );
		}
		if ( $new_status === 'withdrawn' && ! current_user_can( 'cvt_update_status_withdrawn' ) ) {
			return new WP_Error( 'permission', __( 'You do not have permission to withdraw items.', 'corido-vendor-tracker' ) );
		}

		$extra = array();
		if ( $new_status === 'posted' ) {
			$extra['date_posted'] = current_time( 'Y-m-d' );
		}

		$wpdb->update(
			CVT_DB::items(),
			array_merge( array( 'status' => $new_status, 'updated_at' => CVT_DB::now() ), $extra ),
			array( 'id' => $id )
		);

		CVT_Activity_Log::log(
			'item', $id, 'status_changed',
			array( 'status' => $old_status ),
			array( 'status' => $new_status ),
			$note
		);

		// Auto-create payout record when item is sold.
		if ( $new_status === 'sold' ) {
			CVT_Payout::create_from_item( $id );
		}

		return true;
	}

	/**
	 * Fetch a single item by ID (with vendor name via JOIN).
	 *
	 * @param  int        $id
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT i.*, v.name AS vendor_name, v.phone_primary AS vendor_phone,
				 u.display_name AS agent_name
				 FROM %i i
				 LEFT JOIN {$wpdb->prefix}cvt_vendors v ON v.id = i.vendor_id
				 LEFT JOIN {$wpdb->users} u ON u.ID = i.assigned_agent_id
				 WHERE i.id = %d",
				CVT_DB::items(),
				absint( $id )
			)
		);
	}

	/**
	 * Fetch a paginated, filtered list of items.
	 *
	 * @param  array $args { search, vendor_id, status, category, agent_id, orderby, order, per_page, paged }
	 * @return array { items, total }
	 */
	public static function get_all( array $args = array() ) {
		global $wpdb;

		$defaults = array(
			'search'    => '',
			'vendor_id' => 0,
			'status'    => '',
			'category'  => '',
			'agent_id'  => 0,
			'orderby'   => 'created_at',
			'order'     => 'DESC',
			'per_page'  => 20,
			'paged'     => 1,
		);
		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '( i.title LIKE %s OR v.name LIKE %s OR v.phone_primary LIKE %s )';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		if ( ! empty( $args['vendor_id'] ) ) {
			$where[]  = 'i.vendor_id = %d';
			$params[] = absint( $args['vendor_id'] );
		}
		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'i.status = %s';
			$params[] = $args['status'];
		}
		if ( ! empty( $args['category'] ) ) {
			$where[]  = 'i.category = %s';
			$params[] = $args['category'];
		}
		if ( ! empty( $args['agent_id'] ) ) {
			$where[]  = 'i.assigned_agent_id = %d';
			$params[] = absint( $args['agent_id'] );
		}

		$allowed_orderby = array( 'created_at', 'title', 'status', 'selling_price', 'id' );
		$orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
		$offset  = ( absint( $args['paged'] ) - 1 ) * absint( $args['per_page'] );
		$limit   = absint( $args['per_page'] );

		$where_sql = implode( ' AND ', $where );
		$tables    = "{$wpdb->prefix}cvt_items i
			LEFT JOIN {$wpdb->prefix}cvt_vendors v ON v.id = i.vendor_id
			LEFT JOIN {$wpdb->users} u ON u.ID = i.assigned_agent_id";

		$count_sql = "SELECT COUNT(*) FROM $tables WHERE $where_sql";
		$total     = (int) ( $params
			? $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) )
			: $wpdb->get_var( $count_sql ) );

		$select_sql = "SELECT i.*, v.name AS vendor_name, u.display_name AS agent_name
			FROM $tables
			WHERE $where_sql
			ORDER BY i.$orderby $order
			LIMIT %d OFFSET %d";

		$query_params = array_merge( $params, array( $limit, $offset ) );
		$items        = $wpdb->get_results( $wpdb->prepare( $select_sql, ...$query_params ) );

		return array( 'items' => $items, 'total' => $total );
	}

	/**
	 * Permanently delete an item (admin only).
	 *
	 * @param  int        $id
	 * @return true|WP_Error
	 */
	public static function delete( $id ) {
		global $wpdb;
		$id = absint( $id );

		if ( ! current_user_can( 'cvt_delete_items' ) ) {
			return new WP_Error( 'permission', __( 'You do not have permission to delete items.', 'corido-vendor-tracker' ) );
		}

		$item = self::get( $id );
		if ( ! $item ) {
			return new WP_Error( 'not_found', __( 'Item not found.', 'corido-vendor-tracker' ) );
		}

		$wpdb->delete( CVT_DB::items(), array( 'id' => $id ), array( '%d' ) );
		$wpdb->delete( CVT_DB::images(), array( 'item_id' => $id ), array( '%d' ) );
		CVT_Activity_Log::log( 'item', $id, 'deleted', (array) $item, null );
		return true;
	}

	/**
	 * Attach a WordPress media attachment to an item.
	 *
	 * @param  int        $item_id
	 * @param  int        $attachment_id
	 * @return int|WP_Error  New image row ID.
	 */
	public static function add_image( $item_id, $attachment_id ) {
		global $wpdb;
		$item_id       = absint( $item_id );
		$attachment_id = absint( $attachment_id );

		$wpdb->insert(
			CVT_DB::images(),
			array(
				'item_id'       => $item_id,
				'attachment_id' => $attachment_id,
				'sort_order'    => 0,
				'created_at'    => CVT_DB::now(),
			),
			array( '%d', '%d', '%d', '%s' )
		);

		CVT_Activity_Log::log( 'item', $item_id, 'image_added', null, array( 'attachment_id' => $attachment_id ) );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Remove a single image from an item.
	 *
	 * @param  int  $item_id
	 * @param  int  $image_row_id  ID from cvt_item_images table.
	 * @return true|WP_Error
	 */
	public static function remove_image( $item_id, $image_row_id ) {
		global $wpdb;
		$wpdb->delete(
			CVT_DB::images(),
			array( 'id' => absint( $image_row_id ), 'item_id' => absint( $item_id ) ),
			array( '%d', '%d' )
		);
		CVT_Activity_Log::log( 'item', absint( $item_id ), 'image_removed', array( 'image_row_id' => $image_row_id ), null );
		return true;
	}

	/**
	 * Get all images for an item.
	 *
	 * @param  int   $item_id
	 * @return array
	 */
	public static function get_images( $item_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE item_id = %d ORDER BY sort_order ASC, id ASC",
				CVT_DB::images(),
				absint( $item_id )
			)
		);
	}

	/**
	 * Get status counts for the dashboard — results cached 5 minutes.
	 *
	 * @return array { status => count }
	 */
	public static function get_status_counts() {
		$cached = get_transient( 'cvt_status_counts' );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT status, COUNT(*) AS cnt FROM {$wpdb->prefix}cvt_items GROUP BY status"
		);

		$counts = array();
		foreach ( CVT_Settings::all_statuses() as $s ) {
			$counts[ $s ] = 0;
		}
		foreach ( $rows as $row ) {
			$counts[ $row->status ] = (int) $row->cnt;
		}

		set_transient( 'cvt_status_counts', $counts, 5 * MINUTE_IN_SECONDS );
		return $counts;
	}

	/**
	 * Invalidate the status-count cache after any mutation.
	 */
	public static function bust_cache() {
		delete_transient( 'cvt_status_counts' );
	}

	/**
	 * Sanitize and validate item input fields.
	 */
	private static function sanitize( array $data ) {
		$allowed_types = array( 'consignment', 'agency' );
		return array(
			'vendor_id'          => absint( $data['vendor_id'] ?? 0 ),
			'title'              => sanitize_text_field( $data['title'] ?? '' ),
			'description'        => sanitize_textarea_field( $data['description'] ?? '' ),
			'category'           => sanitize_text_field( $data['category'] ?? '' ),
			'market_value'       => ! empty( $data['market_value'] ) ? (float) $data['market_value'] : null,
			'selling_price'      => (float) ( $data['selling_price'] ?? 0 ),
			'deal_type'          => in_array( $data['deal_type'] ?? 'consignment', $allowed_types, true )
				? $data['deal_type'] : 'consignment',
			'assigned_agent_id'  => ! empty( $data['assigned_agent_id'] ) ? absint( $data['assigned_agent_id'] ) : null,
			'listivo_listing_url' => esc_url_raw( $data['listivo_listing_url'] ?? '' ),
			'date_received'      => ! empty( $data['date_received'] ) ? sanitize_text_field( $data['date_received'] ) : null,
			'notes'              => sanitize_textarea_field( $data['notes'] ?? '' ),
		);
	}
}
