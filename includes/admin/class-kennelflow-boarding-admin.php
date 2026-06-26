<?php
/**
 * Admin meta boxes and list tables.
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_Admin
 */
class KennelFlow_Boarding_Admin {

	/**
	 * Hook admin UI.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_booking_edit_screen_scripts' ), 20 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_boxes' ) );
		add_action( 'save_post', array( __CLASS__, 'save_post' ), 10, 2 );
		add_action( 'save_post_kennelpress_booking', array( __CLASS__, 'maybe_fire_booking_saved_action' ), 20, 3 );

		add_filter( 'manage_kennelpress_booking_posts_columns', array( __CLASS__, 'booking_columns' ) );
		add_action( 'manage_kennelpress_booking_posts_custom_column', array( __CLASS__, 'booking_custom_column' ), 10, 2 );
	}

	/**
	 * Admin scripts (localized data for AJAX; matches KennelFlow_Boarding_Ajax::HUB_PING_NONCE_ACTION).
	 *
	 * @param string $hook_suffix Current admin screen ID.
	 * @return void
	 */
	public static function enqueue_admin_scripts( $hook_suffix ) {
		if ( self::is_facility_settings_screen( $hook_suffix ) ) {
			$fs_js  = KENNELFLOW_BOARDING_PLUGIN_DIR . 'assets/dist/facility-settings.js';
			$fs_css = KENNELFLOW_BOARDING_PLUGIN_DIR . 'assets/dist/facility-settings.css';
			if ( is_readable( $fs_js ) ) {
				wp_enqueue_script(
					'kennelflow-boarding-facility-settings',
					KENNELFLOW_BOARDING_PLUGIN_URL . 'assets/dist/facility-settings.js',
					array(),
					KENNELFLOW_BOARDING_VERSION,
					true
				);
				wp_localize_script(
					'kennelflow-boarding-facility-settings',
					'kennelflowBoardingFacilitySettings',
					array(
						'restUrl'           => esc_url_raw( rest_url( 'kennelflow-boarding/v1/facility-settings' ) ),
						'nonce'             => wp_create_nonce( 'wp_rest' ),
						'canEdit'           => current_user_can( 'edit_posts' ) || current_user_can( 'manage_options' ),
						'locationsAdminUrl' => admin_url( 'edit.php?post_type=' . ( function_exists( 'ltkf_get_location_post_type' ) ? ltkf_get_location_post_type() : 'kf_location' ) ),
					)
				);
			}
			if ( is_readable( $fs_css ) ) {
				wp_enqueue_style(
					'kennelflow-boarding-facility-settings',
					KENNELFLOW_BOARDING_PLUGIN_URL . 'assets/dist/facility-settings.css',
					array(),
					KENNELFLOW_BOARDING_VERSION
				);
			}
		}

		if ( false !== strpos( (string) $hook_suffix, 'kennelflow-boarding-calendar' ) ) {
			wp_enqueue_script(
				'kennelflow-boarding-admin-calendar',
				KENNELFLOW_BOARDING_PLUGIN_URL . 'assets/js/admin-calendar.js',
				array(),
				KENNELFLOW_BOARDING_VERSION,
				true
			);
			wp_localize_script(
				'kennelflow-boarding-admin-calendar',
				'kennelflowBoardingCalendar',
				array(
					'restUrl' => esc_url_raw( rest_url( 'kennelflow-boarding/v1/bookings' ) ),
					'nonce'   => wp_create_nonce( 'wp_rest' ),
				)
			);
		}

		wp_enqueue_script(
			'kennelflow-boarding-admin-ajax',
			KENNELFLOW_BOARDING_PLUGIN_URL . 'assets/js/admin-ajax.js',
			array(),
			KENNELFLOW_BOARDING_VERSION,
			true
		);

		wp_localize_script(
			'kennelflow-boarding-admin-ajax',
			'kennelflowBoardingAjax',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( KennelFlow_Boarding_Ajax::HUB_PING_NONCE_ACTION ),
				'nonceAction' => KennelFlow_Boarding_Ajax::HUB_PING_NONCE_ACTION,
				'action'      => 'kennelflow_boarding_hub_ping',
			)
		);
	}

	/**
	 * Keep meta boxes expanded on the booking post editor (classic + block editor containers).
	 *
	 * @param string $hook_suffix Current admin screen hook.
	 * @return void
	 */
	public static function enqueue_booking_edit_screen_scripts( $hook_suffix ) {
		if ( ! in_array( (string) $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'kennelpress_booking' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_script(
			'kennelflow-boarding-admin-booking-postboxes',
			KENNELFLOW_BOARDING_PLUGIN_URL . 'assets/js/admin-booking-open-postboxes.js',
			array( 'jquery' ),
			KENNELFLOW_BOARDING_VERSION,
			true
		);
	}

	/**
	 * Submenu: REST-fed booking calendar (under Front Desk).
	 *
	 * @return void
	 */
	public static function register_calendar_page() {
		$parent = function_exists( 'kennelpress_get_front_desk_menu_slug' ) ? kennelpress_get_front_desk_menu_slug() : ( function_exists( 'ltkf_get_hub_menu_slug' ) ? ltkf_get_hub_menu_slug() : 'edit.php?post_type=kf_pet' );
		add_submenu_page(
			$parent,
			__( 'Kennel calendar', 'kennelflow-boarding' ),
			__( 'Kennel calendar', 'kennelflow-boarding' ),
			'edit_posts',
			'kennelflow-boarding-calendar',
			array( __CLASS__, 'render_calendar_page' )
		);
	}

	/**
	 * Whether the current admin screen is Kennel rules (facility settings).
	 *
	 * @param string $hook_suffix Current screen hook.
	 * @return bool
	 */
	protected static function is_facility_settings_screen( $hook_suffix ) {
		if ( '' !== (string) $hook_suffix && false !== strpos( (string) $hook_suffix, 'kennelflow-boarding-facility-settings' ) ) {
			return true;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && isset( $screen->id ) && false !== strpos( (string) $screen->id, 'kennelflow-boarding-facility-settings' ) ) {
			return true;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Screen detection for enqueue only.
		if ( isset( $_GET['page'] ) ) {
			$page = sanitize_key( wp_unslash( $_GET['page'] ) );
			if ( 'kennelflow-boarding-facility-settings' === $page ) {
				return true;
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		return false;
	}

	/**
	 * Submenu: per-location hours, holidays, blackouts (React).
	 *
	 * @return void
	 */
	public static function register_facility_settings_page() {
		$parent = function_exists( 'ltkf_get_hub_menu_slug' ) ? ltkf_get_hub_menu_slug() : 'edit.php?post_type=kf_pet';
		add_submenu_page(
			$parent,
			__( 'Kennel rules', 'kennelflow-boarding' ),
			__( 'Kennel rules', 'kennelflow-boarding' ),
			'edit_posts',
			'kennelflow-boarding-facility-settings',
			array( __CLASS__, 'render_facility_settings_page' )
		);
	}

	/**
	 * Kennel rules screen (hours, holidays, blackouts per location).
	 *
	 * @return void
	 */
	public static function render_facility_settings_page() {
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view kennel rules.', 'kennelflow-boarding' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Kennel rules', 'kennelflow-boarding' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Configure operating hours, holiday closures, and blackout windows per location. Rules apply when “enforce rules” is on. Drop-off and pick-up times are checked in the location timezone.', 'kennelflow-boarding' ); ?>
			</p>
			<?php if ( ! is_readable( KENNELFLOW_BOARDING_PLUGIN_DIR . 'assets/dist/facility-settings.js' ) ) : ?>
				<div class="notice notice-warning"><p>
					<?php esc_html_e( 'Kennel rules UI assets are missing. From the kennelflow-boarding plugin folder run: npm install && npm run build:facility', 'kennelflow-boarding' ); ?>
				</p></div>
			<?php endif; ?>
			<div id="kennelflow-boarding-facility-settings-root" class="kennelflow-boarding-facility-settings-root" aria-live="polite"></div>
		</div>
		<?php
	}

	/**
	 * Runs late on admin_menu after core Kennel Press submenus are added.
	 *
	 * @return void
	 */
	public static function on_admin_menus_registered() {
		/**
		 * Fires after Kennel Press registers admin submenus (Front Desk: calendar; KennelFlow Hub: kennel rules).
		 * Add-ons may hook here (priority 999) to append submenu pages.
		 *
		 * @since 0.1.0
		 */
		kennelflow_boarding_do_action( 'admin_menus_registered' );
	}

	/**
	 * Calendar screen markup (data loaded via REST in admin-calendar.js).
	 *
	 * @return void
	 */
	public static function render_calendar_page() {
		$month_start   = gmdate( 'Y-m-01 00:00:00' );
		$default_start = $month_start;
		$default_end   = gmdate( 'Y-m-d H:i:s', strtotime( $month_start . ' +1 month' ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Kennel booking calendar', 'kennelflow-boarding' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Loads bookings whose interval overlaps the selected UTC range.', 'kennelflow-boarding' ); ?></p>
			<p>
				<label for="kennelflow-boarding-cal-start"><?php esc_html_e( 'Start (UTC)', 'kennelflow-boarding' ); ?></label>
				<input type="text" id="kennelflow-boarding-cal-start" class="regular-text" value="<?php echo esc_attr( $default_start ); ?>" autocomplete="off" />
				<label for="kennelflow-boarding-cal-end"><?php esc_html_e( 'End (UTC)', 'kennelflow-boarding' ); ?></label>
				<input type="text" id="kennelflow-boarding-cal-end" class="regular-text" value="<?php echo esc_attr( $default_end ); ?>" autocomplete="off" />
				<button type="button" class="button button-primary" id="kennelflow-boarding-cal-load"><?php esc_html_e( 'Load', 'kennelflow-boarding' ); ?></button>
			</p>
			<div id="kennelflow-boarding-calendar-root" aria-live="polite"></div>
		</div>
		<?php
	}

	/**
	 * Register meta boxes.
	 *
	 * @return void
	 */
	public static function register_meta_boxes() {
		add_meta_box(
			'kennelpress_kennel_details',
			__( 'Kennel details', 'kennelflow-boarding' ),
			array( __CLASS__, 'render_kennel_details_box' ),
			'kennelpress_kennel',
			'side',
			'default'
		);

		add_meta_box(
			'kennelpress_booking_details',
			__( 'Booking details', 'kennelflow-boarding' ),
			array( __CLASS__, 'render_booking_details_box' ),
			'kennelpress_booking',
			'normal',
			'high'
		);
	}

	/**
	 * Kennel: Hub location, resource type (exam vs boarding, etc.), capacity.
	 *
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public static function render_kennel_details_box( $post ) {
		wp_nonce_field( 'kennelpress_save_kennel_meta', 'kennelpress_kennel_meta_nonce', true );
		$cap = (int) get_post_meta( $post->ID, KennelFlow_Boarding_Post_Meta::KENNEL_CAPACITY, true );
		if ( $cap < 1 ) {
			$cap = 1;
		}
		$loc_id = (int) get_post_meta( $post->ID, KennelFlow_Boarding_Post_Meta::KENNEL_LOCATION_ID, true );
		$loc_pt = function_exists( 'ltkf_get_location_post_type' ) ? ltkf_get_location_post_type() : 'kf_location';
		$locs   = post_type_exists( $loc_pt ) ? self::get_posts_options( $loc_pt ) : array();

		if ( 1 === count( $locs ) && $loc_id < 1 ) {
			$loc_id = (int) array_key_first( $locs );
		}

		$rtype_raw = (string) get_post_meta( $post->ID, KennelFlow_Boarding_Post_Meta::KENNEL_RESOURCE_TYPE, true );
		$rtype     = KennelFlow_Boarding_Post_Meta::sanitize_kennel_resource_type( $rtype_raw );

		$resource_options = array(
			''                 => __( '— Default (unspecified) —', 'kennelflow-boarding' ),
			'boarding'         => __( 'Boarding run', 'kennelflow-boarding' ),
			'exam'             => __( 'Exam room', 'kennelflow-boarding' ),
			'grooming_station' => __( 'Grooming station', 'kennelflow-boarding' ),
			'general'          => __( 'General / multi-use', 'kennelflow-boarding' ),
		);
		?>
		<p>
			<label for="kennelpress_location_id"><?php esc_html_e( 'Location (Hub)', 'kennelflow-boarding' ); ?></label>
		</p>
		<select name="kennelpress_location_id" id="kennelpress_location_id" class="widefat">
			<option value="0"><?php esc_html_e( '— Select location —', 'kennelflow-boarding' ); ?></option>
			<?php foreach ( $locs as $lid => $label ) : ?>
				<option value="<?php echo esc_attr( (string) $lid ); ?>" <?php selected( $loc_id, $lid ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<p class="description">
			<?php esc_html_e( 'Physical sites are KennelFlow “Locations” posts. Create them under KennelFlow → Locations.', 'kennelflow-boarding' ); ?>
		</p>
		<p>
			<label for="kennelpress_resource_type"><?php esc_html_e( 'Resource type', 'kennelflow-boarding' ); ?></label>
		</p>
		<select name="kennelpress_resource_type" id="kennelpress_resource_type" class="widefat">
			<?php foreach ( $resource_options as $val => $label ) : ?>
				<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $rtype, $val ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<p class="description">
			<?php esc_html_e( 'Used for Omni-Booking: clinic exam rooms, grooming stations, and boarding runs. “Exam room” appears in the clinic intake resource list when tied to this location.', 'kennelflow-boarding' ); ?>
		</p>
		<p>
			<label for="kennelpress_capacity"><?php esc_html_e( 'Capacity (animals)', 'kennelflow-boarding' ); ?></label>
			<input type="number" min="1" class="small-text" id="kennelpress_capacity" name="kennelpress_capacity" value="<?php echo esc_attr( (string) $cap ); ?>" />
		</p>
		<?php
	}

	/**
	 * Booking: pet, kennel, interval, status.
	 *
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public static function render_booking_details_box( $post ) {
		wp_nonce_field( 'kennelpress_save_booking_meta', 'kennelpress_booking_meta_nonce', true );

		$pet_id    = (int) get_post_meta( $post->ID, KennelFlow_Boarding_Post_Meta::BOOKING_PET_ID, true );
		$kennel_id = (int) get_post_meta( $post->ID, KennelFlow_Boarding_Post_Meta::BOOKING_KENNEL_ID, true );
		$start     = (string) get_post_meta( $post->ID, KennelFlow_Boarding_Post_Meta::BOOKING_START_GMT, true );
		$end       = (string) get_post_meta( $post->ID, KennelFlow_Boarding_Post_Meta::BOOKING_END_GMT, true );
		$status    = (string) get_post_meta( $post->ID, KennelFlow_Boarding_Post_Meta::BOOKING_STATUS, true );
		if ( '' === $status ) {
			$status = 'pending';
		}

		$pets    = self::get_posts_options( 'kf_pet' );
		$kennels = self::get_kennel_options();

		$loc_pt              = function_exists( 'ltkf_get_location_post_type' ) ? ltkf_get_location_post_type() : 'kf_location';
		$location_options    = post_type_exists( $loc_pt ) ? self::get_posts_options( $loc_pt ) : array();
		$booking_location_id = (int) get_post_meta( $post->ID, KennelFlow_Boarding_Post_Meta::BOOKING_LOCATION_ID, true );
		if ( 1 === count( $location_options ) && $booking_location_id < 1 ) {
			$booking_location_id = (int) array_key_first( $location_options );
		}
		if ( 1 === count( $kennels ) && $kennel_id < 1 ) {
			$kennel_id = (int) array_key_first( $kennels );
		}

		$statuses = array(
			'pending'    => __( 'Pending', 'kennelflow-boarding' ),
			'confirmed'  => __( 'Confirmed', 'kennelflow-boarding' ),
			'checked_in' => __( 'Checked in', 'kennelflow-boarding' ),
			'completed'  => __( 'Completed', 'kennelflow-boarding' ),
			'cancelled'  => __( 'Cancelled', 'kennelflow-boarding' ),
			'expired'    => __( 'Expired', 'kennelflow-boarding' ),
		);
		?>
		<table class="form-table"><tbody>
		<tr>
			<th scope="row"><label for="kennelpress_booking_pet_id"><?php esc_html_e( 'Pet', 'kennelflow-boarding' ); ?></label></th>
			<td>
				<select name="kennelpress_booking_pet_id" id="kennelpress_booking_pet_id">
					<option value="0"><?php esc_html_e( '— Select —', 'kennelflow-boarding' ); ?></option>
					<?php foreach ( $pets as $pid => $label ) : ?>
						<option value="<?php echo esc_attr( (string) $pid ); ?>" <?php selected( $pet_id, $pid ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<?php if ( ! empty( $location_options ) ) : ?>
		<tr>
			<th scope="row"><label for="kennelpress_booking_location_id"><?php esc_html_e( 'Location (Hub)', 'kennelflow-boarding' ); ?></label></th>
			<td>
				<select name="kennelpress_booking_location_id" id="kennelpress_booking_location_id" class="widefat">
					<option value="0"><?php esc_html_e( '— Select location —', 'kennelflow-boarding' ); ?></option>
					<?php foreach ( $location_options as $lid => $label ) : ?>
						<option value="<?php echo esc_attr( (string) $lid ); ?>" <?php selected( $booking_location_id, $lid ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Used for calendar and reporting when the scheduling resource is not a kennel tied to a site.', 'kennelflow-boarding' ); ?></p>
			</td>
		</tr>
		<?php endif; ?>
		<tr>
			<th scope="row"><label for="kennelpress_booking_kennel_id"><?php esc_html_e( 'Kennel', 'kennelflow-boarding' ); ?></label></th>
			<td>
				<select name="kennelpress_booking_kennel_id" id="kennelpress_booking_kennel_id">
					<option value="0"><?php esc_html_e( '— Select —', 'kennelflow-boarding' ); ?></option>
					<?php foreach ( $kennels as $kid => $label ) : ?>
						<option value="<?php echo esc_attr( (string) $kid ); ?>" <?php selected( $kennel_id, $kid ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="kennelpress_booking_start_gmt"><?php esc_html_e( 'Start (UTC)', 'kennelflow-boarding' ); ?></label></th>
			<td>
				<input type="text" class="regular-text" name="kennelpress_booking_start_gmt" id="kennelpress_booking_start_gmt" value="<?php echo esc_attr( $start ); ?>" placeholder="<?php echo esc_attr__( 'YYYY-MM-DD HH:MM:SS', 'kennelflow-boarding' ); ?>" />
				<p class="description"><?php esc_html_e( 'Store as GMT, e.g. 2026-04-10 15:00:00', 'kennelflow-boarding' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="kennelpress_booking_end_gmt"><?php esc_html_e( 'End (UTC)', 'kennelflow-boarding' ); ?></label></th>
			<td>
				<input type="text" class="regular-text" name="kennelpress_booking_end_gmt" id="kennelpress_booking_end_gmt" value="<?php echo esc_attr( $end ); ?>" placeholder="<?php echo esc_attr__( 'YYYY-MM-DD HH:MM:SS', 'kennelflow-boarding' ); ?>" />
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="kennelpress_booking_status"><?php esc_html_e( 'Status', 'kennelflow-boarding' ); ?></label></th>
			<td>
				<select name="kennelpress_booking_status" id="kennelpress_booking_status">
					<?php foreach ( $statuses as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		</tbody></table>
		<?php
		self::render_boarding_booking_snapshot( (int) $post->ID );
	}

	/**
	 * Show boarding quote / intake / interview meta when present.
	 *
	 * @param int $post_id Booking post ID.
	 * @return void
	 */
	protected static function render_boarding_booking_snapshot( $post_id ) {
		$post_id    = absint( $post_id );
		$quote_raw  = (string) get_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_BOARDING_QUOTE_JSON, true );
		$intake_raw = (string) get_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_BOARDING_INTAKE_JSON, true );
		$interview  = (string) get_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_BOARDING_INTERVIEW_REQUESTED, true );
		$price_app  = (string) get_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_BOARDING_PRICE_APPLICATION, true );
		$wc_oid     = (int) get_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_WC_ORDER_ID, true );
		if ( '' === $quote_raw && '' === $intake_raw && '1' !== $interview && '' === $price_app && $wc_oid < 1 ) {
			return;
		}
		$quote  = $quote_raw ? json_decode( $quote_raw, true ) : null;
		$intake = $intake_raw ? json_decode( $intake_raw, true ) : null;
		?>
		<h4><?php esc_html_e( 'Boarding snapshot', 'kennelflow-boarding' ); ?></h4>
		<?php if ( '' !== $price_app ) : ?>
			<p><strong><?php esc_html_e( 'Price mode (at booking):', 'kennelflow-boarding' ); ?></strong> <?php echo esc_html( $price_app ); ?></p>
		<?php endif; ?>
		<?php if ( is_array( $quote ) ) : ?>
			<p>
				<strong><?php esc_html_e( 'Quoted total:', 'kennelflow-boarding' ); ?></strong>
				<?php
				if ( isset( $quote['total'] ) ) {
					echo esc_html( (string) $quote['total'] );
					if ( ! empty( $quote['currency'] ) ) {
						echo ' ' . esc_html( (string) $quote['currency'] );
					}
				} else {
					echo esc_html( '—' );
				}
				?>
			</p>
			<?php if ( ! empty( $quote['line_items'] ) && is_array( $quote['line_items'] ) ) : ?>
				<ul style="list-style:disc;margin-left:1.25em;">
					<?php foreach ( $quote['line_items'] as $li ) : ?>
						<?php
						if ( ! is_array( $li ) ) {
							continue;
						}
						$lab = isset( $li['label'] ) ? (string) $li['label'] : '';
						$tot = isset( $li['total'] ) ? (string) $li['total'] : '';
						?>
						<li><?php echo esc_html( $lab . ( '' !== $tot ? ' — ' . $tot : '' ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		<?php endif; ?>
		<?php if ( is_array( $intake ) && ( ! empty( $intake['emergency_contact_name'] ) || ! empty( $intake['emergency_contact_phone'] ) || ! empty( $intake['special_instructions'] ) ) ) : ?>
			<p><strong><?php esc_html_e( 'Intake', 'kennelflow-boarding' ); ?></strong></p>
			<table class="form-table"><tbody>
				<?php if ( ! empty( $intake['emergency_contact_name'] ) ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Emergency contact', 'kennelflow-boarding' ); ?></th>
						<td><?php echo esc_html( (string) $intake['emergency_contact_name'] ); ?></td>
					</tr>
				<?php endif; ?>
				<?php if ( ! empty( $intake['emergency_contact_phone'] ) ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Emergency phone', 'kennelflow-boarding' ); ?></th>
						<td><?php echo esc_html( (string) $intake['emergency_contact_phone'] ); ?></td>
					</tr>
				<?php endif; ?>
				<?php if ( ! empty( $intake['special_instructions'] ) ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Special instructions', 'kennelflow-boarding' ); ?></th>
						<td><?php echo nl2br( esc_html( (string) $intake['special_instructions'] ) ); ?></td>
					</tr>
				<?php endif; ?>
			</tbody></table>
		<?php endif; ?>
		<?php if ( '1' === $interview ) : ?>
			<p><strong><?php esc_html_e( 'Pre-boarding interview requested.', 'kennelflow-boarding' ); ?></strong></p>
		<?php endif; ?>
		<?php if ( $wc_oid > 0 ) : ?>
			<p>
				<strong><?php esc_html_e( 'WooCommerce order ID:', 'kennelflow-boarding' ); ?></strong>
				<?php echo esc_html( (string) $wc_oid ); ?>
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Posts map id => title for select.
	 *
	 * @param string $post_type Post type.
	 * @return array<int,string>
	 */
	protected static function get_posts_options( $post_type ) {
		// phpcs:disable WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Admin dropdowns.
		$q   = new WP_Query(
			array(
				'post_type'      => $post_type,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 500,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		$out = array();
		foreach ( $q->posts as $p ) {
			$out[ (int) $p->ID ] = get_the_title( $p ) ? get_the_title( $p ) : '#' . (int) $p->ID;
		}
		// phpcs:enable WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
		return $out;
	}

	/**
	 * Kennels with location labels.
	 *
	 * @return array<int,string>
	 */
	protected static function get_kennel_options() {
		// phpcs:disable WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Admin dropdowns.
		$q   = new WP_Query(
			array(
				'post_type'      => 'kennelpress_kennel',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 500,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		$out = array();
		foreach ( $q->posts as $p ) {
			$title  = get_the_title( $p ) ? get_the_title( $p ) : '#' . (int) $p->ID;
			$loc_id = (int) get_post_meta( $p->ID, KennelFlow_Boarding_Post_Meta::KENNEL_LOCATION_ID, true );
			if ( $loc_id > 0 ) {
				$ln = get_the_title( $loc_id );
				if ( $ln ) {
					$title .= ' — ' . $ln;
				}
			}
			$out[ (int) $p->ID ] = $title;
		}
		// phpcs:enable WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
		return $out;
	}

	/**
	 * Save meta for our CPTs.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 * @return void
	 */
	public static function save_post( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$type = $post->post_type;

		if ( 'kennelpress_kennel' === $type ) {
			self::save_kennel_meta( $post_id );
			return;
		}
		if ( 'kennelpress_booking' === $type ) {
			self::save_booking_meta( $post_id );
		}
	}

	/**
	 * Fire `kennelpress_booking_saved` for classic (non-REST) post screen saves so hooks match REST/React flows.
	 *
	 * Skipped during REST requests: {@see KennelFlow_Boarding_REST_Bookings_Controller} already dispatches this action.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  True when the post existed before this save.
	 * @return void
	 */
	public static function maybe_fire_booking_saved_action( $post_id, $post, $update ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}
		if ( ! $post instanceof WP_Post || 'kennelpress_booking' !== $post->post_type ) {
			return;
		}

		$context = $update ? 'update' : 'create';
		$params  = self::get_classic_booking_save_params_for_action();

		/**
		 * Fires after a booking is saved from the WordPress post editor (classic UI).
		 *
		 * REST and programmatic saves use the same hook name with parameters from the request; see
		 * {@see KennelFlow_Boarding_REST_Bookings_Controller::create_item()} and `update_item()`.
		 *
		 * @since 0.1.0
		 *
		 * @param int    $post_id Booking post ID.
		 * @param array  $params  Sanitized meta fields when the booking meta nonce was present; otherwise empty.
		 * @param string $context `create` (first save) or `update`.
		 */
		kennelflow_boarding_do_action( 'booking_saved', (int) $post_id, $params, $context );
	}

	/**
	 * Build request-style params for `kennelpress_booking_saved` when the booking meta box was submitted.
	 *
	 * @return array<string, mixed>
	 */
	protected static function get_classic_booking_save_params_for_action() {
		if ( ! self::verify_meta_box_nonce( 'kennelpress_save_booking_meta', 'kennelpress_booking_meta_nonce' ) ) {
			return array();
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified via verify_meta_box_nonce().
		$pet_id      = isset( $_POST['kennelpress_booking_pet_id'] ) ? absint( wp_unslash( $_POST['kennelpress_booking_pet_id'] ) ) : 0;
		$kennel      = isset( $_POST['kennelpress_booking_kennel_id'] ) ? absint( wp_unslash( $_POST['kennelpress_booking_kennel_id'] ) ) : 0;
		$booking_loc = isset( $_POST['kennelpress_booking_location_id'] ) ? absint( wp_unslash( $_POST['kennelpress_booking_location_id'] ) ) : 0;
		$start       = isset( $_POST['kennelpress_booking_start_gmt'] ) ? sanitize_text_field( wp_unslash( $_POST['kennelpress_booking_start_gmt'] ) ) : '';
		$end         = isset( $_POST['kennelpress_booking_end_gmt'] ) ? sanitize_text_field( wp_unslash( $_POST['kennelpress_booking_end_gmt'] ) ) : '';
		$status      = isset( $_POST['kennelpress_booking_status'] ) ? sanitize_key( wp_unslash( $_POST['kennelpress_booking_status'] ) ) : 'pending';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return array(
			'source'      => 'classic_editor',
			'pet_id'      => $pet_id,
			'resource_id' => $kennel,
			'location_id' => $booking_loc,
			'start_gmt'   => $start,
			'end_gmt'     => $end,
			'status'      => KennelFlow_Boarding_Post_Meta::sanitize_booking_status( $status ),
		);
	}

	/**
	 * Verify meta box nonce without using check_admin_referer().
	 *
	 * The check_admin_referer() function calls wp_die() when verification fails; save_post handlers must
	 * return early instead so the rest of the post save can complete.
	 *
	 * Security: If the nonce field is missing or invalid, this returns false and callers must return
	 * before any post meta changes. Meta writes cannot proceed without a true return, so a missing
	 * nonce is not a bypass. wp_die() is not used here because save_post runs for many requests
	 * (REST, autosave, bulk actions) that may omit a given meta box nonce while still saving the post.
	 *
	 * @param string $action Nonce action string.
	 * @param string $field  $_POST key holding the nonce value.
	 * @return bool True when the nonce is present and valid; false otherwise.
	 */
	protected static function verify_meta_box_nonce( $action, $field ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce value read for wp_verify_nonce() (same check check_admin_referer() uses internally).
		if ( ! isset( $_POST[ $field ] ) ) {
			return false;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return (bool) wp_verify_nonce( $nonce, $action );
	}

	/**
	 * Save kennel meta.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	protected static function save_kennel_meta( $post_id ) {
		if ( ! self::verify_meta_box_nonce( 'kennelpress_save_kennel_meta', 'kennelpress_kennel_meta_nonce' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified via verify_meta_box_nonce().
		$cap           = isset( $_POST['kennelpress_capacity'] ) ? absint( wp_unslash( $_POST['kennelpress_capacity'] ) ) : 1;
		$loc_id        = isset( $_POST['kennelpress_location_id'] ) ? absint( wp_unslash( $_POST['kennelpress_location_id'] ) ) : 0;
		$resource_type = KennelFlow_Boarding_Post_Meta::sanitize_kennel_resource_type(
			sanitize_text_field( wp_unslash( isset( $_POST['kennelpress_resource_type'] ) ? (string) $_POST['kennelpress_resource_type'] : '' ) )
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( $cap < 1 ) {
			$cap = 1;
		}
		update_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::KENNEL_CAPACITY, $cap );

		$loc_pt = function_exists( 'ltkf_get_location_post_type' ) ? ltkf_get_location_post_type() : 'kf_location';
		if ( $loc_id > 0 && get_post_type( $loc_id ) === $loc_pt ) {
			update_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::KENNEL_LOCATION_ID, $loc_id );
		} else {
			delete_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::KENNEL_LOCATION_ID );
		}
		if ( '' === $resource_type ) {
			delete_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::KENNEL_RESOURCE_TYPE );
		} else {
			update_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::KENNEL_RESOURCE_TYPE, $resource_type );
		}
	}

	/**
	 * Save booking meta.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	protected static function save_booking_meta( $post_id ) {
		if ( ! self::verify_meta_box_nonce( 'kennelpress_save_booking_meta', 'kennelpress_booking_meta_nonce' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified via verify_meta_box_nonce().
		$pet_id      = isset( $_POST['kennelpress_booking_pet_id'] ) ? absint( wp_unslash( $_POST['kennelpress_booking_pet_id'] ) ) : 0;
		$kennel      = isset( $_POST['kennelpress_booking_kennel_id'] ) ? absint( wp_unslash( $_POST['kennelpress_booking_kennel_id'] ) ) : 0;
		$booking_loc = isset( $_POST['kennelpress_booking_location_id'] ) ? absint( wp_unslash( $_POST['kennelpress_booking_location_id'] ) ) : 0;

		if ( $pet_id > 0 ) {
			update_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_PET_ID, $pet_id );
		} else {
			delete_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_PET_ID );
		}
		if ( $kennel > 0 ) {
			update_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_KENNEL_ID, $kennel );
		} else {
			delete_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_KENNEL_ID );
		}

		$loc_pt = function_exists( 'ltkf_get_location_post_type' ) ? ltkf_get_location_post_type() : 'kf_location';
		if ( $booking_loc > 0 && get_post_type( $booking_loc ) === $loc_pt ) {
			update_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_LOCATION_ID, $booking_loc );
		} else {
			delete_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_LOCATION_ID );
		}

		$start = isset( $_POST['kennelpress_booking_start_gmt'] ) ? sanitize_text_field( wp_unslash( $_POST['kennelpress_booking_start_gmt'] ) ) : '';
		$end   = isset( $_POST['kennelpress_booking_end_gmt'] ) ? sanitize_text_field( wp_unslash( $_POST['kennelpress_booking_end_gmt'] ) ) : '';

		$status = isset( $_POST['kennelpress_booking_status'] ) ? sanitize_key( wp_unslash( $_POST['kennelpress_booking_status'] ) ) : 'pending';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$start_ok = KennelFlow_Boarding_Availability::parse_gmt_mysql( $start );
		if ( ! is_wp_error( $start_ok ) ) {
			update_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_START_GMT, $start_ok );
		} elseif ( '' === $start ) {
			delete_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_START_GMT );
		}

		$end_ok = KennelFlow_Boarding_Availability::parse_gmt_mysql( $end );
		if ( ! is_wp_error( $end_ok ) ) {
			update_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_END_GMT, $end_ok );
		} elseif ( '' === $end ) {
			delete_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_END_GMT );
		}

		$status = KennelFlow_Boarding_Post_Meta::sanitize_booking_status( $status );
		update_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_STATUS, $status );
	}

	/**
	 * Booking list columns.
	 *
	 * @param string[] $columns Columns.
	 * @return string[]
	 */
	public static function booking_columns( $columns ) {
		$extra = array(
			'kennelpress_booking_status' => __( 'Status', 'kennelflow-boarding' ),
			'kennelpress_booking_pet'    => __( 'Pet', 'kennelflow-boarding' ),
			'kennelpress_booking_kennel' => __( 'Kennel', 'kennelflow-boarding' ),
			'kennelpress_booking_start'  => __( 'Start (UTC)', 'kennelflow-boarding' ),
			'kennelpress_booking_end'    => __( 'End (UTC)', 'kennelflow-boarding' ),
		);
		if ( isset( $columns['title'] ) ) {
			$out = array();
			foreach ( $columns as $key => $label ) {
				$out[ $key ] = $label;
				if ( 'title' === $key ) {
					$out = array_merge( $out, $extra );
				}
			}
			return $out;
		}
		return array_merge( $columns, $extra );
	}

	/**
	 * Booking list column output.
	 *
	 * @param string $column Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public static function booking_custom_column( $column, $post_id ) {
		switch ( $column ) {
			case 'kennelpress_booking_status':
				$s = (string) get_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_STATUS, true );
				echo $s ? esc_html( $s ) : esc_html__( '—', 'kennelflow-boarding' );
				break;
			case 'kennelpress_booking_pet':
				$pid = (int) get_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_PET_ID, true );
				echo $pid ? esc_html( get_the_title( $pid ) ) : esc_html__( '—', 'kennelflow-boarding' );
				break;
			case 'kennelpress_booking_kennel':
				$kid = (int) get_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_KENNEL_ID, true );
				echo $kid ? esc_html( get_the_title( $kid ) ) : esc_html__( '—', 'kennelflow-boarding' );
				break;
			case 'kennelpress_booking_start':
				echo esc_html( (string) get_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_START_GMT, true ) );
				break;
			case 'kennelpress_booking_end':
				echo esc_html( (string) get_post_meta( $post_id, KennelFlow_Boarding_Post_Meta::BOOKING_END_GMT, true ) );
				break;
			default:
				break;
		}
	}
}
