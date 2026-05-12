<?php
/**
 * REST: users + kennel options for context-aware booking intake UIs.
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_REST_Booking_Intake_Resources_Controller
 */
class KennelFlow_Boarding_REST_Booking_Intake_Resources_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = 'kennelflow-boarding/v1';
		$this->rest_base = 'booking-intake-resources';
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
					'permission_callback' => array( $this, 'permissions_staff' ),
					'args'                => array(
						'location' => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
						'context'  => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
			)
		);
	}

	/**
	 * Staff only.
	 *
	 * @return bool
	 */
	public function permissions_staff() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * GET /booking-intake-resources?location=ID&context=grooming|clinic
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$location_id = absint( $request->get_param( 'location' ) );
		$context     = sanitize_key( (string) $request->get_param( 'context' ) );

		if ( $location_id < 1 ) {
			return new WP_Error( 'kennelpress_bad_location', __( 'Invalid location.', 'kennelflow-boarding' ), array( 'status' => 400 ) );
		}

		$loc_post = get_post( $location_id );
		if ( ! $loc_post || 'kf_location' !== $loc_post->post_type ) {
			return new WP_Error( 'kennelpress_bad_location', __( 'Invalid location.', 'kennelflow-boarding' ), array( 'status' => 400 ) );
		}

		if ( 'grooming' === $context ) {
			$roles = kennelflow_boarding_apply_filters(
				'grooming_resource_roles',
				self::default_grooming_resource_roles()
			);
			$users = self::users_for_roles( $roles );
			return rest_ensure_response(
				array(
					'context'     => 'grooming',
					'location_id' => $location_id,
					'users'       => $users,
					'kennels'     => array(),
				)
			);
		}

		if ( 'clinic' === $context ) {
			$roles = kennelflow_boarding_apply_filters( 'clinic_user_resource_roles', self::default_clinic_user_roles() );
			$users = self::users_for_roles( $roles );

			$kennels = self::kennels_for_clinic( $location_id );

			return rest_ensure_response(
				array(
					'context'     => 'clinic',
					'location_id' => $location_id,
					'users'       => $users,
					'kennels'     => $kennels,
				)
			);
		}

		return new WP_Error( 'kennelpress_bad_context', __( 'Invalid context.', 'kennelflow-boarding' ), array( 'status' => 400 ) );
	}

	/**
	 * Default WordPress roles that may appear as grooming resources (extend via filter).
	 *
	 * Includes the KennelFlow_Boarding `groomer` role plus common staff/clinical roles so cross-trained
	 * users appear without a dedicated groomer assignment. Narrow with `kennelpress_grooming_resource_roles`.
	 *
	 * @return string[]
	 */
	public static function default_grooming_resource_roles() {
		$roles = array(
			KennelFlow_Boarding_Roles::ROLE_GROOMER,
			'administrator',
			'veterinarian',
			'clinician',
		);
		if ( class_exists( 'KennelFlow_Vet_Roles' ) ) {
			$roles[] = KennelFlow_Vet_Roles::ROLE_PROVIDER;
		}
		return array_unique( array_filter( array_map( 'sanitize_key', $roles ) ) );
	}

	/**
	 * Default WordPress roles that may appear as clinical providers (extend via filter).
	 *
	 * @return string[]
	 */
	public static function default_clinic_user_roles() {
		$roles = array( 'administrator' );
		if ( class_exists( 'KennelFlow_Vet_Roles' ) ) {
			$roles[] = KennelFlow_Vet_Roles::ROLE_PROVIDER;
		}
		return array_unique( array_filter( $roles ) );
	}

	/**
	 * Users with any of the given roles (for dropdowns).
	 *
	 * @param string[] $roles Role slugs.
	 * @return array<int, array{id:int, name:string, roles:string[]}>
	 */
	protected static function users_for_roles( array $roles ) {
		$roles = array_filter( array_map( 'sanitize_key', $roles ) );
		if ( empty( $roles ) ) {
			return array();
		}

		$users = get_users(
			array(
				'role__in' => $roles,
				'orderby'  => 'display_name',
				'order'    => 'ASC',
				'number'   => 200,
				'fields'   => array( 'ID', 'display_name' ),
			)
		);

		$out = array();
		foreach ( $users as $u ) {
			if ( ! $u instanceof WP_User ) {
				continue;
			}
			$out[] = array(
				'id'    => (int) $u->ID,
				'name'  => $u->display_name ? $u->display_name : $u->user_login,
				'roles' => array_values( array_map( 'strval', (array) $u->roles ) ),
			);
		}

		return $out;
	}

	/**
	 * Kennels in location suitable for clinic / exam (exam + general + empty type).
	 *
	 * @param int $location_id Hub location post ID.
	 * @return array<int, array{id:int, title:string, resource_type:string}>
	 */
	protected static function kennels_for_clinic( $location_id ) {
		$location_id = absint( $location_id );
		if ( $location_id < 1 ) {
			return array();
		}

		$q = new WP_Query(
			array(
				'post_type'              => 'kennelpress_kennel',
				'post_status'            => 'publish',
				'posts_per_page'         => 200,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'meta_query'             => array(
					array(
						'key'   => KennelFlow_Boarding_Post_Meta::KENNEL_LOCATION_ID,
						'value' => $location_id,
						'type'  => 'NUMERIC',
					),
				),
			)
		);

		$out = array();
		foreach ( $q->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$rtype = (string) get_post_meta( $post->ID, KennelFlow_Boarding_Post_Meta::KENNEL_RESOURCE_TYPE, true );
			$rtype = KennelFlow_Boarding_Post_Meta::sanitize_kennel_resource_type( $rtype );
			if ( '' !== $rtype && 'exam' !== $rtype && 'general' !== $rtype && 'boarding' !== $rtype ) {
				continue;
			}
			$out[] = array(
				'id'            => (int) $post->ID,
				'title'         => get_the_title( $post ),
				'resource_type' => '' === $rtype ? 'general' : $rtype,
			);
		}

		return $out;
	}
}
