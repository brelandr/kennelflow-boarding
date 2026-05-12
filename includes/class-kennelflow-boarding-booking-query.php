<?php
/**
 * Query bookings by time range (GMT half-open intervals) via {$wpdb->prefix}kf_bookings index.
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_Booking_Query
 */
class KennelFlow_Boarding_Booking_Query {

	/**
	 * Booking posts overlapping [range_start, range_end) in UTC.
	 *
	 * Uses the kf_bookings table (not postmeta) for interval + location filters.
	 *
	 * @param string   $range_start_gmt Start Y-m-d H:i:s UTC.
	 * @param string   $range_end_gmt   End Y-m-d H:i:s UTC.
	 * @param int|null $location_post_id Optional: Hub `kf_location` post ID; only bookings whose indexed location matches.
	 * @return int[] Post IDs.
	 */
	public static function get_booking_ids_overlapping_range( $range_start_gmt, $range_end_gmt, $location_post_id = null ) {
		$ok = KennelFlow_Boarding_Availability::validate_interval( $range_start_gmt, $range_end_gmt );
		if ( is_wp_error( $ok ) ) {
			return array();
		}

		global $wpdb;

		$location_post_id = null !== $location_post_id ? absint( $location_post_id ) : 0;
		$table            = KennelFlow_Boarding_Booking_Index::table_name();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name is from KennelFlow_Boarding_Install::bookings_table(); values are prepared.
		if ( $location_post_id > 0 ) {
			$query = $wpdb->prepare(
				"
				SELECT DISTINCT kfb.post_id
				FROM {$table} AS kfb
				INNER JOIN {$wpdb->posts} AS b ON b.ID = kfb.post_id
				WHERE b.post_type = 'kennelpress_booking'
				AND b.post_status IN ( 'publish', 'pending', 'draft', 'private' )
				AND kfb.location_id = %d
				AND kfb.start_gmt < %s
				AND %s < kfb.end_gmt
				ORDER BY kfb.start_gmt ASC
				",
				$location_post_id,
				$range_end_gmt,
				$range_start_gmt
			);
		} else {
			$query = $wpdb->prepare(
				"
				SELECT DISTINCT kfb.post_id
				FROM {$table} AS kfb
				INNER JOIN {$wpdb->posts} AS b ON b.ID = kfb.post_id
				WHERE b.post_type = 'kennelpress_booking'
				AND b.post_status IN ( 'publish', 'pending', 'draft', 'private' )
				AND kfb.start_gmt < %s
				AND %s < kfb.end_gmt
				ORDER BY kfb.start_gmt ASC
				",
				$range_end_gmt,
				$range_start_gmt
			);
		}

		$ids = $wpdb->get_col( $query );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( ! is_array( $ids ) ) {
			return array();
		}

		return array_map( 'absint', $ids );
	}
}
