<?php
/**
 * Register post meta (REST-ready where appropriate).
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_Post_Meta
 */
class KennelFlow_Boarding_Post_Meta {

	/**
	 * Meta keys (stored with leading underscore).
	 */
	const KENNEL_CAPACITY = '_kennelpress_capacity';

	/**
	 * Hub location post ID (`kf_location` CPT from KennelFlow Core).
	 */
	const KENNEL_LOCATION_ID = '_kennelpress_location_id';

	/**
	 * Kennel "slot" type for intake UIs (exam room vs boarding run).
	 */
	const KENNEL_RESOURCE_TYPE = '_kennelpress_resource_type';

	const BOOKING_PET_ID = '_kennelpress_pet_id';

	/**
	 * Scheduling resource: kennel post ID (boarding / exam room) OR WordPress user ID (groomer / vet), per booking_kind.
	 */
	const BOOKING_KENNEL_ID = '_kennelpress_kennel_id';

	/**
	 * Hub `kf_location` post ID for calendar filtering when resource is a user (grooming/clinic vet).
	 */
	const BOOKING_LOCATION_ID = '_kennelpress_booking_location_id';

	const BOOKING_START_GMT = '_kennelpress_start_gmt';

	const BOOKING_END_GMT = '_kennelpress_end_gmt';

	const BOOKING_STATUS = '_kennelpress_status';

	/**
	 * Boarding | clinic — boarding blocks kennel occupancy; clinic does not (Vet Press parity).
	 */
	const BOOKING_KIND = '_kennelpress_booking_kind';

	/**
	 * Stay-specific overrides (phone booking / front desk) — Hub-style keys.
	 */
	const BOOKING_STAY_DIET = '_kf_stay_diet';

	const BOOKING_STAY_MEDICATION_NOTES = '_kf_stay_medication_notes';

	const BOOKING_STAY_BELONGINGS = '_kf_stay_belongings';

	const BOOKING_GROOMING_STYLE_NOTES = '_kf_grooming_style_notes';

	const BOOKING_COAT_CONDITION = '_kf_coat_condition';

	const BOOKING_REASON_FOR_VISIT = '_kf_reason_for_visit';

	/**
	 * Staff / clinical notes for clinic appointments (shared Hub key).
	 */
	const BOOKING_APPOINTMENT_NOTES = '_kf_appointment_notes';

	/**
	 * Optional exam room (kennel CPT) when primary `resource_id` is a clinician user ID.
	 */
	const BOOKING_KF_CLINIC_EXAM_ROOM_ID = '_kf_clinic_exam_room_id';

	/** @var string JSON snapshot from KennelFlow_Boarding_Boarding_Quote::build */
	const BOOKING_BOARDING_QUOTE_JSON = '_kennelpress_boarding_quote_json';

	const BOOKING_BOARDING_PRICE_APPLICATION = '_kennelpress_boarding_price_application';

	const BOOKING_BOARDING_CHOICES_JSON = '_kennelpress_boarding_choices_json';

	const BOOKING_BOARDING_INTAKE_JSON = '_kennelpress_boarding_intake_json';

	const BOOKING_BOARDING_INTERVIEW_REQUESTED = '_kennelpress_boarding_interview_requested';

	const BOOKING_WC_ORDER_ID = '_kennelpress_wc_order_id';

	/**
	 * Register meta keys.
	 *
	 * @return void
	 */
	public static function register() {
		self::register_kennel_meta();
		self::register_booking_meta();

		/**
		 * Fires after Kennel Press post meta is registered.
		 *
		 * @since 0.1.0
		 */
		kennelflow_boarding_do_action( 'after_register_post_meta' );
	}

