<?php
/**
 * REST: read Hub pet care defaults for the Stay Care Sheet (allergies, tags, default diet).
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_REST_Pet_Care_Controller
 */
class KennelFlow_Boarding_REST_Pet_Care_Controller extends KennelFlow_Boarding_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @param string $namespace REST namespace.
	 */
	public function __construct( $namespace = 'kennelflow-boarding/v1' ) {
		parent::__construct( '', $namespace );
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->get_namespace(),
			'/pets/(?P<id>[\d]+)/care-defaults',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_care_defaults' ),
					'permission_callback' => array( $this, 'permissions_staff' ),
					'args'                => array(
						'id' => array(
							'description' => __( 'kf_pet post ID.', 'kennelflow-boarding' ),
							'type'        => 'integer',
							'required'    => true,
						),
					),
				),
			)
		);
	}

	/**
	 * Staff read (edit_posts).
	 *
	 * @return bool
	 */
	public function permissions_staff() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Default behavioral tag labels (aligned with KennelFlow Core; filter may extend).
	 *
	 * @return array<string, string>
	 */
	public static function default_behavioral_tag_labels() {
		return array(
			'escape_artist'   => __( 'Escape Artist', 'kennelflow-boarding' ),
			'dog_aggressive'  => __( 'Dog Aggressive', 'kennelflow-boarding' ),
			'fear_of_thunder' => __( 'Fear of Thunder', 'kennelflow-boarding' ),
			'jumper'          => __( 'Jumper', 'kennelflow-boarding' ),
		);
	}

	/**
	 * GET /kennelflow-boarding/v1/pets/{id}/care-defaults
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_care_defaults( $request ) {
		$id = absint( $request['id'] );
		if ( $id < 1 || 'kf_pet' !== get_post_type( $id ) ) {
			return new WP_Error( 'kennelpress_bad_pet', __( 'Invalid pet.', 'kennelflow-boarding' ), array( 'status' => 400 ) );
		}

		if ( ! function_exists( 'ltkf_get_pet_care_defaults_allergies' ) ) {
			return new WP_Error(
				'kennelpress_hub_required',
				__( 'KennelFlow Core care helpers are required.', 'kennelflow-boarding' ),
				array( 'status' => 500 )
			);
		}

		$tags = ltkf_get_pet_care_defaults_behavioral_tags( $id );

		$options = self::default_behavioral_tag_labels();
		/**
		 * Match KennelFlow Core filter for behavioral tag labels.
		 *
		 * @since 0.1.0
		 *
		 * @param array<string, string> $options Slug => label.
		 */
		$options = apply_filters( 'ltkf_pet_boarding_behavioral_tag_options', $options );

		$labels = array();
		foreach ( $tags as $slug ) {
			$slug = sanitize_key( (string) $slug );
			if ( '' === $slug ) {
				continue;
			}
			$labels[] = isset( $options[ $slug ] ) ? $options[ $slug ] : $slug;
		}

		$data = array(
			'pet_id'                => $id,
			'kf_allergies'          => ltkf_get_pet_care_defaults_allergies( $id ),
			'kf_behavioral_tags'    => $tags,
			'behavioral_tag_labels' => $labels,
			'kf_default_diet'       => ltkf_get_pet_care_defaults_diet( $id ),
		);

		return rest_ensure_response( $data );
	}
}
