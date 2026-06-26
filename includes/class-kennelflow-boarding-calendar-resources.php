<?php
/**
 * Calendar Y-axis resource helpers (no REST controller dependency).
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_Calendar_Resources
 */
class KennelFlow_Boarding_Calendar_Resources {

	/**
	 * Default WordPress roles that may appear as grooming resources.
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
	 * Default WordPress roles that may appear as clinical providers.
	 *
	 * @return string[]
	 */
	public static function default_clinic_user_roles() {
		$roles = array( 'administrator' );
		if ( class_exists( 'KennelFlow_Vet_Roles' ) ) {
			$roles[] = KennelFlow_Vet_Roles::ROLE_PROVIDER;
		}
		return array_unique( array_filter( array_map( 'sanitize_key', $roles ) ) );
	}
}
