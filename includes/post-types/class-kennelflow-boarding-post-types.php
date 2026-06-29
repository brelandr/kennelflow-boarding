<?php
/**
 * Register KennelFlow_Boarding-owned post types (Hub pets/locations come from KennelFlow Core).
 *
 * Pet profiles and physical locations use Core (`kf_pet`, `kf_location`). KennelFlow_Boarding only
 * registers kennels (Hub) and bookings (Front Desk menu).
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_Post_Types
 */
class KennelFlow_Boarding_Post_Types {

	/**
	 * Register on init.
	 *
	 * @return void
	 */
	public static function register() {
		self::register_kennel();
		self::register_booking();

		/**
		 * Fires after Kennel Press post types are registered.
		 *
		 * @since 0.1.0
		 */
		kennelflow_boarding_do_action( 'after_register_post_types' );
	}

	/**
	 * Kennels / runs CPT (assign Hub location via `_kennelpress_location_id` meta → `kf_location` CPT).
	 *
	 * @return void
	 */
	protected static function register_kennel() {
		$labels = array(
			'name'               => __( 'Kennels', 'kennelflow-boarding' ),
			'singular_name'      => __( 'Kennel', 'kennelflow-boarding' ),
			'add_new_item'       => __( 'Add New Kennel', 'kennelflow-boarding' ),
			'edit_item'          => __( 'Edit Kennel', 'kennelflow-boarding' ),
			'new_item'           => __( 'New Kennel', 'kennelflow-boarding' ),
			'view_item'          => __( 'View Kennel', 'kennelflow-boarding' ),
			'search_items'       => __( 'Search Kennels', 'kennelflow-boarding' ),
			'not_found'          => __( 'No kennels found', 'kennelflow-boarding' ),
			'not_found_in_trash' => __( 'No kennels found in Trash', 'kennelflow-boarding' ),
			'menu_name'          => __( 'Kennels', 'kennelflow-boarding' ),
		);

		$args = array(
			'labels'          => $labels,
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => function_exists( 'ltkf_get_hub_menu_slug' ) ? ltkf_get_hub_menu_slug() : 'edit.php?post_type=kf_pet',
			'menu_icon'       => 'dashicons-admin-home',
			'capability_type' => 'post',
			'map_meta_cap'    => true,
			'supports'        => array( 'title', 'editor' ),
			'has_archive'     => false,
			'show_in_rest'    => true,
			'rest_base'       => 'kennelpress-kennels',
			'rewrite'         => false,
		);

		register_post_type( 'kennelpress_kennel', $args );
	}

	/**
	 * Bookings CPT.
	 *
	 * @return void
	 */
	protected static function register_booking() {
		$labels = array(
			'name'               => __( 'Bookings', 'kennelflow-boarding' ),
			'singular_name'      => __( 'Booking', 'kennelflow-boarding' ),
			'add_new_item'       => __( 'Add New Booking', 'kennelflow-boarding' ),
			'edit_item'          => __( 'Edit Booking', 'kennelflow-boarding' ),
			'new_item'           => __( 'New Booking', 'kennelflow-boarding' ),
			'view_item'          => __( 'View Booking', 'kennelflow-boarding' ),
			'search_items'       => __( 'Search Bookings', 'kennelflow-boarding' ),
			'not_found'          => __( 'No bookings found', 'kennelflow-boarding' ),
			'not_found_in_trash' => __( 'No bookings found in Trash', 'kennelflow-boarding' ),
			'menu_name'          => __( 'Kennel bookings', 'kennelflow-boarding' ),
		);

		$args = array(
			'labels'          => $labels,
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => function_exists( 'kennelpress_get_front_desk_menu_slug' ) ? kennelpress_get_front_desk_menu_slug() : ( function_exists( 'ltkf_get_hub_menu_slug' ) ? ltkf_get_hub_menu_slug() : 'edit.php?post_type=kf_pet' ),
			'capability_type' => 'kennelflow_boarding_booking',
			'map_meta_cap'    => true,
			'supports'        => array( 'title', 'editor' ),
			'has_archive'     => false,
			'show_in_rest'    => true,
			'rest_base'       => 'kennelpress-bookings',
			'rewrite'         => false,
		);

		register_post_type( 'kennelpress_booking', $args );
	}
}
