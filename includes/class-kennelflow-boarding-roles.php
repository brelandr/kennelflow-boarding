<?php
/**
 * WordPress roles: pet owner (linked to pets).
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_Roles
 */
class KennelFlow_Boarding_Roles {

	/**
	 * Role slug for pet owners (shared convention with Vet Press).
	 */
	const ROLE_PET_OWNER = 'pet_owner';

	/**
	 * Grooming schedule / intake (calendar resource).
	 */
	const ROLE_GROOMER = 'groomer';

	/**
	 * Register roles on init / activation.
	 *
	 * @return void
	 */
	public static function register_roles() {
		self::register_report_card_capability();

		if ( ! get_role( self::ROLE_PET_OWNER ) ) {
			add_role(
				self::ROLE_PET_OWNER,
				__( 'Pet Owner', 'kennelflow-boarding' ),
				array(
					'read' => true,
				)
			);
		}

		if ( ! get_role( self::ROLE_GROOMER ) ) {
			add_role(
				self::ROLE_GROOMER,
				__( 'Groomer', 'kennelflow-boarding' ),
				array(
					'read' => true,
				)
			);
		}
	}

	/**
	 * Grant kennelpress_send_reports to staff roles that should use the mobile report card API.
	 *
	 * @return void
	 */
	protected static function register_report_card_capability() {
		$roles = array( 'administrator', 'editor', self::ROLE_GROOMER );

		/**
		 * Filters which roles receive the kennelpress_send_reports capability.
		 *
		 * @since 0.2.0
		 *
		 * @param string[] $roles Role slugs.
		 */
		$roles = kennelflow_boarding_apply_filters( 'roles_with_send_reports_cap', $roles );

		foreach ( (array) $roles as $slug ) {
			$slug = sanitize_key( (string) $slug );
			if ( '' === $slug ) {
				continue;
			}
			$role = get_role( $slug );
			if ( $role && ! $role->has_cap( 'kennelpress_send_reports' ) ) {
				$role->add_cap( 'kennelpress_send_reports' );
			}
		}
	}

	/**
	 * Whether a user has the pet owner role.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function user_is_pet_owner( $user_id ) {
		$user_id = absint( $user_id );
		if ( $user_id < 1 ) {
			return false;
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}
		return in_array( self::ROLE_PET_OWNER, (array) $user->roles, true );
	}

	/**
	 * Sanitize owner user ID for post meta (must be pet owner or empty).
	 *
	 * @param mixed $value Meta value.
	 * @return int
	 */
	public static function sanitize_pet_owner_user_id( $value ) {
		$id = absint( $value );
		if ( $id < 1 ) {
			return 0;
		}
		if ( ! self::user_is_pet_owner( $id ) ) {
			return 0;
		}
		return $id;
	}

	/**
	 * Users with the pet owner role for dropdowns (plus optional include ID).
	 *
	 * @param int $include_user_id Always include this user if set (e.g. legacy assignment).
	 * @return \WP_User[]
	 */
	public static function get_pet_owner_users_for_dropdown( $include_user_id = 0 ) {
		$include_user_id = absint( $include_user_id );
		$users           = get_users(
			array(
				'role'    => self::ROLE_PET_OWNER,
				'orderby' => 'display_name',
				'order'   => 'ASC',
			)
		);

		if ( $include_user_id > 0 ) {
			$ids = wp_list_pluck( $users, 'ID' );
			if ( ! in_array( $include_user_id, $ids, true ) ) {
				$extra = get_userdata( $include_user_id );
				if ( $extra ) {
					array_unshift( $users, $extra );
				}
			}
		}

		return $users;
	}
}
