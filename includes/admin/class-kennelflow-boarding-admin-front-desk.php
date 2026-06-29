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
		add_action( 'admin_menu', array( __CLASS__, 'register_pets_submenu' ), 15 );
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
	 * Hub pets under Front Desk so boarding staff do not need the KennelFlow admin menu.
	 *
	 * @return void
	 */
	public static function register_pets_submenu() {
		if ( ! function_exists( 'ltkf_get_pet_post_type' ) ) {
			return;
		}

		$cap = class_exists( 'KennelFlow_Boarding_Capabilities' )
			? KennelFlow_Boarding_Capabilities::CAP_EDIT_HUB_PETS
			: 'edit_posts';

		add_submenu_page(
			kennelpress_get_front_desk_menu_slug(),
			__( 'Pets', 'kennelflow-boarding' ),
			__( 'Pets', 'kennelflow-boarding' ),
			$cap,
			'edit.php?post_type=' . ltkf_get_pet_post_type()
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
		$calendar_url = admin_url( 'admin.php?page=kennelflow-boarding-calendar' );
		$staff_calendar_url = class_exists( 'KennelFlow_Boarding_Calendar_Access' )
			? KennelFlow_Boarding_Calendar_Access::get_frontend_calendar_page_url()
			: '';
		$pets_url     = function_exists( 'ltkf_get_pet_post_type' )
			? admin_url( 'edit.php?post_type=' . ltkf_get_pet_post_type() )
			: '';
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Boarding bookings, kennel calendar, and front-desk tools live here.', 'kennelflow-boarding' ); ?>
			</p>
			<?php KennelFlow_Boarding_Admin_Booking_Actions::render_pending_dashboard_table(); ?>
			<h2><?php esc_html_e( 'Quick links', 'kennelflow-boarding' ); ?></h2>
			<ul class="ul-disc">
				<li>
					<a href="<?php echo esc_url( $bookings_url ); ?>"><?php esc_html_e( 'Kennel bookings', 'kennelflow-boarding' ); ?></a>
				</li>
				<?php if ( '' !== $pets_url ) : ?>
					<li>
						<a href="<?php echo esc_url( $pets_url ); ?>"><?php esc_html_e( 'Pets', 'kennelflow-boarding' ); ?></a>
					</li>
				<?php endif; ?>
				<li>
					<a href="<?php echo esc_url( $calendar_url ); ?>"><?php esc_html_e( 'Kennel calendar (wp-admin)', 'kennelflow-boarding' ); ?></a>
				</li>
				<?php if ( '' !== $staff_calendar_url ) : ?>
					<li>
						<a href="<?php echo esc_url( $staff_calendar_url ); ?>"><?php esc_html_e( 'Boarding staff calendar (front-end)', 'kennelflow-boarding' ); ?></a>
					</li>
				<?php elseif ( current_user_can( 'manage_options' ) ) : ?>
					<li>
						<?php
						esc_html_e(
							'Front-end boarding calendar: create a page with shortcode [kennelflow_boarding_calendar] (requires KennelFlow Core staff calendar).',
							'kennelflow-boarding'
						);
						?>
					</li>
				<?php endif; ?>
			</ul>
		</div>
		<?php
	}
}
