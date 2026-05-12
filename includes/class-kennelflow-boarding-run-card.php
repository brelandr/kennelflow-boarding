<?php
/**
 * Printable Run Card (cage card) for kennel technicians.
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_Run_Card
 */
class KennelFlow_Boarding_Run_Card {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_post_kennelpress_print_run_card', array( __CLASS__, 'handle_admin_post' ) );
	}

	/**
	 * admin-post.php?action=kennelpress_print_run_card&booking_id=&_wpnonce=
	 *
	 * @return void
	 */
	public static function handle_admin_post() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- admin-post GET; verified below.
		if ( ! isset( $_GET['booking_id'], $_GET['_wpnonce'] ) ) {
			wp_die( esc_html__( 'Invalid request.', 'kennelflow-boarding' ) );
		}

		$booking_id = absint( wp_unslash( $_GET['booking_id'] ) );
		$nonce      = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( $booking_id < 1 || ! wp_verify_nonce( $nonce, 'kennelpress_print_run_card' ) ) {
			wp_die( esc_html__( 'Invalid security token.', 'kennelflow-boarding' ) );
		}

		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You must be logged in.', 'kennelflow-boarding' ) );
		}

		if ( 'kennelpress_booking' !== get_post_type( $booking_id ) ) {
			wp_die( esc_html__( 'Booking not found.', 'kennelflow-boarding' ) );
		}

		if ( ! current_user_can( 'edit_post', $booking_id ) ) {
			wp_die( esc_html__( 'You do not have permission to print this run card.', 'kennelflow-boarding' ) );
		}

		self::render_document( $booking_id );
		exit;
	}

	/**
	 * Format UTC MySQL datetime for display in site timezone.
	 *
	 * @param string $mysql_gmt Y-m-d H:i:s UTC.
	 * @return string
	 */
	protected static function format_gmt_local( $mysql_gmt ) {
		$mysql_gmt = trim( (string) $mysql_gmt );
		if ( '' === $mysql_gmt ) {
			return '';
		}
		$ts = strtotime( $mysql_gmt . ' UTC' );
		if ( false === $ts ) {
			return '';
		}

		return wp_date(
			get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
			$ts
		);
	}

	/**
	 * Pet breed string (extensible).
	 *
	 * @param int $pet_id kf_pet ID.
	 * @return string
	 */
	protected static function get_pet_breed( $pet_id ) {
		$pet_id = absint( $pet_id );
		if ( $pet_id < 1 ) {
			return '';
		}

		$breed = (string) get_post_meta( $pet_id, 'ltkf_pet_breed', true );
		if ( '' === $breed ) {
			$breed = (string) get_post_meta( $pet_id, '_kennelflow_vet_pet_breed', true );
		}

		/**
		 * Pet breed line on the Run Card.
		 *
		 * @since 0.1.0
		 *
		 * @param string $breed  Breed text.
		 * @param int    $pet_id Pet post ID.
		 */
		return (string) kennelflow_boarding_apply_filters( 'run_card_pet_breed', $breed, $pet_id );
	}

	/**
	 * Behavioral tag labels for slugs.
	 *
	 * @param string[] $slugs Slugs.
	 * @return string[]
	 */
	protected static function behavioral_labels_for_slugs( $slugs ) {
		if ( ! is_array( $slugs ) ) {
			return array();
		}
		$opts = KennelFlow_Boarding_REST_Pet_Care_Controller::default_behavioral_tag_labels();
		/**
		 * @param array<string, string> $opts
		 */
		$opts = apply_filters( 'ltkf_pet_boarding_behavioral_tag_options', $opts );
		$out  = array();
		foreach ( $slugs as $slug ) {
			$slug = sanitize_key( (string) $slug );
			if ( '' === $slug ) {
				continue;
			}
			$out[] = isset( $opts[ $slug ] ) ? $opts[ $slug ] : $slug;
		}
		return $out;
	}

	/**
	 * Output full HTML document (no admin chrome).
	 *
	 * @param int $booking_id kennelpress_booking post ID.
	 * @return void
	 */
	protected static function render_document( $booking_id ) {
		$booking_id = absint( $booking_id );

		$pet_id    = (int) get_post_meta( $booking_id, KennelFlow_Boarding_Post_Meta::BOOKING_PET_ID, true );
		$kennel_id = (int) get_post_meta( $booking_id, KennelFlow_Boarding_Post_Meta::BOOKING_KENNEL_ID, true );
		$start     = (string) get_post_meta( $booking_id, KennelFlow_Boarding_Post_Meta::BOOKING_START_GMT, true );
		$end       = (string) get_post_meta( $booking_id, KennelFlow_Boarding_Post_Meta::BOOKING_END_GMT, true );

		$stay_diet = (string) get_post_meta( $booking_id, KennelFlow_Boarding_Post_Meta::BOOKING_STAY_DIET, true );
		$stay_med  = (string) get_post_meta( $booking_id, KennelFlow_Boarding_Post_Meta::BOOKING_STAY_MEDICATION_NOTES, true );
		$stay_bel  = (string) get_post_meta( $booking_id, KennelFlow_Boarding_Post_Meta::BOOKING_STAY_BELONGINGS, true );

		$pet_name = $pet_id > 0 ? get_the_title( $pet_id ) : '';
		$breed    = self::get_pet_breed( $pet_id );

		$allergies = '';
		$tags      = array();
		if ( $pet_id > 0 && function_exists( 'ltkf_get_pet_care_defaults_allergies' ) ) {
			$allergies = trim( ltkf_get_pet_care_defaults_allergies( $pet_id ) );
			$tags      = ltkf_get_pet_care_defaults_behavioral_tags( $pet_id );
		}
		$tag_labels = self::behavioral_labels_for_slugs( $tags );

		$kennel_label = $kennel_id > 0 ? get_the_title( $kennel_id ) : __( '—', 'kennelflow-boarding' );
		$loc_id       = $kennel_id > 0 ? (int) get_post_meta( $kennel_id, KennelFlow_Boarding_Post_Meta::KENNEL_LOCATION_ID, true ) : 0;
		$loc_name     = $loc_id > 0 ? get_the_title( $loc_id ) : __( '—', 'kennelflow-boarding' );

		$checkin  = self::format_gmt_local( $start );
		$checkout = self::format_gmt_local( $end );

		nocache_headers();
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );

		$title = sprintf(
			/* translators: %s: pet name */
			__( 'Run card — %s', 'kennelflow-boarding' ),
			$pet_name ? $pet_name : (string) $booking_id
		);

		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title><?php echo esc_html( $title ); ?></title>
	<style>
		* { box-sizing: border-box; }
		body {
			margin: 0;
			padding: 0.35in;
			font-family: "Helvetica Neue", Helvetica, Arial, system-ui, sans-serif;
			color: #1d2327;
			background: #fff;
			-webkit-print-color-adjust: exact;
			print-color-adjust: exact;
		}
		.kprc-screen-actions {
			margin-bottom: 16px;
		}
		.kprc-screen-actions button {
			font-size: 16px;
			padding: 8px 16px;
			cursor: pointer;
		}
		.kprc-card {
			max-width: 7.5in;
			margin: 0 auto;
		}
		.kprc-title {
			font-size: 32px;
			font-weight: 800;
			line-height: 1.15;
			margin: 0 0 8px;
			letter-spacing: -0.02em;
		}
		.kprc-breed {
			font-size: 20px;
			font-weight: 600;
			margin: 0 0 20px;
			color: #50575e;
		}
		.kprc-row {
			font-size: 18px;
			margin: 0 0 10px;
			line-height: 1.35;
		}
		.kprc-row strong {
			display: inline-block;
			min-width: 7.5em;
		}
		.kprc-warn {
			margin-top: 20px;
			padding: 16px 18px;
			border: 4px solid #d63638;
			background: #fcf0f1;
			border-radius: 6px;
		}
		.kprc-warn h2 {
			margin: 0 0 10px;
			font-size: 28px;
			font-weight: 900;
			color: #8a1111;
			text-transform: uppercase;
			letter-spacing: 0.04em;
		}
		.kprc-warn .kprc-warn-body {
			font-size: 22px;
			font-weight: 700;
			line-height: 1.35;
		}
		.kprc-warn ul {
			margin: 8px 0 0 1.2em;
			padding: 0;
		}
		.kprc-section {
			margin-top: 18px;
		}
		.kprc-section h3 {
			margin: 0 0 6px;
			font-size: 14px;
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: 0.06em;
			color: #50575e;
		}
		.kprc-section .kprc-body {
			font-size: 17px;
			line-height: 1.45;
			white-space: pre-wrap;
		}
		@media print {
			body { padding: 0.25in; }
			.kprc-screen-actions { display: none !important; }
			.kprc-card { max-width: none; }
			@page {
				size: letter;
				margin: 0.35in;
			}
		}
		@media print and (max-height: 5in) {
			@page { size: 5.5in 8.5in; margin: 0.2in; }
		}
	</style>
