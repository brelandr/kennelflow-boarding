<?php
/**
 * Sends care sheet confirmation email to the pet owner when staff requests it.
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_Care_Sheet_Emailer
 */
class KennelFlow_Boarding_Care_Sheet_Emailer {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'kennelpress_booking_saved', array( __CLASS__, 'maybe_send_after_save' ), 10, 3 );
		add_action( 'kennelflow_boarding_booking_saved', array( __CLASS__, 'maybe_send_after_save' ), 10, 3 );
	}

	/**
	 * Whether the REST payload asked to email the owner.
	 *
	 * @param mixed $val Raw param.
	 * @return bool
	 */
	protected static function is_send_flag_truthy( $val ) {
		if ( true === $val ) {
			return true;
		}
		if ( is_string( $val ) ) {
			$v = strtolower( trim( $val ) );
			return '1' === $v || 'true' === $v || 'yes' === $v;
		}
		if ( is_int( $val ) ) {
			return 1 === $val;
		}
		return false;
	}

	/**
	 * After POST/PATCH booking: optionally email the owner.
	 *
	 * @param int    $booking_id Booking post ID.
	 * @param array  $params     Request params (may include send_care_sheet_email).
	 * @param string $context    create|update.
	 * @return void
	 */
	public static function maybe_send_after_save( $booking_id, $params, $context ) {
		unset( $context );
		$booking_id = absint( $booking_id );
		if ( $booking_id < 1 || ! is_array( $params ) ) {
			return;
		}
		if ( ! self::is_send_flag_truthy( $params['send_care_sheet_email'] ?? false ) ) {
			return;
		}

		if ( 'kennelpress_booking' !== get_post_type( $booking_id ) ) {
			return;
		}

		$kind = KennelFlow_Boarding_Post_Meta::sanitize_booking_kind(
			(string) get_post_meta( $booking_id, KennelFlow_Boarding_Post_Meta::BOOKING_KIND, true )
		);
		if ( 'boarding' !== $kind ) {
			return;
		}

		$pet_id = (int) get_post_meta( $booking_id, KennelFlow_Boarding_Post_Meta::BOOKING_PET_ID, true );
		if ( $pet_id < 1 || 'kf_pet' !== get_post_type( $pet_id ) ) {
			return;
		}

		if ( ! function_exists( 'ltkf_get_pet_owner_user_id' ) ) {
			return;
		}

		$owner_id = (int) ltkf_get_pet_owner_user_id( $pet_id );
		if ( $owner_id < 1 ) {
			return;
		}

		$user = get_userdata( $owner_id );
		if ( ! $user || ! is_email( $user->user_email ) ) {
			return;
		}

		$pet_name = get_the_title( $pet_id );
		if ( '' === trim( (string) $pet_name ) ) {
			$pet_name = __( 'Your pet', 'kennelflow-boarding' );
		}

		$allergies_key = function_exists( 'ltkf_get_pet_meta_key_allergies' )
			? ltkf_get_pet_meta_key_allergies()
			: 'kf_allergies';
		$allergies     = trim( (string) get_post_meta( $pet_id, $allergies_key, true ) );

		$diet   = (string) get_post_meta( $booking_id, KennelFlow_Boarding_Post_Meta::BOOKING_STAY_DIET, true );
		$meds   = (string) get_post_meta( $booking_id, KennelFlow_Boarding_Post_Meta::BOOKING_STAY_MEDICATION_NOTES, true );
		$belong = (string) get_post_meta( $booking_id, KennelFlow_Boarding_Post_Meta::BOOKING_STAY_BELONGINGS, true );

		$subject = sprintf(
			/* translators: %s: pet name */
			__( 'Care Instructions Confirmed for %s', 'kennelflow-boarding' ),
			$pet_name
		);

		$body = self::build_html_body( $pet_name, $allergies, $diet, $meds, $belong );

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		$sent = wp_mail( $user->user_email, $subject, $body, $headers );

		if ( $sent ) {
			self::maybe_log_kennelflow_vet_audit( $booking_id, $pet_id, $user->user_email );
		}
	}

	/**
	 * Rich HTML body for wp_mail.
	 *
	 * @param string $pet_name  Pet display name.
	 * @param string $allergies Permanent allergies text.
	 * @param string $diet      Stay diet.
	 * @param string $meds      Stay medication notes.
	 * @param string $belong    Belongings.
	 * @return string
	 */
	protected static function build_html_body( $pet_name, $allergies, $diet, $meds, $belong ) {
		$intro = __( 'Please review the care instructions for your upcoming stay. If anything has changed, please reply to this email or call us.', 'kennelflow-boarding' );

		$sections = array(
			array(
				'label' => __( 'Allergies (on file)', 'kennelflow-boarding' ),
				'value' => '' !== $allergies ? $allergies : __( '—', 'kennelflow-boarding' ),
			),
			array(
				'label' => __( 'Stay diet instructions', 'kennelflow-boarding' ),
				'value' => '' !== $diet ? $diet : __( '—', 'kennelflow-boarding' ),
			),
			array(
				'label' => __( 'Stay medication notes', 'kennelflow-boarding' ),
				'value' => '' !== $meds ? $meds : __( '—', 'kennelflow-boarding' ),
			),
			array(
				'label' => __( 'Belongings brought', 'kennelflow-boarding' ),
				'value' => '' !== $belong ? $belong : __( '—', 'kennelflow-boarding' ),
			),
		);

		$blocks = '';
		foreach ( $sections as $row ) {
			$blocks .= sprintf(
				'<tr><td style="padding:12px 0;border-bottom:1px solid #dcdcde;vertical-align:top;"><strong style="display:block;color:#1d2327;font-size:13px;text-transform:uppercase;letter-spacing:0.04em;">%s</strong><div style="margin-top:8px;color:#1d2327;font-size:16px;line-height:1.45;white-space:pre-wrap;">%s</div></td></tr>',
				esc_html( $row['label'] ),
				esc_html( $row['value'] )
			);
		}

		$html = sprintf(
			'<html><body style="margin:0;padding:24px;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;background:#f6f7f7;color:#1d2327;">
<div style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #c3c4c7;border-radius:6px;padding:24px 28px;">
<p style="margin:0 0 20px;font-size:17px;line-height:1.5;">%s</p>
<h1 style="margin:0 0 16px;font-size:22px;font-weight:700;">%s</h1>
<table role="presentation" width="100%%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">%s</table>
<p style="margin:20px 0 0;font-size:13px;color:#646970;">%s</p>
</div></body></html>',
			esc_html( $intro ),
			esc_html(
				sprintf(
					/* translators: %s: pet name */
					__( 'Care summary for %s', 'kennelflow-boarding' ),
					$pet_name
				)
			),
			$blocks,
			esc_html(
				sprintf(
					/* translators: %s: site name */
					__( 'This message was sent from %s.', 'kennelflow-boarding' ),
					wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
				)
			)
		);

		return $html;
	}

	/**
	 * Append to KennelFlow Vet audit log when the schema is present.
	 *
	 * @param int    $booking_id Booking post ID.
	 * @param int    $pet_id     Pet post ID.
	 * @param string $to_email   Recipient email.
	 * @return void
	 */
	protected static function maybe_log_kennelflow_vet_audit( $booking_id, $pet_id, $to_email ) {
		global $wpdb;

		$booking_id = absint( $booking_id );
		$pet_id     = absint( $pet_id );
		if ( $booking_id < 1 || $pet_id < 1 || ! class_exists( 'KennelFlow_Vet_Install' ) ) {
			return;
		}

		$table = KennelFlow_Vet_Install::audit_table();
		if ( ! function_exists( 'ltkf_table_exists' ) || ! ltkf_table_exists( $table ) ) {
			return;
		}

		$user_id = get_current_user_id();
		if ( $user_id < 1 ) {
			$user_id = 0;
		}

		$payload  = array(
			'to_email'   => $to_email,
			'pet_id'     => $pet_id,
			'booking_id' => $booking_id,
		);
		$new_json = wp_json_encode( $payload );
		if ( false === $new_json ) {
			$new_json = '';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Optional audit append when KennelFlow Vet tables exist.
		$wpdb->insert(
			$table,
			array(
				'entity_type' => 'kennelpress_booking',
				'entity_id'   => $booking_id,
				'pet_post_id' => $pet_id,
				'user_id'     => $user_id,
				'action'      => 'care_sheet_owner_email',
				'old_value'   => '',
				'new_value'   => $new_json,
				'created_gmt' => current_time( 'mysql', true ),
			),
			array( '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);
	}
}
