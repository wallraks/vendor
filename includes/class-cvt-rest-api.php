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
 *
 * GET    /corido/v1/items                — list items (filterable)
 * GET    /corido/v1/items/{id}           — single item + images
 * PATCH  /corido/v1/items/{id}           — update item fields
 * DELETE /corido/v1/items/{id}           — delete item
 * POST   /corido/v1/items/{id}/status    — transition item status
 *
 * GET    /corido/v1/payouts              — list payouts (filterable)
 * GET    /corido/v1/payouts/{id}         — single payout
 * POST   /corido/v1/payouts/{id}/mark-paid — mark payout paid + auto-close item
 *
 * GET    /corido/v1/waitlist             — list waitlist entries (filterable)
 * POST   /corido/v1/waitlist             — create waitlist entry
 * GET    /corido/v1/waitlist/{id}        — single entry
 * PATCH  /corido/v1/waitlist/{id}        — update entry
 * DELETE /corido/v1/waitlist/{id}        — delete entry
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

		register_rest_route( $ns, '/items', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'list_items' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'page'      => array( 'type' => 'integer', 'default' => 1,  'minimum' => 1 ),
					'per_page'  => array( 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ),
					's'         => array( 'type' => 'string',  'default' => '' ),
					'vendor_id' => array( 'type' => 'integer', 'default' => 0 ),
					'status'    => array( 'type' => 'string',  'default' => '' ),
					'category'  => array( 'type' => 'string',  'default' => '' ),
					'deal_type' => array( 'type' => 'string',  'default' => '' ),
				),
			),
		) );

		register_rest_route( $ns, '/items/(?P<id>\d+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_item' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			),
			array(
				'methods'             => 'PATCH',
				'callback'            => array( __CLASS__, 'update_item' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'delete_item' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			),
		) );

		register_rest_route( $ns, '/items/(?P<id>\d+)/status', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'update_item_status' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'status' => array( 'type' => 'string', 'required' => true ),
					'note'   => array( 'type' => 'string', 'default'  => '' ),
				),
			),
		) );

		register_rest_route( $ns, '/payouts', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'list_payouts' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'page'      => array( 'type' => 'integer', 'default' => 1,  'minimum' => 1 ),
					'per_page'  => array( 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ),
					'status'    => array( 'type' => 'string',  'default' => '', 'enum' => array( '', 'pending', 'paid' ) ),
					'vendor_id' => array( 'type' => 'integer', 'default' => 0 ),
				),
			),
		) );

		register_rest_route( $ns, '/payouts/(?P<id>\d+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_payout' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			),
		) );

		register_rest_route( $ns, '/payouts/(?P<id>\d+)/mark-paid', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'mark_payout_paid' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'reference_number' => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
					'notes'            => array( 'type' => 'string', 'default'  => '',   'sanitize_callback' => 'sanitize_textarea_field' ),
				),
			),
		) );

		register_rest_route( $ns, '/waitlist', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'list_waitlist' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'page'     => array( 'type' => 'integer', 'default' => 1,  'minimum' => 1 ),
					'per_page' => array( 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ),
					's'        => array( 'type' => 'string',  'default' => '' ),
					'status'   => array( 'type' => 'string',  'default' => '' ),
					'tag'      => array( 'type' => 'string',  'default' => '' ),
				),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'create_waitlist_entry' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'client_name' => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
					'phone'       => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
					'email'       => array( 'type' => 'string', 'default'  => '',   'sanitize_callback' => 'sanitize_email' ),
					'timeframe'   => array( 'type' => 'string', 'default'  => '',   'sanitize_callback' => 'sanitize_text_field' ),
					'notes'       => array( 'type' => 'string', 'default'  => '',   'sanitize_callback' => 'sanitize_textarea_field' ),
					'tags'          => array( 'type' => 'array', 'default' => array(), 'items' => array( 'type' => 'string' ) ),
					'request_items' => array( 'type' => 'array', 'default' => array() ),
				),
			),
		) );

		register_rest_route( $ns, '/waitlist/(?P<id>\d+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_waitlist_entry' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			),
			array(
				'methods'             => 'PATCH',
				'callback'            => array( __CLASS__, 'update_waitlist_entry' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'delete_waitlist_entry' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
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
	// GET /items
	// -------------------------------------------------------------------------

	public static function list_items( $request ) {
		global $wpdb;

		$page      = max( 1, (int) $request->get_param( 'page' ) );
		$per_page  = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$search    = sanitize_text_field( $request->get_param( 's' ) );
		$vendor_id = absint( $request->get_param( 'vendor_id' ) );
		$status    = sanitize_text_field( $request->get_param( 'status' ) );
		$category  = sanitize_text_field( $request->get_param( 'category' ) );
		$deal_type = sanitize_text_field( $request->get_param( 'deal_type' ) );
		$offset    = ( $page - 1 ) * $per_page;

		$where  = array( '1=1' );
		$params = array();

		if ( $search !== '' ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '( i.title LIKE %s OR v.name LIKE %s )';
			$params[] = $like;
			$params[] = $like;
		}
		if ( $vendor_id > 0 ) {
			$where[]  = 'i.vendor_id = %d';
			$params[] = $vendor_id;
		}
		if ( $status !== '' ) {
			$where[]  = 'i.status = %s';
			$params[] = $status;
		}
		if ( $category !== '' ) {
			$where[]  = 'i.category = %s';
			$params[] = $category;
		}
		if ( $deal_type !== '' ) {
			$where[]  = 'i.deal_type = %s';
			$params[] = $deal_type;
		}

		$where_sql = implode( ' AND ', $where );
		$tables    = "{$wpdb->prefix}cvt_items i LEFT JOIN {$wpdb->prefix}cvt_vendors v ON v.id = i.vendor_id";

		$count_sql = "SELECT COUNT(*) FROM $tables WHERE $where_sql";
		$total     = (int) ( $params
			? $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) )
			: $wpdb->get_var( $count_sql ) );

		$select_sql   = "SELECT i.id, i.vendor_id, v.name AS vendor_name, i.title, i.category,
			i.selling_price, i.market_value, i.deal_type, i.status,
			i.listivo_listing_url, i.date_received, i.date_posted, i.created_at
			FROM $tables WHERE $where_sql
			ORDER BY i.created_at DESC LIMIT %d OFFSET %d";
		$query_params = array_merge( $params, array( $per_page, $offset ) );
		$items        = $wpdb->get_results( $wpdb->prepare( $select_sql, ...$query_params ) );

		return new WP_REST_Response( array(
			'items'       => $items,
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $total / $per_page ),
		), 200 );
	}

	// -------------------------------------------------------------------------
	// GET /items/{id}
	// -------------------------------------------------------------------------

	public static function get_item( $request ) {
		global $wpdb;

		$id   = absint( $request->get_param( 'id' ) );
		$item = $wpdb->get_row( $wpdb->prepare(
			"SELECT i.*, v.name AS vendor_name
			 FROM {$wpdb->prefix}cvt_items i
			 LEFT JOIN {$wpdb->prefix}cvt_vendors v ON v.id = i.vendor_id
			 WHERE i.id = %d",
			$id
		) );

		if ( ! $item ) {
			return new WP_Error( 'not_found', 'Item not found.', array( 'status' => 404 ) );
		}

		$images = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, attachment_id, sort_order FROM {$wpdb->prefix}cvt_item_images WHERE item_id = %d ORDER BY sort_order ASC",
			$id
		) );

		return new WP_REST_Response( array(
			'item'   => $item,
			'images' => $images,
		), 200 );
	}

	// -------------------------------------------------------------------------
	// PATCH /items/{id}
	// -------------------------------------------------------------------------

	public static function update_item( $request ) {
		global $wpdb;

		$id   = absint( $request->get_param( 'id' ) );
		$item = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}cvt_items WHERE id = %d",
			$id
		) );

		if ( ! $item ) {
			return new WP_Error( 'not_found', 'Item not found.', array( 'status' => 404 ) );
		}

		$params = $request->get_params();
		$update = array( 'updated_at' => CVT_DB::now() );

		$text_fields = array( 'title' => 500, 'category' => 100 );
		foreach ( $text_fields as $field => $max_len ) {
			if ( array_key_exists( $field, $params ) ) {
				$update[ $field ] = substr( sanitize_text_field( $params[ $field ] ), 0, $max_len );
			}
		}

		if ( array_key_exists( 'selling_price', $params ) ) {
			$update['selling_price'] = max( 0, (float) $params['selling_price'] );
		}
		if ( array_key_exists( 'market_value', $params ) ) {
			$update['market_value'] = $params['market_value'] !== null ? max( 0, (float) $params['market_value'] ) : null;
		}
		if ( array_key_exists( 'commission_rate', $params ) ) {
			$update['commission_rate'] = $params['commission_rate'] !== null ? max( 0, (float) $params['commission_rate'] ) : null;
		}
		if ( array_key_exists( 'listing_fee', $params ) ) {
			$update['listing_fee'] = $params['listing_fee'] !== null ? max( 0, (float) $params['listing_fee'] ) : null;
		}

		$allowed_types = array( 'consignment', 'agency', 'listing' );
		if ( array_key_exists( 'deal_type', $params ) && in_array( $params['deal_type'], $allowed_types, true ) ) {
			$update['deal_type'] = $params['deal_type'];
		}

		if ( array_key_exists( 'listing_url', $params ) ) {
			$update['listivo_listing_url'] = substr( esc_url_raw( $params['listing_url'] ), 0, 500 );
		}
		if ( array_key_exists( 'notes', $params ) ) {
			$update['notes'] = sanitize_textarea_field( $params['notes'] );
		}
		if ( array_key_exists( 'date_received', $params ) && $params['date_received'] ) {
			$update['date_received'] = sanitize_text_field( $params['date_received'] );
		}
		if ( array_key_exists( 'date_posted', $params ) && $params['date_posted'] ) {
			$update['date_posted'] = sanitize_text_field( $params['date_posted'] );
		}

		if ( count( $update ) === 1 ) {
			return new WP_REST_Response( array( 'success' => true, 'message' => 'Nothing to update.' ), 200 );
		}

		$result = $wpdb->update( CVT_DB::items(), $update, array( 'id' => $id ) );
		if ( false === $result ) {
			return new WP_Error( 'db_error', 'Could not update item: ' . $wpdb->last_error, array( 'status' => 500 ) );
		}

		CVT_Activity_Log::log( 'item', $id, 'updated', (array) $item, $update );

		return new WP_REST_Response( array( 'success' => true, 'message' => 'Item updated.' ), 200 );
	}

	// -------------------------------------------------------------------------
	// DELETE /items/{id}
	// -------------------------------------------------------------------------

	public static function delete_item( $request ) {
		global $wpdb;

		$id   = absint( $request->get_param( 'id' ) );
		$item = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}cvt_items WHERE id = %d",
			$id
		) );

		if ( ! $item ) {
			return new WP_Error( 'not_found', 'Item not found.', array( 'status' => 404 ) );
		}

		$wpdb->delete( CVT_DB::images(),  array( 'item_id' => $id ),                              array( '%d' ) );
		$wpdb->delete( CVT_DB::payouts(), array( 'item_id' => $id, 'status' => 'pending' ),       array( '%d', '%s' ) );
		$wpdb->delete( CVT_DB::items(),   array( 'id' => $id ),                                   array( '%d' ) );
		CVT_Activity_Log::log( 'item', $id, 'deleted', (array) $item, null );

		return new WP_REST_Response( array( 'success' => true, 'message' => 'Item deleted.' ), 200 );
	}

	// -------------------------------------------------------------------------
	// POST /items/{id}/status
	// -------------------------------------------------------------------------

	public static function update_item_status( $request ) {
		global $wpdb;

		$id         = absint( $request->get_param( 'id' ) );
		$new_status = sanitize_text_field( $request->get_param( 'status' ) );
		$note       = sanitize_textarea_field( $request->get_param( 'note' ) );

		$item = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}cvt_items WHERE id = %d",
			$id
		) );

		if ( ! $item ) {
			return new WP_Error( 'not_found', 'Item not found.', array( 'status' => 404 ) );
		}

		$old_status    = $item->status;
		$valid_forward = CVT_Settings::valid_transitions( $old_status );
		$valid_reverse = CVT_Settings::reverse_transitions( $old_status );

		$is_reverse = in_array( $new_status, $valid_reverse, true );
		$is_forward = in_array( $new_status, $valid_forward, true );

		if ( ! $is_forward && ! $is_reverse ) {
			return new WP_Error(
				'invalid_transition',
				sprintf( 'Cannot transition from "%s" to "%s".', $old_status, $new_status ),
				array( 'status' => 422 )
			);
		}

		// Reverse transitions require admin capability.
		if ( $is_reverse && ! current_user_can( 'cvt_manage_settings' ) ) {
			return new WP_Error( 'rest_forbidden', 'Only administrators can reverse a status.', array( 'status' => 403 ) );
		}

		$wpdb->update(
			CVT_DB::items(),
			array( 'status' => $new_status, 'updated_at' => CVT_DB::now() ),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		CVT_Activity_Log::log( 'item', $id, 'status_changed',
			array( 'status' => $old_status ),
			array( 'status' => $new_status ),
			$note ?: null
		);

		$payout_id = null;

		if ( $new_status === 'sold' ) {
			$result = CVT_Payout::create_from_item( $id );
			if ( ! is_wp_error( $result ) ) {
				$payout_id = $result;
			}
		}

		// Reversing from 'sold' voids any pending payout.
		if ( $is_reverse && $old_status === 'sold' ) {
			$wpdb->delete( CVT_DB::payouts(), array( 'item_id' => $id, 'status' => 'pending' ), array( '%d', '%s' ) );
		}

		return new WP_REST_Response( array(
			'success'    => true,
			'message'    => sprintf( 'Status updated to "%s".', $new_status ),
			'old_status' => $old_status,
			'new_status' => $new_status,
			'payout_id'  => $payout_id,
		), 200 );
	}

	// -------------------------------------------------------------------------
	// GET /payouts
	// -------------------------------------------------------------------------

	public static function list_payouts( $request ) {
		global $wpdb;

		$page      = max( 1, (int) $request->get_param( 'page' ) );
		$per_page  = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$status    = sanitize_text_field( $request->get_param( 'status' ) );
		$vendor_id = absint( $request->get_param( 'vendor_id' ) );
		$search    = sanitize_text_field( $request->get_param( 's' ) );
		$offset    = ( $page - 1 ) * $per_page;

		$where  = array( '1=1' );
		$params = array();

		if ( $search !== '' ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '( v.name LIKE %s OR COALESCE(i.title, p.item_title) LIKE %s )';
			$params[] = $like;
			$params[] = $like;
		}
		if ( $status !== '' ) {
			$where[]  = 'p.status = %s';
			$params[] = $status;
		}
		if ( $vendor_id > 0 ) {
			$where[]  = 'p.vendor_id = %d';
			$params[] = $vendor_id;
		}

		$where_sql = implode( ' AND ', $where );
		$tables    = "{$wpdb->prefix}cvt_payouts p
			LEFT JOIN {$wpdb->prefix}cvt_vendors v ON v.id = p.vendor_id
			LEFT JOIN {$wpdb->prefix}cvt_items i ON i.id = p.item_id";

		$count_sql = "SELECT COUNT(*) FROM $tables WHERE $where_sql";
		$total     = (int) ( $params
			? $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) )
			: $wpdb->get_var( $count_sql ) );

		$select_sql   = "SELECT p.*, v.name AS vendor_name, COALESCE(i.title, p.item_title) AS item_title
			FROM $tables WHERE $where_sql
			ORDER BY p.created_at DESC LIMIT %d OFFSET %d";
		$query_params = array_merge( $params, array( $per_page, $offset ) );
		$payouts      = $wpdb->get_results( $wpdb->prepare( $select_sql, ...$query_params ) );

		return new WP_REST_Response( array(
			'payouts'     => $payouts,
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $total / $per_page ),
		), 200 );
	}

	// -------------------------------------------------------------------------
	// GET /payouts/{id}
	// -------------------------------------------------------------------------

	public static function get_payout( $request ) {
		global $wpdb;

		$id     = absint( $request->get_param( 'id' ) );
		$payout = $wpdb->get_row( $wpdb->prepare(
			"SELECT p.*, v.name AS vendor_name, COALESCE(i.title, p.item_title) AS item_title,
			 u.display_name AS processed_by_name
			 FROM {$wpdb->prefix}cvt_payouts p
			 LEFT JOIN {$wpdb->prefix}cvt_vendors v ON v.id = p.vendor_id
			 LEFT JOIN {$wpdb->prefix}cvt_items i ON i.id = p.item_id
			 LEFT JOIN {$wpdb->users} u ON u.ID = p.processed_by
			 WHERE p.id = %d",
			$id
		) );

		if ( ! $payout ) {
			return new WP_Error( 'not_found', 'Payout not found.', array( 'status' => 404 ) );
		}

		return new WP_REST_Response( array( 'payout' => $payout ), 200 );
	}

	// -------------------------------------------------------------------------
	// POST /payouts/{id}/mark-paid
	// -------------------------------------------------------------------------

	public static function mark_payout_paid( $request ) {
		global $wpdb;

		if ( ! current_user_can( 'cvt_mark_payouts' ) ) {
			return new WP_Error( 'rest_forbidden', 'You do not have permission to mark payouts.', array( 'status' => 403 ) );
		}

		$id     = absint( $request->get_param( 'id' ) );
		$payout = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}cvt_payouts WHERE id = %d",
			$id
		) );

		if ( ! $payout ) {
			return new WP_Error( 'not_found', 'Payout not found.', array( 'status' => 404 ) );
		}
		if ( $payout->status === 'paid' ) {
			return new WP_Error( 'already_paid', 'This payout is already marked as paid.', array( 'status' => 409 ) );
		}

		$reference = sanitize_text_field( $request->get_param( 'reference_number' ) ?? '' );
		$notes     = sanitize_textarea_field( $request->get_param( 'notes' ) ?? '' );

		$wpdb->update(
			CVT_DB::payouts(),
			array(
				'status'           => 'paid',
				'payout_date'      => current_time( 'Y-m-d' ),
				'reference_number' => $reference,
				'notes'            => $notes,
				'processed_by'     => get_current_user_id(),
				'updated_at'       => CVT_DB::now(),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%s', '%d', '%s' ),
			array( '%d' )
		);

		CVT_Activity_Log::log( 'payout', $id, 'payout_marked_paid',
			array( 'status' => 'pending' ),
			array( 'status' => 'paid', 'reference' => $reference )
		);

		// Auto-close the item.
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

		return new WP_REST_Response( array(
			'success' => true,
			'message' => 'Payout marked paid and item closed.',
		), 200 );
	}

	// -------------------------------------------------------------------------
	// GET /waitlist
	// -------------------------------------------------------------------------

	public static function list_waitlist( $request ) {
		global $wpdb;

		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$search   = sanitize_text_field( $request->get_param( 's' ) );
		$status   = sanitize_text_field( $request->get_param( 'status' ) );
		$tag      = sanitize_text_field( $request->get_param( 'tag' ) );
		$offset   = ( $page - 1 ) * $per_page;

		$where  = array( '1=1' );
		$params = array();

		if ( $search !== '' ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '( w.client_name LIKE %s OR w.phone LIKE %s OR w.description LIKE %s )';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		if ( $status !== '' ) {
			$where[]  = 'w.status = %s';
			$params[] = $status;
		}
		if ( $tag !== '' ) {
			$where[]  = 'w.tags LIKE %s';
			$params[] = '%' . $wpdb->esc_like( '"' . $tag . '"' ) . '%';
		}

		$where_sql = implode( ' AND ', $where );
		$table     = CVT_DB::waitlist();

		$count_sql = "SELECT COUNT(*) FROM $table w WHERE $where_sql";
		$total     = (int) ( $params
			? $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) )
			: $wpdb->get_var( $count_sql ) );

		$select_sql   = "SELECT w.*, u.display_name AS agent_name
			FROM $table w
			LEFT JOIN {$wpdb->users} u ON u.ID = w.assigned_agent_id
			WHERE $where_sql
			ORDER BY w.created_at DESC LIMIT %d OFFSET %d";
		$query_params = array_merge( $params, array( $per_page, $offset ) );
		$entries      = $wpdb->get_results( $wpdb->prepare( $select_sql, ...$query_params ) );

		// Decode JSON fields for the response.
		foreach ( $entries as $entry ) {
			$entry->tags          = CVT_Waitlist::decode_tags( $entry->tags );
			$entry->request_items = CVT_Waitlist::decode_items( $entry->request_items );
		}

		return new WP_REST_Response( array(
			'entries'     => $entries,
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $total / $per_page ),
		), 200 );
	}

	// -------------------------------------------------------------------------
	// POST /waitlist
	// -------------------------------------------------------------------------

	public static function create_waitlist_entry( $request ) {
		$params = $request->get_params();

		// Delegate to the model — it handles sanitization, permission check, and insert.
		$id = CVT_Waitlist::create( $params );

		if ( is_wp_error( $id ) ) {
			$code = $id->get_error_code() === 'permission' ? 403 : 500;
			return new WP_Error( $id->get_error_code(), $id->get_error_message(), array( 'status' => $code ) );
		}

		return new WP_REST_Response( array(
			'success'  => true,
			'entry_id' => $id,
			'message'  => 'Waitlist entry created.',
		), 201 );
	}

	// -------------------------------------------------------------------------
	// GET /waitlist/{id}
	// -------------------------------------------------------------------------

	public static function get_waitlist_entry( $request ) {
		$id    = absint( $request->get_param( 'id' ) );
		$entry = CVT_Waitlist::get( $id );

		if ( ! $entry ) {
			return new WP_Error( 'not_found', 'Waitlist entry not found.', array( 'status' => 404 ) );
		}

		$entry->tags          = CVT_Waitlist::decode_tags( $entry->tags );
		$entry->request_items = CVT_Waitlist::decode_items( $entry->request_items );

		return new WP_REST_Response( array( 'entry' => $entry ), 200 );
	}

	// -------------------------------------------------------------------------
	// PATCH /waitlist/{id}
	// -------------------------------------------------------------------------

	public static function update_waitlist_entry( $request ) {
		$id     = absint( $request->get_param( 'id' ) );
		$params = $request->get_params();

		$result = CVT_Waitlist::update( $id, $params );

		if ( is_wp_error( $result ) ) {
			$code = $result->get_error_code() === 'not_found' ? 404 : ( $result->get_error_code() === 'permission' ? 403 : 500 );
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => $code ) );
		}

		return new WP_REST_Response( array( 'success' => true, 'message' => 'Waitlist entry updated.' ), 200 );
	}

	// -------------------------------------------------------------------------
	// DELETE /waitlist/{id}
	// -------------------------------------------------------------------------

	public static function delete_waitlist_entry( $request ) {
		$id     = absint( $request->get_param( 'id' ) );
		$result = CVT_Waitlist::delete( $id );

		if ( is_wp_error( $result ) ) {
			$code = $result->get_error_code() === 'not_found' ? 404 : ( $result->get_error_code() === 'permission' ? 403 : 500 );
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => $code ) );
		}

		return new WP_REST_Response( array( 'success' => true, 'message' => 'Waitlist entry deleted.' ), 200 );
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
