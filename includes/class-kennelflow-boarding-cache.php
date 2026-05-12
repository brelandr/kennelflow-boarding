<?php
/**
 * Object cache + option bust for expensive queries (24-hour TTL, slug-prefixed keys).
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_Cache
 */
class KennelFlow_Boarding_Cache {

	/**
	 * Object cache group for availability + per-location unit lists (shared with KennelFlow Core occupancy).
	 *
	 * @var string
	 */
	const OBJECT_CACHE_GROUP_AVAILABILITY = 'kennelflow_availability';

	/**
	 * Object cache group for Hub locations REST payload.
	 *
	 * @var string
	 */
	const OBJECT_CACHE_GROUP_LOCATIONS = 'kennelflow_locations';

	/**
	 * Transient lifetime in seconds (24 hours).
	 *
	 * @var int
	 */
	const TRANSIENT_TTL = DAY_IN_SECONDS;

	const OPTION_QUERY_CACHE_BUST = 'kennelflow_boarding_query_cache_bust';

	const OPTION_QUERY_CACHE_BUST_LEGACY = 'kennelpress_query_cache_bust';

	/**
	 * Flush availability-related object cache entries (Redis/Memcached); no-op if unsupported.
	 *
	 * @return void
	 */
	public static function flush_availability_cache() {
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( self::OBJECT_CACHE_GROUP_AVAILABILITY );
		}
		wp_cache_delete( 'ltkf_occ_pct_' . gmdate( 'Y-m-d' ), self::OBJECT_CACHE_GROUP_AVAILABILITY );
	}

	/**
	 * Flush locations REST object cache entries.
	 *
	 * @return void
	 */
	public static function flush_locations_cache() {
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( self::OBJECT_CACHE_GROUP_LOCATIONS );
		}
	}

	/**
	 * Option: bumps locations list cache when location terms change.
	 *
	 * @return int
	 */
	public static function get_locations_bust() {
		return (int) get_option( 'ltkf_locations_list_bust', 1 );
	}

	/**
	 * Option: bumps availability and per-location unit list caches.
	 *
	 * @return int
	 */
	public static function get_query_bust() {
		$v = get_option( self::OPTION_QUERY_CACHE_BUST, null );
		if ( null === $v || false === $v || '' === $v ) {
			$v = get_option( self::OPTION_QUERY_CACHE_BUST_LEGACY, 1 );
		}
		return (int) $v;
	}

	/**
	 * Invalidate locations REST cache.
	 *
	 * @return void
	 */
	public static function bump_locations_bust() {
		update_option( 'ltkf_locations_list_bust', self::get_locations_bust() + 1 );
		self::flush_locations_cache();
	}

	/**
	 * Invalidate availability and unit-in-location caches.
	 *
	 * @return void
	 */
	public static function bump_query_bust() {
		update_option( self::OPTION_QUERY_CACHE_BUST, self::get_query_bust() + 1 );
		delete_option( self::OPTION_QUERY_CACHE_BUST_LEGACY );
		self::flush_availability_cache();
	}

	/**
	 * Register invalidation hooks.
	 *
	 * @return void
	 */
	public static function register_invalidation_hooks() {
		static $registered = false;
		if ( $registered ) {
			return;
		}
		$registered = true;

		add_action( 'save_post_kennelpress_booking', array( __CLASS__, 'bump_query_bust' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'maybe_bump_on_booking_delete' ), 10, 1 );
		add_action( 'save_post_kennelpress_kennel', array( __CLASS__, 'bump_query_bust' ) );
		add_action( 'kennelpress_facility_settings_saved', array( __CLASS__, 'bump_query_bust' ) );
		add_action( 'kennelflow_boarding_facility_settings_saved', array( __CLASS__, 'bump_query_bust' ) );
		add_action( 'save_post', array( __CLASS__, 'maybe_bump_on_hub_location_save' ), 20, 2 );
		add_action( 'before_delete_post', array( __CLASS__, 'maybe_bump_on_hub_location_delete' ), 10, 1 );
	}

	/**
	 * Invalidate caches when a Hub location (`kf_location` CPT) is saved.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public static function maybe_bump_on_hub_location_save( $post_id, $post ) {
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		if ( ! function_exists( 'ltkf_get_location_post_type' ) ) {
			return;
		}
		if ( ltkf_get_location_post_type() !== $post->post_type ) {
			return;
		}
		self::bump_locations_bust();
		self::bump_query_bust();
	}

	/**
	 * Invalidate query caches when a booking post is permanently deleted.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function maybe_bump_on_booking_delete( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id < 1 ) {
			return;
		}
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		if ( 'kennelpress_booking' !== $post->post_type ) {
			return;
		}
		self::bump_query_bust();
	}

	/**
	 * Invalidate caches when a Hub location post is deleted.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function maybe_bump_on_hub_location_delete( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id < 1 ) {
			return;
		}
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		if ( ! function_exists( 'ltkf_get_location_post_type' ) ) {
			return;
		}
		if ( ltkf_get_location_post_type() !== $post->post_type ) {
			return;
		}
		self::bump_locations_bust();
		self::bump_query_bust();
	}
}