	/**
	 * Kennel meta.
	 *
	 * @return void
	 */
	protected static function register_kennel_meta() {
		register_post_meta(
			'kennelpress_kennel',
			self::KENNEL_CAPACITY,
			array(
				'type'              => 'integer',
				'single'            => true,
				'default'           => 1,
				'show_in_rest'      => true,
				'sanitize_callback' => 'absint',
				'auth_callback'     => array( __CLASS__, 'auth_edit_kennel' ),
			)
		);

		register_post_meta(
			'kennelpress_kennel',
			self::KENNEL_LOCATION_ID,
			array(
				'type'              => 'integer',
				'single'            => true,
				'default'           => 0,
				'show_in_rest'      => true,
				'sanitize_callback' => 'absint',
				'auth_callback'     => array( __CLASS__, 'auth_edit_kennel' ),
			)
		);

		register_post_meta(
			'kennelpress_kennel',
			self::KENNEL_RESOURCE_TYPE,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_kennel_resource_type' ),
				'auth_callback'     => array( __CLASS__, 'auth_edit_kennel' ),
			)
		);
	}

	/**
	 * Booking meta.
	 *
	 * @return void
	 */
	protected static function register_booking_meta() {
		$auth_booking = array( __CLASS__, 'auth_edit_booking' );

		register_post_meta(
			'kennelpress_booking',
			self::BOOKING_PET_ID,
			array(
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'absint',
				'auth_callback'     => $auth_booking,
			)
		);

		register_post_meta(
			'kennelpress_booking',
			self::BOOKING_KENNEL_ID,
			array(
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'absint',
				'auth_callback'     => $auth_booking,
			)
		);

		register_post_meta(
			'kennelpress_booking',
			self::BOOKING_LOCATION_ID,
			array(
				'type'              => 'integer',
				'single'            => true,
				'default'           => 0,
				'show_in_rest'      => true,
				'sanitize_callback' => 'absint',
				'auth_callback'     => $auth_booking,
			)
		);

		register_post_meta(
			'kennelpress_booking',
			self::BOOKING_START_GMT,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_datetime_string' ),
				'auth_callback'     => $auth_booking,
			)
		);

		register_post_meta(
			'kennelpress_booking',
			self::BOOKING_END_GMT,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_datetime_string' ),
				'auth_callback'     => $auth_booking,
			)
		);

		register_post_meta(
			'kennelpress_booking',
			self::BOOKING_STATUS,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_booking_status' ),
				'auth_callback'     => $auth_booking,
			)
		);

		register_post_meta(
			'kennelpress_booking',
			self::BOOKING_KIND,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => 'boarding',
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_booking_kind' ),
				'auth_callback'     => $auth_booking,
			)
		);

		register_post_meta(
			'kennelpress_booking',
			self::BOOKING_STAY_DIET,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_stay_text' ),
				'auth_callback'     => $auth_booking,
			)
		);

		register_post_meta(
			'kennelpress_booking',
			self::BOOKING_STAY_MEDICATION_NOTES,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_stay_text' ),
				'auth_callback'     => $auth_booking,
			)
		);

		register_post_meta(
			'kennelpress_booking',
			self::BOOKING_STAY_BELONGINGS,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_stay_text' ),
				'auth_callback'     => $auth_booking,
			)
		);

		register_post_meta(
			'kennelpress_booking',
			self::BOOKING_GROOMING_STYLE_NOTES,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_stay_text' ),
				'auth_callback'     => $auth_booking,
			)
		);

		register_post_meta(
			'kennelpress_booking',
			self::BOOKING_COAT_CONDITION,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_stay_text' ),
				'auth_callback'     => $auth_booking,
			)
		);

		register_post_meta(
			'kennelpress_booking',
			self::BOOKING_REASON_FOR_VISIT,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_stay_text' ),
				'auth_callback'     => $auth_booking,
			)
		);

		register_post_meta(
			'kennelpress_booking',
			self::BOOKING_APPOINTMENT_NOTES,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_stay_text' ),
				'auth_callback'     => $auth_booking,
			)
		);

		register_post_meta(
			'kennelpress_booking',
			self::BOOKING_KF_CLINIC_EXAM_ROOM_ID,
			array(
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'absint',
				'auth_callback'     => $auth_booking,
			)
		);

		register_post_meta(
			'kennelpress_booking',
			self::BOOKING_BOARDING_QUOTE_JSON,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_boarding_json_string' ),
				'auth_callback'     => $auth_booking,
			)
		);

		register_post_meta(
			'kennelpress_booking',
			self::BOOKING_BOARDING_PRICE_APPLICATION,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_boarding_price_application' ),
				'auth_callback'     => $auth_booking,
			)
		);

		register_post_meta(
			'kennelpress_booking',
			self::BOOKING_BOARDING_CHOICES_JSON,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_boarding_json_string' ),
				'auth_callback'     => $auth_booking,
			)
		);

		register_post_meta(
			'kennelpress_booking',
			self::BOOKING_BOARDING_INTAKE_JSON,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_boarding_json_string' ),
				'auth_callback'     => $auth_booking,
			)
		);

		register_post_meta(
			'kennelpress_booking',
			self::BOOKING_BOARDING_INTERVIEW_REQUESTED,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_yes_no' ),
				'auth_callback'     => $auth_booking,
			)
		);

		register_post_meta(
			'kennelpress_booking',
			self::BOOKING_WC_ORDER_ID,
			array(
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'absint',
				'auth_callback'     => $auth_booking,
			)
		);
	}

	/**
	 * Sanitize stay sheet text fields.
	 *
	 * @param string $value Raw.
	 * @return string
	 */
	public static function sanitize_stay_text( $value ) {
		return sanitize_textarea_field( (string) $value );
	}

	/**
	 * Auth: edit kennel.
	 *
	 * @param bool   $allowed Whether allowed.
	 * @param string $meta_key Meta key.
	 * @param int    $post_id Post ID.
	 * @return bool
	 */
	public static function auth_edit_kennel( $allowed, $meta_key, $post_id ) {
		unset( $meta_key );
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Auth: edit booking.
	 *
	 * @param bool   $allowed Whether allowed.
	 * @param string $meta_key Meta key.
	 * @param int    $post_id Post ID.
	 * @return bool
	 */
	public static function auth_edit_booking( $allowed, $meta_key, $post_id ) {
		unset( $meta_key );
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Sanitize datetime stored as Y-m-d H:i:s UTC.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function sanitize_datetime_string( $value ) {
		$value = sanitize_text_field( (string) $value );
		$valid = KennelFlow_Boarding_Availability::parse_gmt_mysql( $value );
		if ( is_wp_error( $valid ) ) {
			return '';
		}
		return $valid;
	}

	/**
	 * Sanitize booking status.
	 *
	 * @param string $value Raw status.
	 * @return string
	 */
	public static function sanitize_booking_status( $value ) {
		$value   = sanitize_key( (string) $value );
		$allowed = array( 'pending', 'pending_payment', 'confirmed', 'checked_in', 'completed', 'cancelled', 'expired' );
		if ( ! in_array( $value, $allowed, true ) ) {
			return 'pending';
		}
		return $value;
	}

	/**
	 * Sanitize booking kind for availability rules.
	 *
	 * @param string $value Raw.
	 * @return string
	 */
	public static function sanitize_booking_kind( $value ) {
		$value   = sanitize_key( (string) $value );
		$allowed = array( 'boarding', 'clinic', 'grooming' );
		if ( ! in_array( $value, $allowed, true ) ) {
			return 'boarding';
		}
		return $value;
	}

	/**
	 * Kennel resource slot type (exam room vs boarding run).
	 *
	 * @param string $value Raw.
	 * @return string
	 */
	public static function sanitize_kennel_resource_type( $value ) {
		$value   = sanitize_key( (string) $value );
		$allowed = array( '', 'boarding', 'exam', 'grooming_station', 'general' );
		if ( ! in_array( $value, $allowed, true ) ) {
			return '';
		}
		return $value;
	}

	/**
	 * Sanitize JSON object stored as string (boarding quote / choices / intake).
	 *
	 * @param mixed $value Raw.
	 * @return string
	 */
	public static function sanitize_boarding_json_string( $value ) {
		if ( is_array( $value ) ) {
			return wp_json_encode( $value );
		}
		$s = (string) $value;
		if ( '' === $s ) {
			return '';
		}
		$decoded = json_decode( wp_unslash( $s ), true );
		if ( ! is_array( $decoded ) ) {
			return '';
		}
		return wp_json_encode( $decoded );
	}

	/**
	 * @param mixed $value Raw.
	 * @return string
	 */
	public static function sanitize_boarding_price_application( $value ) {
		$v       = sanitize_key( (string) $value );
		$allowed = array( 'quote_only', 'woocommerce', 'both' );
		if ( ! in_array( $v, $allowed, true ) ) {
			return 'quote_only';
		}
		return $v;
	}

	/**
	 * @param mixed $value Raw.
	 * @return string
	 */
	public static function sanitize_yes_no( $value ) {
		return ! empty( $value ) ? '1' : '';
	}
}
