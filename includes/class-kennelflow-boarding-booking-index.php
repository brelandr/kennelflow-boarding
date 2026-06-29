<?php
/**
 * Keeps {$wpdb->prefix}kf_bookings in sync with kennelpress_booking posts (query index).
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_Booking_Index
 */
class KennelFlow_Boarding_Booking_Index {

	/**
	 * Register sync hooks.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		add_action( 'save_post_kennelpress_booking', array( __CLASS__, 'sync_from_post' ), 99, 3 );
		add_action( 'save_post_kennelpress_kennel', array( __CLASS__, 'sync_bookings_for_kennel' ), 99, 3 );
		add_action( 'before_delete_post', array( __CLASS__, 'before_delete_post' ), 9, 1 );
		add_action( 'added_post_meta', array( __CLASS__, 'on_meta_change' ), 20, 4 );
		add_action( 'updated_post_meta', array( __CLASS__, 'on_meta_change' ), 20, 4 );
		add_action( 'updated_post_meta', array( __CLASS__, 'on_kennel_location_meta_updated' ), 25, 4 );
	}

	/**
	 * Table name including prefix.
	 *
	 * @return string
	 */
	public static function table_name() {
		return KennelFlow_Boarding_Install::bookings_table();
	}

	/**
	 * Current index row for a booking post (if any).
	 *
	 * @param int $post_id Booking post ID.
	 * @return object|null
	 */
	public static function get_index_row_for_post( $post_id ) {
		global $wpdb;

		$post_id = absint( $post_id );
		if ( $post_id < 1 ) {
			return null;
		}

		$table = self::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Single-row lookup; table from install.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE post_id = %d LIMIT 1", $post_id ) );

		return is_object( $row ) ? $row : null;
	}

	/**
	 * Whether a booking status consumes a kennel slot for waitlist purposes.
	 *
	 * @param string $status Status slug.
	 * @return bool
	 */
	protected static function status_blocks_kennel_slot( $status ) {
		$status = sanitize_key( (string) $status );
		$block  = array( 'pending', 'pending_payment', 'confirmed', 'checked_in' );

		/**
		 * Filters statuses that block a kennel when deciding if a cancellation frees a slot.
		 *
		 * @since 0.1.0
		 *
		 * @param string[] $block   Statuses.
		 * @param string   $status  Current status being checked.
		 */
		$block = kennelflow_boarding_apply_filters( 'booking_blocking_statuses_for_waitlist', $block, $status );

		return in_array( $status, $block, true );
	}

	/**
	 * Fires KennelFlow Core hook when a boarding slot may have been freed.
	 *
	 * @param int    $post_id Booking post ID.
	 * @param object $row     Index row snapshot.
	 * @param string $reason  deleted|cancelled.
	 * @return void
	 */
	protected static function fire_kf_booking_cancelled( $post_id, $row, $reason ) {
		if ( ! is_object( $row ) ) {
			return;
		}

		$kind = isset( $row->booking_kind ) ? sanitize_key( (string) $row->booking_kind ) : '';
		if ( 'clinic' === $kind ) {
			return;
		}

		$payload = array(
			'booking_post_id' => absint( $post_id ),
			'start_gmt'       => isset( $row->start_gmt ) ? (string) $row->start_gmt : '',
			'end_gmt'         => isset( $row->end_gmt ) ? (string) $row->end_gmt : '',
			'location_id'     => isset( $row->location_id ) ? absint( $row->location_id ) : 0,
			'pet_id'          => isset( $row->pet_id ) ? absint( $row->pet_id ) : 0,
			'kennel_id'       => isset( $row->kennel_id ) ? absint( $row->kennel_id ) : 0,
			'reason'          => sanitize_key( (string) $reason ),
			'previous_status' => isset( $row->status ) ? (string) $row->status : '',
		);

		/**
		 * Fires when a boarding booking was cancelled or removed and may free a kennel slot.
		 *
		 * @since 0.1.0
		 *
		 * @param array $payload Context (booking_post_id, start_gmt, end_gmt, location_id, pet_id, kennel_id, reason, previous_status).
		 */
		do_action( 'ltkf_booking_cancelled', $payload );
	}

