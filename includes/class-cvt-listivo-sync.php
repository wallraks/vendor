<?php
defined( 'ABSPATH' ) || exit;

/**
 * Website sold sync — flags the live Listivo listing on corido.co.ke when an
 * item is marked Sold in the tracker, and un-flags it if the sale falls through.
 *
 * The listing deliberately stays published: it keeps its SEO value and shows
 * buyers the item moved. Only a "sold" flag is written to the listing, via
 * post meta and (optionally) a taxonomy term. Both are configurable under
 * Settings → Website Sold Sync so the integration survives Listivo updates.
 *
 * Nothing is written unless the item carries a Listivo Listing URL that
 * resolves to a listing on this site. Every attempt — success or failure —
 * is written to the activity log.
 *
 * @since 1.9.0
 */
class CVT_Listivo_Sync {

	/**
	 * Marker meta written alongside the sold flag. Records which item set the
	 * flag and what the field held before, so a reversal restores the listing
	 * rather than blindly deleting a value we did not own.
	 */
	const MARKER_META = '_cvt_sold_synced';

	/** Register the status-change listener. */
	public static function boot() {
		add_action( 'cvt_item_status_changed', array( __CLASS__, 'on_status_changed' ), 10, 3 );
	}

	// -------------------------------------------------------------------------
	// Settings
	// -------------------------------------------------------------------------

	/** Is the sync switched on? */
	public static function enabled() {
		return (bool) get_option( 'cvt_sold_sync_enabled', 1 );
	}

	/**
	 * Candidate keys for Listivo's sold flag, in priority order.
	 *
	 * Listivo's CT framework prefixes listing fields with the post type, so the
	 * bare key is rarely right — compare the price lookup in
	 * admin/class-cvt-ajax.php, which walks the same shape of list. Leaving the
	 * setting blank makes the sync detect which of these the site actually uses.
	 */
	public static function candidate_keys() {
		return array( '_listivo1_listing_sold', 'listivo1_listing_sold', '_sold', 'sold' );
	}

	/** Cached result of scanning the site for its sold key. */
	const KEY_CACHE = 'cvt_sold_sync_detected_key';

	/**
	 * Listing meta key that holds the sold flag. An explicit Settings value wins;
	 * otherwise the key is detected from the listings already on the site.
	 */
	public static function meta_key() {
		$configured = (string) get_option( 'cvt_sold_sync_meta_key', '' );
		if ( trim( $configured ) !== '' ) {
			return self::sanitize_meta_key( $configured );
		}
		return self::detected_key()['key'];
	}

	/** Is the key in use one we actually found on a listing, or just the fallback? */
	public static function key_is_confirmed() {
		if ( trim( (string) get_option( 'cvt_sold_sync_meta_key', '' ) ) !== '' ) {
			return true; // An admin set it deliberately.
		}
		return (bool) self::detected_key()['confirmed'];
	}

	/**
	 * Detect the site's sold key, cached for a day.
	 *
	 * @return array { key: string, confirmed: bool }
	 */
	public static function detected_key() {
		$cached = get_transient( self::KEY_CACHE );
		if ( is_array( $cached ) && ! empty( $cached['key'] ) ) {
			return $cached;
		}

		$found  = self::scan_for_sold_key();
		$result = array(
			'key'       => $found !== '' ? $found : self::candidate_keys()[0],
			'confirmed' => $found !== '',
		);

		set_transient( self::KEY_CACHE, $result, DAY_IN_SECONDS );

		return $result;
	}

	/** Forget the detected key — call after Settings or the listing set changes. */
	public static function bust_key_cache() {
		delete_transient( self::KEY_CACHE );
	}

	/**
	 * Which candidate key actually appears on this site's Listivo listings?
	 * Any listing already marked sold by hand carries it.
	 *
	 * @return string  The key, or '' when none of the candidates is present.
	 */
	public static function scan_for_sold_key() {
		global $wpdb;

		$post_type = CVT_Settings::get_listivo_post_type();
		if ( ! $post_type ) {
			return '';
		}

		foreach ( self::candidate_keys() as $key ) {
			$exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT 1 FROM {$wpdb->postmeta} m
				 INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
				 WHERE m.meta_key = %s AND p.post_type = %s
				 LIMIT 1",
				$key,
				$post_type
			) );

