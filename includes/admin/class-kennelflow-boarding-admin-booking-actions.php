<?php
/**
 * Front Desk: quick approve / check-in actions for kennel bookings.
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_Admin_Booking_Actions
 */
class KennelFlow_Boarding_Admin_Booking_Actions {

	/**
	 * Admin-post action slug.
	 */
	const ACTION_SET_STATUS = 'kennelflow_boarding_set_status';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION_SET_STATUS, array( __CLASS__, 'handle_set_status' ) );
		add_filter( 'post_row_actions', array( __CLASS__, 'filter_row_actions' ), 10, 2 );
		add_action( 'edit_form_after_title', array( __CLASS__, 'render_edit_screen_actions' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_status_changed_notice' ) );
	}

	/**
	 * Whether the current user may change booking status.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage() {
		if ( class_exists( 'KennelFlow_Boarding_Capabilities' ) ) {
			return KennelFlow_Boarding_Capabilities::user_can_edit_bookings();
		}
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		return (bool) current_user_can( 'edit_posts' );
	}

	/**
	 * Update booking status and sync the availability index.
	 *
	 * @param int    $booking_id Booking post ID.
	 * @param string $new_status Target status slug.
	 * @return true|WP_Error
	 */
	public static function set_booking_status( $booking_id, $new_status ) {
		$booking_id = absint( $booking_id );
		if ( $booking_id < 1 || 'kennelpress_booking' !== get_post_type( $booking_id ) ) {
			return new WP_Error( 'kennelpress_bad_booking', __( 'Invalid booking.', 'kennelflow-boarding' ), array( 'status' => 400 ) );
		}
		if ( ! self::current_user_can_manage() ) {
			return new WP_Error( 'kennelpress_forbidden', __( 'You do not have permission to update this booking.', 'kennelflow-boarding' ), array( 'status' => 403 ) );
		}

		$status = KennelFlow_Boarding_Post_Meta::sanitize_booking_status( (string) $new_status );
		update_post_meta( $booking_id, KennelFlow_Boarding_Post_Meta::BOOKING_STATUS, $status );
		KennelFlow_Boarding_Booking_Index::sync_from_post( $booking_id );
		KennelFlow_Boarding_Cache::bump_query_bust();

		/**
		 * Fires after front desk or REST updates a booking status.
		 *
		 * @since 0.1.5
		 *
		 * @param int    $booking_id Booking post ID.
		 * @param string $status     New status slug.
		 */
		kennelflow_boarding_do_action( 'booking_status_changed', $booking_id, $status );

		return true;
	}

	/**
	 * Build a nonce-protected admin URL to set status.
	 *
	 * @param int    $booking_id Booking post ID.
	 * @param string $status     Target status.
	 * @param string $redirect   Redirect URL after action.
	 * @return string
	 */
	public static function status_action_url( $booking_id, $status, $redirect = '' ) {
		$booking_id = absint( $booking_id );
		if ( '' === $redirect ) {
			$redirect = wp_get_referer();
		}
		if ( ! is_string( $redirect ) || '' === $redirect ) {
			$redirect = admin_url( 'edit.php?post_type=kennelpress_booking' );
		}

		$url = add_query_arg(
			array(
				'action'      => self::ACTION_SET_STATUS,
				'booking_id'  => $booking_id,
				'status'      => sanitize_key( $status ),
				'redirect_to' => rawurlencode( $redirect ),
			),
			admin_url( 'admin-post.php' )
		);

		return wp_nonce_url( $url, self::nonce_action( $booking_id ) );
	}

	/**
	 * Nonce action for a booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return string
	 */
	protected static function nonce_action( $booking_id ) {
		return 'kennelflow_boarding_set_status_' . absint( $booking_id );
	}

	/**
	 * Handle admin-post status change.
	 *
	 * @return void
	 */
	public static function handle_set_status() {
		if ( ! self::current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to update bookings.', 'kennelflow-boarding' ) );
		}

		$booking_id = isset( $_GET['booking_id'] ) ? absint( wp_unslash( $_GET['booking_id'] ) ) : 0;
		$status     = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$redirect   = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '';

		if ( $booking_id < 1 || '' === $status ) {
			wp_die( esc_html__( 'Invalid booking action.', 'kennelflow-boarding' ) );
		}

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), self::nonce_action( $booking_id ) ) ) {
			wp_die( esc_html__( 'Invalid security token.', 'kennelflow-boarding' ) );
		}

		$result = self::set_booking_status( $booking_id, $status );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		if ( '' === $redirect ) {
			$redirect = admin_url( 'edit.php?post_type=kennelpress_booking' );
		}

		$redirect = add_query_arg(
			array(
				'kennelflow_boarding_status_updated' => 1,
				'booking_id'                         => $booking_id,
				'new_status'                         => $status,
			),
			$redirect
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Row actions on the bookings list table.
	 *
	 * @param string[] $actions Row actions.
	 * @param WP_Post  $post    Post.
	 * @return string[]
	 */
	public static function filter_row_actions( $actions, $post ) {
		if ( ! $post instanceof WP_Post || 'kennelpress_booking' !== $post->post_type ) {
			return $actions;
		}
		if ( ! self::current_user_can_manage() ) {
			return $actions;
		}

		$edit_url = get_edit_post_link( $post->ID, 'raw' );
		if ( is_string( $edit_url ) && '' !== $edit_url ) {
			$open = array(
				'kennelflow_open' => sprintf(
					'<a href="%s" aria-label="%s">%s</a>',
					esc_url( $edit_url ),
					esc_attr(
						sprintf(
							/* translators: %s: booking title */
							__( 'Open booking “%s”', 'kennelflow-boarding' ),
							get_the_title( $post )
						)
					),
					esc_html__( 'Open booking', 'kennelflow-boarding' )
				),
			);
			$actions = array_merge( $open, $actions );
		}

		$status = (string) get_post_meta( $post->ID, KennelFlow_Boarding_Post_Meta::BOOKING_STATUS, true );
		if ( '' === $status ) {
			$status = 'pending';
		}

		$redirect = admin_url( 'edit.php?post_type=kennelpress_booking' );

		if ( in_array( $status, array( 'pending', 'pending_payment' ), true ) ) {
			$actions['kennelflow_confirm'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( self::status_action_url( $post->ID, 'confirmed', $redirect ) ),
				esc_html__( 'Confirm', 'kennelflow-boarding' )
			);
		}
		if ( 'confirmed' === $status ) {
			$actions['kennelflow_checkin'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( self::status_action_url( $post->ID, 'checked_in', $redirect ) ),
				esc_html__( 'Check in', 'kennelflow-boarding' )
			);
		}

		return $actions;
	}

	/**
	 * Prominent approve / check-in controls on the booking editor.
	 *
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public static function render_edit_screen_actions( $post ) {
		if ( ! $post instanceof WP_Post || 'kennelpress_booking' !== $post->post_type ) {
			return;
		}
		if ( ! self::current_user_can_manage() ) {
			return;
		}

		$status = (string) get_post_meta( $post->ID, KennelFlow_Boarding_Post_Meta::BOOKING_STATUS, true );
		if ( '' === $status ) {
			$status = 'pending';
		}

		$redirect = get_edit_post_link( $post->ID, 'raw' );
		if ( ! is_string( $redirect ) ) {
			$redirect = '';
		}

		if ( in_array( $status, array( 'pending', 'pending_payment' ), true ) ) {
			?>
			<div class="notice notice-warning inline" style="margin:12px 0;">
				<p>
					<strong><?php esc_html_e( 'This booking is awaiting front-desk approval.', 'kennelflow-boarding' ); ?></strong>
					<a class="button button-primary" style="margin-left:8px;" href="<?php echo esc_url( self::status_action_url( $post->ID, 'confirmed', $redirect ) ); ?>">
						<?php esc_html_e( 'Confirm booking', 'kennelflow-boarding' ); ?>
					</a>
				</p>
			</div>
			<?php
			return;
		}

		if ( 'confirmed' === $status ) {
			?>
			<div class="notice notice-info inline" style="margin:12px 0;">
				<p>
					<?php esc_html_e( 'Guest is confirmed — check them in when they arrive.', 'kennelflow-boarding' ); ?>
					<a class="button button-secondary" style="margin-left:8px;" href="<?php echo esc_url( self::status_action_url( $post->ID, 'checked_in', $redirect ) ); ?>">
						<?php esc_html_e( 'Check in', 'kennelflow-boarding' ); ?>
					</a>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Success notice after a quick status change.
	 *
	 * @return void
	 */
	public static function render_status_changed_notice() {
		if ( ! self::current_user_can_manage() ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display-only flash query args.
		if ( empty( $_GET['kennelflow_boarding_status_updated'] ) ) {
			return;
		}
		$booking_id = isset( $_GET['booking_id'] ) ? absint( wp_unslash( $_GET['booking_id'] ) ) : 0;
		$new_status = isset( $_GET['new_status'] ) ? sanitize_key( wp_unslash( $_GET['new_status'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( $booking_id < 1 || '' === $new_status ) {
			return;
		}

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: 1: booking ID, 2: new status label */
					__( 'Booking #%1$d updated to %2$s.', 'kennelflow-boarding' ),
					$booking_id,
					$new_status
				)
			)
		);
	}

	/**
	 * Pending bookings for the Front Desk dashboard.
	 *
	 * @param int $limit Max rows.
	 * @return WP_Post[]
	 */
	public static function get_pending_bookings( $limit = 25 ) {
		$limit = max( 1, min( 50, absint( $limit ) ) );

		$q = new WP_Query(
			array(
				'post_type'              => 'kennelpress_booking',
				'post_status'            => 'publish',
				'posts_per_page'         => $limit,
				'orderby'                => 'meta_value',
				'meta_key'               => KennelFlow_Boarding_Post_Meta::BOOKING_START_GMT,
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'meta_query'             => array(
					array(
						'key'     => KennelFlow_Boarding_Post_Meta::BOOKING_STATUS,
						'value'   => array( 'pending', 'pending_payment' ),
						'compare' => 'IN',
					),
				),
			)
		);

		return $q->posts;
	}

	/**
	 * Render pending-approval table (Front Desk home).
	 *
	 * @return void
	 */
	public static function render_pending_dashboard_table() {
		if ( ! self::current_user_can_manage() ) {
			return;
		}

		$pending = self::get_pending_bookings();
		?>
		<h2><?php esc_html_e( 'Pending approvals', 'kennelflow-boarding' ); ?></h2>
		<?php if ( empty( $pending ) ) : ?>
			<p class="description"><?php esc_html_e( 'No bookings are waiting for confirmation.', 'kennelflow-boarding' ); ?></p>
			<?php
			return;
		endif;
		$redirect = admin_url( 'admin.php?page=' . kennelflow_boarding_get_front_desk_menu_slug() );
		?>
		<table class="widefat striped" style="max-width:960px;">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Pet', 'kennelflow-boarding' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Kennel', 'kennelflow-boarding' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Check-in (UTC)', 'kennelflow-boarding' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Check-out (UTC)', 'kennelflow-boarding' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'kennelflow-boarding' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $pending as $post ) : ?>
					<?php
					$pid    = (int) get_post_meta( $post->ID, KennelFlow_Boarding_Post_Meta::BOOKING_PET_ID, true );
					$kid    = (int) get_post_meta( $post->ID, KennelFlow_Boarding_Post_Meta::BOOKING_KENNEL_ID, true );
					$start  = (string) get_post_meta( $post->ID, KennelFlow_Boarding_Post_Meta::BOOKING_START_GMT, true );
					$end    = (string) get_post_meta( $post->ID, KennelFlow_Boarding_Post_Meta::BOOKING_END_GMT, true );
					$edit   = get_edit_post_link( $post->ID, 'raw' );
					$confirm_url = self::status_action_url( $post->ID, 'confirmed', $redirect );
					?>
					<tr>
						<td>
							<?php
							if ( $pid ) {
								$pet_edit = get_edit_post_link( $pid, 'raw' );
								if ( is_string( $pet_edit ) && '' !== $pet_edit ) {
									printf(
										'<a href="%s">%s</a>',
										esc_url( $pet_edit ),
										esc_html( get_the_title( $pid ) )
									);
								} else {
									echo esc_html( get_the_title( $pid ) );
								}
							} else {
								esc_html_e( '—', 'kennelflow-boarding' );
							}
							?>
						</td>
						<td><?php echo $kid ? esc_html( get_the_title( $kid ) ) : esc_html__( '—', 'kennelflow-boarding' ); ?></td>
						<td><?php echo esc_html( $start ); ?></td>
						<td><?php echo esc_html( $end ); ?></td>
						<td>
							<a class="button button-primary button-small" href="<?php echo esc_url( $confirm_url ); ?>">
								<?php esc_html_e( 'Confirm', 'kennelflow-boarding' ); ?>
							</a>
							<?php if ( is_string( $edit ) && '' !== $edit ) : ?>
								<a class="button button-small" href="<?php echo esc_url( $edit ); ?>"><?php esc_html_e( 'Open booking', 'kennelflow-boarding' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
