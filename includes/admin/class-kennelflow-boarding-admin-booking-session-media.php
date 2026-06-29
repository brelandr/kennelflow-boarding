<?php
/**
 * kennelpress_booking: boarding stay photos (check-in / check-out).
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_Admin_Booking_Session_Media
 */
class KennelFlow_Boarding_Admin_Booking_Session_Media {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_boxes' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
	}

	/**
	 * @return void
	 */
	public static function register_meta_boxes() {
		global $post;

		if ( $post instanceof WP_Post && $post->ID > 0 && class_exists( 'KennelFlow_Boarding_Post_Meta' ) ) {
			$kind = sanitize_key( (string) get_post_meta( $post->ID, KennelFlow_Boarding_Post_Meta::BOOKING_KIND, true ) );
			if ( in_array( $kind, array( 'grooming', 'clinic' ), true ) ) {
				return;
			}
		}

		add_meta_box(
			'kf_boarding_session_media',
			__( 'Stay photos', 'kennelflow-boarding' ),
			array( __CLASS__, 'render_box' ),
			'kennelpress_booking',
			'normal',
			'default'
		);
	}

	/**
	 * @param string $hook Admin hook suffix.
	 * @return void
	 */
	public static function enqueue_admin_assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		global $post;
		if ( ! $post instanceof WP_Post || 'kennelpress_booking' !== $post->post_type ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style(
			'kf-boarding-device-camera',
			KENNELFLOW_BOARDING_PLUGIN_URL . 'assets/css/device-camera-capture.css',
			array(),
			KENNELFLOW_BOARDING_VERSION
		);
		wp_enqueue_script(
			'kf-boarding-device-camera',
			KENNELFLOW_BOARDING_PLUGIN_URL . 'assets/js/device-camera-capture.js',
			array(),
			KENNELFLOW_BOARDING_VERSION,
			true
		);
		wp_enqueue_script(
			'kf-boarding-booking-media',
			KENNELFLOW_BOARDING_PLUGIN_URL . 'assets/js/admin-booking-session-media.js',
			array( 'jquery', 'media-editor', 'kf-boarding-device-camera' ),
			KENNELFLOW_BOARDING_VERSION,
			true
		);
		wp_localize_script(
			'kf-boarding-booking-media',
			'kennelflowBoardingBookingMedia',
			array(
				'bookingId' => (int) $post->ID,
				'restBase'  => KennelFlow_Boarding_Session_Media_REST::NS,
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'i18n'      => self::script_i18n(),
			)
		);
	}

	/**
	 * @return array<string, string>
	 */
	protected static function script_i18n() {
		return array(
			'empty'             => __( 'No stay photos yet.', 'kennelflow-boarding' ),
			'loading'           => __( 'Loading…', 'kennelflow-boarding' ),
			'loadError'         => __( 'Could not load photos.', 'kennelflow-boarding' ),
			'saving'            => __( 'Saving…', 'kennelflow-boarding' ),
			'saved'             => __( 'Photo saved.', 'kennelflow-boarding' ),
			'saveError'         => __( 'Could not save photo.', 'kennelflow-boarding' ),
			'removing'          => __( 'Removing…', 'kennelflow-boarding' ),
			'removeError'       => __( 'Could not remove photo.', 'kennelflow-boarding' ),
			'remove'            => __( 'Remove', 'kennelflow-boarding' ),
			'confirmRemove'     => __( 'Remove this stay photo?', 'kennelflow-boarding' ),
			'pickTitle'         => __( 'Choose stay photo', 'kennelflow-boarding' ),
			'usePhoto'          => __( 'Use photo', 'kennelflow-boarding' ),
			'uploading'         => __( 'Uploading…', 'kennelflow-boarding' ),
			'uploadError'       => __( 'Could not upload photo.', 'kennelflow-boarding' ),
			'invalidImage'      => __( 'Please choose an image file.', 'kennelflow-boarding' ),
			'takeCheckIn'       => __( 'Take check-in photo', 'kennelflow-boarding' ),
			'takeCheckOut'      => __( 'Take check-out photo', 'kennelflow-boarding' ),
			'capture'           => __( 'Capture photo', 'kennelflow-boarding' ),
			'cancel'            => __( 'Cancel', 'kennelflow-boarding' ),
			'cameraUnsupported' => __( 'Camera is not available in this browser.', 'kennelflow-boarding' ),
			'cameraAccessError' => __( 'Could not access the camera. Check browser permissions and try again.', 'kennelflow-boarding' ),
			'captureError'      => __( 'Could not capture photo.', 'kennelflow-boarding' ),
		);
	}

	/**
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public static function render_box( $post ) {
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		?>
		<p class="description"><?php esc_html_e( 'Check-in and check-out photos for this boarding stay. Use Take photo for the device camera or Choose photo for the Media Library.', 'kennelflow-boarding' ); ?></p>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Kind', 'kennelflow-boarding' ); ?></th>
					<th><?php esc_html_e( 'Preview', 'kennelflow-boarding' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'kennelflow-boarding' ); ?></th>
				</tr>
			</thead>
			<tbody id="kf-boarding-media-rows">
				<tr><td colspan="3"><?php esc_html_e( 'No stay photos yet.', 'kennelflow-boarding' ); ?></td></tr>
			</tbody>
		</table>
		<?php if ( current_user_can( 'upload_files' ) ) : ?>
			<p class="kf-boarding-media-actions">
				<button type="button" class="button kf-boarding-camera-check_in"><?php esc_html_e( 'Take check-in photo', 'kennelflow-boarding' ); ?></button>
				<button type="button" class="button kf-boarding-pick-check_in"><?php esc_html_e( 'Choose check-in photo', 'kennelflow-boarding' ); ?></button>
				<button type="button" class="button kf-boarding-camera-check_out"><?php esc_html_e( 'Take check-out photo', 'kennelflow-boarding' ); ?></button>
				<button type="button" class="button kf-boarding-pick-check_out"><?php esc_html_e( 'Choose check-out photo', 'kennelflow-boarding' ); ?></button>
			</p>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'You can view stay photos but cannot upload. Ask an administrator to grant the Upload Files capability to your role.', 'kennelflow-boarding' ); ?></p>
		<?php endif; ?>
		<p id="kf-boarding-media-status" class="description" aria-live="polite"></p>
		<?php
	}

	/**
	 * Calendar localized settings for boarding stay photos.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @return array<string, mixed>
	 */
	public static function append_calendar_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$can_upload = current_user_can( 'upload_files' )
			&& (
				KennelFlow_Boarding_Capabilities::user_can_edit_bookings()
				|| current_user_can( 'manage_options' )
			);
		if ( ! isset( $settings['booking_session_photos'] ) || ! is_array( $settings['booking_session_photos'] ) ) {
			$settings['booking_session_photos'] = array();
		}

		$settings['booking_session_photos']['boarding'] = array(
			'active'      => true,
			'enabled'     => $can_upload,
			'can_upload'  => $can_upload,
			'rest_ns'     => KennelFlow_Boarding_Session_Media_REST::NS,
			'heading'     => __( 'Stay photos', 'kennelflow-boarding' ),
			'media_kinds' => array(
				array(
					'key'         => 'check_in',
					'label'       => __( 'Check-in', 'kennelflow-boarding' ),
					'takeLabel'   => __( 'Take check-in photo', 'kennelflow-boarding' ),
					'chooseLabel' => __( 'Choose check-in photo', 'kennelflow-boarding' ),
				),
				array(
					'key'         => 'check_out',
					'label'       => __( 'Check-out', 'kennelflow-boarding' ),
					'takeLabel'   => __( 'Take check-out photo', 'kennelflow-boarding' ),
					'chooseLabel' => __( 'Choose check-out photo', 'kennelflow-boarding' ),
				),
			),
		);

		return $settings;
	}
}
