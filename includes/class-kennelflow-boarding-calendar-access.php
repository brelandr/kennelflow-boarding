<?php
/**
 * Front-end Hub calendar for boarding desk staff (kennel rows, Add booking).
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_Calendar_Access
 */
class KennelFlow_Boarding_Calendar_Access {

	/**
	 * Shortcode for a boarding-only staff calendar page.
	 */
	const SHORTCODE = 'kennelflow_boarding_calendar';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_shortcode' ) );
		add_filter( 'ltkf_hub_calendar_shortcode_tags', array( __CLASS__, 'register_shortcode_tag' ) );
		add_filter( 'ltkf_hub_calendar_shortcode_cap', array( __CLASS__, 'filter_shortcode_cap' ) );
		add_filter( 'ltkf_hub_calendar_shortcode_atts', array( __CLASS__, 'filter_shortcode_atts' ), 15 );
		add_filter( 'ltkf_admin_calendar_localized_settings', array( __CLASS__, 'filter_calendar_localized_settings' ), 12 );
		add_action( 'save_post_page', array( __CLASS__, 'maybe_flush_page_cache' ), 10, 1 );
	}

	/**
	 * Register `[kennelflow_boarding_calendar]`.
	 *
	 * @return void
	 */
	public static function register_shortcode() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render_shortcode' ) );
	}

	/**
	 * Pre-enqueue Hub calendar assets when this shortcode is on the page.
	 *
	 * @param string[] $tags Shortcode tags Core checks.
	 * @return string[]
	 */
	public static function register_shortcode_tag( $tags ) {
		if ( ! is_array( $tags ) ) {
			$tags = array();
		}
		if ( ! in_array( self::SHORTCODE, $tags, true ) ) {
			$tags[] = self::SHORTCODE;
		}
		return $tags;
	}

	/**
	 * Whether the user is boarding desk staff (not necessarily a site admin).
	 *
	 * @param int $user_id User ID (0 = current).
	 * @return bool
	 */
	public static function user_is_boarding_desk( $user_id = 0 ) {
		if ( ! class_exists( 'KennelFlow_Boarding_Capabilities' ) ) {
			return false;
		}

		if ( class_exists( 'KennelFlow_Vet_Calendar_Access' ) && KennelFlow_Vet_Calendar_Access::user_is_clinical_staff( $user_id ) ) {
			return false;
		}

		$user_id = absint( $user_id );
		if ( $user_id > 0 ) {
			if ( user_can( $user_id, 'manage_options' ) ) {
				return false;
			}
			return KennelFlow_Boarding_Capabilities::user_can_edit_bookings( $user_id )
				|| KennelFlow_Boarding_Capabilities::user_can_edit_hub_pets( $user_id );
		}

		if ( current_user_can( 'manage_options' ) ) {
			return false;
		}

		return KennelFlow_Boarding_Capabilities::user_can_edit_bookings()
			|| KennelFlow_Boarding_Capabilities::user_can_edit_hub_pets();
	}

	/**
	 * Whether the user may create bookings from the calendar modal.
	 *
	 * @param int $user_id User ID (0 = current).
	 * @return bool
	 */
	public static function user_can_create_bookings( $user_id = 0 ) {
		if ( ! class_exists( 'KennelFlow_Boarding_Capabilities' ) ) {
			return false;
		}
		return KennelFlow_Boarding_Capabilities::user_can_edit_bookings( $user_id );
	}

	/**
	 * Capability for `[ltkf_hub_calendar]` when boarding desk staff use the shared staff page.
	 *
	 * @param string $cap Default capability.
	 * @return string
	 */
	public static function filter_shortcode_cap( $cap ) {
		if ( self::user_is_boarding_desk() ) {
			return KennelFlow_Boarding_Capabilities::CAP_EDIT_BOOKINGS;
		}
		return $cap;
	}

	/**
	 * Default kennel rows on the shared staff calendar for boarding desk users.
	 *
	 * @param array<string, string> $atts Shortcode attributes.
	 * @return array<string, string>
	 */
	public static function filter_shortcode_atts( $atts ) {
		if ( ! is_array( $atts ) ) {
			$atts = array();
		}
		if ( ! self::user_is_boarding_desk() ) {
			return $atts;
		}
		if ( empty( $atts['booking_kind'] ) ) {
			$atts['booking_kind'] = 'boarding';
		}
		if ( empty( $atts['corner_label'] ) ) {
			$atts['corner_label'] = __( 'Kennel', 'kennelflow-boarding' );
		}
		return $atts;
	}

	/**
	 * Calendar script settings for boarding desk (default kind + create flag).
	 *
	 * @param array<string, mixed> $settings Localized settings.
	 * @return array<string, mixed>
	 */
	public static function filter_calendar_localized_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		if ( class_exists( 'KennelFlow_Boarding_Admin_Booking_Session_Media' ) ) {
			$settings = KennelFlow_Boarding_Admin_Booking_Session_Media::append_calendar_settings( $settings );
		}

		if ( ! self::user_can_create_bookings() ) {
			return $settings;
		}

		$settings['can_create_bookings'] = true;

		if ( self::user_is_boarding_desk() || self::is_boarding_calendar_request() ) {
			$settings['default_booking_kind'] = 'boarding';
		}

		return $settings;
	}

	/**
	 * Boarding-only staff calendar shortcode output.
	 *
	 * @param string[]|string $atts Attributes.
	 * @return string
	 */
	public static function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'corner_label' => __( 'Kennel', 'kennelflow-boarding' ),
			),
			is_array( $atts ) ? $atts : array(),
			self::SHORTCODE
		);

		$hub_atts = array(
			'booking_kind' => 'boarding',
			'corner_label' => sanitize_text_field( (string) $atts['corner_label'] ),
		);

		if ( class_exists( '\Landtech\KennelFlow\Core\FrontendHubCalendar' ) ) {
			return \Landtech\KennelFlow\Core\FrontendHubCalendar::render_shortcode( $hub_atts );
		}

		return '';
	}

	/**
	 * Published page URL containing the boarding staff calendar shortcode.
	 *
	 * @return string Permalink or empty.
	 */
	public static function get_frontend_calendar_page_url() {
		$cached = get_transient( 'kennelflow_boarding_staff_calendar_url' );
		if ( is_string( $cached ) ) {
			return $cached;
		}

		$url = self::find_frontend_calendar_page_url();
		set_transient( 'kennelflow_boarding_staff_calendar_url', $url, DAY_IN_SECONDS );

		return $url;
	}

	/**
	 * Clear cached staff calendar page URL (call when pages change).
	 *
	 * @return void
	 */
	public static function flush_frontend_calendar_page_cache() {
		delete_transient( 'kennelflow_boarding_staff_calendar_url' );
	}

	/**
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function maybe_flush_page_cache( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		self::flush_frontend_calendar_page_cache();
	}

	/**
	 * @return string
	 */
	protected static function find_frontend_calendar_page_url() {
		// phpcs:disable WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- One-time lookup for admin link.
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);
		// phpcs:enable WordPress.WP.PostsPerPage.posts_per_page_posts_per_page

		if ( ! is_array( $pages ) ) {
			return '';
		}

		foreach ( $pages as $page ) {
			if ( ! $page instanceof WP_Post ) {
				continue;
			}
			if ( has_shortcode( $page->post_content, self::SHORTCODE ) ) {
				return (string) get_permalink( $page );
			}
		}

		foreach ( $pages as $page ) {
			if ( ! $page instanceof WP_Post ) {
				continue;
			}
			if ( ! has_shortcode( $page->post_content, 'ltkf_hub_calendar' ) ) {
				continue;
			}
			if ( false !== stripos( $page->post_content, 'booking_kind="boarding"' )
				|| false !== stripos( $page->post_content, "booking_kind='boarding'" ) ) {
				return (string) get_permalink( $page );
			}
		}

		return '';
	}

	/**
	 * Whether the current front-end view is the boarding staff calendar shortcode.
	 *
	 * @return bool
	 */
	protected static function is_boarding_calendar_request() {
		if ( is_admin() || ! is_singular() ) {
			return false;
		}

		$post = get_post();
		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		if ( has_shortcode( $post->post_content, self::SHORTCODE ) ) {
			return true;
		}

		if ( has_shortcode( $post->post_content, 'ltkf_hub_calendar' )
			&& ( false !== stripos( $post->post_content, 'booking_kind="boarding"' )
				|| false !== stripos( $post->post_content, "booking_kind='boarding'" ) ) ) {
			return true;
		}

		return false;
	}
}
