<?php
/**
 * Front-desk capabilities: edit any kennel booking and Hub pets without manage_options.
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_Capabilities
 */
class KennelFlow_Boarding_Capabilities {

	/**
	 * List / edit any kennelpress_booking (including online owner submissions).
	 */
	const CAP_EDIT_BOOKINGS = 'edit_kennelflow_boarding_bookings';

	/**
	 * Read / edit Hub kf_pet records for intake and boarding desk.
	 */
	const CAP_EDIT_HUB_PETS = 'edit_kennelflow_hub_pets';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_caps' ), 6 );
		add_filter( 'map_meta_cap', array( __CLASS__, 'map_meta_cap' ), 15, 4 );
		add_filter( 'ltkf_hub_menu_capability', array( __CLASS__, 'filter_hub_menu_capability' ) );
		add_filter( 'ltkf_user_can_view_hub_calendar', array( __CLASS__, 'filter_user_can_view_hub_calendar' ), 10, 2 );
		add_filter( 'kennelflow_boarding_front_desk_menu_capability', array( __CLASS__, 'filter_front_desk_menu_capability' ) );
		add_filter( 'kennelpress_front_desk_menu_capability', array( __CLASS__, 'filter_front_desk_menu_capability' ) );
	}

	/**
	 * Front-end Staff Calendar and Hub calendar REST for boarding desk staff.
	 *
	 * @param bool $can     Prior result.
	 * @param int  $user_id User ID (0 = current).
	 * @return bool
	 */
	public static function filter_user_can_view_hub_calendar( $can, $user_id ) {
		if ( $can ) {
			return true;
		}
		self::register_caps();
		if ( self::user_can_edit_bookings( $user_id ) || self::user_can_edit_hub_pets( $user_id ) ) {
			return true;
		}
		return $can;
	}

	/**
	 * Hub menu: allow boarding desk staff without manage_options.
	 *
	 * @param string $cap Default capability.
	 * @return string
	 */
	public static function filter_hub_menu_capability( $cap ) {
		if ( current_user_can( 'manage_options' ) ) {
			return $cap;
		}
		if ( self::user_can_edit_hub_pets() ) {
			return self::CAP_EDIT_HUB_PETS;
		}
		if ( self::user_can_edit_bookings() ) {
			return self::CAP_EDIT_BOOKINGS;
		}
		return $cap;
	}

	/**
	 * Front Desk menu capability.
	 *
	 * @param string $cap Default capability.
	 * @return string
	 */
	public static function filter_front_desk_menu_capability( $cap ) {
		unset( $cap );
		return self::CAP_EDIT_BOOKINGS;
	}

	/**
	 * Grant caps to staff roles (idempotent).
	 *
	 * @return void
	 */
	public static function register_caps() {
		$roles = array( 'administrator', 'editor', 'kfvet_kennel_attendant', 'kfvet_staff' );

		/**
		 * Roles that receive front-desk booking + Hub pet edit caps.
		 *
		 * @since 0.1.6
		 *
		 * @param string[] $roles Role slugs.
		 */
		$roles = kennelflow_boarding_apply_filters( 'roles_with_boarding_desk_caps', $roles );

		$booking_caps = array(
			self::CAP_EDIT_BOOKINGS,
			'edit_others_kennelflow_boarding_bookings',
			'read_private_kennelflow_boarding_bookings',
			'edit_kennelflow_boarding_booking',
			'read_kennelflow_boarding_booking',
			'publish_kennelflow_boarding_bookings',
		);

		foreach ( (array) $roles as $slug ) {
			$slug = sanitize_key( (string) $slug );
			if ( '' === $slug ) {
				continue;
			}
			$role = get_role( $slug );
			if ( ! $role ) {
				continue;
			}
			foreach ( $booking_caps as $booking_cap ) {
				if ( ! $role->has_cap( $booking_cap ) ) {
					$role->add_cap( $booking_cap );
				}
			}
			if ( ! $role->has_cap( self::CAP_EDIT_HUB_PETS ) ) {
				$role->add_cap( self::CAP_EDIT_HUB_PETS );
			}
			// Legacy checks in boarding admin still use edit_posts.
			if ( ! $role->has_cap( 'edit_posts' ) ) {
				$role->add_cap( 'edit_posts' );
			}
			// Stay photos on the staff calendar require upload_files.
			if ( ! $role->has_cap( 'upload_files' ) ) {
				$role->add_cap( 'upload_files' );
			}
		}

		if ( class_exists( 'KennelFlow_Vet_Capabilities' ) ) {
			foreach ( (array) $roles as $slug ) {
				$role = get_role( sanitize_key( (string) $slug ) );
				if ( ! $role ) {
					continue;
				}
				if ( ! $role->has_cap( KennelFlow_Vet_Capabilities::CAP_READ_FACILITY ) ) {
					$role->add_cap( KennelFlow_Vet_Capabilities::CAP_READ_FACILITY );
				}
				if ( ! $role->has_cap( KennelFlow_Vet_Capabilities::CAP_MANAGE_BOOKINGS ) ) {
					$role->add_cap( KennelFlow_Vet_Capabilities::CAP_MANAGE_BOOKINGS );
				}
			}
		}
	}

	/**
	 * Whether the user may manage kennel bookings in admin / REST.
	 *
	 * @param int $user_id User ID (0 = current).
	 * @return bool
	 */
	public static function user_can_edit_bookings( $user_id = 0 ) {
		self::register_caps();

		$user_id = absint( $user_id );
		if ( $user_id < 1 ) {
			return current_user_can( self::CAP_EDIT_BOOKINGS )
				|| current_user_can( 'manage_options' );
		}
		return user_can( $user_id, self::CAP_EDIT_BOOKINGS )
			|| user_can( $user_id, 'manage_options' );
	}

	/**
	 * Whether the user may edit Hub pet posts.
	 *
	 * @param int $user_id User ID (0 = current).
	 * @return bool
	 */
	public static function user_can_edit_hub_pets( $user_id = 0 ) {
		$user_id = absint( $user_id );
		if ( $user_id < 1 ) {
			return current_user_can( self::CAP_EDIT_HUB_PETS )
				|| current_user_can( 'manage_options' );
		}
		return user_can( $user_id, self::CAP_EDIT_HUB_PETS )
			|| user_can( $user_id, 'manage_options' );
	}

	/**
	 * Allow desk staff to open bookings and pets they did not author.
	 *
	 * @param string[] $caps    Primitive caps.
	 * @param string   $cap     Requested cap.
	 * @param int      $user_id User ID.
	 * @param array    $args    Cap args.
	 * @return string[]
	 */
	public static function map_meta_cap( $caps, $cap, $user_id, $args ) {
		if ( ! in_array( $cap, array( 'read_post', 'edit_post', 'delete_post' ), true ) ) {
			return $caps;
		}
		if ( empty( $args[0] ) || ! is_numeric( (string) $args[0] ) ) {
			return $caps;
		}

		$post_id = absint( $args[0] );
		if ( $post_id < 1 ) {
			return $caps;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return $caps;
		}

		$pet_pt = function_exists( 'ltkf_get_pet_post_type' ) ? ltkf_get_pet_post_type() : 'kf_pet';

		if ( 'kennelpress_booking' === $post->post_type && self::user_has_cap( $user_id, self::CAP_EDIT_BOOKINGS ) ) {
			if ( 'delete_post' === $cap && ! self::user_has_cap( $user_id, 'manage_options' ) ) {
				return array( 'do_not_allow' );
			}
			return array( self::CAP_EDIT_BOOKINGS );
		}

		if ( 'kennelpress_booking' === $post->post_type && class_exists( 'KennelFlow_Vet_Capabilities' ) ) {
			$kind = '';
			if ( class_exists( 'KennelFlow_Boarding_Post_Meta' ) ) {
				$kind = sanitize_key( (string) get_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_KIND, true ) );
			}
			if ( 'clinic' === $kind && in_array( $cap, array( 'read_post', 'edit_post', 'delete_post' ), true ) ) {
				if ( 'delete_post' === $cap ) {
					if ( self::user_has_cap( $user_id, KennelFlow_Vet_Capabilities::CAP_MANAGE_BOOKINGS ) ) {
						return array( KennelFlow_Vet_Capabilities::CAP_MANAGE_BOOKINGS );
					}
					return array( 'do_not_allow' );
				}
				if ( self::user_has_cap( $user_id, KennelFlow_Vet_Capabilities::CAP_MANAGE_BOOKINGS )
					|| self::user_has_cap( $user_id, KennelFlow_Vet_Capabilities::CAP_EDIT_EMR ) ) {
					return array( KennelFlow_Vet_Capabilities::CAP_MANAGE_BOOKINGS );
				}
				if ( 'read_post' === $cap
					&& ( self::user_has_cap( $user_id, KennelFlow_Vet_Capabilities::CAP_VIEW_BOOKINGS )
						|| self::user_has_cap( $user_id, KennelFlow_Vet_Capabilities::CAP_READ_EMR ) ) ) {
					return array( KennelFlow_Vet_Capabilities::CAP_VIEW_BOOKINGS );
				}
			}
		}

		if ( $pet_pt === $post->post_type && self::user_has_cap( $user_id, self::CAP_EDIT_HUB_PETS ) ) {
			if ( 'delete_post' === $cap && ! self::user_has_cap( $user_id, 'manage_options' ) ) {
				return array( 'do_not_allow' );
			}
			return array( self::CAP_EDIT_HUB_PETS );
		}

		return $caps;
	}

	/**
	 * @param int    $user_id User ID.
	 * @param string $cap     Capability.
	 * @return bool
	 */
	protected static function user_has_cap( $user_id, $cap ) {
		$user = get_userdata( absint( $user_id ) );
		if ( ! $user || ! is_array( $user->allcaps ) ) {
			return false;
		}
		return ! empty( $user->allcaps[ $cap ] ) || ! empty( $user->allcaps['manage_options'] );
	}
}
