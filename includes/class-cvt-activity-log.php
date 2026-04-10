<?php
defined( 'ABSPATH' ) || exit;

/**
 * Writes and reads the audit activity log.
 * Every mutation in the plugin should call CVT_Activity_Log::log().
 */
class CVT_Activity_Log {

	/**
	 * Write a log entry.
	 *
	 * @param string     $entity_type  'vendor' | 'item' | 'payout'
	 * @param int        $entity_id
	 * @param string     $action       e.g. 'created', 'status_changed', 'payout_marked_paid'
	 * @param mixed|null $old_value    Previous state (will be JSON-encoded).
	 * @param mixed|null $new_value    New state (will be JSON-encoded).
	 * @param string     $note         Optional human-readable note.
	 */
	public static function log( $entity_type, $entity_id, $action, $old_value = null, $new_value = null, $note = '' ) {
		global $wpdb;

		$wpdb->insert(
			CVT_DB::activity(),
			array(
				'entity_type' => $entity_type,
				'entity_id'   => absint( $entity_id ),
				'action'      => sanitize_key( $action ),
				'old_value'   => $old_value !== null ? wp_json_encode( $old_value ) : null,
				'new_value'   => $new_value !== null ? wp_json_encode( $new_value ) : null,
				'note'        => sanitize_textarea_field( $note ),
				'user_id'     => get_current_user_id(),
				'created_at'  => CVT_DB::now(),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s' )
		);
	}

	/**
	 * Retrieve all log entries for a specific entity, newest first.
	 *
	 * @param string $entity_type
	 * @param int    $entity_id
	 * @return array
	 */
	public static function get_for_entity( $entity_type, $entity_id ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.*, u.display_name
				 FROM %i l
				 LEFT JOIN {$wpdb->users} u ON u.ID = l.user_id
				 WHERE l.entity_type = %s AND l.entity_id = %d
				 ORDER BY l.created_at DESC",
				CVT_DB::activity(),
				$entity_type,
				absint( $entity_id )
			)
		);
	}

	/**
	 * Retrieve recent log entries across all entities, for the dashboard feed.
	 *
	 * @param  int   $limit
	 * @return array
	 */
	public static function get_recent( $limit = 15 ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.*, u.display_name
				 FROM %i l
				 LEFT JOIN {$wpdb->users} u ON u.ID = l.user_id
				 ORDER BY l.created_at DESC
				 LIMIT %d",
				CVT_DB::activity(),
				absint( $limit )
			)
		);
	}

	/**
	 * Returns a human-readable sentence describing a log action.
	 */
	public static function describe( $log_entry ) {
		$actor = $log_entry->display_name ?: 'System';
		$new   = $log_entry->new_value ? json_decode( $log_entry->new_value, true ) : array();
		$old   = $log_entry->old_value ? json_decode( $log_entry->old_value, true ) : array();

		switch ( $log_entry->action ) {
			case 'created':
				return "$actor created this record.";

			case 'status_changed':
				$from = CVT_Settings::status_info( $old['status'] ?? '' )['label'] ?? ( $old['status'] ?? '?' );
				$to   = CVT_Settings::status_info( $new['status'] ?? '' )['label'] ?? ( $new['status'] ?? '?' );
				return "$actor changed status from <strong>$from</strong> to <strong>$to</strong>.";

			case 'updated':
				return "$actor updated record details.";

			case 'payout_created':
				return "$actor — payout record auto-created on sale.";

			case 'payout_marked_paid':
				$ref = $new['reference'] ?? '';
				return "$actor marked payout as Paid" . ( $ref ? " (ref: <strong>" . esc_html( $ref ) . "</strong>)" : '' ) . '.';

			case 'note_added':
				return "$actor added a note.";

			case 'image_added':
				return "$actor added an image.";

			case 'image_removed':
				return "$actor removed an image.";

			default:
				return "$actor performed action: " . esc_html( $log_entry->action ) . '.';
		}
	}
}
