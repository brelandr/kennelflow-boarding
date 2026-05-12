<?php
/**
 * REST: Mobile Report Card — photo + checklist + email to pet owner.
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_REST_Report_Cards_Controller
 */
class KennelFlow_Boarding_REST_Report_Cards_Controller extends WP_REST_Controller {

	/**
	 * Allowed mood slugs.
	 */
	const MOODS = array( 'happy', 'calm', 'anxious' );

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = 'kennelflow-boarding/v1';
		$this->rest_base = 'report-cards';
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'permission_create' ),
			)
		);
	}

	/**
	 * Logged-in users with kennelpress_send_reports or edit_posts.
	 *
	 * @return bool
	 */
	public function permission_create() {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		return current_user_can( 'kennelpress_send_reports' ) || current_user_can( 'edit_posts' );
	}

	/**
	 * POST /report-cards (multipart/form-data).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$booking_id = absint( $request->get_param( 'booking_id' ) );
		if ( $booking_id < 1 ) {
			return new WP_Error(
				'kennelpress_report_card_invalid_booking',
				__( 'A valid booking_id is required.', 'kennelflow-boarding' ),
				array( 'status' => 400 )
			);
		}

		if ( 'kennelpress_booking' !== get_post_type( $booking_id ) ) {
			return new WP_Error(
				'kennelpress_report_card_booking_not_found',
				__( 'Booking not found.', 'kennelflow-boarding' ),
				array( 'status' => 404 )
			);
		}

		$mood = sanitize_key( (string) $request->get_param( 'mood' ) );
		if ( '' === $mood || ! in_array( $mood, self::MOODS, true ) ) {
			return new WP_Error(
				'kennelpress_report_card_invalid_mood',
				__( 'Mood must be one of: happy, calm, anxious.', 'kennelflow-boarding' ),
				array( 'status' => 400 )
			);
		}

		$ate_food = self::parse_bool_param( $request->get_param( 'ate_food' ) );
		$bathroom = self::parse_bool_param( $request->get_param( 'bathroom' ) );
		if ( null === $ate_food || null === $bathroom ) {
			return new WP_Error(
				'kennelpress_report_card_invalid_flags',
				__( 'ate_food and bathroom must be boolean values.', 'kennelflow-boarding' ),
				array( 'status' => 400 )
			);
		}

		$notes = isset( $request['notes'] ) ? wp_unslash( (string) $request['notes'] ) : '';
		$notes = sanitize_textarea_field( $notes );

		$file = self::get_uploaded_file_array( $request );
		if ( null === $file ) {
			return new WP_Error(
				'kennelpress_report_card_missing_photo',
				__( 'A photo file is required.', 'kennelflow-boarding' ),
				array( 'status' => 400 )
			);
		}

		if ( isset( $file['error'] ) && UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return new WP_Error(
				'kennelpress_report_card_upload_error',
				__( 'Photo upload failed.', 'kennelflow-boarding' ),
				array( 'status' => 400 )
			);
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$upload = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => array(
					'jpg|jpeg|jpe' => 'image/jpeg',
					'gif'          => 'image/gif',
					'png'          => 'image/png',
					'webp'         => 'image/webp',
				),
			)
		);

		if ( isset( $upload['error'] ) ) {
			return new WP_Error(
				'kennelpress_report_card_handle_upload',
				$upload['error'],
				array( 'status' => 400 )
			);
		}

		$file_path = isset( $upload['file'] ) ? (string) $upload['file'] : '';
		$filetype  = isset( $upload['type'] ) ? (string) $upload['type'] : '';
		if ( '' === $file_path || '' === $filetype ) {
			return new WP_Error(
				'kennelpress_report_card_upload_incomplete',
				__( 'Could not process the uploaded file.', 'kennelflow-boarding' ),
				array( 'status' => 400 )
			);
		}

		$attachment = array(
			'post_mime_type' => $filetype,
			'post_title'     => sanitize_file_name( pathinfo( $file_path, PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
			'post_parent'    => $booking_id,
			'post_author'    => get_current_user_id(),
		);

		$attach_id = wp_insert_attachment( $attachment, $file_path, $booking_id );
		if ( is_wp_error( $attach_id ) ) {
			wp_delete_file( $file_path );
			return $attach_id;
		}

		$attach_id = absint( $attach_id );
		if ( $attach_id < 1 ) {
			wp_delete_file( $file_path );
			return new WP_Error(
				'kennelpress_report_card_attachment_failed',
				__( 'Could not save the photo to the media library.', 'kennelflow-boarding' ),
				array( 'status' => 500 )
			);
		}
		wp_update_attachment_metadata(
			$attach_id,
			wp_generate_attachment_metadata( $attach_id, $file_path )
		);

		$pet_id = (int) get_post_meta( $booking_id, KennelFlow_Boarding_Post_Meta::BOOKING_PET_ID, true );
		if ( $pet_id < 1 || 'kf_pet' !== get_post_type( $pet_id ) ) {
			wp_delete_attachment( $attach_id, true );
			return new WP_Error(
				'kennelpress_report_card_no_pet',
				__( 'This booking has no linked pet.', 'kennelflow-boarding' ),
				array( 'status' => 400 )
			);
		}

		if ( ! function_exists( 'ltkf_get_pet_owner_user_id' ) ) {
			wp_delete_attachment( $attach_id, true );
			return new WP_Error(
				'kennelpress_report_card_hub_missing',
				__( 'KennelFlow Core is required to resolve the pet owner.', 'kennelflow-boarding' ),
				array( 'status' => 500 )
			);
		}

		$owner_id = (int) ltkf_get_pet_owner_user_id( $pet_id );
		if ( $owner_id < 1 ) {
			wp_delete_attachment( $attach_id, true );
			return new WP_Error(
				'kennelpress_report_card_no_owner',
				__( 'No pet owner is assigned for this booking.', 'kennelflow-boarding' ),
				array( 'status' => 400 )
			);
		}

		$owner = get_userdata( $owner_id );
		if ( ! $owner || ! is_email( $owner->user_email ) ) {
			wp_delete_attachment( $attach_id, true );
			return new WP_Error(
				'kennelpress_report_card_no_email',
				__( 'The pet owner does not have a valid email address.', 'kennelflow-boarding' ),
				array( 'status' => 400 )
			);
		}

		$pet_name = get_the_title( $pet_id );
		if ( '' === trim( (string) $pet_name ) ) {
			$pet_name = __( 'Your pet', 'kennelflow-boarding' );
		}

		$image_url = wp_get_attachment_url( $attach_id );
		if ( ! $image_url ) {
			$image_url = '';
		}

		$subject = sprintf(
			/* translators: %s: pet name */
			__( 'Daily update for %s', 'kennelflow-boarding' ),
			$pet_name
		);

		/**
		 * Filters the report card email subject.
		 *
		 * @since 0.2.0
		 *
		 * @param string $subject    Subject line.
		 * @param int    $booking_id Booking post ID.
		 * @param int    $pet_id     Pet post ID.
		 */
		$subject = kennelflow_boarding_apply_filters( 'report_card_email_subject', $subject, $booking_id, $pet_id );

		$body = self::build_email_html(
			$pet_name,
			$image_url,
			$mood,
			$ate_food,
			$bathroom,
			$notes
		);

		/**
		 * Filters the report card HTML body before wp_mail.
		 *
		 * @since 0.2.0
		 *
		 * @param string $body       HTML.
		 * @param int    $booking_id Booking post ID.
		 * @param int    $pet_id     Pet post ID.
		 * @param int    $attach_id  Attachment ID.
		 */
		$body = kennelflow_boarding_apply_filters( 'report_card_email_body', $body, $booking_id, $pet_id, $attach_id );

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		$sent = wp_mail( $owner->user_email, $subject, $body, $headers );

		if ( ! $sent ) {
			wp_delete_attachment( $attach_id, true );
			return new WP_Error(
				'kennelpress_report_card_mail_failed',
				__( 'The report could not be emailed. Please try again.', 'kennelflow-boarding' ),
				array( 'status' => 500 )
			);
		}

		/**
		 * Fires after a report card email was sent successfully.
		 *
		 * @since 0.2.0
		 *
		 * @param int $booking_id Booking post ID.
		 * @param int $attach_id  Attachment ID.
		 * @param int $owner_id   Owner user ID.
		 */
		kennelflow_boarding_do_action( 'report_card_sent', $booking_id, $attach_id, $owner_id );

		$data = array(
			'success'       => true,
			'attachment_id' => $attach_id,
			'booking_id'    => $booking_id,
			'pet_id'        => $pet_id,
			'message'       => __( 'Report sent successfully.', 'kennelflow-boarding' ),
		);

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Parse boolean from multipart / JSON (string or bool).
	 *
	 * @param mixed $value Raw.
	 * @return bool|null Null if invalid.
	 */
	protected static function parse_bool_param( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			return (bool) (int) $value;
		}
		if ( is_string( $value ) ) {
			$v = strtolower( trim( $value ) );
			if ( 'true' === $v || '1' === $v || 'yes' === $v ) {
				return true;
			}
			if ( 'false' === $v || '0' === $v || 'no' === $v ) {
				return false;
			}
		}
		return null;
	}

	/**
	 * Uploaded file array for wp_handle_upload.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string,mixed>|null
	 */
	protected static function get_uploaded_file_array( $request ) {
		if ( method_exists( $request, 'get_file_params' ) ) {
			$files = $request->get_file_params();
			if ( ! empty( $files['photo'] ) && is_array( $files['photo'] ) ) {
				return $files['photo'];
			}
		}
		if ( ! empty( $_FILES['photo'] ) && is_array( $_FILES['photo'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File array for wp_handle_upload; validated by WordPress upload API.
			return $_FILES['photo'];
		}
		return null;
	}

	/**
	 * HTML email body with photo and checklist.
	 *
	 * @param string $pet_name   Pet name.
	 * @param string $image_url  Attachment URL (absolute).
	 * @param string $mood       Mood slug.
	 * @param bool   $ate_food   Ate food.
	 * @param bool   $bathroom   Bathroom.
	 * @param string $notes      Notes (plain).
	 * @return string
	 */
	protected static function build_email_html( $pet_name, $image_url, $mood, $ate_food, $bathroom, $notes ) {
		$mood_labels = array(
			'happy'   => __( 'Happy', 'kennelflow-boarding' ),
			'calm'    => __( 'Calm', 'kennelflow-boarding' ),
			'anxious' => __( 'Anxious', 'kennelflow-boarding' ),
		);
		$mood_label  = isset( $mood_labels[ $mood ] ) ? $mood_labels[ $mood ] : $mood;

		$yes = __( 'Yes', 'kennelflow-boarding' );
		$no  = __( 'No', 'kennelflow-boarding' );

		$intro = sprintf(
			/* translators: %s: pet name */
			__( 'Here is a quick update from our team about %s.', 'kennelflow-boarding' ),
			$pet_name
		);

		$photo_block = '';
		if ( '' !== $image_url ) {
			$photo_block = sprintf(
				'<div style="margin:0 0 24px;text-align:center;">
<img src="%s" alt="" width="520" style="max-width:100%%;height:auto;border-radius:10px;border:1px solid #dcdcde;display:block;margin:0 auto;" />
</div>',
				esc_url( $image_url )
			);
		}

		$rows = array(
			array(
				'label' => __( 'Mood', 'kennelflow-boarding' ),
				'value' => esc_html( $mood_label ),
			),
			array(
				'label' => __( 'Ate food', 'kennelflow-boarding' ),
				'value' => $ate_food ? $yes : $no,
			),
			array(
				'label' => __( 'Bathroom', 'kennelflow-boarding' ),
				'value' => $bathroom ? $yes : $no,
			),
		);

		$blocks = '';
		foreach ( $rows as $row ) {
			$blocks .= sprintf(
				'<tr><td style="padding:14px 0;border-bottom:1px solid #e8e8eb;vertical-align:top;">
<strong style="display:block;color:#1d2327;font-size:12px;text-transform:uppercase;letter-spacing:0.06em;">%s</strong>
<div style="margin-top:6px;color:#1d2327;font-size:17px;line-height:1.4;font-weight:600;">%s</div>
</td></tr>',
				esc_html( $row['label'] ),
				$row['value']
			);
		}

		$notes_section = '';
		if ( '' !== trim( $notes ) ) {
			$notes_section = sprintf(
				'<tr><td style="padding:20px 0 0;vertical-align:top;">
<strong style="display:block;color:#1d2327;font-size:12px;text-transform:uppercase;letter-spacing:0.06em;">%s</strong>
<div style="margin-top:10px;color:#2c3338;font-size:15px;line-height:1.55;white-space:pre-wrap;">%s</div>
</td></tr>',
				esc_html__( 'Notes from our team', 'kennelflow-boarding' ),
				esc_html( $notes )
			);
		}

		$footer = sprintf(
			/* translators: %s: site name */
			__( 'Sent with care from %s.', 'kennelflow-boarding' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);

		$html = sprintf(
			'<html><body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;background:linear-gradient(180deg,#f0f4f8 0%%,#e8eef5 100%%);color:#1d2327;">
<table role="presentation" width="100%%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;padding:32px 16px;">
<tr><td align="center">
<div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 8px 32px rgba(15,23,42,0.08);border:1px solid #e2e8f0;">
<div style="background:linear-gradient(135deg,#2563eb 0%%,#1d4ed8 100%%);padding:22px 28px;">
<p style="margin:0;font-size:13px;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:rgba(255,255,255,0.9);">%s</p>
<h1 style="margin:8px 0 0;font-size:24px;font-weight:700;color:#ffffff;line-height:1.25;">%s</h1>
</div>
<div style="padding:28px 28px 32px;">
<p style="margin:0 0 20px;font-size:16px;line-height:1.55;color:#2c3338;">%s</p>
%s
<table role="presentation" width="100%%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">%s%s</table>
<p style="margin:24px 0 0;font-size:13px;color:#646970;line-height:1.5;">%s</p>
</div>
</div>
</td></tr></table>
</body></html>',
			esc_html__( 'Daily report card', 'kennelflow-boarding' ),
			esc_html(
				sprintf(
					/* translators: %s: pet name */
					__( 'How %s is doing', 'kennelflow-boarding' ),
					$pet_name
				)
			),
			esc_html( $intro ),
			$photo_block,
			$blocks,
			$notes_section,
			esc_html( $footer )
		);

		return $html;
	}
}