	/**
	 * Meta keys that affect the index row.
	 *
	 * @return string[]
	 */
	protected static function tracked_meta_keys() {
		return array(
			KennelFlow_Boarding_Post_Meta::BOOKING_PET_ID,
			KennelFlow_Boarding_Post_Meta::BOOKING_KENNEL_ID,
			KennelFlow_Boarding_Post_Meta::BOOKING_LOCATION_ID,
			KennelFlow_Boarding_Post_Meta::BOOKING_START_GMT,
			KennelFlow_Boarding_Post_Meta::BOOKING_END_GMT,
			KennelFlow_Boarding_Post_Meta::BOOKING_STATUS,
			KennelFlow_Boarding_Post_Meta::BOOKING_KIND,
		);
	}

	/**
	 * Re-sync when booking meta changes without save_post (e.g. REST PATCH status).
	 *
	 * @param int    $meta_id    Meta ID.
	 * @param int    $post_id    Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @return void
	 */
	public static function on_meta_change( $meta_id, $post_id, $meta_key, $meta_value ) {
		unset( $meta_id, $meta_value );
		$post_id = absint( $post_id );
		if ( $post_id < 1 ) {
			return;
		}
		if ( 'kennelpress_booking' !== get_post_type( $post_id ) ) {
			return;
		}
		if ( ! in_array( $meta_key, self::tracked_meta_keys(), true ) ) {
			return;
		}
		self::sync_from_post( $post_id );
	}

