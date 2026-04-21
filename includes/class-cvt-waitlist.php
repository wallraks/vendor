<?php
defined( 'ABSPATH' ) || exit;

/**
 * Waiting-list model — tracks client requests for items not yet in stock.
 */
class CVT_Waitlist {

	// -------------------------------------------------------------------------
	// CRUD
	// -------------------------------------------------------------------------

	public static function create( array $data ) {
		global $wpdb;

		if ( ! current_user_can( 'cvt_add_items' ) ) {
			return new WP_Error( 'permission', __( 'You do not have permission to add waiting list entries.', 'corido-vendor-tracker' ) );
		}

		$insert               = self::sanitize( $data );
		$insert['created_by'] = get_current_user_id();
		$insert['created_at'] = CVT_DB::now();
		$insert['updated_at'] = CVT_DB::now();

		$result = $wpdb->insert( CVT_DB::waitlist(), $insert );
		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Could not save waiting list entry.', 'corido-vendor-tracker' ) );
		}

		return (int) $wpdb->insert_id;
	}

	public static function update( $id, array $data ) {
		global $wpdb;
		$id = absint( $id );

		if ( ! self::get( $id ) ) {
			return new WP_Error( 'not_found', __( 'Waiting list entry not found.', 'corido-vendor-tracker' ) );
		}
		if ( ! current_user_can( 'cvt_add_items' ) ) {
			return new WP_Error( 'permission', __( 'You do not have permission to edit this entry.', 'corido-vendor-tracker' ) );
		}

		$update               = self::sanitize( $data );
		$update['updated_at'] = CVT_DB::now();

		$result = $wpdb->update( CVT_DB::waitlist(), $update, array( 'id' => $id ) );
		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Could not update waiting list entry.', 'corido-vendor-tracker' ) );
		}

		return true;
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT w.*, u.display_name AS agent_name
			 FROM %i w
			 LEFT JOIN {$wpdb->users} u ON u.ID = w.assigned_agent_id
			 WHERE w.id = %d",
			CVT_DB::waitlist(),
			absint( $id )
		) );
	}

	/**
	 * Paginated, filtered list.
	 *
	 * @param  array $args { search, status, category, tag, agent_id, orderby, order, per_page, paged }
	 * @return array { items, total }
	 */
	public static function get_all( array $args = array() ) {
		global $wpdb;

		$defaults = array(
			'search'   => '',
			'status'   => '',
			'category' => '',
			'tag'      => '',
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
			$where[]  = '( w.client_name LIKE %s OR w.phone LIKE %s OR w.description LIKE %s )';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'w.status = %s';
			$params[] = $args['status'];
		}
		if ( ! empty( $args['category'] ) ) {
			$where[]  = 'w.category = %s';
			$params[] = $args['category'];
		}
		if ( ! empty( $args['tag'] ) ) {
			// JSON array search: match the exact quoted tag string within the stored JSON.
			$where[]  = 'w.tags LIKE %s';
			$params[] = '%' . $wpdb->esc_like( '"' . $args['tag'] . '"' ) . '%';
		}
		if ( ! empty( $args['agent_id'] ) ) {
			$where[]  = 'w.assigned_agent_id = %d';
			$params[] = absint( $args['agent_id'] );
		}

		$allowed_orderby = array( 'created_at', 'client_name', 'status', 'category', 'budget_max' );
		$orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
		$offset  = ( absint( $args['paged'] ) - 1 ) * absint( $args['per_page'] );
		$limit   = absint( $args['per_page'] );

		$where_sql = implode( ' AND ', $where );
		$table     = CVT_DB::waitlist();

		$count_sql = "SELECT COUNT(*) FROM $table w WHERE $where_sql";
		$total     = (int) ( $params
			? $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) )
			: $wpdb->get_var( $count_sql ) );

		$select_sql = "SELECT w.*, u.display_name AS agent_name
			FROM $table w
			LEFT JOIN {$wpdb->users} u ON u.ID = w.assigned_agent_id
			WHERE $where_sql
			ORDER BY w.$orderby $order
			LIMIT %d OFFSET %d";

		$query_params = array_merge( $params, array( $limit, $offset ) );
		$items        = $wpdb->get_results( $wpdb->prepare( $select_sql, ...$query_params ) );

		return array( 'items' => $items, 'total' => $total );
	}

	public static function delete( $id ) {
		global $wpdb;
		$id = absint( $id );

		if ( ! current_user_can( 'cvt_add_items' ) ) {
			return new WP_Error( 'permission', __( 'You do not have permission to delete this entry.', 'corido-vendor-tracker' ) );
		}
		if ( ! self::get( $id ) ) {
			return new WP_Error( 'not_found', __( 'Waiting list entry not found.', 'corido-vendor-tracker' ) );
		}

		$wpdb->delete( CVT_DB::waitlist(), array( 'id' => $id ), array( '%d' ) );
		return true;
	}

	// -------------------------------------------------------------------------
	// Tags
	// -------------------------------------------------------------------------

	/**
	 * Decode a stored JSON tags value to a plain PHP array.
	 *
	 * @param  string $raw  JSON string from the DB column (may be empty).
	 * @return string[]
	 */
	public static function decode_tags( $raw ) {
		if ( ! $raw ) {
			return array();
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Return a map of tag => count across waiting-list entries.
	 * Counts only entries whose status matches $status (pass '' for all statuses).
	 *
	 * @param  string $status  'open' | 'matched' | 'fulfilled' | 'cancelled' | ''
	 * @return array  tag => count, sorted by count descending
	 */
	public static function get_tag_counts( $status = 'open' ) {
		global $wpdb;
		$table = CVT_DB::waitlist();

		if ( $status !== '' ) {
			$rows = $wpdb->get_col( $wpdb->prepare(
				"SELECT tags FROM $table WHERE status = %s AND tags != ''",
				$status
			) );
		} else {
			$rows = $wpdb->get_col( "SELECT tags FROM $table WHERE tags != ''" );
		}

		$counts = array();
		foreach ( $rows as $json ) {
			foreach ( self::decode_tags( $json ) as $tag ) {
				$tag = trim( $tag );
				if ( $tag !== '' ) {
					$counts[ $tag ] = ( $counts[ $tag ] ?? 0 ) + 1;
				}
			}
		}
		arsort( $counts );
		return $counts;
	}

	// -------------------------------------------------------------------------
	// Match detection
	// -------------------------------------------------------------------------

	/**
	 * Find open waiting-list entries whose criteria overlap with a given item.
	 * Matching rules (all must pass to be considered a match):
	 *   - status = 'open'
	 *   - category is empty OR matches item category (case-insensitive)
	 *   - budget_max is NULL OR budget_max >= item selling_price
	 *
	 * @param  object $item  A row from wp_cvt_items (with selling_price and category).
	 * @return array         Array of waitlist row objects.
	 */
	public static function find_matches( $item ) {
		global $wpdb;

		$price    = (float) $item->selling_price;
		$category = sanitize_text_field( $item->category );
		$table    = CVT_DB::waitlist();

		// Category match: entry has no category, OR category matches item.
		// Budget match: entry has no max budget, OR max budget covers the price.
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT w.*, u.display_name AS agent_name
			 FROM $table w
			 LEFT JOIN {$wpdb->users} u ON u.ID = w.assigned_agent_id
			 WHERE w.status = 'open'
			   AND ( w.category = '' OR w.category = %s )
			   AND ( w.budget_max IS NULL OR w.budget_max >= %f )
			 ORDER BY w.created_at ASC
			 LIMIT 20",
			$category,
			$price
		) );

		return $rows ?: array();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private static function sanitize( array $data ) {
		$allowed_statuses = array( 'open', 'matched', 'fulfilled', 'cancelled' );

		return array(
			'client_name'      => substr( sanitize_text_field( $data['client_name'] ?? '' ), 0, 200 ),
			'phone'            => substr( sanitize_text_field( $data['phone'] ?? '' ), 0, 50 ),
			'email'            => substr( sanitize_email( $data['email'] ?? '' ), 0, 200 ),
			'description'      => sanitize_textarea_field( $data['description'] ?? '' ),
			'category'         => substr( sanitize_text_field( $data['category'] ?? '' ), 0, 100 ),
			'budget_min'       => ! empty( $data['budget_min'] ) ? max( 0, (float) $data['budget_min'] ) : null,
			'budget_max'       => ! empty( $data['budget_max'] ) ? max( 0, (float) $data['budget_max'] ) : null,
			'quantity'         => max( 1, absint( $data['quantity'] ?? 1 ) ),
			'timeframe'        => substr( sanitize_text_field( $data['timeframe'] ?? '' ), 0, 200 ),
			'notes'            => sanitize_textarea_field( $data['notes'] ?? '' ),
			'tags'             => self::normalize_tags( $data['tags'] ?? '' ),
			'status'           => in_array( $data['status'] ?? 'open', $allowed_statuses, true )
				? $data['status'] : 'open',
			'matched_item_id'  => ! empty( $data['matched_item_id'] ) ? absint( $data['matched_item_id'] ) : null,
			'assigned_agent_id' => ! empty( $data['assigned_agent_id'] ) ? absint( $data['assigned_agent_id'] ) : null,
		);
	}

	/**
	 * Normalise raw tag input (comma-separated string or array) into a
	 * JSON-encoded array string for storage, or '' if no tags given.
	 */
	private static function normalize_tags( $raw ) {
		$tags = is_array( $raw ) ? $raw : explode( ',', (string) $raw );
		$tags = array_values( array_unique( array_filter( array_map(
			function( $t ) { return substr( sanitize_text_field( trim( $t ) ), 0, 100 ); },
			$tags
		), function( $t ) { return $t !== ''; } ) ) );
		return $tags ? (string) wp_json_encode( $tags ) : '';
	}
}
