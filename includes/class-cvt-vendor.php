<?php
defined( 'ABSPATH' ) || exit;

/**
 * Vendor model — CRUD operations with integrated activity logging.
 */
class CVT_Vendor {

	/**
	 * Create a new vendor.
	 *
	 * @param  array      $data  Unsanitized input array.
	 * @return int|WP_Error      New vendor ID on success.
	 */
	public static function create( array $data ) {
		global $wpdb;

		if ( ! current_user_can( 'cvt_add_vendors' ) ) {
			return new WP_Error( 'permission', __( 'You do not have permission to add vendors.', 'corido-vendor-tracker' ) );
		}

		$insert = self::sanitize( $data );
		$insert['created_by'] = get_current_user_id();
		$insert['created_at'] = CVT_DB::now();
		$insert['updated_at'] = CVT_DB::now();

		$result = $wpdb->insert( CVT_DB::vendors(), $insert );
		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Could not save vendor.', 'corido-vendor-tracker' ) );
		}

		$id = (int) $wpdb->insert_id;
		CVT_Activity_Log::log( 'vendor', $id, 'created', null, $insert );
		return $id;
	}

	/**
	 * Update an existing vendor.
	 *
	 * @param  int        $id
	 * @param  array      $data  Unsanitized input array.
	 * @return true|WP_Error
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		$id = absint( $id );

		$vendor = self::get( $id );
		if ( ! $vendor ) {
			return new WP_Error( 'not_found', __( 'Vendor not found.', 'corido-vendor-tracker' ) );
		}

		// Own vs any-vendor permission.
		$is_owner = (int) $vendor->created_by === get_current_user_id();
		$cap      = $is_owner ? 'cvt_edit_own_vendor' : 'cvt_edit_any_vendor';
		if ( ! current_user_can( $cap ) ) {
			return new WP_Error( 'permission', __( 'You do not have permission to edit this vendor.', 'corido-vendor-tracker' ) );
		}

		$update             = self::sanitize( $data );
		$update['updated_at'] = CVT_DB::now();

		$result = $wpdb->update( CVT_DB::vendors(), $update, array( 'id' => $id ) );
		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Could not update vendor.', 'corido-vendor-tracker' ) );
		}

		CVT_Activity_Log::log( 'vendor', $id, 'updated', (array) $vendor, $update );
		return true;
	}

	/**
	 * Fetch a single vendor by ID.
	 *
	 * @param  int        $id
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM %i WHERE id = %d", CVT_DB::vendors(), absint( $id ) )
		);
	}

	/**
	 * Fetch a paginated, filtered list of vendors.
	 *
	 * @param  array $args { search, agent_id, orderby, order, per_page, paged }
	 * @return array { items, total }
	 */
	public static function get_all( array $args = array() ) {
		global $wpdb;

		$defaults = array(
			'search'   => '',
			'agent_id' => 0,
			'orderby'  => 'created_at',
			'order'    => 'DESC',
			'per_page' => 20,
			'paged'    => 1,
		);
		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '( v.name LIKE %s OR v.phone_primary LIKE %s OR v.phone_secondary LIKE %s OR v.email LIKE %s )';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		if ( ! empty( $args['agent_id'] ) ) {
			$where[]  = 'v.assigned_agent_id = %d';
			$params[] = absint( $args['agent_id'] );
		}

		$allowed_orderby = array( 'created_at', 'name', 'id' );
		$orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		$offset  = ( absint( $args['paged'] ) - 1 ) * absint( $args['per_page'] );
		$limit   = absint( $args['per_page'] );

		$where_sql = implode( ' AND ', $where );

		// Count total.
		$count_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}cvt_vendors v WHERE $where_sql";
		$total     = (int) ( $params
			? $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) )
			: $wpdb->get_var( $count_sql ) );

		// Fetch rows — join to get item counts and agent name.
		$select_sql = "SELECT v.*,
			u.display_name AS agent_name,
			( SELECT COUNT(*) FROM {$wpdb->prefix}cvt_items i WHERE i.vendor_id = v.id ) AS item_count
			FROM {$wpdb->prefix}cvt_vendors v
			LEFT JOIN {$wpdb->users} u ON u.ID = v.assigned_agent_id
			WHERE $where_sql
			ORDER BY v.$orderby $order
			LIMIT %d OFFSET %d";

		$query_params   = array_merge( $params, array( $limit, $offset ) );
		$items          = $wpdb->get_results( $wpdb->prepare( $select_sql, ...$query_params ) );

		return array( 'items' => $items, 'total' => $total );
	}

	/**
	 * Delete a vendor (admin only). Also removes their items.
	 *
	 * @param  int        $id
	 * @return true|WP_Error
	 */
	public static function delete( $id ) {
		global $wpdb;
		$id = absint( $id );

		if ( ! current_user_can( 'cvt_delete_vendors' ) ) {
			return new WP_Error( 'permission', __( 'You do not have permission to delete vendors.', 'corido-vendor-tracker' ) );
		}

		$vendor = self::get( $id );
		if ( ! $vendor ) {
			return new WP_Error( 'not_found', __( 'Vendor not found.', 'corido-vendor-tracker' ) );
		}

		$wpdb->delete( CVT_DB::vendors(), array( 'id' => $id ), array( '%d' ) );
		CVT_Activity_Log::log( 'vendor', $id, 'deleted', (array) $vendor, null );
		return true;
	}

	/**
	 * Search vendors by name or phone — used for typeahead in item form.
	 *
	 * @param  string $query
	 * @param  int    $limit
	 * @return array
	 */
	public static function search( $query, $limit = 10 ) {
		global $wpdb;
		$like = '%' . $wpdb->esc_like( sanitize_text_field( $query ) ) . '%';
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, name, phone_primary FROM %i
				 WHERE name LIKE %s OR phone_primary LIKE %s OR phone_secondary LIKE %s
				 ORDER BY name ASC LIMIT %d",
				CVT_DB::vendors(), $like, $like, $like, absint( $limit )
			)
		);
	}

	/**
	 * Sanitize and validate vendor input fields.
	 */
	private static function sanitize( array $data ) {
		$allowed_channels = array( 'phone', 'whatsapp', 'email', 'walkin' );
		return array(
			'name'            => sanitize_text_field( $data['name'] ?? '' ),
			'phone_primary'   => sanitize_text_field( $data['phone_primary'] ?? '' ),
			'phone_secondary' => sanitize_text_field( $data['phone_secondary'] ?? '' ),
			'email'           => sanitize_email( $data['email'] ?? '' ),
			'location'        => sanitize_text_field( $data['location'] ?? '' ),
			'intake_channel'  => in_array( $data['intake_channel'] ?? 'phone', $allowed_channels, true )
				? $data['intake_channel']
				: 'phone',
			'notes'           => sanitize_textarea_field( $data['notes'] ?? '' ),
			'assigned_agent_id' => ! empty( $data['assigned_agent_id'] ) ? absint( $data['assigned_agent_id'] ) : null,
		);
	}
}
