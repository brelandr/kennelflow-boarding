<?php
/**
 * Kennel availability queries (half-open intervals, GMT strings).
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_Availability
 */
class KennelFlow_Boarding_Availability {

	/**
	 * Statuses that block a kennel slot for overlap checks.
	 *
	 * @return string[]
	 */
	public static function get_blocking_statuses() {
		$statuses = array( 'pending', 'confirmed', 'checked_in' );

		/**
		 * Filters which booking statuses block availability for a kennel.
		 *
		 * @since 0.1.0
		 *
		 * @param string[] $statuses Status slugs.
		 */
		return kennelflow_boarding_apply_filters( 'availability_blocking_statuses', $statuses );
	}

	/**
	 * Booking kinds that consume a kennel slot (overlap). Clinic does not block kennel occupancy.
	 *
	 * @return string[]
	 */
	public static function get_kennel_blocking_booking_kinds() {
		$kinds = array( 'boarding', '' );

		/**
		 * Filters which booking_kind values block kennel availability.
		 *
		 * @since 0.1.0
		 *
		 * @param string[] $kinds Kind slugs (empty string = legacy rows without meta).
		 */
		return kennelflow_boarding_apply_filters( 'availability_blocking_booking_kinds', $kinds );
	}

	/**
	 * Parse a value into MySQL datetime string in GMT (Y-m-d H:i:s).
	 *
	 * Accepts ISO 8601 / RFC3339 or Y-m-d H:i:s interpreted as UTC.
	 *
	 * @param string $value Raw input.
	 * @return string|WP_Error
	 */
	public static function parse_gmt_mysql( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return new WP_Error( 'kennelpress_empty_datetime', __( 'Empty datetime.', 'kennelflow-boarding' ) );
		}

		$utc = new DateTimeZone( 'UTC' );

