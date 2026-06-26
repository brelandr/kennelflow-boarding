<?php
/**
 * REST: list Hub location posts (`kf_location` CPT).
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_REST_Locations_Controller
 */
class KennelFlow_Boarding_REST_Locations_Controller extends KennelFlow_Boarding_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @param string $namespace REST namespace.
	 */
	public function __construct( $namespace = 'kennelflow-boarding/v1' ) {
		parent::__construct( 'locations', $namespace );
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
	 * Query parameters for paginated collection (page, per_page).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_collection_params() {
		return array_merge(
			parent::get_collection_params(),
			array(
				'page'     => array(
					'description' => __( 'Current page of the collection.', 'kennelflow-boarding' ),
					'type'        => 'integer',
					'default'     => 1,
					'minimum'     => 1,
				),
				'per_page' => array(
					'description' => __( 'Maximum number of items to be returned in result set.', 'kennelflow-boarding' ),
					'type'        => 'integer',
					'default'     => 100,
					'minimum'     => 1,
					'maximum'     => 100,
				),
			)
		);
	}

	/**
	 * Public read.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		unset( $request );
		return true;
	}

	/**
	 * GET /kennelflow-boarding/v1/locations
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$page     = (int) $request->get_param( 'page' );
		$per_page = (int) $request->get_param( 'per_page' );
		if ( $page < 1 ) {
			$page = 1;
		}
		if ( $per_page < 1 ) {
			$per_page = 100;
		}
		if ( $per_page > 100 ) {
			$per_page = 100;
		}

		$bust   = KennelFlow_Boarding_Cache::get_locations_bust();
		$key    = 'ltkf_locations_' . $bust . '_' . $page . '_' . $per_page;
		$cached = wp_cache_get( $key, KennelFlow_Boarding_Cache::OBJECT_CACHE_GROUP_LOCATIONS );
		if ( false !== $cached && is_array( $cached ) && isset( $cached['items'], $cached['total'] ) ) {
			$out         = $cached['items'];
			$total       = (int) $cached['total'];
			$total_pages = (int) $cached['total_pages'];

			/**
			 * Filters the REST response for locations list.
			 *
			 * @since 0.1.0
			 *
			 * @param array $out Locations payload.
			 */
			$out = kennelflow_boarding_apply_filters( 'rest_locations_response', $out );

			$response = rest_ensure_response( $out );
			$response->header( 'X-WP-Total', $total );
			$response->header( 'X-WP-TotalPages', $total_pages );

			return $response;
		}

		if ( ! class_exists( 'KennelFlow_Boarding_Locations' ) || ! post_type_exists( KennelFlow_Boarding_Locations::post_type_slug() ) ) {
			$out         = array();
			$total       = 0;
			$total_pages = 0;
		} else {
			$q = new WP_Query(
				array(
					'post_type'              => KennelFlow_Boarding_Locations::post_type_slug(),
					'post_status'            => array( 'publish', 'draft', 'pending', 'private' ),
					'posts_per_page'         => $per_page,
					'paged'                  => $page,
					'orderby'                => 'title',
					'order'                  => 'ASC',
					'no_found_rows'          => false,
					'update_post_meta_cache' => false,
				)
			);

			$out   = array();
			$posts = $q->posts;
			foreach ( (array) $posts as $p ) {
				if ( ! $p instanceof WP_Post ) {
					continue;
				}
				$out[] = array(
					'id'          => (int) $p->ID,
					'name'        => $p->post_title,
					'slug'        => $p->post_name,
					'description' => $p->post_content,
				);
			}

			$total       = (int) $q->found_posts;
			$total_pages = (int) $q->max_num_pages;
		}

		wp_cache_set(
			$key,
			array(
				'items'       => $out,
				'total'       => $total,
				'total_pages' => $total_pages,
			),
			KennelFlow_Boarding_Cache::OBJECT_CACHE_GROUP_LOCATIONS,
			KennelFlow_Boarding_Cache::TRANSIENT_TTL
		);

		/**
		 * Filters the REST response for locations list.
		 *
		 * @since 0.1.0
		 *
		 * @param array $out Locations payload.
		 */
		$out = kennelflow_boarding_apply_filters( 'rest_locations_response', $out );

		$response = rest_ensure_response( $out );
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', $total_pages );

		return $response;
	}
}
