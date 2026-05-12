<?php
/**
 * Append-only audit log for high-compliance booking and entity changes.
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_Audit
 */
class KennelFlow_Boarding_Audit {

	/**
	 * Register hooks that write to the audit log.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		add_filter( 'update_post_metadata', array( __CLASS__, 'on_update_post_metadata' ), 10, 5 );
		add_action( 'added_post_meta', array( __CLASS__, 'on_added_post_meta' ), 10, 4 );
	}

	/**
	 * Log booking status changes when meta is updated.
	 *
	 * @param null|bool $check      Short-circuit.
	 * @param int       $object_id  Post ID.
	 * @param string    $meta_key   Meta key.
	 * @param mixed     $meta_value New value.
	 * @param mixed     $prev_value Previous value.
	 * @return null|bool
	 */
	public static function on_update_post_metadata( $check, $object_id, $meta_key, $meta_value, $prev_value ) {
		unset( $check );
		if ( KennelFlow_Boarding_Post_Meta::BOOKING_STATUS !== $meta_key ) {
			return null;
		}
		if ( 'kennelpress_booking' !== get_post_type( $object_id ) ) {
			return null;
		}
		self::log( 'kennelpress_booking', (int) $object_id, 'status_change', $prev_value, $meta_value );
		return null;
	}

	/**
	 * Log first-time status when meta is added.
	 *
	 * @param int    $meta_id    Meta row ID.
	 * @param int    $object_id  Post ID.
	 * @param string $meta_key   Key.
	 * @param mixed  $meta_value Value.
	 * @return void
	 */
	public static function on_added_post_meta( $meta_id, $object_id, $meta_key, $meta_value ) {
		unset( $meta_id );
		if ( KennelFlow_Boarding_Post_Meta::BOOKING_STATUS !== $meta_key ) {
			return;
		}
		if ( 'kennelpress_booking' !== get_post_type( $object_id ) ) {
			return;
		}
		self::log( 'kennelpress_booking', (int) $object_id, 'status_set', null, $meta_value );
	}

	/**
	 * Insert audit row (no updates/deletes).
	 *
	 * @param string $entity_type Entity slug (e.g. kennelpress_booking).
	 * @param int    $entity_id   Post or record ID.
	 * @param string $action      Action slug (e.g. status_change).
	 * @param mixed  $old_value   Serializable previous value.
	 * @param mixed  $new_value   Serializable new value.
	 * @param int    $user_id     Actor; defaults to current user.
	 * @return bool Whether a row was written.
	 */
	public static function log( $entity_type, $entity_id, $action, $old_value, $new_value, $user_id = 0 ) {
		global $wpdb;

		$table = KennelFlow_Boarding_Install::audit_table();
		$uid   = $user_id > 0 ? absint( $user_id ) : get_current_user_id();
		if ( $uid < 1 ) {
			$uid = 0;
		}

		$entity_type = sanitize_key( (string) $entity_type );
		$action      = sanitize_key( (string) $action );
		$entity_id   = absint( $entity_id );

		$old_json = wp_json_encode( $old_value );
		$new_json = wp_json_encode( $new_value );
		if ( false === $old_json ) {
			$old_json = '';
		}
		if ( false === $new_json ) {
			$new_json = '';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Audit insert; no WP API for custom table.
		$ok = $wpdb->insert(
			$table,
			array(
				'entity_type' => $entity_type,
				'entity_id'   => $entity_id,
				'user_id'     => $uid,
				'action'      => $action,
				'old_value'   => $old_json,
				'new_value'   => $new_json,
				'created_gmt' => current_time( 'mysql', true ),
			),
			array( '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		return false !== $ok;
	}
}
