<?php
/**
 * Pass Kennel Press REST URLs into KennelFlow Core admin calendar script.
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_Calendar_Bridge
 */
class KennelFlow_Boarding_Calendar_Bridge {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'ltkf_admin_calendar_localized_settings', array( __CLASS__, 'filter_calendar_settings' ) );
	}

	/**
	 * Add Kennel Press booking + pet care REST bases for the React calendar modal.
	 *
	 * @param array<string, mixed> $settings Localized settings.
	 * @return array<string, mixed>
	 */
	public static function filter_calendar_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		$settings['kennelpress_bookings_url']         = esc_url_raw( rest_url( 'kennelflow-boarding/v1/bookings' ) );
		$settings['bookings_create_path']             = '/kennelflow/v1/bookings';
		$settings['kennelpress_print_run_card_nonce'] = wp_create_nonce( 'kennelpress_print_run_card' );
		return $settings;
	}
}
