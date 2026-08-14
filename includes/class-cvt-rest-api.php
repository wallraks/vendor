<?php
defined( 'ABSPATH' ) || exit;

/**
 * REST API for CR Business Suite.
 * Base namespace: corido/v1
 *
 * All routes require current_user_can('edit_posts').
 *
 * POST   /corido/v1/vendors              — create vendor (+ optional linked item)
 * GET    /corido/v1/vendors              — list vendors with pagination
 * GET    /corido/v1/vendors/{id}         — single vendor + their items
 * PATCH  /corido/v1/vendors/{id}         — update vendor fields
 * DELETE /corido/v1/vendors/{id}         — delete vendor + all their items
 * POST   /corido/v1/vendors/{id}/items   — add item to existing vendor
 */
class CVT_REST_API {

	const NAMESPACE = 'corido/v1';

	// -------------------------------------------------------------------------
	// Route registration
	// -------------------------------------------------------------------------

	public static function register_routes() {
		$ns = self::NAMESPACE;

		register_rest_route( $ns, '/vendors', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'list_vendors' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'page'     => array( 'type' => 'integer', 'default' => 1,  'minimum' => 1 ),
					'per_page' => array( 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ),
					's'        => array( 'type' => 'string',  'default' => '' ),
				),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'create_vendor' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => self::vendor_args( true ),
			),
		) );

		register_rest_route( $ns, '/vendors/(?P<id>\d+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_vendor' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			),
			array(
				'methods'             => 'PATCH',
				'callback'            => array( __CLASS__, 'update_vendor' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => self::vendor_args( false ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'delete_vendor' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			),
		) );

		register_rest_route( $ns, '/vendors/(?P<id>\d+)/items', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'create_item' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'title'         => array( 'type' => 'string', 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ),
					'category'      => array( 'type' => 'string', 'default'  => '',    'sanitize_callback' => 'sanitize_text_field' ),
					'selling_price' => array( 'type' => 'number', 'default'  => 0,     'minimum' => 0 ),
					'deal_type'     => array(
						'type'    => 'string',
						'default' => 'consignment',
						'enum'    => array( 'consignment', 'agency', 'listing' ),
					),
					'listing_url'   => array( 'type' => 'string', 'default' => '' ),
					'listing_id'    => array( 'type' => 'string', 'default' => '' ),
					'notes'         => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field' ),
				),
			),
		) );
	}

	// -------------------------------------------------------------------------
	// Shared argument definitions
	// -------------------------------------------------------------------------

	private static function vendor_args( $required ) {
		return array(
			'name'              => array( 'type' => 'string', 'required' => $required, 'sanitize_callback' => 'sanitize_text_field' ),
			'phone_primary'     => array( 'type' => 'string', 'required' => $required, 'sanitize_callback' => 'sanitize_text_field' ),
			'phone_secondary'   => array( 'type' => 'string', 'default'  => '',        'sanitize_callback' => 'sanitize_text_field' ),
			'email'             => array( 'type' => 'string', 'default'  => '',        'sanitize_callback' => 'sanitize_email' ),
			'location'          => array( 'type' => 'string', 'default'  => '',        'sanitize_callback' => 'sanitize_text_field' ),
			'apartment_name'    => array( 'type' => 'string', 'default'  => '',        'sanitize_callback' => 'sanitize_text_field' ),
			'house_number'      => array( 'type' => 'string', 'default'  => '',        'sanitize_callback' => 'sanitize_text_field' ),
			'intake_channel'    => array(
				'type'    => 'string',
				'default' => 'whatsapp',
				'enum'    => array( 'phone', 'whatsapp', 'email', 'walkin' ),
			),
			'notes'             => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field' ),
			// Listing fields — if listing_title is provided, a cvt_items row is auto-created.
			'listing_id'        => array( 'type' => 'string', 'default' => '' ),
			'listing_url'       => array( 'type' => 'string', 'default' => '' ),
			'listing_title'     => array( 'type' => 'string', 'default' => '' ),
			'listing_price'     => array( 'type' => 'number', 'default' => 0, 'minimum' => 0 ),
			'listing_category'  => array( 'type' => 'string', 'default' => '' ),
		);
	}

	// -------------------------------------------------------------------------
	// Permission
	// -------------------------------------------------------------------------

	public static function check_permission( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_forbidden', 'Authentication required.', array( 'status' => 401 ) );
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error( 'rest_forbidden', 'You do not have permission to use this API.', array( 'status' => 403 ) );
		}
		return true;
	}

	// -------------------------------------------------------------------------
	// GET /vendors
	// -------------------------------------------------------------------------

	public static function list_vendors( $request ) {
		global $wpdb;

		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$search   = sanitize_text_field( $request->get_param( 's' ) );
		$offset   = ( $page - 1 ) * $per_page;

		$where  = array( '1=1' );
		$params = array();

		if ( $search !== '' ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '( v.name LIKE %s OR v.phone_primary LIKE %s OR v.email LIKE %s )';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}cvt_vendors v WHERE $where_sql";
		$total     = (int) ( $params
			? $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) )
			: $wpdb->get_var( $count_sql ) );

		$select_sql = "SELECT v.id, v.name, v.phone_primary, v.phone_secondary,
			v.email, v.location, v.intake_channel, v.created_at,
			( SELECT COUNT(*) FROM {$wpdb->prefix}cvt_items i WHERE i.vendor_id = v.id ) AS item_count
			FROM {$wpdb->prefix}cvt_vendors v
			WHERE $where_sql
			ORDER BY v.created_at DESC
			LIMIT %d OFFSET %d";

		$query_params = array_merge( $params, array( $per_page, $offset ) );
		$vendors      = $wpdb->get_results( $wpdb->prepare( $select_sql, ...$query_params ) );

		return new WP_REST_Response( array(
			'vendors'     => $vendors,
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $total / $per_page ),
		), 200 );
	}

	// -------------------------------------------------------------------------
	// POST /vendors
	// -------------------------------------------------------------------------

	public static function create_vendor( $request ) {
		global $wpdb;

		$name  = substr( sanitize_text_field( $request->get_param( 'name' ) ?? '' ), 0, 200 );
		$phone = substr( sanitize_text_field( $request->get_param( 'phone_primary' ) ?? '' ), 0, 50 );

		if ( $name === '' ) {
			return new WP_Error( 'missing_name', 'Vendor name is required.', array( 'status' => 400 ) );
		}
		if ( $phone === '' ) {
			return new WP_Error( 'missing_phone', 'Phone number is required.', array( 'status' => 400 ) );
		}

		$allowed_channels = array( 'phone', 'whatsapp', 'email', 'walkin' );
		$channel          = in_array( $request->get_param( 'intake_channel' ), $allowed_channels, true )
			? $request->get_param( 'intake_channel' )
			: 'whatsapp';

		$insert = array(
			'name'             => $name,
			'phone_primary'    => $phone,
			'phone_secondary'  => substr( sanitize_text_field( $request->get_param( 'phone_secondary' ) ?? '' ), 0, 50 ),
			'email'            => substr( sanitize_email( $request->get_param( 'email' ) ?? '' ), 0, 200 ),
			'location'         => substr( sanitize_text_field( $request->get_param( 'location' ) ?? '' ), 0, 300 ),
			'apartment_name'   => substr( sanitize_text_field( $request->get_param( 'apartment_name' ) ?? '' ), 0, 200 ),
			'house_number'     => substr( sanitize_text_field( $request->get_param( 'house_number' ) ?? '' ), 0, 100 ),
			'intake_channel'   => $channel,
			'notes'            => sanitize_textarea_field( $request->get_param( 'notes' ) ?? '' ),
			'created_by'       => get_current_user_id(),
			'created_at'       => CVT_DB::now(),
			'updated_at'       => CVT_DB::now(),
		);

		$result = $wpdb->insert( CVT_DB::vendors(), $insert );
		if ( false === $result ) {
			return new WP_Error( 'db_error', 'Could not save vendor: ' . $wpdb->last_error, array( 'status' => 500 ) );
		}

		$vendor_id = (int) $wpdb->insert_id;
		CVT_Activity_Log::log( 'vendor', $vendor_id, 'created', null, $insert );

		// Auto-create a linked item when listing details are provided.
		$item_id      = null;
		$item_message = '';
		$listing_title = substr( sanitize_text_field( $request->get_param( 'listing_title' ) ?? '' ), 0, 500 );

		if ( $listing_title !== '' ) {
			$item_id      = self::insert_item( $vendor_id, array(
				'title'       => $listing_title,
				'category'    => sanitize_text_field( $request->get_param( 'listing_category' ) ?? '' ),
				'price'       => (float) $request->get_param( 'listing_price' ),
				'listing_url' => $request->get_param( 'listing_url' ) ?? '',
				'listing_id'  => sanitize_text_field( $request->get_param( 'listing_id' ) ?? '' ),
				'notes'       => '',
				'deal_type'   => 'consignment',
			) );

			if ( is_wp_error( $item_id ) ) {
				$item_message = ' Item could not be saved: ' . $item_id->get_error_message();
				$item_id      = null;
			}
		}

		$message = $item_id
			? 'Vendor and item created.'
			: ( $listing_title !== '' ? 'Vendor created.' . $item_message : 'Vendor created.' );

		return new WP_REST_Response( array(
			'success'   => true,
			'vendor_id' => $vendor_id,
			'item_id'   => $item_id,
			'message'   => $message,
		), 201 );
	}

	// -------------------------------------------------------------------------
	// GET /vendors/{id}
	// -------------------------------------------------------------------------

	public static function get_vendor( $request ) {
		global $wpdb;

		$id     = absint( $request->get_param( 'id' ) );
		$vendor = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}cvt_vendors WHERE id = %d",
			$id
		) );

		if ( ! $vendor ) {
			return new WP_Error( 'not_found', 'Vendor not found.', array( 'status' => 404 ) );
		}

		$items = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, title, category, selling_price, deal_type, status,
			 listivo_listing_url, date_received, date_posted, created_at
			 FROM {$wpdb->prefix}cvt_items
			 WHERE vendor_id = %d
			 ORDER BY created_at DESC",
			$id
		) );

		return new WP_REST_Response( array(
			'vendor' => $vendor,
			'items'  => $items,
		), 200 );
	}

	// -------------------------------------------------------------------------
	// PATCH /vendors/{id}
	// -------------------------------------------------------------------------

	public static function update_vendor( $request ) {
		global $wpdb;

		$id     = absint( $request->get_param( 'id' ) );
		$vendor = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}cvt_vendors WHERE id = %d",
			$id
		) );

		if ( ! $vendor ) {
			return new WP_Error( 'not_found', 'Vendor not found.', array( 'status' => 404 ) );
		}

		$params = $request->get_params();
		$update = array( 'updated_at' => CVT_DB::now() );

		// Simple text/email fields — only include when the caller passed them.
		$text_fields = array(
			'name'           => 200,
			'phone_primary'  => 50,
			'phone_secondary'=> 50,
			'location'       => 300,
			'apartment_name' => 200,
			'house_number'   => 100,
		);
		foreach ( $text_fields as $field => $max_len ) {
			if ( array_key_exists( $field, $params ) ) {
				$update[ $field ] = substr( sanitize_text_field( $params[ $field ] ), 0, $max_len );
			}
		}

		if ( array_key_exists( 'email', $params ) ) {
			$update['email'] = substr( sanitize_email( $params['email'] ), 0, 200 );
		}

		if ( array_key_exists( 'notes', $params ) ) {
			$update['notes'] = sanitize_textarea_field( $params['notes'] );
		}

		$allowed_channels = array( 'phone', 'whatsapp', 'email', 'walkin' );
		if ( array_key_exists( 'intake_channel', $params )
			&& in_array( $params['intake_channel'], $allowed_channels, true ) ) {
			$update['intake_channel'] = $params['intake_channel'];
		}

		if ( count( $update ) === 1 ) {
			return new WP_REST_Response( array( 'success' => true, 'message' => 'Nothing to update.' ), 200 );
		}

		$result = $wpdb->update( CVT_DB::vendors(), $update, array( 'id' => $id ) );
		if ( false === $result ) {
			return new WP_Error( 'db_error', 'Could not update vendor: ' . $wpdb->last_error, array( 'status' => 500 ) );
		}

		CVT_Activity_Log::log( 'vendor', $id, 'updated', (array) $vendor, $update );

		return new WP_REST_Response( array( 'success' => true, 'message' => 'Vendor updated.' ), 200 );
	}

	// -------------------------------------------------------------------------
	// DELETE /vendors/{id}
	// -------------------------------------------------------------------------

	public static function delete_vendor( $request ) {
		global $wpdb;

		$id     = absint( $request->get_param( 'id' ) );
		$vendor = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}cvt_vendors WHERE id = %d",
			$id
		) );

		if ( ! $vendor ) {
			return new WP_Error( 'not_found', 'Vendor not found.', array( 'status' => 404 ) );
		}

		// Fetch all item IDs for this vendor before deletion so we can cascade.
		$item_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}cvt_items WHERE vendor_id = %d",
			$id
		) );

		// Cascade: remove images, pending payouts, and items.
		// Paid payouts are kept — they are financial history and now carry an item_title snapshot.
		foreach ( $item_ids as $item_id ) {
			$item_id = (int) $item_id;
			$wpdb->delete( CVT_DB::images(),  array( 'item_id' => $item_id ),               array( '%d' ) );
			$wpdb->delete( CVT_DB::payouts(), array( 'item_id' => $item_id, 'status' => 'pending' ), array( '%d', '%s' ) );
			CVT_Activity_Log::log( 'item', $item_id, 'deleted', array( 'vendor_id' => $id ), null );
		}

		if ( $item_ids ) {
			$ids_sql = implode( ',', array_map( 'absint', $item_ids ) );
			$wpdb->query( "DELETE FROM {$wpdb->prefix}cvt_items WHERE id IN ($ids_sql)" );
		}

		$wpdb->delete( CVT_DB::vendors(), array( 'id' => $id ), array( '%d' ) );
		CVT_Activity_Log::log( 'vendor', $id, 'deleted', (array) $vendor, null );

		return new WP_REST_Response( array(
			'success'       => true,
			'message'       => sprintf( 'Vendor and %d item(s) deleted.', count( $item_ids ) ),
			'deleted_items' => count( $item_ids ),
		), 200 );
	}

	// -------------------------------------------------------------------------
	// POST /vendors/{id}/items
	// -------------------------------------------------------------------------

	public static function create_item( $request ) {
		global $wpdb;

		$vendor_id = absint( $request->get_param( 'id' ) );
		$exists    = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}cvt_vendors WHERE id = %d",
			$vendor_id
		) );

		if ( ! $exists ) {
			return new WP_Error( 'not_found', 'Vendor not found.', array( 'status' => 404 ) );
		}

		$title = substr( sanitize_text_field( $request->get_param( 'title' ) ?? '' ), 0, 500 );
		if ( $title === '' ) {
			return new WP_Error( 'missing_title', 'Item title is required.', array( 'status' => 400 ) );
		}

		$allowed_types = array( 'consignment', 'agency', 'listing' );
		$deal_type     = in_array( $request->get_param( 'deal_type' ), $allowed_types, true )
			? $request->get_param( 'deal_type' )
			: 'consignment';

		$item_id = self::insert_item( $vendor_id, array(
			'title'       => $title,
			'category'    => sanitize_text_field( $request->get_param( 'category' ) ?? '' ),
			'price'       => (float) $request->get_param( 'selling_price' ),
			'listing_url' => $request->get_param( 'listing_url' ) ?? '',
			'listing_id'  => sanitize_text_field( $request->get_param( 'listing_id' ) ?? '' ),
			'notes'       => sanitize_textarea_field( $request->get_param( 'notes' ) ?? '' ),
			'deal_type'   => $deal_type,
		) );

		if ( is_wp_error( $item_id ) ) {
			return $item_id;
		}

		return new WP_REST_Response( array(
			'success' => true,
			'item_id' => $item_id,
			'message' => 'Item created.',
		), 201 );
	}

	// -------------------------------------------------------------------------
	// Internal helpers
	// -------------------------------------------------------------------------

	/**
	 * Insert a cvt_items row, log it, and return the new ID (or WP_Error on failure).
	 *
	 * @param  int   $vendor_id
	 * @param  array $data  { title, category, price, listing_url, listing_id, notes, deal_type }
	 * @return int|WP_Error
	 */
	private static function insert_item( $vendor_id, array $data ) {
		global $wpdb;

		$listing_url = esc_url_raw( $data['listing_url'] ?? '' );
		$listing_id  = sanitize_text_field( $data['listing_id'] ?? '' );

		// Store external listing ID in notes when present.
		$notes = sanitize_textarea_field( $data['notes'] ?? '' );
		if ( $listing_id !== '' ) {
			$prefix = 'Listivo listing ID: ' . $listing_id;
			$notes  = $notes !== '' ? $prefix . "\n" . $notes : $prefix;
		}

		$allowed_types = array( 'consignment', 'agency', 'listing' );
		$deal_type     = in_array( $data['deal_type'] ?? '', $allowed_types, true )
			? $data['deal_type']
			: 'consignment';

		$insert = array(
			'vendor_id'           => absint( $vendor_id ),
			'title'               => substr( sanitize_text_field( $data['title'] ?? '' ), 0, 500 ),
			'category'            => substr( sanitize_text_field( $data['category'] ?? '' ), 0, 100 ),
			'selling_price'       => max( 0, (float) ( $data['price'] ?? 0 ) ),
			'deal_type'           => $deal_type,
			'status'              => 'under_review',
			'listivo_listing_url' => substr( $listing_url, 0, 500 ),
			'notes'               => $notes,
			'date_received'       => current_time( 'Y-m-d' ),
			'created_by'          => get_current_user_id(),
			'created_at'          => CVT_DB::now(),
			'updated_at'          => CVT_DB::now(),
		);

		$result = $wpdb->insert( CVT_DB::items(), $insert );
		if ( false === $result ) {
			return new WP_Error( 'db_error', 'Could not save item: ' . $wpdb->last_error );
		}

		$item_id = (int) $wpdb->insert_id;
		CVT_Activity_Log::log( 'item', $item_id, 'created', null, $insert );
		return $item_id;
	}
}
