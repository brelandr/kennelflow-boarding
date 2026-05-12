<?php
/**
 * Admin AJAX (hub ping for scripts / integrations).
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_Ajax
 */
class KennelFlow_Boarding_Ajax {

	/**
	 * Nonce action for hub ping (must match wp_localize_script / admin-ajax.js).
	 */
	const HUB_PING_NONCE_ACTION = 'kennelflow_boarding_hub_ping';

	/**
	 * Register wp_ajax_* hooks.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		add_action( 'wp_ajax_kennelflow_boarding_hub_ping', array( __CLASS__, 'handle_hub_ping' ) );
		// Back-compat (legacy admin-ajax action name).
		add_action( 'wp_ajax_kennelpress_hub_ping', array( __CLASS__, 'handle_hub_ping' ) );

		/**
		 * Fires after Kennel Press registers AJAX hooks.
		 *
		 * @since 0.1.0
		 */
		kennelflow_boarding_do_action( 'register_ajax' );
	}

	/**
	 * JSON hub status for admin scripts (plugin version, hub readiness).
	 *
	 * POST: action=kennelpress_hub_ping, nonce=(from kennelpressAjax).
	 *
	 * @return void
	 */
	public static function handle_hub_ping() {
		if ( ! check_ajax_referer( self::HUB_PING_NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid security token.', 'kennelflow-boarding' ),
				),
				403
			);
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Sorry, you are not allowed to do this.', 'kennelflow-boarding' ),
				),
				403
			);
		}

		$data = array(
			'slug'       => 'kennelflow-boarding',
			'version'    => KENNELFLOW_BOARDING_VERSION,
			'hub_ready'  => ( did_action( 'kennelpress_hub_ready' ) > 0 || did_action( 'kennelflow_boarding_hub_ready' ) > 0 ),
			'pro_active' => ( function_exists( 'kennelflow_boarding_pro' ) || function_exists( 'kennelpress_pro' ) ),
		);

		$data = kennelflow_boarding_apply_filters( 'ajax_hub_ping_data', $data );

		wp_send_json_success( $data );
	}
}
