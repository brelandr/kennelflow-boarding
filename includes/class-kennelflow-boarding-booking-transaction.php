<?php
/**
 * MySQL transactions and SELECT … FOR UPDATE for booking race prevention.
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_Booking_Transaction
 */
class KennelFlow_Boarding_Booking_Transaction {

	/**
	 * Start a transaction on the primary wpdb connection.
	 *
	 * @return bool True when START TRANSACTION succeeded.
	 */
	public static function begin() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction boundary.
		$result = $wpdb->query( 'START TRANSACTION' );

		return false !== $result && '' === $wpdb->last_error;
	}

	/**
	 * Commit the current transaction.
	 *
	 * @return bool True when COMMIT succeeded.
	 */
	public static function commit() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction boundary.
		$result = $wpdb->query( 'COMMIT' );

		return false !== $result && '' === $wpdb->last_error;
	}

	/**
	 * Roll back the current transaction (safe to call multiple times).
	 *
	 * @return void
	 */
	public static function rollback() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction boundary.
		$wpdb->query( 'ROLLBACK' );
	}

	/**
	 * Boarding: overlapping blocking rows for a kennel (matches hub availability NOT EXISTS semantics).
	 *
	 * @param int    $kennel_id       Kennel post ID (kf_bookings.kennel_id).
	 * @param string $start_gmt       Start UTC.
	 * @param string $end_gmt         End UTC.
	 * @param int    $exclude_post_id Booking post to exclude (0 = none).
	 * @return bool True when a conflicting row exists (slot taken).
	 */
	public static function locked_boarding_kennel_has_conflict( $kennel_id, $start_gmt, $end_gmt, $exclude_post_id = 0 ) {
		global $wpdb;

		$kennel_id    = absint( $kennel_id );
		$exclude_post = absint( $exclude_post_id );
		if ( $kennel_id < 1 ) {
			return true;
		}

		$statuses = KennelFlow_Boarding_Availability::get_blocking_statuses();
		if ( empty( $statuses ) ) {
			return false;
		}

		$kinds = KennelFlow_Boarding_Availability::get_kennel_blocking_booking_kinds();
		if ( empty( $kinds ) ) {
			return false;
		}

		$table               = KennelFlow_Boarding_Booking_Index::table_name();
		$kind_placeholders   = implode( ', ', array_fill( 0, count( $kinds ), '%s' ) );
		$status_placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );

		$prepare_args = array_merge(
			array( $table, $wpdb->posts, $kennel_id, $exclude_post, 'kennelpress_booking' ),
			$kinds,
			$statuses,
			array( $end_gmt, $start_gmt )
		);

		$query = 'SELECT kfb.id FROM %i AS kfb INNER JOIN %i AS b ON b.ID = kfb.post_id WHERE kfb.kennel_id = %d AND kfb.post_id <> %d AND b.post_type = %s AND b.post_status IN ( \'publish\', \'draft\', \'pending\', \'private\' ) AND kfb.booking_kind IN (' . $kind_placeholders . ') AND kfb.status IN (' . $status_placeholders . ') AND kfb.start_gmt < %s AND %s < kfb.end_gmt LIMIT 1 FOR UPDATE';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- IN (...) lists expand sanitized literal %s tokens; arguments merged for prepare().
		$sql = call_user_func_array(
			array( $wpdb, 'prepare' ),
			array_merge( array( $query ), $prepare_args )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		if ( null === $sql ) {
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		$hit = $wpdb->get_var( $sql );

		return (bool) $hit;
	}

	/**
	 * Global clinician/groomer overlap (any booking_kind on kennel_id = user id), aligned with ltkf_clinician_has_global_booking_overlap().
	 *
	 * @param int    $user_id         WordPress user ID stored in kennel_id.
	 * @param string $start_gmt       Start UTC.
	 * @param string $end_gmt         End UTC.
	 * @param int    $exclude_post_id Booking post to exclude.
	 * @return bool True when a conflicting row exists.
	 */
	public static function locked_user_global_has_conflict( $user_id, $start_gmt, $end_gmt, $exclude_post_id = 0 ) {
		global $wpdb;

		$user_id      = absint( $user_id );
		$exclude_post = absint( $exclude_post_id );
		if ( $user_id < 1 ) {
			return true;
		}

		/**
		 * Statuses that block clinician time in the hub booking index.
		 *
		 * @since 0.2.0
		 *
		 * @param string[] $statuses Default aligns with KennelFlow_Boarding availability blocking.
		 */
		$statuses = apply_filters(
			'ltkf_clinician_overlap_blocking_statuses',
			array( 'pending', 'confirmed', 'checked_in' )
		);
		$statuses = array_filter( array_map( 'sanitize_key', (array) $statuses ) );
		if ( empty( $statuses ) ) {
			return false;
		}

		$table               = KennelFlow_Boarding_Booking_Index::table_name();
		$status_placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );

		if ( $exclude_post > 0 ) {
			$prepare_args = array_merge(
				array( $table, $user_id, $exclude_post ),
				$statuses,
				array( $end_gmt, $start_gmt )
			);
			$query        = 'SELECT id FROM %i WHERE kennel_id = %d AND post_id <> %d AND status IN (' . $status_placeholders . ') AND start_gmt < %s AND end_gmt > %s LIMIT 1 FOR UPDATE';
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			$sql = call_user_func_array(
				array( $wpdb, 'prepare' ),
				array_merge( array( $query ), $prepare_args )
			);
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		} else {
			$prepare_args = array_merge(
				array( $table, $user_id ),
				$statuses,
				array( $end_gmt, $start_gmt )
			);
			$query        = 'SELECT id FROM %i WHERE kennel_id = %d AND status IN (' . $status_placeholders . ') AND start_gmt < %s AND end_gmt > %s LIMIT 1 FOR UPDATE';
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			$sql = call_user_func_array(
				array( $wpdb, 'prepare' ),
				array_merge( array( $query ), $prepare_args )
			);
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		}

		if ( null === $sql ) {
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		$hit = $wpdb->get_var( $sql );

		return (bool) $hit;
	}

	/**
	 * Kennel resource + specific booking_kind overlap (matches KennelFlow_Boarding_REST_Bookings_Controller::has_resource_interval_conflict kennel path).
	 *
	 * @param int    $kennel_id       Kennel post ID.
	 * @param string $booking_kind    booking_kind value.
	 * @param string $start_gmt       Start UTC.
	 * @param string $end_gmt         End UTC.
	 * @param int    $exclude_post_id Booking post to exclude.
	 * @return bool True when a conflicting row exists.
	 */
	public static function locked_kennel_kind_has_conflict( $kennel_id, $booking_kind, $start_gmt, $end_gmt, $exclude_post_id = 0 ) {
		global $wpdb;

		$kennel_id    = absint( $kennel_id );
		$exclude_post = absint( $exclude_post_id );
		if ( $kennel_id < 1 ) {
			return true;
		}

		$booking_kind = KennelFlow_Boarding_Post_Meta::sanitize_booking_kind( (string) $booking_kind );
		$table        = KennelFlow_Boarding_Booking_Index::table_name();
		$statuses     = KennelFlow_Boarding_Availability::get_blocking_statuses();
		if ( empty( $statuses ) ) {
			return false;
		}

		$status_placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
		$query               = 'SELECT id FROM %i WHERE post_id <> %d AND kennel_id = %d AND booking_kind = %s AND status IN (' . $status_placeholders . ') AND start_gmt < %s AND end_gmt > %s LIMIT 1 FOR UPDATE';
		$prepare_args        = array_merge(
			array( $table, $exclude_post, $kennel_id, $booking_kind ),
			$statuses,
			array( $end_gmt, $start_gmt )
		);

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$sql = call_user_func_array(
			array( $wpdb, 'prepare' ),
			array_merge( array( $query ), $prepare_args )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		if ( null === $sql ) {
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		$hit = $wpdb->get_var( $sql );

		return (bool) $hit;
	}

	/**
	 * Same branching as has_resource_interval_conflict(): global user overlap when KennelFlow provides it; else kennel_id + booking_kind.
	 *
	 * @param int    $resource_id     Kennel post ID or user ID.
	 * @param string $booking_kind    booking_kind value.
	 * @param string $start_gmt       Start UTC.
	 * @param string $end_gmt         End UTC.
	 * @param int    $exclude_post_id Booking post to exclude.
	 * @return bool True when a conflicting row exists.
	 */
	public static function locked_resource_interval_has_conflict( $resource_id, $booking_kind, $start_gmt, $end_gmt, $exclude_post_id = 0 ) {
		$resource_id = absint( $resource_id );
		if ( $resource_id < 1 ) {
			return false;
		}

		if (
			'kennelpress_kennel' !== get_post_type( $resource_id )
			&& get_userdata( $resource_id )
			&& function_exists( 'ltkf_clinician_has_global_booking_overlap' )
			&& function_exists( 'ltkf_bookings_table_name' )
			&& function_exists( 'ltkf_table_exists' )
			&& ltkf_table_exists( ltkf_bookings_table_name() )
		) {
			return self::locked_user_global_has_conflict( $resource_id, $start_gmt, $end_gmt, $exclude_post_id );
		}

		return self::locked_kennel_kind_has_conflict( $resource_id, $booking_kind, $start_gmt, $end_gmt, $exclude_post_id );
	}
}
