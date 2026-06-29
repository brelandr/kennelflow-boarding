<?php
/**
 * REST: boarding stay session photos (check-in / check-out).
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_Session_Media_REST
 */
class KennelFlow_Boarding_Session_Media_REST {

	const NS = 'kennelflow-boarding/v1';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			self::NS,
			'/bookings/(?P<id>\d+)/media',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_booking_media' ),
					'permission_callback' => array( __CLASS__, 'can_view_booking' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create_booking_media' ),
					'permission_callback' => array( __CLASS__, 'can_modify_booking_media' ),
					'args'                => array(
						'media_kind'    => array(
							'type'    => 'string',
							'default' => 'check_in',
						),
						'attachment_id' => array(
							'type'     => 'integer',
							'required' => true,
						),
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/bookings/(?P<id>\d+)/media/(?P<media_id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'delete_booking_media' ),
				'permission_callback' => array( __CLASS__, 'can_modify_booking_media' ),
			)
		);
	}

	/**
	 * @param int $booking_id Booking post ID.
	 * @return bool|\WP_Error
	 */
	protected static function validate_boarding_booking( $booking_id ) {
		$booking_id = absint( $booking_id );
		if ( $booking_id < 1 || 'kennelpress_booking' !== get_post_type( $booking_id ) ) {
			return new WP_Error( 'kennelflow_boarding_invalid_booking', __( 'Invalid booking.', 'kennelflow-boarding' ), array( 'status' => 404 ) );
		}

		if ( ! class_exists( 'KennelFlow_Boarding_Post_Meta' ) ) {
			return true;
		}

		$kind = sanitize_key( (string) get_post_meta( $booking_id, KennelFlow_Boarding_Post_Meta::BOOKING_KIND, true ) );
		if ( in_array( $kind, array( 'grooming', 'clinic' ), true ) ) {
			return new WP_Error( 'kennelflow_boarding_invalid_booking', __( 'This booking is not a boarding stay.', 'kennelflow-boarding' ), array( 'status' => 400 ) );
		}

		return true;
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public static function can_view_booking( $request ) {
		$id = (int) $request['id'];
		$ok = self::validate_boarding_booking( $id );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		if ( ! current_user_can( 'read_post', $id ) && ! current_user_can( 'edit_post', $id ) ) {
			return new WP_Error( 'kennelflow_boarding_forbidden', __( 'Forbidden.', 'kennelflow-boarding' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public static function can_modify_booking_media( $request ) {
		$id = (int) $request['id'];
		$ok = self::validate_boarding_booking( $id );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		if ( ! current_user_can( 'edit_post', $id ) ) {
			return new WP_Error( 'kennelflow_boarding_forbidden', __( 'Forbidden.', 'kennelflow-boarding' ), array( 'status' => 403 ) );
		}
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error( 'kennelflow_boarding_forbidden', __( 'Forbidden.', 'kennelflow-boarding' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_booking_media( $request ) {
		$id   = (int) $request['id'];
		$rows = KennelFlow_Boarding_Session_Media_Db::get_media_rows( $id );
		$out  = array();
		foreach ( $rows as $r ) {
			$aid   = (int) $r->attachment_id;
			$out[] = array(
				'id'            => (int) $r->id,
				'media_kind'    => (string) $r->media_kind,
				'attachment_id' => $aid,
				'staff_user_id' => (int) $r->staff_user_id,
				'created_gmt'   => (string) $r->created_gmt,
				'url'           => wp_get_attachment_url( $aid ),
				'thumbnail_url' => $aid > 0 ? wp_get_attachment_image_url( $aid, array( 80, 80 ) ) : '',
			);
		}
		return rest_ensure_response(
			array(
				'booking_id' => $id,
				'media'      => $out,
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public static function create_booking_media( $request ) {
		$id     = (int) $request['id'];
		$new_id = KennelFlow_Boarding_Session_Media_Db::insert_media_row(
			$id,
			array(
				'media_kind'    => (string) $request->get_param( 'media_kind' ),
				'attachment_id' => (int) $request->get_param( 'attachment_id' ),
			),
			get_current_user_id()
		);
		if ( $new_id < 1 ) {
			return new WP_Error( 'kennelflow_boarding_media_failed', __( 'Could not save media.', 'kennelflow-boarding' ), array( 'status' => 400 ) );
		}
		return rest_ensure_response( array( 'id' => $new_id ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function delete_booking_media( $request ) {
		$media_id = (int) $request['media_id'];
		KennelFlow_Boarding_Session_Media_Db::delete_media_row( $media_id );
		return rest_ensure_response( array( 'deleted' => true ) );
	}
}
