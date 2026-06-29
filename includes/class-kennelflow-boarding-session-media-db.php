<?php
/**
 * Session photo rows for boarding stays.
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_Session_Media_Db
 */
class KennelFlow_Boarding_Session_Media_Db {

	/**
	 * Allowed media kinds for boarding stays.
	 */
	const MEDIA_KINDS = array( 'check_in', 'check_out' );

	/**
	 * @return string
	 */
	public static function media_table() {
		global $wpdb;

		return $wpdb->prefix . 'kennelflow_boarding_session_media';
	}

	/**
	 * @param int $booking_post_id Booking post ID.
	 * @return object[]
	 */
	public static function get_media_rows( $booking_post_id ) {
		global $wpdb;

		$booking_post_id = absint( $booking_post_id );
		if ( $booking_post_id < 1 || ! self::table_exists( self::media_table() ) ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM `' . self::media_table() . '` WHERE booking_post_id = %d ORDER BY id ASC', $booking_post_id ) );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param int                 $booking_post_id Booking post ID.
	 * @param array<string,mixed> $data            media_kind, attachment_id.
	 * @param int                 $staff_user_id   Current user.
	 * @return int
	 */
	public static function insert_media_row( $booking_post_id, array $data, $staff_user_id = 0 ) {
		global $wpdb;

		$booking_post_id = absint( $booking_post_id );
		if ( $booking_post_id < 1 || ! self::table_exists( self::media_table() ) ) {
			return 0;
		}

		$kind = isset( $data['media_kind'] ) ? sanitize_key( (string) $data['media_kind'] ) : 'check_in';
		if ( ! in_array( $kind, self::MEDIA_KINDS, true ) ) {
			$kind = 'check_in';
		}

		$attachment_id = isset( $data['attachment_id'] ) ? absint( $data['attachment_id'] ) : 0;
		if ( $attachment_id < 1 ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			self::media_table(),
			array(
				'booking_post_id' => $booking_post_id,
				'media_kind'      => $kind,
				'attachment_id'   => $attachment_id,
				'staff_user_id'   => absint( $staff_user_id ),
				'created_gmt'     => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%d', '%d', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * @param int $id Row ID.
	 * @return void
	 */
	public static function delete_media_row( $id ) {
		global $wpdb;

		$id = absint( $id );
		if ( $id < 1 || ! self::table_exists( self::media_table() ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->delete( self::media_table(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * @param string $table Full table name.
	 * @return bool
	 */
	protected static function table_exists( $table ) {
		if ( function_exists( 'ltkf_table_exists' ) ) {
			return ltkf_table_exists( $table );
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		return (string) $found === $table;
	}
}
