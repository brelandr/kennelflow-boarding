<?php
/**
 * Plugin Name:       KennelFlow Boarding Manager
 * Plugin URI:        https://wordpress.org/plugins/kennelflow-boarding/
 * Description:       KennelFlow: manage pets, kennels, locations, and boarding bookings with availability checks and a REST API for custom booking and scheduling flows.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            LandTech Web Designs
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       kennelflow-boarding
 * Requires Plugins:  kennelflow-core
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

define( 'KENNELFLOW_BOARDING_VERSION', '0.1.0' );
define( 'KENNELFLOW_BOARDING_PLUGIN_FILE', __FILE__ );
define( 'KENNELFLOW_BOARDING_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KENNELFLOW_BOARDING_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'KENNELFLOW_BOARDING_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load translations at init priority 0 (WordPress 6.7+: avoid _load_textdomain_just_in_time before init).
 *
 * @return void
 */
function kennelflow_boarding_load_textdomain() {
	load_plugin_textdomain( 'kennelflow-boarding', false, dirname( KENNELFLOW_BOARDING_PLUGIN_BASENAME ) . '/languages' );
}
add_action( 'init', 'kennelflow_boarding_load_textdomain', 0 );

require_once KENNELFLOW_BOARDING_PLUGIN_DIR . 'includes/class-kennelflow-boarding-autoloader.php';

KennelFlow_Boarding_Autoloader::register();

require_once KENNELFLOW_BOARDING_PLUGIN_DIR . 'includes/functions-kennelflow-boarding.php';
require_once KENNELFLOW_BOARDING_PLUGIN_DIR . 'includes/hook-aliases-kennelflow-boarding.php';

/**
 * Returns the main plugin instance.
 *
 * @return KennelFlow_Boarding_Plugin
 */
function kennelflow_boarding() {
	static $instance = null;
	if ( null === $instance ) {
		$instance = new KennelFlow_Boarding_Plugin();
	}
	return $instance;
}

/**
 * Back-compat accessor (legacy “Kennel Press” name).
 *
 * @return KennelFlow_Boarding_Plugin
 */
function kennelpress() {
	return kennelflow_boarding();
}

/**
 * Boot only when KennelFlow Core (Hub) is active.
 *
 * Hub code runs at init priority 1 so translations load on init priority 0 first (WP 6.7+).
 *
 * @return void
 */
function kennelflow_boarding_load() {
	if ( ! defined( 'LTKF_CORE_VERSION' ) ) {
		add_action( 'admin_notices', 'kennelflow_boarding_notice_kf_core_required' );
		return;
	}

	add_action( 'init', 'kennelflow_boarding_boot', 1 );
}

/**
 * Instantiate after kennelflow_boarding_load_textdomain (init 0).
 *
 * @return void
 */
function kennelflow_boarding_boot() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;

	kennelflow_boarding()->init();
}
add_action( 'plugins_loaded', 'kennelflow_boarding_load', 5 );

/**
 * Admin notice when KennelFlow Core is missing.
 *
 * @return void
 */
function kennelflow_boarding_notice_kf_core_required() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	if ( defined( 'LTKF_CORE_VERSION' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html__( 'KennelFlow Boarding requires KennelFlow Core to be installed and active.', 'kennelflow-boarding' )
	);
}

register_activation_hook( KENNELFLOW_BOARDING_PLUGIN_FILE, array( 'KennelFlow_Boarding_Plugin', 'activate' ) );
register_deactivation_hook( KENNELFLOW_BOARDING_PLUGIN_FILE, array( 'KennelFlow_Boarding_Plugin', 'deactivate' ) );
