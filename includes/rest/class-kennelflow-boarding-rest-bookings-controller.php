<?php
/**
 * REST: booking list/create/update for staff and integrations.
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_REST_Bookings_Controller
 */
class KennelFlow_Boarding_REST_Bookings_Controller extends KennelFlow_Boarding_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @param string $namespace REST namespace.
	 */
	public function __construct( $namespace = 'kennelflow-boarding/v1' ) {
		parent::__construct( 'bookings', $namespace );
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
					'permission_callback' => array( $this, 'permissions_staff' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'permissions_create_booking' ),
					'args'                => $this->get_create_params(),
				),
			)
		);

		register_rest_route(
			$this->get_namespace(),
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'permissions_staff' ),
					'args'                => array(
						'id' => array(
							'description' => __( 'Booking post ID.', 'kennelflow-boarding' ),
							'type'        => 'integer',
							'required'    => true,
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'permissions_edit_booking' ),
					'args'                => $this->get_update_params(),
				),
			)
		);
	}

	/**
	 * Staff may list/create (edit_posts).
	 *
	 * @return bool
	 */
	public function permissions_staff() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Staff may create any booking; logged-in pet owners may create for their pets only (booking wizard).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function permissions_create_booking( $request ) {
		if ( current_user_can( 'edit_posts' ) ) {
			return true;
		}

		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'kennelpress_rest_not_logged_in',
				__( 'You must be logged in to request a booking.', 'kennelflow-boarding' ),
				array( 'status' => 401 )
			);
		}

		$pet_id = absint( $request->get_param( 'pet_id' ) );
		if ( $pet_id < 1 ) {
			return new WP_Error(
				'kennelpress_rest_need_pet',
				__( 'Choose a pet for this booking.', 'kennelflow-boarding' ),
				array( 'status' => 400 )
			);
		}

		if ( ! function_exists( 'ltkf_get_pet_owner_user_id' ) ) {
			return new WP_Error(
				'kennelpress_rest_kf_required',
				__( 'KennelFlow Core is required to verify pet ownership.', 'kennelflow-boarding' ),
				array( 'status' => 503 )
			);
		}

		if ( (int) ltkf_get_pet_owner_user_id( $pet_id ) !== get_current_user_id() ) {
			return new WP_Error(
				'kennelpress_rest_not_your_pet',
				__( 'That pet is not linked to your account.', 'kennelflow-boarding' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * May edit this booking.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function permissions_edit_booking( $request ) {
		$id = absint( $request['id'] );
		if ( $id < 1 ) {
			return false;
		}
		return current_user_can( 'edit_post', $id );
	}

	/**
	 * GET /bookings
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$start = $request->get_param( 'start' );
		$end   = $request->get_param( 'end' );

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

		$location         = $request->get_param( 'location' );
		$location_post_id = null;
		if ( null !== $location && '' !== $location ) {
			$location_post = KennelFlow_Boarding_REST_Availability_Controller::resolve_location_term( $location );
			if ( is_wp_error( $location_post ) ) {
				return $location_post;
			}
			$location_post_id = (int) $location_post->ID;
		}

		$ids = KennelFlow_Boarding_Booking_Query::get_booking_ids_overlapping_range( $start_gmt, $end_gmt, $location_post_id );

		$filter_kind   = $request->get_param( 'booking_kind' );
		$filter_kind   = null !== $filter_kind && '' !== (string) $filter_kind
			? KennelFlow_Boarding_Post_Meta::sanitize_booking_kind( (string) $filter_kind )
			: '';
		$filter_status = $request->get_param( 'status' );
		$filter_status = null !== $filter_status && '' !== (string) $filter_status
			? KennelFlow_Boarding_Post_Meta::sanitize_booking_status( (string) $filter_status )
			: '';

		$data = array();
		foreach ( $ids as $bid ) {
			$item = $this->prepare_booking( $bid );
			if ( null === $item ) {
				continue;
			}
			if ( '' !== $filter_kind && $filter_kind !== $item['booking_kind'] ) {
				continue;
			}
			if ( '' !== $filter_status && $filter_status !== $item['status'] ) {
				continue;
			}
			$data[] = $item;
		}

		$response = array(
			'start_gmt' => $start_gmt,
			'end_gmt'   => $end_gmt,
			'bookings'  => $data,
		);

		return rest_ensure_response( $response );
	}

	/**
	 * GET /bookings/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$id = absint( $request['id'] );
		if ( $id < 1 || 'kennelpress_booking' !== get_post_type( $id ) ) {
			return new WP_Error( 'kennelpress_not_found', __( 'Booking not found.', 'kennelflow-boarding' ), array( 'status' => 404 ) );
		}
		$item = $this->prepare_booking( $id );
		if ( null === $item ) {
			return new WP_Error( 'kennelpress_not_found', __( 'Booking not found.', 'kennelflow-boarding' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $item );
	}

	/**
	 * POST /bookings
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$pet_id = absint( $request->get_param( 'pet_id' ) );
		$start  = $request->get_param( 'start' );
		$end    = $request->get_param( 'end' );
		$status = $request->get_param( 'status' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			$status = 'pending';
		}

		if ( $pet_id < 1 || 'kf_pet' !== get_post_type( $pet_id ) ) {
			return new WP_Error( 'kennelpress_bad_pet', __( 'Invalid pet.', 'kennelflow-boarding' ), array( 'status' => 400 ) );
		}

		$booking_kind = KennelFlow_Boarding_Post_Meta::sanitize_booking_kind( (string) $request->get_param( 'booking_kind' ) );
		if ( ! current_user_can( 'edit_posts' ) ) {
			$booking_kind = 'boarding';
		}

		$resource_id = absint( $request->get_param( 'resource_id' ) );
		if ( $resource_id < 1 ) {
			$resource_id = absint( $request->get_param( 'kennel_id' ) );
		}
		if ( $resource_id < 1 ) {
			$resource_id = absint( $request->get_param( 'room_id' ) );
		}

		$location_request = absint( $request->get_param( 'location_id' ) );

		$stored_resource = $resource_id;

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

		$location_post_id  = 0;
		$stored_resource   = $resource_id;
		$can_force_overlap = false;
		$exam_room_extra   = 0;
		$clinic_lock_ops   = array();

		if ( 'boarding' === $booking_kind ) {
			if ( $resource_id < 1 || 'kennelpress_kennel' !== get_post_type( $resource_id ) ) {
				return new WP_Error( 'kennelpress_bad_kennel', __( 'Invalid kennel.', 'kennelflow-boarding' ), array( 'status' => 400 ) );
			}
			$location_post_id = absint( get_post_meta( $resource_id, KennelFlow_Boarding_Post_Meta::KENNEL_LOCATION_ID, true ) );
			if ( $location_post_id < 1 ) {
				return new WP_Error( 'kennelpress_kennel_no_location', __( 'Kennel has no location assigned.', 'kennelflow-boarding' ), array( 'status' => 400 ) );
			}
		} elseif ( 'grooming' === $booking_kind ) {
			if ( $location_request < 1 ) {
				return new WP_Error( 'kennelpress_location_required', __( 'Location is required for grooming bookings.', 'kennelflow-boarding' ), array( 'status' => 400 ) );
			}
			if ( 'kf_location' !== get_post_type( $location_request ) ) {
				return new WP_Error( 'kennelpress_bad_location', __( 'Invalid location.', 'kennelflow-boarding' ), array( 'status' => 400 ) );
			}
			if ( ! $this->user_can_be_grooming_resource( $resource_id ) ) {
				return new WP_Error( 'kennelpress_bad_resource', __( 'Select a valid groomer.', 'kennelflow-boarding' ), array( 'status' => 400 ) );
			}
			$location_post_id = $location_request;
		} elseif ( 'clinic' === $booking_kind ) {
			if ( $location_request < 1 ) {
				return new WP_Error( 'kennelpress_location_required', __( 'Location is required for clinic bookings.', 'kennelflow-boarding' ), array( 'status' => 400 ) );
			}
			if ( 'kf_location' !== get_post_type( $location_request ) ) {
				return new WP_Error( 'kennelpress_bad_location', __( 'Invalid location.', 'kennelflow-boarding' ), array( 'status' => 400 ) );
			}
			$location_post_id = $location_request;

			$can_force_overlap = (bool) $request->get_param( 'force_clinic_overlap' ) && $this->permissions_staff();
			$exam_room_extra   = absint( $request->get_param( 'clinic_exam_room_id' ) );

			if ( $resource_id > 0 && 'kennelpress_kennel' === get_post_type( $resource_id ) ) {
				if ( ! $this->kennel_is_in_location( $resource_id, $location_post_id ) ) {
					return new WP_Error( 'kennelpress_bad_kennel', __( 'That exam resource is not in the selected location.', 'kennelflow-boarding' ), array( 'status' => 400 ) );
				}
				if ( ! $can_force_overlap ) {
					$clinic_lock_ops[] = array(
						'type' => 'kennel_kind',
						'id'   => $resource_id,
						'msg'  => __( 'That room already has a booking in this time range.', 'kennelflow-boarding' ),
					);
				}
			} elseif ( $resource_id > 0 ) {
				if ( ! $this->user_can_be_clinic_resource( $resource_id ) ) {
					return new WP_Error( 'kennelpress_bad_resource', __( 'Select a valid clinician.', 'kennelflow-boarding' ), array( 'status' => 400 ) );
				}
				if ( ! $can_force_overlap ) {
					$clinic_lock_ops[] = array(
						'type' => 'interval',
						'id'   => $resource_id,
						'msg'  => __( 'That clinician already has a booking in this time range.', 'kennelflow-boarding' ),
					);
					if ( $exam_room_extra > 0 ) {
						if ( 'kennelpress_kennel' !== get_post_type( $exam_room_extra ) ) {
							return new WP_Error( 'kennelpress_bad_kennel', __( 'Invalid exam room.', 'kennelflow-boarding' ), array( 'status' => 400 ) );
						}
						if ( ! $this->kennel_is_in_location( $exam_room_extra, $location_post_id ) ) {
							return new WP_Error( 'kennelpress_bad_kennel', __( 'That exam room is not in the selected location.', 'kennelflow-boarding' ), array( 'status' => 400 ) );
						}
						$clinic_lock_ops[] = array(
							'type' => 'kennel_kind',
							'id'   => $exam_room_extra,
							'msg'  => __( 'That exam room already has a booking in this time range.', 'kennelflow-boarding' ),
						);
					}
				}
			} else {
				return new WP_Error( 'kennelpress_bad_resource', __( 'Select a clinician or exam room.', 'kennelflow-boarding' ), array( 'status' => 400 ) );
			}

			usort(
				$clinic_lock_ops,
				static function ( $a, $b ) {
					return $a['id'] <=> $b['id'];
				}
			);
		} else {
			return new WP_Error( 'kennelpress_bad_kind', __( 'Invalid booking kind.', 'kennelflow-boarding' ), array( 'status' => 400 ) );
		}

		$facility = KennelFlow_Boarding_Facility_Settings::validate_booking_interval( $location_post_id, $start_gmt, $end_gmt );
		if ( is_wp_error( $facility ) ) {
			return $facility;
		}

		if ( ! KennelFlow_Boarding_Booking_Transaction::begin() ) {
			return new WP_Error(
				'kennelpress_db_error',
				__( 'Could not start database transaction.', 'kennelflow-boarding' ),
				array( 'status' => 500 )
			);
		}

		$conflict_error = null;

		if ( 'boarding' === $booking_kind ) {
			if ( KennelFlow_Boarding_Booking_Transaction::locked_boarding_kennel_has_conflict( $resource_id, $start_gmt, $end_gmt, 0 ) ) {
				$conflict_error = new WP_Error(
					'kennelpress_kennel_unavailable',
					__( 'That kennel is not available for the selected interval.', 'kennelflow-boarding' ),
					array( 'status' => 409 )
				);
			}
		} elseif ( 'grooming' === $booking_kind ) {
			if ( KennelFlow_Boarding_Booking_Transaction::locked_resource_interval_has_conflict( $resource_id, $booking_kind, $start_gmt, $end_gmt, 0 ) ) {
				$conflict_error = new WP_Error(
					'kennelpress_resource_unavailable',
					__( 'That groomer already has a booking in this time range.', 'kennelflow-boarding' ),
					array( 'status' => 409 )
				);
			}
		} elseif ( 'clinic' === $booking_kind && ! $can_force_overlap ) {
			foreach ( $clinic_lock_ops as $op ) {
				$hit = false;
				if ( 'interval' === $op['type'] ) {
					$hit = KennelFlow_Boarding_Booking_Transaction::locked_resource_interval_has_conflict( (int) $op['id'], 'clinic', $start_gmt, $end_gmt, 0 );
				} else {
					$hit = KennelFlow_Boarding_Booking_Transaction::locked_kennel_kind_has_conflict( (int) $op['id'], 'clinic', $start_gmt, $end_gmt, 0 );
				}
				if ( $hit ) {
					$conflict_error = new WP_Error(
						'kennelpress_resource_unavailable',
						(string) $op['msg'],
						array( 'status' => 409 )
					);
					break;
				}
			}
		}

		if ( null !== $conflict_error ) {
			KennelFlow_Boarding_Booking_Transaction::rollback();
			return $conflict_error;
		}

		$status = $status ? KennelFlow_Boarding_Post_Meta::sanitize_booking_status( $status ) : 'pending';

		$title = $request->get_param( 'title' );
		$title = $title ? sanitize_text_field( (string) $title ) : sprintf(
			/* translators: 1: pet title, 2: start datetime (UTC). */
			__( 'Booking: %1$s — %2$s', 'kennelflow-boarding' ),
			get_the_title( $pet_id ),
			$start_gmt
		);

		$notes = $request->get_param( 'notes' );
		if ( null !== $notes && '' !== $notes ) {
			$notes = sanitize_text_field( (string) $notes );
			if ( '' !== $notes ) {
				$title .= ' — ' . $notes;
			}
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'kennelpress_booking',
				'post_status' => 'publish',
				'post_title'  => $title,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			KennelFlow_Boarding_Booking_Transaction::rollback();
			return $post_id;
		}

		if ( $post_id < 1 ) {
			KennelFlow_Boarding_Booking_Transaction::rollback();
			return new WP_Error(
				'kennelpress_booking_create_failed',
				__( 'Could not create booking.', 'kennelflow-boarding' ),
				array( 'status' => 500 )
			);
		}

		update_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_PET_ID, $pet_id );
		update_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_KENNEL_ID, $stored_resource );
		update_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_KIND, $booking_kind );
		update_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_START_GMT, $start_gmt );
		update_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_END_GMT, $end_gmt );
		update_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_STATUS, $status );

		if ( $location_post_id > 0 ) {
			update_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_LOCATION_ID, $location_post_id );
		}

		$kf_clin = absint( $request->get_param( '_kf_clinician_id' ) );
		if ( $kf_clin < 1 ) {
			$kf_clin = absint( $request->get_param( 'kf_clinician_id' ) );
		}
		if ( $kf_clin > 0 && get_userdata( $kf_clin ) ) {
			update_post_meta( $post_id, '_kf_clinician_id', $kf_clin );
		}

		if ( 'clinic' === $booking_kind ) {
			$exam_save = absint( $request->get_param( 'clinic_exam_room_id' ) );
			if ( $exam_save > 0 && 'kennelpress_kennel' !== get_post_type( $stored_resource ) ) {
				update_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_KF_CLINIC_EXAM_ROOM_ID, $exam_save );
			} else {
				delete_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_KF_CLINIC_EXAM_ROOM_ID );
			}
		}

		$this->save_stay_meta_from_request( (int) $post_id, $request );

		$commerce_extra = array();
		if ( 'boarding' === $booking_kind && $location_post_id > 0 ) {
			$commerce_extra = $this->save_boarding_booking_meta( (int) $post_id, $location_post_id, $start_gmt, $end_gmt, $request );
		}

		if ( ! KennelFlow_Boarding_Booking_Transaction::commit() ) {
			KennelFlow_Boarding_Booking_Transaction::rollback();
			return new WP_Error(
				'kennelpress_db_error',
				__( 'Could not commit booking transaction.', 'kennelflow-boarding' ),
				array( 'status' => 500 )
			);
		}

		KennelFlow_Boarding_Cache::bump_query_bust();

		/**
		 * Fires after a booking is created and saved via REST (after DB commit).
		 *
		 * @since 0.1.0
		 *
		 * @param int    $post_id Booking post ID.
		 * @param array  $params  Request parameters (may include send_care_sheet_email).
		 * @param string $context 'create'.
		 */
		kennelflow_boarding_do_action( 'booking_saved', (int) $post_id, $request->get_params(), 'create' );

		$item = $this->prepare_booking( (int) $post_id );
		if ( ! empty( $commerce_extra['checkout_url'] ) ) {
			$item['boarding_checkout_url'] = $commerce_extra['checkout_url'];
		}
		if ( ! empty( $commerce_extra['cart_error'] ) ) {
			$item['boarding_cart_error'] = $commerce_extra['cart_error'];
		}
		$res = rest_ensure_response( $item );
		$res->set_status( 201 );
		return $res;
	}

	/**
	 * PATCH /bookings/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$id = absint( $request['id'] );
		if ( $id < 1 || 'kennelpress_booking' !== get_post_type( $id ) ) {
			return new WP_Error( 'kennelpress_not_found', __( 'Booking not found.', 'kennelflow-boarding' ), array( 'status' => 404 ) );
		}

		$status = $request->get_param( 'status' );
		if ( null !== $status && '' !== $status ) {
			update_post_meta( $id, KennelFlow_Boarding_Post_Meta::BOOKING_STATUS, KennelFlow_Boarding_Post_Meta::sanitize_booking_status( $status ) );
			KennelFlow_Boarding_Cache::bump_query_bust();
		}

		$this->save_stay_meta_from_request( $id, $request );

		$booking_kind_param = $request->get_param( 'booking_kind' );
		if ( null !== $booking_kind_param && '' !== $booking_kind_param ) {
			update_post_meta( $id, KennelFlow_Boarding_Post_Meta::BOOKING_KIND, KennelFlow_Boarding_Post_Meta::sanitize_booking_kind( (string) $booking_kind_param ) );
			KennelFlow_Boarding_Cache::bump_query_bust();
		}

		$loc_param = $request->get_param( 'location_id' );
		if ( null !== $loc_param && '' !== $loc_param ) {
			update_post_meta( $id, KennelFlow_Boarding_Post_Meta::BOOKING_LOCATION_ID, absint( $loc_param ) );
			KennelFlow_Boarding_Cache::bump_query_bust();
		}

		$res_id = $request->get_param( 'resource_id' );
		if ( null !== $res_id && '' !== $res_id ) {
			update_post_meta( $id, KennelFlow_Boarding_Post_Meta::BOOKING_KENNEL_ID, absint( $res_id ) );
			KennelFlow_Boarding_Cache::bump_query_bust();
		}

		/**
		 * Fires after a booking is updated via REST.
		 *
		 * @since 0.1.0
		 *
		 * @param int    $id      Booking post ID.
		 * @param array  $params  Request parameters (may include send_care_sheet_email).
		 * @param string $context 'update'.
		 */
		kennelflow_boarding_do_action( 'booking_saved', $id, $request->get_params(), 'update' );

		$item = $this->prepare_booking( $id );
		return rest_ensure_response( $item );
	}

	/**
	 * Whether another booking blocks the same scheduling resource (kennel id or user id in kennel_id column).
	 *
	 * @param int    $resource_id   Kennel post ID or user ID.
	 * @param string $booking_kind  booking_kind value.
	 * @param string $start_gmt     UTC.
	 * @param string $end_gmt       UTC.
	 * @param int    $exclude_post_id Exclude this booking post (0 = none).
	 * @return bool
	 */
	protected function has_resource_interval_conflict( $resource_id, $booking_kind, $start_gmt, $end_gmt, $exclude_post_id = 0 ) {
		global $wpdb;

		$resource_id  = absint( $resource_id );
		$exclude_post = absint( $exclude_post_id );
		if ( $resource_id < 1 ) {
			return false;
		}

		// WordPress user as scheduling resource: any blocking row in kf_bookings for this user id (global conflict).
		if (
			'kennelpress_kennel' !== get_post_type( $resource_id )
			&& get_userdata( $resource_id )
			&& function_exists( 'ltkf_clinician_has_global_booking_overlap' )
			&& function_exists( 'ltkf_bookings_table_name' )
			&& function_exists( 'ltkf_table_exists' )
			&& ltkf_table_exists( ltkf_bookings_table_name() )
		) {
			return ltkf_clinician_has_global_booking_overlap( $resource_id, $start_gmt, $end_gmt, $exclude_post_id );
		}

		$booking_kind = KennelFlow_Boarding_Post_Meta::sanitize_booking_kind( (string) $booking_kind );
		$table        = KennelFlow_Boarding_Booking_Index::table_name();
		$statuses     = KennelFlow_Boarding_Availability::get_blocking_statuses();
		if ( empty( $statuses ) ) {
			return false;
		}

		$status_placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
		$prepare_args        = array_merge(
			array( $table, $exclude_post, $resource_id, $booking_kind ),
			$statuses,
			array( $end_gmt, $start_gmt )
		);

		$query = 'SELECT 1 FROM %i WHERE post_id <> %d AND kennel_id = %d AND booking_kind = %s AND status IN (' . $status_placeholders . ') AND start_gmt < %s AND end_gmt > %s LIMIT 1';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$sql = call_user_func_array(
			array( $wpdb, 'prepare' ),
			array_merge( array( $query ), $prepare_args )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		if ( null === $sql ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		$hit = $wpdb->get_var( $sql );

		return (bool) $hit;
	}

	/**
	 * Groomer user check.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	protected function user_can_be_grooming_resource( $user_id ) {
		$user_id = absint( $user_id );
		if ( $user_id < 1 ) {
			return false;
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}
		$roles = kennelflow_boarding_apply_filters(
			'grooming_resource_roles',
			KennelFlow_Boarding_REST_Booking_Intake_Resources_Controller::default_grooming_resource_roles()
		);
		$roles = array_filter( array_map( 'sanitize_key', (array) $roles ) );
		return (bool) array_intersect( $roles, (array) $user->roles );
	}

	/**
	 * Clinical provider user check.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	protected function user_can_be_clinic_resource( $user_id ) {
		$user_id = absint( $user_id );
		if ( $user_id < 1 ) {
			return false;
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}
		$roles = kennelflow_boarding_apply_filters(
			'clinic_user_resource_roles',
			KennelFlow_Boarding_REST_Booking_Intake_Resources_Controller::default_clinic_user_roles()
		);
		$roles = array_filter( array_map( 'sanitize_key', (array) $roles ) );
		return (bool) array_intersect( $roles, (array) $user->roles );
	}

	/**
	 * Kennel belongs to Hub location.
	 *
	 * @param int $kennel_id Kennel post ID.
	 * @param int $location_id Hub location post ID.
	 * @return bool
	 */
	protected function kennel_is_in_location( $kennel_id, $location_id ) {
		return absint( get_post_meta( $kennel_id, KennelFlow_Boarding_Post_Meta::KENNEL_LOCATION_ID, true ) ) === absint( $location_id );
	}

	/**
	 * Save stay sheet meta from REST request (optional keys).
	 *
	 * @param int             $post_id Booking post ID.
	 * @param WP_REST_Request $request Request.
	 * @return void
	 */
	protected function save_stay_meta_from_request( $post_id, $request ) {
		$post_id = absint( $post_id );
		if ( $post_id < 1 ) {
			return;
		}

		$map = array(
			'stay_diet'             => KennelFlow_Boarding_Post_Meta::BOOKING_STAY_DIET,
			'stay_medication_notes' => KennelFlow_Boarding_Post_Meta::BOOKING_STAY_MEDICATION_NOTES,
			'stay_belongings'       => KennelFlow_Boarding_Post_Meta::BOOKING_STAY_BELONGINGS,
			'grooming_style_notes'  => KennelFlow_Boarding_Post_Meta::BOOKING_GROOMING_STYLE_NOTES,
			'coat_condition'        => KennelFlow_Boarding_Post_Meta::BOOKING_COAT_CONDITION,
			'reason_for_visit'      => KennelFlow_Boarding_Post_Meta::BOOKING_REASON_FOR_VISIT,
		);

		$params = $request->get_params();
		foreach ( $map as $rest_key => $meta_key ) {
			if ( ! array_key_exists( $rest_key, $params ) ) {
				continue;
			}
			$raw = $params[ $rest_key ];
			if ( null === $raw ) {
				continue;
			}
			$val = KennelFlow_Boarding_Post_Meta::sanitize_stay_text( (string) $raw );
			if ( '' === $val ) {
				delete_post_meta( $post_id, $meta_key );
			} else {
				update_post_meta( $post_id, $meta_key, $val );
			}
		}
	}

	/**
	 * Booking payload for REST.
	 *
	 * @param int $post_id Booking ID.
	 * @return array|null
	 */
	protected function prepare_booking( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id < 1 ) {
			return null;
		}
		$post = get_post( $post_id );
		if ( ! $post || 'kennelpress_booking' !== $post->post_type ) {
			return null;
		}

		$pet_id    = (int) get_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_PET_ID, true );
		$kennel_id = (int) get_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_KENNEL_ID, true );
		$kind      = (string) get_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_KIND, true );
		if ( '' === $kind ) {
			$kind = 'boarding';
		}

		$loc_id = (int) get_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_LOCATION_ID, true );

		$data = array(
			'id'                    => $post_id,
			'title'                 => get_the_title( $post_id ),
			'pet_id'                => $pet_id,
			'pet_title'             => $pet_id ? get_the_title( $pet_id ) : '',
			'kennel_id'             => $kennel_id,
			'resource_id'           => $kennel_id,
			'kennel_title'          => $kennel_id && 'kennelpress_kennel' === get_post_type( $kennel_id ) ? get_the_title( $kennel_id ) : '',
			'location_id'           => $loc_id,
			'booking_kind'          => $kind,
			'start_gmt'             => (string) get_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_START_GMT, true ),
			'end_gmt'               => (string) get_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_END_GMT, true ),
			'status'                => (string) get_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_STATUS, true ),
			'stay_diet'             => (string) get_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_STAY_DIET, true ),
			'stay_medication_notes' => (string) get_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_STAY_MEDICATION_NOTES, true ),
			'stay_belongings'       => (string) get_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_STAY_BELONGINGS, true ),
			'grooming_style_notes'  => (string) get_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_GROOMING_STYLE_NOTES, true ),
			'coat_condition'        => (string) get_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_COAT_CONDITION, true ),
			'reason_for_visit'      => (string) get_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_REASON_FOR_VISIT, true ),
		);

		$clinic_exam = (int) get_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_KF_CLINIC_EXAM_ROOM_ID, true );
		if ( $clinic_exam > 0 ) {
			$data['clinic_exam_room_id'] = $clinic_exam;
		}

		$quote_json = (string) get_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_BOARDING_QUOTE_JSON, true );
		if ( '' !== $quote_json ) {
			$decoded = json_decode( $quote_json, true );
			if ( is_array( $decoded ) ) {
				$data['boarding_quote'] = $decoded;
			}
		}
		$price_app = (string) get_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_BOARDING_PRICE_APPLICATION, true );
		if ( '' !== $price_app ) {
			$data['boarding_price_application'] = $price_app;
		}
		$choices_json = (string) get_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_BOARDING_CHOICES_JSON, true );
		if ( '' !== $choices_json ) {
			$c = json_decode( $choices_json, true );
			if ( is_array( $c ) ) {
				$data['boarding_choices'] = $c;
			}
		}
		$intake_json = (string) get_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_BOARDING_INTAKE_JSON, true );
		if ( '' !== $intake_json ) {
			$i = json_decode( $intake_json, true );
			if ( is_array( $i ) ) {
				$data['boarding_intake'] = $i;
			}
		}
		if ( '1' === (string) get_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_BOARDING_INTERVIEW_REQUESTED, true ) ) {
			$data['boarding_interview_requested'] = true;
		}
		$wc_oid = (int) get_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_WC_ORDER_ID, true );
		if ( $wc_oid > 0 ) {
			$data['wc_order_id'] = $wc_oid;
		}

		/**
		 * Filter booking payload for REST (Kennel Press Pro may add payment URLs, WooCommerce order IDs, etc.).
		 *
		 * @since 0.1.0
		 *
		 * @param array $data    Booking data.
		 * @param int   $post_id Booking post ID.
		 */
		return kennelflow_boarding_apply_filters( 'rest_booking_data', $data, $post_id );
	}

	/**
	 * List params.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		return array(
			'start'        => array(
				'description'       => __( 'Range start UTC.', 'kennelflow-boarding' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'end'          => array(
				'description'       => __( 'Range end UTC.', 'kennelflow-boarding' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'location'     => array(
				'description'       => __( 'Optional location term ID or slug (filter by kennels in location).', 'kennelflow-boarding' ),
				'type'              => array( 'string', 'integer' ),
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'booking_kind' => array(
				'description'       => __( 'Optional: only return bookings of this kind (e.g. boarding).', 'kennelflow-boarding' ),
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_key',
			),
			'status'       => array(
				'description'       => __( 'Optional: only return bookings with this status (e.g. checked_in).', 'kennelflow-boarding' ),
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_key',
			),
		);
	}

	/**
	 * Create body params.
	 *
	 * @return array
	 */
	protected function get_create_params() {
		return array(
			'pet_id'                            => array(
				'type'     => 'integer',
				'required' => true,
			),
			'kennel_id'                         => array(
				'type'     => 'integer',
				'required' => false,
			),
			'resource_id'                       => array(
				'type'     => 'integer',
				'required' => false,
			),
			'location_id'                       => array(
				'type'     => 'integer',
				'required' => false,
			),
			'booking_kind'                      => array(
				'type'     => 'string',
				'required' => false,
			),
			'start'                             => array(
				'type'     => 'string',
				'required' => true,
			),
			'end'                               => array(
				'type'     => 'string',
				'required' => true,
			),
			'status'                            => array(
				'type'     => 'string',
				'required' => false,
			),
			'title'                             => array(
				'type'     => 'string',
				'required' => false,
			),
			'stay_diet'                         => array(
				'type'     => 'string',
				'required' => false,
			),
			'stay_medication_notes'             => array(
				'type'     => 'string',
				'required' => false,
			),
			'stay_belongings'                   => array(
				'type'     => 'string',
				'required' => false,
			),
			'grooming_style_notes'              => array(
				'type'     => 'string',
				'required' => false,
			),
			'coat_condition'                    => array(
				'type'     => 'string',
				'required' => false,
			),
			'reason_for_visit'                  => array(
				'type'     => 'string',
				'required' => false,
			),
			'clinic_exam_room_id'               => array(
				'type'     => 'integer',
				'required' => false,
			),
			'force_clinic_overlap'              => array(
				'type'     => 'boolean',
				'required' => false,
			),
			'send_care_sheet_email'             => array(
				'type'     => 'boolean',
				'required' => false,
			),
			'room_id'                           => array(
				'type'     => 'integer',
				'required' => false,
			),
			'notes'                             => array(
				'type'     => 'string',
				'required' => false,
			),
			'_kf_clinician_id'                  => array(
				'type'     => 'integer',
				'required' => false,
			),
			'kf_clinician_id'                   => array(
				'type'     => 'integer',
				'required' => false,
			),
			'boarding_pet_size'                 => array(
				'type'     => 'string',
				'required' => false,
			),
			'boarding_pet_count'                => array(
				'type'     => 'integer',
				'required' => false,
			),
			'boarding_emergency_drop'           => array(
				'type'     => 'boolean',
				'required' => false,
			),
			'boarding_extended_pickup'          => array(
				'type'     => 'boolean',
				'required' => false,
			),
			'boarding_kennel_food'              => array(
				'type'     => 'boolean',
				'required' => false,
			),
			'boarding_extra_day_after_extended' => array(
				'type'     => 'boolean',
				'required' => false,
			),
			'boarding_intake'                   => array(
				'type'     => 'object',
				'required' => false,
			),
			'boarding_interview_requested'      => array(
				'type'     => 'boolean',
				'required' => false,
			),
		);
	}

	/**
	 * Update body params.
	 *
	 * @return array
	 */
	protected function get_update_params() {
		return array(
			'status'                => array(
				'type'     => 'string',
				'required' => false,
			),
			'booking_kind'          => array(
				'type'     => 'string',
				'required' => false,
			),
			'location_id'           => array(
				'type'     => 'integer',
				'required' => false,
			),
			'resource_id'           => array(
				'type'     => 'integer',
				'required' => false,
			),
			'stay_diet'             => array(
				'type'     => 'string',
				'required' => false,
			),
			'stay_medication_notes' => array(
				'type'     => 'string',
				'required' => false,
			),
			'stay_belongings'       => array(
				'type'     => 'string',
				'required' => false,
			),
			'grooming_style_notes'  => array(
				'type'     => 'string',
				'required' => false,
			),
			'coat_condition'        => array(
				'type'     => 'string',
				'required' => false,
			),
			'reason_for_visit'      => array(
				'type'     => 'string',
				'required' => false,
			),
			'send_care_sheet_email' => array(
				'type'     => 'boolean',
				'required' => false,
			),
		);
	}

	/**
	 * Persist boarding commercial meta and optional Woo cart line.
	 *
	 * @param int             $post_id          Booking ID.
	 * @param int             $location_post_id Hub location post ID.
	 * @param string          $start_gmt        Start UTC mysql.
	 * @param string          $end_gmt          End UTC mysql.
	 * @param WP_REST_Request $request          Request.
	 * @return array<string, string>
	 */
	protected function save_boarding_booking_meta( $post_id, $location_post_id, $start_gmt, $end_gmt, $request ) {
		$settings = KennelFlow_Boarding_Facility_Settings::get_for_location( $location_post_id );
		$args     = array(
			'pet_size'                 => sanitize_key( (string) $request->get_param( 'boarding_pet_size' ) ),
			'pet_count'                => absint( $request->get_param( 'boarding_pet_count' ) ),
			'emergency_drop'           => (bool) $request->get_param( 'boarding_emergency_drop' ),
			'extended_pickup'          => (bool) $request->get_param( 'boarding_extended_pickup' ),
			'kennel_food'              => (bool) $request->get_param( 'boarding_kennel_food' ),
			'extra_day_after_extended' => (bool) $request->get_param( 'boarding_extra_day_after_extended' ),
		);
		if ( $args['pet_count'] < 1 ) {
			$args['pet_count'] = 1;
		}

		$quote = KennelFlow_Boarding_Boarding_Quote::build( $location_post_id, $start_gmt, $end_gmt, $args );
		if ( is_wp_error( $quote ) ) {
			return array();
		}

		update_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_BOARDING_QUOTE_JSON, wp_json_encode( $quote ) );
		$app = isset( $settings['boarding_price_application'] ) ? KennelFlow_Boarding_Post_Meta::sanitize_boarding_price_application( $settings['boarding_price_application'] ) : 'quote_only';
		update_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_BOARDING_PRICE_APPLICATION, $app );

		$choices = array(
			'pet_size'                 => $args['pet_size'],
			'pet_count'                => $args['pet_count'],
			'emergency_drop'           => $args['emergency_drop'],
			'extended_pickup'          => $args['extended_pickup'],
			'kennel_food'              => $args['kennel_food'],
			'extra_day_after_extended' => $args['extra_day_after_extended'],
		);
		update_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_BOARDING_CHOICES_JSON, wp_json_encode( $choices ) );

		$intake_raw = $request->get_param( 'boarding_intake' );
		$intake     = $this->sanitize_boarding_intake_request( $intake_raw );
		if ( ! empty( $settings['boarding_intake_form_enabled'] ) && ! empty( array_filter( $intake ) ) ) {
			update_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_BOARDING_INTAKE_JSON, wp_json_encode( $intake ) );
		} else {
			delete_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_BOARDING_INTAKE_JSON );
		}

		$interview = (bool) $request->get_param( 'boarding_interview_requested' );
		if ( ! empty( $settings['boarding_interview_enabled'] ) && $interview ) {
			update_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_BOARDING_INTERVIEW_REQUESTED, '1' );
		} else {
			delete_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_BOARDING_INTERVIEW_REQUESTED );
		}

		return KennelFlow_Boarding_Boarding_Commerce::maybe_add_booking_to_cart( $post_id, $location_post_id, $quote, $settings );
	}

	/**
	 * @param mixed $raw Raw request param.
	 * @return array<string, string>
	 */
	protected function sanitize_boarding_intake_request( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		return array(
			'emergency_contact_name'  => isset( $raw['emergency_contact_name'] ) ? sanitize_text_field( wp_unslash( $raw['emergency_contact_name'] ) ) : '',
			'emergency_contact_phone' => isset( $raw['emergency_contact_phone'] ) ? sanitize_text_field( wp_unslash( $raw['emergency_contact_phone'] ) ) : '',
			'special_instructions'    => isset( $raw['special_instructions'] ) ? sanitize_textarea_field( wp_unslash( $raw['special_instructions'] ) ) : '',
		);
	}
}
