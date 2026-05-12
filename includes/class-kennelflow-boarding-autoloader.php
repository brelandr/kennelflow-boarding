<?php
/**
 * PSR-style autoload for KennelFlow_Boarding_* classes.
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_Autoloader
 */
class KennelFlow_Boarding_Autoloader {

	/**
	 * Register spl autoload.
	 *
	 * @return void
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Autoload handler.
	 *
	 * @param string $class_name Class name.
	 * @return void
	 */
	public static function autoload( $class_name ) {
		if ( 0 !== strpos( $class_name, 'KennelFlow_Boarding_' ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( 'KennelFlow_Boarding_' ) );
		$slug     = strtolower( str_replace( '_', '-', $relative ) );
		$file     = 'class-kennelflow-boarding-' . $slug . '.php';

		$paths = array(
			KENNELFLOW_BOARDING_PLUGIN_DIR . 'includes/' . $file,
			KENNELFLOW_BOARDING_PLUGIN_DIR . 'includes/admin/' . $file,
			KENNELFLOW_BOARDING_PLUGIN_DIR . 'includes/post-types/' . $file,
			KENNELFLOW_BOARDING_PLUGIN_DIR . 'includes/meta/' . $file,
			KENNELFLOW_BOARDING_PLUGIN_DIR . 'includes/rest/' . $file,
		);

		foreach ( $paths as $path ) {
			if ( is_readable( $path ) ) {
				require_once $path;
				return;
			}
		}
	}
}
