<?php
/**
 * Main plugin bootstrap.
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_Plugin
 */
class KennelFlow_Boarding_Plugin {

	/**
	 * Boot hooks.
	 *
	 * @return void
	 */
	public function init() {
		KennelFlow_Boarding_Roles::register_roles();

		KennelFlow_Boarding_Boarding_Commerce::init();

		if ( class_exists( 'KennelFlow_Boarding_Admin_Front_Desk' ) ) {
			KennelFlow_Boarding_Admin_Front_Desk::init();
		}

		KennelFlow_Boarding_Calendar_Bridge::init();
		KennelFlow_Boarding_Run_Card::init();
		KennelFlow_Boarding_Care_Sheet_Emailer::init();
		if ( class_exists( 'KennelFlow_Boarding_PWA_Frontend' ) ) {
			KennelFlow_Boarding_PWA_Frontend::init();
		}
		KennelFlow_Boarding_Install::maybe_upgrade();
		KennelFlow_Boarding_Booking_Index::register_hooks();
		KennelFlow_Boarding_Audit::register_hooks();
		KennelFlow_Boarding_Cache::register_invalidation_hooks();
		KennelFlow_Boarding_Cron::init();
		KennelFlow_Boarding_Cron::maybe_schedule();
		KennelFlow_Boarding_Ajax::register_hooks();
		add_action( 'init', array( $this, 'register_content' ), 5 );
		add_action( 'rest_api_init', array( $this, 'register_rest' ) );
		// admin_menu runs before admin_init; register submenu here so Calendar appears.
		add_action( 'admin_menu', array( 'KennelFlow_Boarding_Admin', 'register_calendar_page' ) );
		add_action( 'admin_menu', array( 'KennelFlow_Boarding_Admin', 'register_facility_settings_page' ) );
		add_action( 'admin_menu', array( 'KennelFlow_Boarding_Admin', 'on_admin_menus_registered' ), 999 );
		add_action( 'admin_init', array( $this, 'boot_admin' ) );

		/**
		 * Fires after KennelFlow Boarding registers core hooks.
		 *
		 * @since 0.1.0
		 */
		kennelflow_boarding_do_action( 'init' );

		/**
		 * Fires when the KennelFlow Boarding add-on is ready (after init textdomain; replaces former plugins_loaded priority 99).
		 *
		 * @since 0.1.0
		 */
		kennelflow_boarding_do_action( 'hub_ready' );
	}

	/**
	 * Load admin-only UI (meta boxes, list columns).
	 *
	 * @return void
	 */
	public function boot_admin() {
		KennelFlow_Boarding_Admin::init();
		if ( class_exists( 'KennelFlow_Boarding_PWA_Report_Admin' ) ) {
			KennelFlow_Boarding_PWA_Report_Admin::init();
		}

		/**
		 * Fires after KennelFlow Boarding admin is bootstrapped.
		 *
		 * @since 0.1.0
		 */
		kennelflow_boarding_do_action( 'admin_init' );
	}

	/**
	 * CPT, taxonomy, meta.
	 *
	 * @return void
	 */
	public function register_content() {
		KennelFlow_Boarding_Post_Types::register();
		KennelFlow_Boarding_Post_Meta::register();
	}

	/**
	 * REST API controllers.
	 *
	 * @return void
	 */
	public function register_rest() {
		$availability = new KennelFlow_Boarding_REST_Availability_Controller();
		$availability->register_routes();

		$locations = new KennelFlow_Boarding_REST_Locations_Controller();
		$locations->register_routes();

		$kennels = new KennelFlow_Boarding_REST_Kennels_Controller();
		$kennels->register_routes();

		$bookings = new KennelFlow_Boarding_REST_Bookings_Controller();
		$bookings->register_routes();

		$intake_res = new KennelFlow_Boarding_REST_Booking_Intake_Resources_Controller();
		$intake_res->register_routes();

		$pet_care = new KennelFlow_Boarding_REST_Pet_Care_Controller();
		$pet_care->register_routes();

		$facility = new KennelFlow_Boarding_REST_Facility_Settings_Controller();
		$facility->register_routes();

		$boarding = new KennelFlow_Boarding_REST_Boarding_Controller();
		$boarding->register_routes();

		$report_cards = new KennelFlow_Boarding_REST_Report_Cards_Controller();
		$report_cards->register_routes();

		/**
		 * Fires after KennelFlow Boarding registers REST routes.
		 *
		 * @since 0.1.0
		 */
		kennelflow_boarding_do_action( 'register_rest' );
	}

	/**
	 * Activation: rewrite rules.
	 *
	 * @return void
	 */
	public static function activate() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		if ( function_exists( 'kennelflow_boarding_load_textdomain' ) ) {
			kennelflow_boarding_load_textdomain();
		}

		if ( ! is_plugin_active( 'kennelflow-core/kennelflow-core.php' ) ) {
			deactivate_plugins( KENNELFLOW_BOARDING_PLUGIN_BASENAME, true );
			wp_die(
				esc_html__( 'KennelFlow Boarding requires KennelFlow Core. Please install and activate KennelFlow Core first.', 'kennelflow-boarding' ),
				esc_html__( 'Plugin dependency', 'kennelflow-boarding' ),
				array( 'back_link' => true )
			);
		}

		KennelFlow_Boarding_Roles::register_roles();
		KennelFlow_Boarding_Install::install();
		KennelFlow_Boarding_Post_Types::register();
		KennelFlow_Boarding_Cron::maybe_schedule();
		if ( class_exists( 'KennelFlow_Boarding_PWA_Frontend' ) ) {
			KennelFlow_Boarding_PWA_Frontend::register_rewrite_rules();
			update_option( KennelFlow_Boarding_PWA_Frontend::OPTION_REWRITE_RULES_VERSION, KennelFlow_Boarding_PWA_Frontend::REWRITE_RULES_VERSION );
		}
		flush_rewrite_rules();

		/**
		 * Fires on plugin activation.
		 *
		 * @since 0.1.0
		 */
		kennelflow_boarding_do_action( 'activate' );
	}

	/**
	 * Deactivation.
	 *
	 * @return void
	 */
	public static function deactivate() {
		KennelFlow_Boarding_Cron::unschedule();
		flush_rewrite_rules();

		/**
		 * Fires on plugin deactivation.
		 *
		 * @since 0.1.0
		 */
		kennelflow_boarding_do_action( 'deactivate' );
	}
}
