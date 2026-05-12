<?php
/**
 * KennelFlow_Boarding Front Desk: top-level admin menu (calendar, bookings, boarding tools).
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_Admin_Front_Desk
 */
class KennelFlow_Boarding_Admin_Front_Desk {

	/**
	 * Capability for viewing the Front Desk menu (default: staff who can edit content).
	 *
	 * @return string
	 */
	public static function required_cap() {
		return (string) kennelflow_boarding_apply_filters( 'front_desk_menu_capability', 'edit_posts' );
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 5 );
	}

	/**
	 * Register top-level menu before Front Desk submenus attach.
	 *
	 * @return void
	 */
	public static function register_menu() {
		add_menu_page(
			__( 'KennelPress Front Desk', 'kennelflow-boarding' ),
			__( 'KennelPress', 'kennelflow-boarding' ),
			self::required_cap(),
			kennelpress_get_front_desk_menu_slug(),
			array( __CLASS__, 'render_page' ),
			'dashicons-calendar-alt',
			55
		);
	}

	/**
	 * Front Desk landing screen.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( self::required_cap() ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'kennelflow-boarding' ) );
		}

		$bookings_url = admin_url( 'edit.php?post_type=kennelpress_booking' );
		$calendar_url = admin_url( 'admin.php?page=kennelpress-calendar' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Boarding bookings, kennel calendar, and front-desk tools live here.', 'kennelflow-boarding' ); ?>
			</p>
			<ul class="ul-disc">
				<li>
					<a href="<?php echo esc_url( $bookings_url ); ?>"><?php esc_html_e( 'Kennel bookings', 'kennelflow-boarding' ); ?></a>
				</li>
				<li>
					<a href="<?php echo esc_url( $calendar_url ); ?>"><?php esc_html_e( 'Kennel calendar', 'kennelflow-boarding' ); ?></a>
				</li>
			</ul>
		</div>
		<?php
	}
}