		try {
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}$/', $value ) ) {
				$d = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', str_replace( 'T', ' ', $value ), $utc );
			} else {
				$d = new DateTimeImmutable( $value, $utc );
			}
		} catch ( Exception $e ) {
			return new WP_Error( 'kennelpress_bad_datetime', __( 'Invalid datetime.', 'kennelflow-boarding' ) );
		}

		return $d->setTimezone( $utc )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Ensure half-open interval [start, end) is valid.
	 *
	 * @param string $start_gmt Start Y-m-d H:i:s UTC.
	 * @param string $end_gmt   End Y-m-d H:i:s UTC.
	 * @return true|WP_Error
	 */
	public static function validate_interval( $start_gmt, $end_gmt ) {
		$s = strtotime( $start_gmt . ' UTC' );
		$e = strtotime( $end_gmt . ' UTC' );
		if ( false === $s || false === $e ) {
			return new WP_Error( 'kennelpress_bad_interval', __( 'Invalid interval.', 'kennelflow-boarding' ) );
		}
		if ( $e <= $s ) {
			return new WP_Error( 'kennelpress_end_before_start', __( 'End must be after start.', 'kennelflow-boarding' ) );
		}
		return true;
	}

	/**
	 * Whether a clinician has any blocking hub booking overlapping [start, end) (kennel_id = user id, any kind).
	 *
	 * Delegates to {@see ltkf_clinician_has_global_booking_overlap()} when KennelFlow provides it; otherwise falls back to
	 * clinic-only rows (legacy).
	 *
	 * @param int    $clinician_user_id User ID.
	 * @param string $start_gmt         Start Y-m-d H:i:s UTC.
	 * @param string $end_gmt           End Y-m-d H:i:s UTC.
	 * @return bool|WP_Error True when a blocking booking overlaps the interval.
	 */
	public static function clinician_has_overlapping_clinic_booking( $clinician_user_id, $start_gmt, $end_gmt ) {
		$clinician_user_id = absint( $clinician_user_id );
		if ( $clinician_user_id < 1 ) {
			return false;
		}

		$ok = self::validate_interval( $start_gmt, $end_gmt );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}

		if ( function_exists( 'ltkf_clinician_has_global_booking_overlap' ) ) {
			return ltkf_clinician_has_global_booking_overlap( $clinician_user_id, $start_gmt, $end_gmt );
		}

		$statuses = self::get_blocking_statuses();
		if ( empty( $statuses ) ) {
			return false;
		}

		$kind = KennelFlow_Boarding_Post_Meta::sanitize_booking_kind( 'clinic' );

		global $wpdb;

		$table               = KennelFlow_Boarding_Booking_Index::table_name();
		$status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$prepare_args        = array_merge(
			array( $clinician_user_id, $kind ),
			$statuses,
			array( $end_gmt, $start_gmt )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- IN list from fixed blocking statuses array; table from install.
		$sql = $wpdb->prepare(
			"SELECT 1 FROM `{$table}` WHERE kennel_id = %d AND booking_kind = %s AND status IN ({$status_placeholders}) AND start_gmt < %s AND end_gmt > %s LIMIT 1",
			...$prepare_args
		);

		if ( null === $sql ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		$hit = $wpdb->get_var( $sql );

		return (bool) $hit;
	}

	/**
	 * Kennel post IDs in a location term available for [start, end).
	 *
	 * Uses NOT EXISTS against the kf_bookings index (same semantics as LEFT JOIN kf_bookings … WHERE kfb.id IS NULL).
	 *
	 * When `$clinician_id` is set, the interval is unavailable unless the clinician passes roster checks at this Hub
	 * location and has no overlapping booking as a global resource in `kf_bookings` (any kind), even when kennels are free.
	 *
	 * @param int    $location_post_id Hub `kf_location` post ID.
	 * @param string $start_gmt        Start Y-m-d H:i:s UTC.
	 * @param string $end_gmt          End Y-m-d H:i:s UTC.
	 * @param int    $clinician_id     Optional WordPress user ID (clinic resource) to reserve against the index.
	 * @return int[]|WP_Error
	 */
	public static function get_available_kennel_ids( $location_post_id, $start_gmt, $end_gmt, $clinician_id = 0 ) {
		$location_post_id = absint( $location_post_id );
		if ( $location_post_id < 1 ) {
			return new WP_Error( 'kennelpress_bad_location', __( 'Invalid location.', 'kennelflow-boarding' ) );
		}

		$ok = self::validate_interval( $start_gmt, $end_gmt );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}

		$clinician_id = absint( $clinician_id );
		if ( $clinician_id > 0 ) {
			if ( class_exists( \Landtech\KennelFlow\Core\AdminClinicianProfiles::class ) ) {
				if ( ! \Landtech\KennelFlow\Core\AdminClinicianProfiles::is_clinician_interval_within_roster_at_location( $clinician_id, $location_post_id, $start_gmt, $end_gmt ) ) {
					return array();
				}
			}
			$busy = self::clinician_has_overlapping_clinic_booking( $clinician_id, $start_gmt, $end_gmt );
			if ( is_wp_error( $busy ) ) {
				return $busy;
			}
			if ( $busy ) {
				return array();
			}
		}

		$statuses = self::get_blocking_statuses();
		if ( empty( $statuses ) ) {
			return self::get_kennel_ids_in_location( $location_post_id );
		}

		$kinds  = self::get_kennel_blocking_booking_kinds();
		$bust   = KennelFlow_Boarding_Cache::get_query_bust();
		$sig    = md5( (string) $location_post_id . '|' . $start_gmt . '|' . $end_gmt . '|' . wp_json_encode( $statuses ) . '|' . wp_json_encode( $kinds ) . '|c:' . $clinician_id );
		$key    = 'kennelpress_availability_ids_' . $bust . '_' . $sig;
		$cached = wp_cache_get( $key, KennelFlow_Boarding_Cache::OBJECT_CACHE_GROUP_AVAILABILITY );
		if ( false !== $cached && is_array( $cached ) ) {
			return array_map( 'absint', $cached );
		}

		global $wpdb;

		$placeholders      = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$kind_placeholders = implode( ',', array_fill( 0, count( $kinds ), '%s' ) );
		$table             = KennelFlow_Boarding_Booking_Index::table_name();
		$loc_meta_key      = KennelFlow_Boarding_Post_Meta::KENNEL_LOCATION_ID;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from KennelFlow_Boarding_Install::bookings_table(); IN lists built from fixed arrays.
		$sql = "
			SELECT p.ID
			FROM {$wpdb->posts} AS p
			INNER JOIN {$wpdb->postmeta} AS pm ON pm.post_id = p.ID
				AND pm.meta_key = %s
				AND pm.meta_value = %s
			WHERE p.post_type = 'kennelpress_kennel'
			AND p.post_status = 'publish'
			AND NOT EXISTS (
				SELECT 1
				FROM {$table} AS kfb
				INNER JOIN {$wpdb->posts} AS b ON b.ID = kfb.post_id
				WHERE kfb.kennel_id = p.ID
				AND b.post_type = 'kennelpress_booking'
				AND b.post_status IN ( 'publish', 'draft', 'pending', 'private' )
				AND kfb.booking_kind IN ( {$kind_placeholders} )
				AND kfb.status IN ( {$placeholders} )
				AND kfb.start_gmt < %s
				AND %s < kfb.end_gmt
			)
		";

		$prepare_args = array_merge(
			array( $loc_meta_key, (string) $location_post_id ),
			$kinds,
			$statuses,
			array( $end_gmt, $start_gmt )
		);

		$query = $wpdb->prepare( $sql, ...$prepare_args );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Result cached via wp_cache_set; bust on booking/kennel/location changes.
		$ids = $wpdb->get_col( $query );

		if ( ! is_array( $ids ) ) {
			return new WP_Error( 'kennelpress_db_error', __( 'Could not load availability.', 'kennelflow-boarding' ) );
		}

		$ids = array_map( 'absint', $ids );
		wp_cache_set( $key, $ids, KennelFlow_Boarding_Cache::OBJECT_CACHE_GROUP_AVAILABILITY, KennelFlow_Boarding_Cache::TRANSIENT_TTL );

		return $ids;
	}

	/**
	 * All published kennel IDs in a location (ignores bookings).
	 *
	 * @param int $location_post_id Hub `kf_location` post ID.
	 * @return int[]
	 */
	public static function get_kennel_ids_in_location( $location_post_id ) {
		$location_post_id = absint( $location_post_id );
		if ( $location_post_id < 1 ) {
			return array();
		}

		$bust   = KennelFlow_Boarding_Cache::get_query_bust();
		$key    = 'kennelpress_loc_kennels_' . $bust . '_' . $location_post_id;
		$cached = wp_cache_get( $key, KennelFlow_Boarding_Cache::OBJECT_CACHE_GROUP_AVAILABILITY );
		if ( false !== $cached && is_array( $cached ) ) {
			return array_map( 'absint', $cached );
		}

		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Filter by Hub location post ID on kennel meta.
		$q = new WP_Query(
			array(
				'post_type'              => 'kennelpress_kennel',
				'post_status'            => 'publish',
				'fields'                 => 'ids',
				'posts_per_page'         => -1,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'meta_query'             => array(
					array(
						'key'   => KennelFlow_Boarding_Post_Meta::KENNEL_LOCATION_ID,
						'value' => $location_post_id,
						'type'  => 'NUMERIC',
					),
				),
			)
		);
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query

		if ( ! is_array( $q->posts ) ) {
			return array();
		}

		$ids = array_map( 'absint', $q->posts );
		wp_cache_set( $key, $ids, KennelFlow_Boarding_Cache::OBJECT_CACHE_GROUP_AVAILABILITY, KennelFlow_Boarding_Cache::TRANSIENT_TTL );

		return $ids;
	}

	/**
	 * Whether the pet has any boarding booking overlapping [start, end) with blocking statuses.
	 *
	 * @param int           $pet_id          Pet post ID.
	 * @param string        $start_gmt       Start Y-m-d H:i:s UTC.
	 * @param string        $end_gmt         End Y-m-d H:i:s UTC.
	 * @param string[]|null $booking_kinds   If null, only kinds that block occupancy (boarding + legacy empty).
	 * @return bool|WP_Error
	 */
	public static function pet_has_overlapping_boarding_booking( $pet_id, $start_gmt, $end_gmt, $booking_kinds = null ) {
		$pet_id = absint( $pet_id );
		if ( $pet_id < 1 ) {
			return new WP_Error( 'kennelpress_bad_pet', __( 'Invalid pet.', 'kennelflow-boarding' ) );
		}

		$ok = self::validate_interval( $start_gmt, $end_gmt );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}

		$statuses = self::get_blocking_statuses();
		if ( empty( $statuses ) ) {
			return false;
		}

		$kinds = null !== $booking_kinds ? array_map( 'sanitize_key', (array) $booking_kinds ) : self::get_kennel_blocking_booking_kinds();

		global $wpdb;

		$placeholders      = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$kind_placeholders = implode( ',', array_fill( 0, count( $kinds ), '%s' ) );
		$table             = KennelFlow_Boarding_Booking_Index::table_name();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "
			SELECT 1
			FROM {$table} AS kfb
			INNER JOIN {$wpdb->posts} AS b ON b.ID = kfb.post_id
			WHERE kfb.pet_id = %d
			AND b.post_type = 'kennelpress_booking'
			AND b.post_status IN ( 'publish', 'draft', 'pending', 'private' )
			AND kfb.booking_kind IN ( {$kind_placeholders} )
			AND kfb.status IN ( {$placeholders} )
			AND kfb.start_gmt < %s
			AND %s < kfb.end_gmt
			LIMIT 1
		";

		$prepare_args = array_merge(
			array( $pet_id ),
			$kinds,
			$statuses,
			array( $end_gmt, $start_gmt )
		);

		$query = $wpdb->prepare( $sql, ...$prepare_args );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$found = $wpdb->get_var( $query );

		return (bool) $found;
	}
}
