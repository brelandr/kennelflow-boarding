<?php
/**
 * REST: boarding config and quote (logged-in owners).
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_REST_Boarding_Controller
 */
class KennelFlow_Boarding_REST_Boarding_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = 'kennelflow-boarding/v1';
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/boarding/config',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_config' ),
				'permission_callback' => array( $this, 'permissions_logged_in' ),
				'args'                => array(
					'location_id' => array(
						'description' => __( 'Hub location post ID.', 'kennelflow-boarding' ),
						'type'        => 'integer',
						'required'    => true,
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/boarding/quote',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'post_quote' ),
				'permission_callback' => array( $this, 'permissions_logged_in' ),
				'args'                => array(
					'location_id' => array(
						'type'     => 'integer',
						'required' => true,
					),
					'start'       => array(
						'type'     => 'string',
						'required' => true,
					),
					'end'         => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * @return bool|WP_Error
	 */
	public function permissions_logged_in() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'kennelpress_rest_not_logged_in',
				__( 'You must be logged in.', 'kennelflow-boarding' ),
				array( 'status' => 401 )
			);
		}
		return true;
	}

	/**
	 * GET boarding/config.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_config( $request ) {
		$id     = absint( $request->get_param( 'location_id' ) );
		$loc_pt = function_exists( 'ltkf_get_location_post_type' ) ? ltkf_get_location_post_type() : 'kf_location';
		if ( $id < 1 ) {
			return new WP_Error( 'kennelpress_bad_location', __( 'Invalid location.', 'kennelflow-boarding' ), array( 'status' => 400 ) );
		}
		$post = get_post( $id );
		if ( ! $post instanceof WP_Post || $loc_pt !== $post->post_type ) {
			return new WP_Error( 'kennelpress_unknown_location', __( 'Unknown location.', 'kennelflow-boarding' ), array( 'status' => 404 ) );
		}

		$s = KennelFlow_Boarding_Facility_Settings::get_for_location( $id );

		$out = array(
			'location_id' => $id,
			'name'        => get_the_title( $post ),
			'timezone'    => $s['timezone'],
			'enabled'     => ! empty( $s['enabled'] ),
			'weekly'      => $s['weekly'],
		);

		$boarding_keys = array(
			'boarding_rules_enabled',
			'boarding_daily',
			'boarding_extended_hours_enabled',
			'boarding_extended_fee',
			'boarding_charge_extra_day_after_extended',
			'boarding_emergency_drop_enabled',
			'boarding_emergency_drop_fee',
			'boarding_daily_fee',
			'boarding_fee_small',
			'boarding_fee_medium',
			'boarding_fee_large',
			'boarding_additional_pet_fee',
			'boarding_food_enabled',
			'boarding_food_fee',
			'boarding_discount_tiers',
			'boarding_price_application',
			'boarding_wc_product_id',
			'boarding_wc_variation_small',
			'boarding_wc_variation_medium',
			'boarding_wc_variation_large',
			'boarding_intake_form_enabled',
			'boarding_interview_enabled',
			'boarding_interview_instructions',
		);
		foreach ( $boarding_keys as $k ) {
			if ( array_key_exists( $k, $s ) ) {
				$out[ $k ] = $s[ $k ];
			}
		}

		return rest_ensure_response( $out );
	}

	/**
	 * POST boarding/quote.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function post_quote( $request ) {
		$id     = absint( $request->get_param( 'location_id' ) );
		$loc_pt = function_exists( 'ltkf_get_location_post_type' ) ? ltkf_get_location_post_type() : 'kf_location';
		if ( $id < 1 ) {
			return new WP_Error( 'kennelpress_bad_location', __( 'Invalid location.', 'kennelflow-boarding' ), array( 'status' => 400 ) );
		}
		$post = get_post( $id );
		if ( ! $post instanceof WP_Post || $loc_pt !== $post->post_type ) {
			return new WP_Error( 'kennelpress_unknown_location', __( 'Unknown location.', 'kennelflow-boarding' ), array( 'status' => 404 ) );
		}

		$start = $request->get_param( 'start' );
		$end   = $request->get_param( 'end' );
		$args  = array(
			'pet_size'                 => sanitize_key( (string) $request->get_param( 'pet_size' ) ),
			'pet_count'                => absint( $request->get_param( 'pet_count' ) ),
			'emergency_drop'           => (bool) $request->get_param( 'emergency_drop' ),
			'extended_pickup'          => (bool) $request->get_param( 'extended_pickup' ),
			'kennel_food'              => (bool) $request->get_param( 'kennel_food' ),
			'extra_day_after_extended' => (bool) $request->get_param( 'extra_day_after_extended' ),
		);
		if ( $args['pet_count'] < 1 ) {
			$args['pet_count'] = 1;
		}

		$quote = KennelFlow_Boarding_Boarding_Quote::build( $id, (string) $start, (string) $end, $args );
		if ( is_wp_error( $quote ) ) {
			return $quote;
		}

		return rest_ensure_response( $quote );
	}
}