</head>
<body>
	<div class="kprc-screen-actions">
		<button type="button" onclick="window.print();"><?php esc_html_e( 'Print', 'kennelflow-boarding' ); ?></button>
	</div>
	<div class="kprc-card">
		<h1 class="kprc-title"><?php echo esc_html( $pet_name ? $pet_name : __( 'Pet', 'kennelflow-boarding' ) ); ?></h1>
		<?php if ( '' !== $breed ) : ?>
			<p class="kprc-breed"><?php echo esc_html( $breed ); ?></p>
		<?php endif; ?>

		<p class="kprc-row"><strong><?php esc_html_e( 'Kennel', 'kennelflow-boarding' ); ?></strong> <?php echo esc_html( $kennel_label ); ?></p>
		<p class="kprc-row"><strong><?php esc_html_e( 'Location', 'kennelflow-boarding' ); ?></strong> <?php echo esc_html( $loc_name ); ?></p>
		<p class="kprc-row"><strong><?php esc_html_e( 'Check-in', 'kennelflow-boarding' ); ?></strong> <?php echo esc_html( $checkin ? $checkin : $start ); ?></p>
		<p class="kprc-row"><strong><?php esc_html_e( 'Check-out', 'kennelflow-boarding' ); ?></strong> <?php echo esc_html( $checkout ? $checkout : $end ); ?></p>

		<div class="kprc-warn">
			<h2><?php esc_html_e( 'Allergies & warnings', 'kennelflow-boarding' ); ?></h2>
			<div class="kprc-warn-body">
				<?php if ( '' !== $allergies ) : ?>
					<div><?php echo esc_html( $allergies ); ?></div>
				<?php endif; ?>
				<?php if ( ! empty( $tag_labels ) ) : ?>
					<ul>
						<?php foreach ( $tag_labels as $lab ) : ?>
							<li><?php echo esc_html( $lab ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ( '' === $allergies && empty( $tag_labels ) ) : ?>
					<div><?php esc_html_e( 'None on file.', 'kennelflow-boarding' ); ?></div>
				<?php endif; ?>
			</div>
		</div>

		<div class="kprc-section">
			<h3><?php esc_html_e( 'Stay diet instructions', 'kennelflow-boarding' ); ?></h3>
			<div class="kprc-body"><?php echo esc_html( '' !== $stay_diet ? $stay_diet : __( '—', 'kennelflow-boarding' ) ); ?></div>
		</div>
		<div class="kprc-section">
			<h3><?php esc_html_e( 'Stay medication notes', 'kennelflow-boarding' ); ?></h3>
			<div class="kprc-body"><?php echo esc_html( '' !== $stay_med ? $stay_med : __( '—', 'kennelflow-boarding' ) ); ?></div>
		</div>
		<div class="kprc-section">
			<h3><?php esc_html_e( 'Belongings brought', 'kennelflow-boarding' ); ?></h3>
			<div class="kprc-body"><?php echo esc_html( '' !== $stay_bel ? $stay_bel : __( '—', 'kennelflow-boarding' ) ); ?></div>
		</div>
	</div>
</body>
</html>
		<?php
	}
}
