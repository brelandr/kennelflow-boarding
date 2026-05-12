<?php
/**
 * Full-screen Mobile Report Card PWA (no admin chrome).
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_PWA_Report_Admin
 */
class KennelFlow_Boarding_PWA_Report_Admin {

	const PAGE_SLUG = 'kennelflow-boarding-pwa-report';

	const SCRIPT_HANDLE = 'kennelflow-boarding-pwa-report-card';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 25 );
		add_action( 'admin_menu', array( __CLASS__, 'maybe_remove_menu' ), 999 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_head', array( __CLASS__, 'strip_admin_chrome' ) );
	}

	/**
	 * User may open the PWA.
	 *
	 * @return bool
	 */
	public static function user_can_access() {
		return current_user_can( 'kennelpress_send_reports' ) || current_user_can( 'edit_posts' );
	}

	/**
	 * Submenu under KennelFlow_Boarding Front Desk.
	 *
	 * Uses `read` so the item exists before maybe_remove_menu; only users passing user_can_access() keep it.
	 *
	 * @return void
	 */
	public static function register_menu() {
		if ( ! function_exists( 'ltkf_get_pet_post_type' ) ) {
			return;
		}

		$parent = function_exists( 'kennelpress_get_front_desk_menu_slug' ) ? kennelpress_get_front_desk_menu_slug() : ( function_exists( 'ltkf_get_hub_menu_slug' ) ? ltkf_get_hub_menu_slug() : 'edit.php?post_type=' . ltkf_get_pet_post_type() );
		add_submenu_page(
			$parent,
			__( 'Mobile Report Card', 'kennelflow-boarding' ),
			__( 'Mobile Report Card', 'kennelflow-boarding' ),
			'read',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Hide the submenu for users who may not send report cards.
	 *
	 * @return void
	 */
	public static function maybe_remove_menu() {
		if ( ! function_exists( 'ltkf_get_pet_post_type' ) ) {
			return;
		}
		if ( self::user_can_access() ) {
			return;
		}
		$parent = function_exists( 'kennelpress_get_front_desk_menu_slug' ) ? kennelpress_get_front_desk_menu_slug() : ( function_exists( 'ltkf_get_hub_menu_slug' ) ? ltkf_get_hub_menu_slug() : 'edit.php?post_type=' . ltkf_get_pet_post_type() );
		remove_submenu_page(
			$parent,
			self::PAGE_SLUG
		);
	}

	/**
	 * REST + nonce + today range for the report card bundle (admin + public PWA).
	 *
	 * @return array{restUrl:string,nonce:string,todayStartGmt:string,todayEndGmt:string}
	 */
	public static function get_pwa_boot_data() {
		list( $start_gmt, $end_gmt ) = self::today_range_gmt();
		return array(
			'restUrl'       => esc_url_raw( rest_url() ),
			'nonce'         => wp_create_nonce( 'wp_rest' ),
			'todayStartGmt' => $start_gmt,
			'todayEndGmt'   => $end_gmt,
		);
	}

	/**
	 * Today’s UTC range for bookings list (site “today” in local TZ → GMT strings).
	 *
	 * @return array{0:string,1:string} start_gmt, end_gmt
	 */
	public static function today_range_gmt() {
		try {
			$tz    = wp_timezone();
			$start = new DateTimeImmutable( 'today', $tz );
			$end   = $start->modify( '+1 day' )->modify( '-1 second' );
			$utc   = new DateTimeZone( 'UTC' );
			return array(
				$start->setTimezone( $utc )->format( 'Y-m-d H:i:s' ),
				$end->setTimezone( $utc )->format( 'Y-m-d H:i:s' ),
			);
		} catch ( Exception $e ) {
			unset( $e );
			$ts = time();
			return array(
				gmdate( 'Y-m-d 00:00:00', $ts ),
				gmdate( 'Y-m-d 23:59:59', $ts ),
			);
		}
	}

	/**
	 * Enqueue React bundle on our screen only.
	 *
	 * @param string $hook_suffix Hook.
	 * @return void
	 */
	public static function enqueue( $hook_suffix ) {
		$expected = '';
		if ( function_exists( 'kennelpress_get_front_desk_page_hook_suffix' ) ) {
			$expected = kennelpress_get_front_desk_page_hook_suffix( self::PAGE_SLUG );
		} elseif ( function_exists( 'ltkf_get_hub_page_hook_suffix' ) ) {
			$expected = ltkf_get_hub_page_hook_suffix( self::PAGE_SLUG );
		} elseif ( function_exists( 'ltkf_get_pet_post_type' ) ) {
			$expected = ltkf_get_pet_post_type() . '_page_' . self::PAGE_SLUG;
		}
		if ( '' === $expected || $expected !== $hook_suffix ) {
			return;
		}

		if ( ! self::user_can_access() ) {
			return;
		}

		$path = KENNELFLOW_BOARDING_PLUGIN_DIR . 'build/pwa-report-card.js';
		if ( ! is_readable( $path ) ) {
			return;
		}

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			KENNELFLOW_BOARDING_PLUGIN_URL . 'build/pwa-report-card.js',
			array(),
			KENNELFLOW_BOARDING_VERSION,
			true
		);

		wp_localize_script(
			self::SCRIPT_HANDLE,
			'kennelflowBoardingPwaReport',
			self::get_pwa_boot_data()
		);

		wp_add_inline_script(
			self::SCRIPT_HANDLE,
			'document.documentElement.classList.add("kennelflow-boarding-pwa-report-active");',
			'before'
		);
	}

	/**
	 * Hide wp-admin menu bar and sidebar on this screen.
	 *
	 * @return void
	 */
	public static function strip_admin_chrome() {
		$screen      = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$expected_id = '';
		if ( function_exists( 'kennelpress_get_front_desk_page_hook_suffix' ) ) {
			$expected_id = kennelpress_get_front_desk_page_hook_suffix( self::PAGE_SLUG );
		} elseif ( function_exists( 'ltkf_get_hub_page_hook_suffix' ) ) {
			$expected_id = ltkf_get_hub_page_hook_suffix( self::PAGE_SLUG );
		} elseif ( function_exists( 'ltkf_get_pet_post_type' ) ) {
			$expected_id = ltkf_get_pet_post_type() . '_page_' . self::PAGE_SLUG;
		}
		if ( ! $screen || '' === $expected_id || $expected_id !== $screen->id ) {
			return;
		}
		if ( ! self::user_can_access() ) {
			return;
		}
		?>
		<style id="kennelflow-boarding-pwa-report-chrome-hide">
			#wpadminbar, #adminmenumain, #adminmenuback, #adminmenuwrap { display: none !important; }
			html.kennelflow-boarding-pwa-report-active { margin-top: 0 !important; }
			#wpcontent { margin-left: 0 !important; padding-left: 0 !important; }
			#wpbody-content { padding-bottom: 0; }
			#wpfooter { display: none !important; }
			.kpr-root { min-height: calc(100vh - 32px); }
			@media screen and (max-width: 782px) {
				.kpr-root { min-height: 100vh; }
			}
		</style>
		<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
		<?php
	}

	/**
	 * Render root only (React mounts here).
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! self::user_can_access() ) {
			wp_die( esc_html__( 'You do not have permission to access this screen.', 'kennelflow-boarding' ) );
		}

		$path = KENNELFLOW_BOARDING_PLUGIN_DIR . 'build/pwa-report-card.js';
		if ( ! is_readable( $path ) ) {
			echo '<div class="wrap"><p>';
			esc_html_e( 'The Mobile Report Card bundle is missing. Run npm run build:pwa in the Kennel Press plugin directory.', 'kennelflow-boarding' );
			echo '</p></div>';
			return;
		}

		echo '<div id="kf-pwa-root"></div>';
	}
}
