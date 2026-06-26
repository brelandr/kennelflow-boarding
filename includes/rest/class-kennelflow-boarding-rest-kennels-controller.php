<?php
/**
 * REST: list kennels by location (paginated).
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_REST_Kennels_Controller
 */
class KennelFlow_Boarding_REST_Kennels_Controller extends KennelFlow_Boarding_REST_Controller {

	/**
	 * Max posts per page.
	 */
	const MAX_PER_PAGE = 100;

	/**
	 * Constructor.
	 *
	 * @param string $namespace REST namespace.
	 */
	public function __construct( $namespace = 'kennelflow-boarding/v1' ) {
		parent::__construct( 'kennels', $namespace );
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
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);
	}

	/**
	 * Public read for intake UIs (includes capacity for anonymous booking flows).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		unset( $request );
		return true;
	}

	/**
	 * GET /kennelflow-boarding/v1/kennels
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$location      = $request->get_param( 'location' );
		$location_post = KennelFlow_Boarding_REST_Availability_Controller::resolve_location_term( $location );
		if ( is_wp_error( $location_post ) ) {
			return $location_post;
		}

		$page = max( 1, absint( $request->get_param( 'page' ) ) );

		$per_raw = absint( $request->get_param( 'per_page' ) );
		if ( $per_raw < 1 ) {
			$per_raw = 20;
		}
		$per_page = max( 1, min( self::MAX_PER_PAGE, $per_raw ) );

		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		$query = new WP_Query(
			array(
				'post_type'              => 'kennelpress_kennel',
				'post_status'            => 'publish',
				'posts_per_page'         => $per_page,
				'paged'                  => $page,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => false,
				'update_post_meta_cache' => true,
				'meta_query'             => array(
					array(
						'key'   => KennelFlow_Boarding_Post_Meta::KENNEL_LOCATION_ID,
						'value' => (int) $location_post->ID,
						'type'  => 'NUMERIC',
					),
				),
			)
		);
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query

		$items = array();
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$cap = (int) get_post_meta( $post->ID, KennelFlow_Boarding_Post_Meta::KENNEL_CAPACITY, true );
			if ( $cap < 1 ) {
				$cap = 1;
			}
			$items[] = array(
				'id'       => (int) $post->ID,
				'title'    => get_the_title( $post ),
				'slug'     => $post->post_name,
				'capacity' => $cap,
			);
		}

		$data = array(
			'location_id'   => (int) $location_post->ID,
			'location_slug' => $location_post->post_name,
			'page'          => $page,
			'per_page'      => $per_page,
			'total'         => (int) $query->found_posts,
			'total_pages'   => (int) $query->max_num_pages,
			'kennels'       => $items,
		);

		/**
		 * Filters the REST response for the kennels list.
		 *
		 * @since 0.1.0
		 *
		 * @param array           $data    Payload.
		 * @param WP_Post         $location_post Hub location post.
		 * @param WP_REST_Request $request       Request.
		 */
		$data = kennelflow_boarding_apply_filters( 'rest_kennels_response', $data, $location_post, $request );

		return rest_ensure_response( $data );
	}

	/**
	 * Query args.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		return array(
			'location' => array(
				'description'       => __( 'Hub location post ID or slug.', 'kennelflow-boarding' ),
				'type'              => array( 'string', 'integer' ),
				'required'          => true,
				'sanitize_callback' => array( $this, 'sanitize_location_param' ),
			),
			'page'     => array(
				'description' => __( 'Page number.', 'kennelflow-boarding' ),
				'type'        => 'integer',
				'default'     => 1,
				'minimum'     => 1,
			),
			'per_page' => array(
				'description' => __( 'Items per page (max 100).', 'kennelflow-boarding' ),
				'type'        => 'integer',
				'default'     => 20,
				'minimum'     => 1,
				'maximum'     => self::MAX_PER_PAGE,
			),
		);
	}

	/**
	 * Sanitize location parameter.
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
}