	/**
	 * Remove index row before the post row is deleted.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function before_delete_post( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id < 1 ) {
			return;
		}
		if ( 'kennelpress_booking' !== get_post_type( $post_id ) ) {
			return;
		}
		self::delete_for_post( $post_id );
	}

	/**
	 * Upsert or delete index row from post + meta.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an update.
	 * @return void
	 */
	public static function sync_from_post( $post_id, $post = null, $update = null ) {
		unset( $update );
		$post_id = absint( $post_id );
		if ( $post_id < 1 ) {
			return;
		}

		$post = $post instanceof WP_Post ? $post : get_post( $post_id );
		if ( ! $post || 'kennelpress_booking' !== $post->post_type ) {
			self::delete_for_post( $post_id );
			return;
		}

		if ( in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
			self::delete_for_post( $post_id );
			return;
		}

		$pet_id        = absint( get_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_PET_ID, true ) );
		$kennel_id     = absint( get_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_KENNEL_ID, true ) );
		$loc_from_meta = absint( get_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_LOCATION_ID, true ) );
		$start         = (string) get_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_START_GMT, true );
		$end           = (string) get_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_END_GMT, true );
		$status        = (string) get_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_STATUS, true );
		$kind_raw      = get_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_KIND, true );

		$status = KennelFlow_Boarding_Post_Meta::sanitize_booking_status( $status );
		if ( '' === $kind_raw || false === $kind_raw ) {
			$kind = '';
		} else {
			$kind = KennelFlow_Boarding_Post_Meta::sanitize_booking_kind( (string) $kind_raw );
		}

		if ( '' === $start || '' === $end ) {
			self::delete_for_post( $post_id );
			return;
		}

		if ( $kennel_id < 1 ) {
			self::delete_for_post( $post_id );
			return;
		}

		$ok = KennelFlow_Boarding_Availability::validate_interval( $start, $end );
		if ( is_wp_error( $ok ) ) {
			self::delete_for_post( $post_id );
			return;
		}

		$location_id = $loc_from_meta > 0 ? $loc_from_meta : self::get_location_id_for_kennel( $kennel_id );

		$created_gmt = '';
		if ( $post instanceof WP_Post ) {
			$created_gmt = (string) $post->post_date_gmt;
		}
		if ( '' === $created_gmt || '0000-00-00 00:00:00' === $created_gmt || '1970-01-01 00:00:00' === $created_gmt ) {
			$created_gmt = current_time( 'mysql', true );
		}

		$old_row = self::get_index_row_for_post( $post_id );

		global $wpdb;
		$table = self::table_name();

		$row_data = array(
			'pet_id'       => $pet_id,
			'kennel_id'    => $kennel_id,
			'location_id'  => $location_id,
			'start_gmt'    => $start,
			'end_gmt'      => $end,
			'status'       => $status,
			'booking_kind' => $kind,
		);
		$row_format = array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Index maintenance.
		if ( $old_row ) {
			$wpdb->update(
				$table,
				$row_data,
				array( 'post_id' => $post_id ),
				$row_format,
				array( '%d' )
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Index maintenance.
			$wpdb->insert(
				$table,
				array(
					'post_id'      => $post_id,
					'pet_id'       => $pet_id,
					'kennel_id'    => $kennel_id,
					'location_id'  => $location_id,
					'start_gmt'    => $start,
					'end_gmt'      => $end,
					'status'       => $status,
					'booking_kind' => $kind,
					'created_gmt'  => $created_gmt,
				),
				array( '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
			);
		}

		if ( 'cancelled' === $status && $old_row && self::status_blocks_kennel_slot( (string) $old_row->status ) && 'cancelled' !== (string) $old_row->status ) {
			self::fire_kf_booking_cancelled( $post_id, $old_row, 'cancelled' );
		}
	}

	/**
	 * Hub location post ID for a kennel (`_kennelpress_location_id` → `kf_location` CPT).
	 *
	 * @param int $kennel_id Kennel post ID.
	 * @return int
	 */
	protected static function get_location_id_for_kennel( $kennel_id ) {
		$kennel_id = absint( $kennel_id );
		if ( $kennel_id < 1 ) {
			return 0;
		}

		return absint( get_post_meta( $kennel_id, KennelFlow_Boarding_Post_Meta::KENNEL_LOCATION_ID, true ) );
	}

	/**
	 * Re-sync booking index rows when a kennel is saved (e.g. location assignment changed).
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 * @param bool    $update  Whether update.
	 * @return void
	 */
	public static function sync_bookings_for_kennel( $post_id, $post = null, $update = null ) {
		unset( $post, $update );
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		$post_id = absint( $post_id );
		if ( $post_id < 1 ) {
			return;
		}
		if ( 'kennelpress_kennel' !== get_post_type( $post_id ) ) {
			return;
		}

		$booking_ids = get_posts(
			array(
				'post_type'      => 'kennelpress_booking',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'   => KennelFlow_Boarding_Post_Meta::BOOKING_KENNEL_ID,
						'value' => $post_id,
					),
				),
			)
		);

		foreach ( (array) $booking_ids as $bid ) {
			self::sync_from_post( (int) $bid );
		}
	}

	/**
	 * When kennel location meta is updated outside a full save, refresh booking rows.
	 *
	 * @param int    $meta_id    Meta ID.
	 * @param int    $post_id    Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @return void
	 */
	public static function on_kennel_location_meta_updated( $meta_id, $post_id, $meta_key, $meta_value ) {
		unset( $meta_id, $meta_value );
		if ( KennelFlow_Boarding_Post_Meta::KENNEL_LOCATION_ID !== $meta_key ) {
			return;
		}
		if ( 'kennelpress_kennel' !== get_post_type( $post_id ) ) {
			return;
		}
		self::sync_bookings_for_kennel( (int) $post_id );
	}

	/**
	 * Delete index row for a booking post.
	 *
	 * @param int $post_id Booking post ID.
	 * @return void
	 */
	public static function delete_for_post( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id < 1 ) {
			return;
		}

		$row = self::get_index_row_for_post( $post_id );

		global $wpdb;
		$table = self::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Index row removal.
		$wpdb->delete( $table, array( 'post_id' => $post_id ), array( '%d' ) );

		if ( $row && self::status_blocks_kennel_slot( (string) $row->status ) ) {
			self::fire_kf_booking_cancelled( $post_id, $row, 'deleted' );
		}
	}

	/**
	 * Backfill index from existing booking posts (upgrade / repair).
	 *
	 * @return void
	 */
	public static function backfill_all() {
		// phpcs:disable WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Admin migration.
		$ids = get_posts(
			array(
				'post_type'      => 'kennelpress_booking',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		// phpcs:enable WordPress.WP.PostsPerPage.posts_per_page_posts_per_page

		if ( ! is_array( $ids ) ) {
			return;
		}

		foreach ( $ids as $post_id ) {
			self::sync_from_post( (int) $post_id );
		}
	}
}
