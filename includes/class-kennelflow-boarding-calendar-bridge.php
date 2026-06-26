<?php
/**
 * Pass Kennel Press REST URLs into KennelFlow Core admin calendar script.
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_Calendar_Bridge
 */
class KennelFlow_Boarding_Calendar_Bridge {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'ltkf_admin_calendar_localized_settings', array( __CLASS__, 'filter_calendar_settings' ) );
		// After GroomPress (priority 10) so booking_kind=grooming keeps groomer rows.
		add_filter( 'ltkf_rest_calendar_resources', array( __CLASS__, 'filter_calendar_resources' ), 20, 3 );
	}

	/**
	 * Add Kennel Press booking + pet care REST bases for the React calendar modal.
	 *
	 * @param array<string, mixed> $settings Localized settings.
	 * @return array<string, mixed>
	 */
	public static function filter_calendar_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		$settings['kennelpress_bookings_url']          = esc_url_raw( rest_url( 'kennelflow-boarding/v1/bookings' ) );
		$settings['kennelflow_boarding_rest_base']     = 'kennelflow-boarding/v1';
		$settings['bookings_create_path']              = '/kennelflow/v1/bookings';
		$settings['kennelpress_print_run_card_nonce']  = wp_create_nonce( 'kennelpress_print_run_card' );
		return $settings;
	}

	/**
	 * Y-axis rows for Hub calendar: kennels and/or staff by booking_kind filter.
	 *
	 * GroomPress owns rows when booking_kind=grooming.
	 *
	 * @param null|array[]    $resources Prior filter value.
	 * @param array[]         $bookings  Normalized bookings.
	 * @param WP_REST_Request $request   Request.
	 * @return null|array[]
	 */
	public static function filter_calendar_resources( $resources, $bookings, $request ) {
		if ( ! $request instanceof WP_REST_Request ) {
			return $resources;
		}

		$kind = sanitize_key( (string) $request->get_param( 'booking_kind' ) );
		if ( 'grooming' === $kind ) {
			return $resources;
		}

		$by_id = array();
		if ( is_array( $resources ) ) {
			foreach ( $resources as $row ) {
				if ( ! is_array( $row ) || ! isset( $row['id'] ) ) {
					continue;
				}
				$id            = absint( $row['id'] );
				$by_id[ $id ] = array(
					'id'    => $id,
					'title' => isset( $row['title'] ) ? (string) $row['title'] : '',
				);
			}
		}

		if ( '' === $kind || 'boarding' === $kind ) {
			self::collect_kennel_resources( $by_id );
		}

		if ( '' === $kind || 'clinic' === $kind ) {
			self::collect_user_role_resources(
				$by_id,
				KennelFlow_Boarding_Calendar_Resources::default_clinic_user_roles()
			);
			self::collect_user_role_resources( $by_id, array( 'veterinarian' ) );
		}

		if ( '' === $kind ) {
			self::collect_user_role_resources(
				$by_id,
				KennelFlow_Boarding_Calendar_Resources::default_grooming_resource_roles()
			);
		}

		foreach ( (array) $bookings as $booking ) {
			if ( ! is_array( $booking ) ) {
				continue;
			}
			$resource_id = isset( $booking['resource_id'] ) ? absint( $booking['resource_id'] ) : 0;
			if ( $resource_id < 1 || isset( $by_id[ $resource_id ] ) ) {
				continue;
			}
			$by_id[ $resource_id ] = array(
				'id'    => $resource_id,
				'title' => self::resource_title_for_id( $resource_id ),
			);
		}

		if ( empty( $by_id ) ) {
			return null;
		}

		$out = array_values( $by_id );
		usort(
			$out,
			static function ( $a, $b ) {
				return strcasecmp( (string) $a['title'], (string) $b['title'] );
			}
		);

		return $out;
	}

	/**
	 * Published kennel/run posts as calendar resources.
	 *
	 * @param array<int, array{id:int, title:string}> $by_id Resource map keyed by ID.
	 * @return void
	 */
	protected static function collect_kennel_resources( array &$by_id ) {
		// phpcs:disable WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Admin calendar resource list.
		$kennel_ids = get_posts(
			array(
				'post_type'      => 'kennelpress_kennel',
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'fields'         => 'ids',
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		// phpcs:enable WordPress.WP.PostsPerPage.posts_per_page_posts_per_page

		if ( ! is_array( $kennel_ids ) ) {
			return;
		}

		foreach ( $kennel_ids as $kennel_id ) {
			$kennel_id = absint( $kennel_id );
			if ( $kennel_id < 1 ) {
				continue;
			}
			$by_id[ $kennel_id ] = array(
				'id'    => $kennel_id,
				'title' => get_the_title( $kennel_id ),
			);
		}
	}

	/**
	 * WordPress users with any of the given roles as calendar resources.
	 *
	 * @param array<int, array{id:int, title:string}> $by_id Resource map keyed by ID.
	 * @param string[]                                $roles Role slugs.
	 * @return void
	 */
	protected static function collect_user_role_resources( array &$by_id, array $roles ) {
		$roles = array_filter( array_map( 'sanitize_key', $roles ) );
		if ( empty( $roles ) ) {
			return;
		}

		$user_query = new WP_User_Query(
			array(
				'role__in' => $roles,
				'orderby'  => 'display_name',
				'order'    => 'ASC',
				'fields'   => 'all',
			)
		);

		$users = $user_query->get_results();
		if ( ! is_array( $users ) ) {
			return;
		}

		foreach ( $users as $user ) {
			if ( ! $user instanceof WP_User ) {
				continue;
			}
			$user_id = (int) $user->ID;
			if ( $user_id < 1 ) {
				continue;
			}
			$by_id[ $user_id ] = array(
				'id'    => $user_id,
				'title' => $user->display_name,
			);
		}
	}

	/**
	 * Resolve a resource label from a kennel post ID or WordPress user ID.
	 *
	 * @param int $resource_id Kennel post ID or user ID.
	 * @return string
	 */
	protected static function resource_title_for_id( $resource_id ) {
		$resource_id = absint( $resource_id );
		if ( $resource_id < 1 ) {
			return '';
		}

		$kennel = get_post( $resource_id );
		if ( $kennel instanceof WP_Post && 'kennelpress_kennel' === $kennel->post_type ) {
			return get_the_title( $kennel );
		}

		$user = get_userdata( $resource_id );
		if ( $user instanceof WP_User ) {
			return $user->display_name;
		}

		return sprintf(
			/* translators: %d: resource id */
			__( 'Resource %d', 'kennelflow-boarding' ),
			$resource_id
		);
	}
}
