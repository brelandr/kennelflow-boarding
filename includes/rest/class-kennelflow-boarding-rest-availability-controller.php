<?php
/**
 * REST: availability for kennels in a location and interval.
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_REST_Availability_Controller
 */
class KennelFlow_Boarding_REST_Availability_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = 'kennelflow-boarding/v1';
		$this->rest_base = 'availability';
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);
	}

	/**
	 * Public read for booking UIs.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		unset( $request );
		return true;
	}

	/**
	 * GET /kennelflow-boarding/v1/availability
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$location = $request->get_param( 'location' );
		$start    = $request->get_param( 'start' );
		$end      = $request->get_param( 'end' );

		$location_post = KennelFlow_Boarding_Locations::resolve_location_post( $location );
		if ( is_wp_error( $location_post ) ) {
			return $location_post;
		}

		$start_gmt = KennelFlow_Boarding_Availability::parse_gmt_mysql( $start );
		if ( is_wp_error( $start_gmt ) ) {
			return $start_gmt;
		}

		$end_gmt = KennelFlow_Boarding_Availability::parse_gmt_mysql( $end );
		if ( is_wp_error( $end_gmt ) ) {
			return $end_gmt;
		}

		$interval = KennelFlow_Boarding_Availability::validate_interval( $start_gmt, $end_gmt );
		if ( is_wp_error( $interval ) ) {
			return $interval;
		}

		$clinician_id = absint( $request->get_param( 'clinician_id' ) );

		$ids = KennelFlow_Boarding_Availability::get_available_kennel_ids( (int) $location_post->ID, $start_gmt, $end_gmt, $clinician_id );
		if ( is_wp_error( $ids ) ) {
			return $ids;
		}

		$kennels = array();
		foreach ( $ids as $kennel_id ) {
			$title     = get_the_title( $kennel_id );
			$kennels[] = array(
				'id'    => (int) $kennel_id,
				'title' => $title ? $title : '',
			);
		}

		$data = array(
			'location_id'   => (int) $location_post->ID,
			'location_slug' => $location_post->post_name,
			'start_gmt'     => $start_gmt,
			'end_gmt'       => $end_gmt,
			'kennel_ids'    => $ids,
			'kennels'       => $kennels,
		);

		/**
		 * Filters the REST response for kennel availability.
		 *
		 * @since 0.1.0
		 *
		 * @param array           $data           Payload.
		 * @param WP_Post         $location_post  Hub location post (`kf_location`).
		 * @param WP_REST_Request $request        Request.
		 */
		$data = kennelflow_boarding_apply_filters( 'rest_availability_response', $data, $location_post, $request );

		return rest_ensure_response( $data );
	}

	/**
	 * Collection params for availability.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		return array(
			'location'     => array(
				'description'       => __( 'Hub location post ID or post slug.', 'kennelflow-boarding' ),
				'type'              => array( 'string', 'integer' ),
				'required'          => true,
				'sanitize_callback' => array( $this, 'sanitize_location_param' ),
			),
			'start'        => array(
				'description'       => __( 'Interval start (UTC ISO 8601 or Y-m-d H:i:s).', 'kennelflow-boarding' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'end'          => array(
				'description'       => __( 'Interval end (UTC ISO 8601 or Y-m-d H:i:s), half-open interval.', 'kennelflow-boarding' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'clinician_id' => array(
				'description'       => __( 'Optional WordPress user ID. When set, returns no kennels if that clinician already has an overlapping clinic booking.', 'kennelflow-boarding' ),
				'type'              => 'integer',
				'required'          => false,
				'sanitize_callback' => 'absint',
			),
		);
	}

	/**
	 * Sanitize location parameter (keep string or digit string).
	 *
	 * @param mixed $value Raw.
	 * @return string
	 */
	public function sanitize_location_param( $value ) {
		if ( is_numeric( $value ) ) {
			return (string) absint( $value );
		}
		return sanitize_text_field( (string) $value );
	}

	/**
	 * Resolve Hub location post from slug or numeric string ID.
	 *
	 * @param mixed $location Location param.
	 * @return WP_Post|WP_Error
	 */
	public static function resolve_location_term( $location ) {
		return KennelFlow_Boarding_Locations::resolve_location_post( $location );
	}
}
