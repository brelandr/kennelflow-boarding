<?php
/**
 * Scheduled tasks (pending booking TTL).
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_Cron
 */
class KennelFlow_Boarding_Cron {

	/**
	 * How long a pending booking may sit before expiring (seconds).
	 */
	const PENDING_TTL_SECONDS = 1800;

	/**
	 * Cron hook name.
	 */
	const CRON_HOOK = 'kennelpress_cron_expire_pending';

	/**
	 * Register schedules and hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_schedule' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'expire_stale_pending_bookings' ) );
	}

	/**
	 * Add 15-minute schedule.
	 *
	 * @param array $schedules Schedules.
	 * @return array
	 */
	public static function add_schedule( $schedules ) {
		if ( ! isset( $schedules['kennelpress_15min'] ) ) {
			$schedules['kennelpress_15min'] = array(
				'interval' => 15 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 15 minutes', 'kennelflow-boarding' ),
			);
		}
		return $schedules;
	}

	/**
	 * Ensure cron event is scheduled (idempotent).
	 *
	 * @return void
	 */
	public static function maybe_schedule() {
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}
		wp_schedule_event( time() + 120, 'kennelpress_15min', self::CRON_HOOK );
	}

	/**
	 * Clear scheduled event (on deactivation).
	 *
	 * @return void
	 */
	public static function unschedule() {
		$ts = wp_next_scheduled( self::CRON_HOOK );
		if ( $ts ) {
			wp_unschedule_event( $ts, self::CRON_HOOK );
		}
	}

	/**
	 * Mark old pending bookings as expired.
	 *
	 * @return void
	 */
	public static function expire_stale_pending_bookings() {
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - self::PENDING_TTL_SECONDS );

		$q = new WP_Query(
			array(
				'post_type'              => 'kennelpress_booking',
				'post_status'            => 'any',
				'posts_per_page'         => 100,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'date_query'             => array(
					array(
						'column'    => 'post_date_gmt',
						'before'    => $cutoff,
						'inclusive' => true,
					),
				),
				'meta_query'             => array(
					array(
						'key'   => KennelFlow_Boarding_Post_Meta::BOOKING_STATUS,
						'value' => 'pending',
					),
				),
			)
		);

		if ( empty( $q->posts ) ) {
			return;
		}

		foreach ( $q->posts as $post_id ) {
			update_post_meta( (int) $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_STATUS, 'expired' );
			/**
			 * Fires when a stale pending booking is expired by cron.
			 *
			 * @since 0.1.0
			 *
			 * @param int $post_id Booking post ID.
			 */
			kennelflow_boarding_do_action( 'booking_expired', (int) $post_id );
		}

		KennelFlow_Boarding_Cache::bump_query_bust();
	}
}
