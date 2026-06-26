<?php
/**
 * REST: kennel rules per location (hours, holidays, blackouts).
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_REST_Facility_Settings_Controller
 */
class KennelFlow_Boarding_REST_Facility_Settings_Controller extends KennelFlow_Boarding_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @param string $namespace REST namespace.
	 */
	public function __construct( $namespace = 'kennelflow-boarding/v1' ) {
		parent::__construct( 'facility-settings', $namespace );
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->get_namespace(),
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'permissions_read' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_items' ),
					'permission_callback' => array( $this, 'permissions_edit' ),
					'args'                => array(
						'locations' => array(
							'description' => __( 'Map of Hub location post ID to settings object.', 'kennelflow-boarding' ),
							'type'        => 'object',
							'required'    => true,
						),
					),
				),
			)
		);
	}

	/**
	 * Read kennel rules.
	 *
	 * @return bool
	 */
	public function permissions_read() {
		return current_user_can( 'edit_posts' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Edit kennel rules.
	 *
	 * @return bool
	 */
	public function permissions_edit() {
		return current_user_can( 'edit_posts' ) || current_user_can( 'manage_options' );
	}

	/**
	 * GET /facility-settings
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		unset( $request );

		$locations = array();

		if ( class_exists( 'KennelFlow_Boarding_Locations' ) && post_type_exists( KennelFlow_Boarding_Locations::post_type_slug() ) ) {
			$loc_posts = get_posts(
				array(
					'post_type'              => KennelFlow_Boarding_Locations::post_type_slug(),
					'post_status'            => array( 'publish', 'draft', 'pending', 'private' ),
					'posts_per_page'         => 500,
					'orderby'                => 'title',
					'order'                  => 'ASC',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
				)
			);

			foreach ( (array) $loc_posts as $lp ) {
				if ( ! $lp instanceof WP_Post ) {
					continue;
				}
				$locations[] = array(
					'id'          => (int) $lp->ID,
					'name'        => $lp->post_title,
					'slug'        => $lp->post_name,
					'description' => $lp->post_content,
					'settings'    => KennelFlow_Boarding_Facility_Settings::get_for_location( (int) $lp->ID ),
				);
			}
		}

		$tz_list = self::timezone_identifier_choices();

		$data = array(
			'locations'        => $locations,
			'timezone_choices' => $tz_list,
		);

		return rest_ensure_response( $data );
	}

	/**
	 * IANA timezone identifiers for dropdowns.
	 *
	 * Uses the default group (equivalent to DateTimeZone::ALL), not ALL_WITH_BC, for broad host compatibility.
	 *
	 * @return string[]
	 */
	protected static function timezone_identifier_choices() {
		if ( ! function_exists( 'timezone_identifiers_list' ) ) {
			return array();
		}
		$list = timezone_identifiers_list();
		return is_array( $list ) ? $list : array();
	}

	/**
	 * PUT /facility-settings
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_items( $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) || empty( $params['locations'] ) || ! is_array( $params['locations'] ) ) {
			return new WP_Error(
				'kennelpress_facility_bad_payload',
				__( 'Expected JSON object with a "locations" map.', 'kennelflow-boarding' ),
				array( 'status' => 400 )
			);
		}

		$updated = array();
		foreach ( $params['locations'] as $id => $payload ) {
			$tid = absint( $id );
			if ( $tid < 1 ) {
				continue;
			}
			if ( ! is_array( $payload ) ) {
				continue;
			}
			$res = KennelFlow_Boarding_Facility_Settings::update_location( $tid, $payload );
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			$updated[ (string) $tid ] = $res;
		}

		/**
		 * Fires after kennel rules are saved via REST.
		 *
		 * @since 0.1.0
		 *
		 * @param array<string,array> $updated Sanitized settings per location ID string.
		 */
		kennelflow_boarding_do_action( 'facility_settings_saved', $updated );

		$locations_out = array();
		if ( class_exists( 'KennelFlow_Boarding_Locations' ) && post_type_exists( KennelFlow_Boarding_Locations::post_type_slug() ) ) {
			$loc_posts = get_posts(
				array(
					'post_type'              => KennelFlow_Boarding_Locations::post_type_slug(),
					'post_status'            => array( 'publish', 'draft', 'pending', 'private' ),
					'posts_per_page'         => 500,
					'orderby'                => 'title',
					'order'                  => 'ASC',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
				)
			);
			foreach ( (array) $loc_posts as $lp ) {
				if ( ! $lp instanceof WP_Post ) {
					continue;
				}
				$locations_out[] = array(
					'id'       => (int) $lp->ID,
					'name'     => $lp->post_title,
					'slug'     => $lp->post_name,
					'settings' => KennelFlow_Boarding_Facility_Settings::get_for_location( (int) $lp->ID ),
				);
			}
		}

		return rest_ensure_response(
			array(
				'success'   => true,
				'updated'   => $updated,
				'locations' => $locations_out,
			)
		);
	}
}