			if ( $exists ) {
				return $key;
			}
		}

		return '';
	}

	/** Value written to that meta key when an item sells. */
	public static function sold_value() {
		$value = (string) get_option( 'cvt_sold_sync_meta_value', '1' );
		return $value === '' ? '1' : $value;
	}

	/** Optional taxonomy used for a visible "Sold" label. Empty = meta only. */
	public static function taxonomy() {
		return sanitize_key( get_option( 'cvt_sold_sync_taxonomy', '' ) );
	}

	/** Term slug applied within that taxonomy. */
	public static function term_slug() {
		return sanitize_title( get_option( 'cvt_sold_sync_term', 'sold' ) );
	}

	/** Item statuses that mean "sold" as far as the website is concerned. */
	public static function sold_statuses() {
		return array( 'sold', 'closed' );
	}

	/**
	 * Meta keys may carry uppercase letters and leading underscores, which
	 * sanitize_key() would strip — keep those, drop everything else.
	 */
	public static function sanitize_meta_key( $key ) {
		return preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $key );
	}

	// -------------------------------------------------------------------------
	// Status change listener
	// -------------------------------------------------------------------------

	/**
	 * Fired by CVT_Item::update_status() and CVT_Payout::mark_paid().
	 *
	 * @param int    $item_id
	 * @param string $old_status
	 * @param string $new_status
	 */
	public static function on_status_changed( $item_id, $old_status, $new_status ) {
		if ( ! self::enabled() ) {
			return;
		}

		$was_sold = in_array( $old_status, self::sold_statuses(), true );
		$is_sold  = in_array( $new_status, self::sold_statuses(), true );

		// sold → closed and the like need no website change.
		if ( $was_sold === $is_sold ) {
			return;
		}

		$result = $is_sold ? self::mark_sold( $item_id ) : self::clear_sold( $item_id );
		self::log_result( $item_id, $is_sold, $result );
	}

	/**
	 * Record the outcome in the activity log. A missing or unresolvable listing
	 * URL is normal for items that were never posted on the site, so it is
	 * logged quietly rather than treated as a failure worth shouting about.
	 *
	 * @param int                 $item_id
	 * @param bool                $is_sold
	 * @param int|WP_Error        $result  Listing post ID, or the reason it did not happen.
	 */
	public static function log_result( $item_id, $is_sold, $result ) {
		if ( is_wp_error( $result ) ) {
			if ( in_array( $result->get_error_code(), array( 'no_url', 'disabled', 'foreign_flag' ), true ) ) {
				return;
			}
			CVT_Activity_Log::log(
				'item', $item_id, 'listing_sync_failed',
				null,
				array( 'error' => $result->get_error_code() ),
				$result->get_error_message()
			);
			return;
		}

		CVT_Activity_Log::log(
			'item', $item_id,
			$is_sold ? 'listing_marked_sold' : 'listing_unmarked_sold',
			null,
			array( 'listing_post_id' => (int) $result ),
			$is_sold
				? __( 'Listing flagged Sold on the website.', 'corido-vendor-tracker' )
				: __( 'Sold flag removed from the website listing.', 'corido-vendor-tracker' )
		);
	}

	// -------------------------------------------------------------------------
	// Write operations
	// -------------------------------------------------------------------------

	/**
	 * Flag the item's listing as sold on the website.
	 *
	 * @param  int $item_id
	 * @return int|WP_Error  Listing post ID on success.
	 */
	public static function mark_sold( $item_id ) {
		$post_id = self::resolve_for_item( $item_id );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$key      = self::meta_key();
		$previous = get_post_meta( $post_id, $key, true );

		update_post_meta( $post_id, $key, self::sold_value() );

		// Only record the pre-sync value the first time, so repeated syncs
		// never overwrite the marker with our own flag.
		if ( ! get_post_meta( $post_id, self::MARKER_META, true ) ) {
			update_post_meta( $post_id, self::MARKER_META, array(
				'item_id'   => absint( $item_id ),
				'previous'  => is_scalar( $previous ) ? (string) $previous : '',
				'meta_key'  => $key,
				'synced_at' => current_time( 'mysql' ),
			) );
		}

		self::apply_term( $post_id, true );
		clean_post_cache( $post_id );
		self::bust_pending_cache();

		return $post_id;
	}

	/**
	 * Remove the sold flag — used when a sale falls through and the item goes
	 * back on the market (or is withdrawn).
	 *
	 * @param  int $item_id
	 * @return int|WP_Error  Listing post ID on success.
	 */
	public static function clear_sold( $item_id ) {
		$post_id = self::resolve_for_item( $item_id );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$marker  = get_post_meta( $post_id, self::MARKER_META, true );
		$key     = is_array( $marker ) && ! empty( $marker['meta_key'] )
			? self::sanitize_meta_key( $marker['meta_key'] )
			: self::meta_key();
		$current = get_post_meta( $post_id, $key, true );

		// Leave alone a flag this plugin never set — it belongs to someone else.
		$ours = is_array( $marker ) || ( is_scalar( $current ) && (string) $current === self::sold_value() );
		if ( ! $ours ) {
			return new WP_Error( 'foreign_flag', __( 'The listing\'s sold state was not set by the tracker, so it was left untouched.', 'corido-vendor-tracker' ) );
		}

		$previous = is_array( $marker ) ? (string) ( $marker['previous'] ?? '' ) : '';
		if ( $previous !== '' ) {
			update_post_meta( $post_id, $key, $previous );
		} else {
			delete_post_meta( $post_id, $key );
		}

		delete_post_meta( $post_id, self::MARKER_META );
		self::apply_term( $post_id, false );
		clean_post_cache( $post_id );
		self::bust_pending_cache();

		return $post_id;
	}

	/**
	 * Add or remove the optional "Sold" term on the listing. Other terms in the
	 * taxonomy are left untouched.
	 *
	 * @param int  $post_id
	 * @param bool $add
	 */
	private static function apply_term( $post_id, $add ) {
		$taxonomy = self::taxonomy();
		$slug     = self::term_slug();

		if ( ! $taxonomy || ! $slug || ! taxonomy_exists( $taxonomy ) ) {
			return;
		}

		$term = get_term_by( 'slug', $slug, $taxonomy );

		if ( $add ) {
			if ( ! $term ) {
				$created = wp_insert_term( __( 'Sold', 'corido-vendor-tracker' ), $taxonomy, array( 'slug' => $slug ) );
				if ( is_wp_error( $created ) ) {
					return;
				}
				$term_id = (int) $created['term_id'];
			} else {
				$term_id = (int) $term->term_id;
			}
			wp_set_object_terms( $post_id, array( $term_id ), $taxonomy, true );
			return;
		}

		if ( $term ) {
			wp_remove_object_terms( $post_id, array( (int) $term->term_id ), $taxonomy );
		}
	}

	// -------------------------------------------------------------------------
	// Listing resolution
	// -------------------------------------------------------------------------

	/**
	 * Resolve an item's stored listing URL to a post ID on this site.
	 *
	 * @param  int $item_id
	 * @return int|WP_Error
	 */
	public static function resolve_for_item( $item_id ) {
		$item = CVT_Item::get( $item_id );
		if ( ! $item ) {
			return new WP_Error( 'item_not_found', __( 'Item not found.', 'corido-vendor-tracker' ) );
		}
		return self::resolve_post_id( $item );
	}

	/**
	 * Resolve a listing URL to the Listivo listing it points at.
	 *
	 * @param  object $item  Item row with a listivo_listing_url property.
	 * @return int|WP_Error
	 */
	public static function resolve_post_id( $item ) {
		$url = trim( (string) ( $item->listivo_listing_url ?? '' ) );

		if ( $url === '' ) {
			return new WP_Error( 'no_url', __( 'No website listing URL is saved for this item.', 'corido-vendor-tracker' ) );
		}

		if ( ! self::is_same_site( $url ) ) {
			return new WP_Error( 'offsite_url', sprintf(
				/* translators: %s: site host */
				__( 'The listing URL points outside %s, so it cannot be updated from here.', 'corido-vendor-tracker' ),
				(string) wp_parse_url( home_url(), PHP_URL_HOST )
			) );
		}

		$post_id = url_to_postid( $url );
		if ( ! $post_id ) {
			$post_id = self::lookup_by_slug( $url );
		}

		if ( ! $post_id ) {
			return new WP_Error( 'listing_not_found', __( 'No listing on the website matches the saved URL. Check the Listivo Listing URL on this item.', 'corido-vendor-tracker' ) );
		}

		$post_type = get_post_type( $post_id );
		$expected  = CVT_Settings::get_listivo_post_type();

		// Only enforce the expected type when Listivo actually registered it —
		// a mis-typed slug in Settings should not block a valid listing.
		if ( $expected && post_type_exists( $expected ) && $post_type !== $expected ) {
			return new WP_Error( 'wrong_post_type', sprintf(
				/* translators: 1: found post type, 2: expected post type */
				__( 'The saved URL resolves to a "%1$s", not a Listivo listing ("%2$s").', 'corido-vendor-tracker' ),
				$post_type,
				$expected
			) );
		}

		return (int) $post_id;
	}

	/** Does this URL belong to this WordPress site? */
	private static function is_same_site( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		$home = wp_parse_url( home_url(), PHP_URL_HOST );

		if ( ! $host ) {
			return true; // Relative URL — treat as local.
		}

		$strip = static function ( $h ) {
			return strtolower( preg_replace( '/^www\./i', '', (string) $h ) );
		};

		return $strip( $host ) === $strip( $home );
	}

	/**
	 * Fallback for permalink structures url_to_postid() cannot parse: match the
	 * last path segment against listing slugs.
	 *
	 * @param  string $url
	 * @return int
	 */
	private static function lookup_by_slug( $url ) {
		$post_type = CVT_Settings::get_listivo_post_type();
		if ( ! $post_type || ! post_type_exists( $post_type ) ) {
			return 0;
		}

		$path     = (string) wp_parse_url( $url, PHP_URL_PATH );
		$segments = array_values( array_filter( explode( '/', $path ) ) );
		$slug     = $segments ? end( $segments ) : '';

		if ( $slug === '' ) {
			return 0;
		}

		$post = get_page_by_path( sanitize_title( $slug ), OBJECT, $post_type );

		return $post ? (int) $post->ID : 0;
	}

	// -------------------------------------------------------------------------
	// Backfill — items sold before the sync existed, or whose URL arrived late
	// -------------------------------------------------------------------------

	/** Transient holding the pending count, so the settings page stays cheap. */
	const PENDING_CACHE = 'cvt_sold_sync_pending';

	/** Drop the cached pending count after anything that could change it. */
	public static function bust_pending_cache() {
		delete_transient( self::PENDING_CACHE );
	}

	/**
	 * Items marked Sold or Closed here whose website listing is not flagged yet.
	 *
	 * Sold items are walked in chunks — an item sold long ago is as much a
	 * candidate as one sold this morning — and each candidate costs a URL
	 * lookup, so both how many we return and how many we examine are capped.
	 *
	 * @param  int $limit     Stop once this many pending items are found.
	 * @param  int $scan_cap  Never examine more sold items than this in one pass.
	 * @return array[]        Rows of { id, status, listivo_listing_url }.
	 */
	public static function pending_items( $limit = 200, $scan_cap = 2000 ) {
		global $wpdb;

		$pending = array();
		$chunk   = 200;

		for ( $offset = 0; $offset < $scan_cap; $offset += $chunk ) {
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, status, listivo_listing_url FROM %i
				 WHERE status IN ( 'sold', 'closed' ) AND listivo_listing_url <> ''
				 ORDER BY updated_at DESC, id DESC
				 LIMIT %d OFFSET %d",
				CVT_DB::items(),
				$chunk,
				$offset
			) );

			if ( empty( $rows ) ) {
				break;
			}

			foreach ( $rows as $row ) {
				$post_id = self::resolve_post_id( $row );
				if ( is_wp_error( $post_id ) ) {
					continue;
				}
				if ( (string) get_post_meta( $post_id, self::meta_key(), true ) !== self::sold_value() ) {
					$pending[] = $row;
					if ( count( $pending ) >= $limit ) {
						return $pending;
					}
				}
			}
		}

		return $pending;
	}

	/** Cached count of items awaiting a website flag. */
	public static function unsynced_count() {
		if ( ! self::enabled() ) {
			return 0;
		}

		$cached = get_transient( self::PENDING_CACHE );
		if ( $cached !== false ) {
			return (int) $cached;
		}

		$count = count( self::pending_items() );
		set_transient( self::PENDING_CACHE, $count, 5 * MINUTE_IN_SECONDS );

		return $count;
	}

	/**
	 * Flag every pending item's listing in one pass.
	 *
	 * @param  int $limit
	 * @return array { synced, failed }
	 */
	public static function backfill( $limit = 200 ) {
		$synced = 0;
		$failed = 0;

		foreach ( self::pending_items( $limit ) as $row ) {
			$result = self::mark_sold( (int) $row->id );
			if ( is_wp_error( $result ) ) {
				$failed++;
				self::log_result( (int) $row->id, true, $result );
				continue;
			}
			$synced++;
			CVT_Activity_Log::log(
				'item', (int) $row->id, 'listing_marked_sold',
				null,
				array( 'listing_post_id' => (int) $result ),
				__( 'Listing flagged Sold on the website (backfill).', 'corido-vendor-tracker' )
			);
		}

		self::bust_pending_cache();

		return array( 'synced' => $synced, 'failed' => $failed );
	}

	// -------------------------------------------------------------------------
	// Read-only helpers for the admin UI
	// -------------------------------------------------------------------------

	/**
	 * Describe the live state of an item's listing, for the item detail screen.
	 *
	 * @param  object $item
	 * @return array { state, label, message, post_id, can_sync }
	 *                state: disabled | no_url | error | sold | not_sold
	 */
	public static function site_state( $item ) {
		$base = array( 'state' => 'not_sold', 'label' => '', 'message' => '', 'post_id' => 0, 'can_sync' => false );

		if ( ! self::enabled() ) {
			return array_merge( $base, array(
				'state'   => 'disabled',
				'label'   => __( 'Sync off', 'corido-vendor-tracker' ),
				'message' => __( 'Website sold sync is switched off in Settings.', 'corido-vendor-tracker' ),
			) );
		}

		$post_id = self::resolve_post_id( $item );

		if ( is_wp_error( $post_id ) ) {
			return array_merge( $base, array(
				'state'   => $post_id->get_error_code() === 'no_url' ? 'no_url' : 'error',
				'label'   => __( 'Not linked', 'corido-vendor-tracker' ),
				'message' => $post_id->get_error_message(),
			) );
		}

		$flagged  = (string) get_post_meta( $post_id, self::meta_key(), true ) === self::sold_value();
		$is_sold  = in_array( $item->status, self::sold_statuses(), true );

		return array(
			'state'    => $flagged ? 'sold' : 'not_sold',
			'label'    => $flagged
				? __( 'Marked Sold on site', 'corido-vendor-tracker' )
				: __( 'Live on site', 'corido-vendor-tracker' ),
			'message'  => '',
			'post_id'  => (int) $post_id,
			'can_sync' => $flagged !== $is_sold,
		);
	}
}
