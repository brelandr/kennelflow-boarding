<?php
/**
 * Boarding price quote from facility settings (per Hub location post).
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_Boarding_Quote
 */
class KennelFlow_Boarding_Boarding_Quote {

	/**
	 * Build a quote for boarding.
	 *
	 * @param int                  $location_post_id Hub `kf_location` post ID.
	 * @param string               $start_gmt        Start UTC mysql.
	 * @param string               $end_gmt          End UTC mysql.
	 * @param array<string, mixed> $args             pet_size, pet_count, flags.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function build( $location_post_id, $start_gmt, $end_gmt, $args = array() ) {
		$location_post_id = absint( $location_post_id );
		if ( $location_post_id < 1 ) {
			return new WP_Error( 'kennelpress_bad_loc', __( 'Invalid location.', 'kennelflow-boarding' ), array( 'status' => 400 ) );
		}

		$s = KennelFlow_Boarding_Availability::parse_gmt_mysql( $start_gmt );
		if ( is_wp_error( $s ) ) {
			return $s;
		}
		$e = KennelFlow_Boarding_Availability::parse_gmt_mysql( $end_gmt );
		if ( is_wp_error( $e ) ) {
			return $e;
		}

		$settings = KennelFlow_Boarding_Facility_Settings::get_for_location( $location_post_id );

		$pet_size = isset( $args['pet_size'] ) ? sanitize_key( (string) $args['pet_size'] ) : 'medium';
		if ( ! in_array( $pet_size, array( 'small', 'medium', 'large' ), true ) ) {
			$pet_size = 'medium';
		}
		$pet_count = isset( $args['pet_count'] ) ? max( 1, absint( $args['pet_count'] ) ) : 1;
		$emergency = ! empty( $args['emergency_drop'] );
		$extended  = ! empty( $args['extended_pickup'] );
		$food      = ! empty( $args['kennel_food'] );

		try {
			$tz = new DateTimeZone( $settings['timezone'] );
		} catch ( Exception $ex ) {
			unset( $ex );
			$tz = new DateTimeZone( 'UTC' );
		}

		$start_local = ( new DateTimeImmutable( $s, new DateTimeZone( 'UTC' ) ) )->setTimezone( $tz );
		$end_local   = ( new DateTimeImmutable( $e, new DateTimeZone( 'UTC' ) ) )->setTimezone( $tz );

		$start_ts = $start_local->getTimestamp();
		$end_ts   = $end_local->getTimestamp();
		if ( $end_ts <= $start_ts ) {
			return new WP_Error( 'kennelpress_bad_interval', __( 'End must be after start.', 'kennelflow-boarding' ), array( 'status' => 400 ) );
		}

		$nights = (int) max( 1, ceil( ( $end_ts - $start_ts ) / DAY_IN_SECONDS ) );

		$base = (float) self::pick_fee_for_size( $settings, $pet_size );
		if ( $base <= 0 ) {
			$base = (float) ( isset( $settings['boarding_daily_fee'] ) ? $settings['boarding_daily_fee'] : 0 );
		}

		$line_items = array();
		$subtotal   = 0.0;

		$room_sub     = $base * $nights * $pet_count;
		$line_items[] = array(
			'code'  => 'room',
			'label' => __( 'Boarding', 'kennelflow-boarding' ),
			'qty'   => $nights * $pet_count,
			'unit'  => $base,
			'total' => round( $room_sub, 2 ),
		);
		$subtotal    += $room_sub;

		if ( $pet_count > 1 ) {
			$add_fee = (float) ( isset( $settings['boarding_additional_pet_fee'] ) ? $settings['boarding_additional_pet_fee'] : 0 );
			if ( $add_fee > 0 ) {
				$extra_pets   = $pet_count - 1;
				$ap           = $add_fee * $nights * $extra_pets;
				$line_items[] = array(
					'code'  => 'additional_pets',
					'label' => __( 'Additional pets (same stay)', 'kennelflow-boarding' ),
					'qty'   => $nights * $extra_pets,
					'unit'  => $add_fee,
					'total' => round( $ap, 2 ),
				);
				$subtotal    += $ap;
			}
		}

		if ( $emergency && ! empty( $settings['boarding_emergency_drop_enabled'] ) ) {
			$ef = (float) ( isset( $settings['boarding_emergency_drop_fee'] ) ? $settings['boarding_emergency_drop_fee'] : 0 );
			if ( $ef > 0 ) {
				$line_items[] = array(
					'code'  => 'emergency_drop',
					'label' => __( 'Emergency drop-off', 'kennelflow-boarding' ),
					'qty'   => 1,
					'unit'  => $ef,
					'total' => round( $ef, 2 ),
				);
				$subtotal    += $ef;
			}
		}

		if ( $extended && ! empty( $settings['boarding_extended_hours_enabled'] ) ) {
			$xf = (float) ( isset( $settings['boarding_extended_fee'] ) ? $settings['boarding_extended_fee'] : 0 );
			if ( $xf > 0 ) {
				$line_items[] = array(
					'code'  => 'extended_hours',
					'label' => __( 'Extended hours', 'kennelflow-boarding' ),
					'qty'   => 1,
					'unit'  => $xf,
					'total' => round( $xf, 2 ),
				);
				$subtotal    += $xf;
			}
		}

		if ( $food && ! empty( $settings['boarding_food_enabled'] ) ) {
			$ff = (float) ( isset( $settings['boarding_food_fee'] ) ? $settings['boarding_food_fee'] : 0 );
			if ( $ff > 0 ) {
				$ftotal       = $ff * $nights * $pet_count;
				$line_items[] = array(
					'code'  => 'kennel_food',
					'label' => __( 'Kennel-provided food', 'kennelflow-boarding' ),
					'qty'   => $nights * $pet_count,
					'unit'  => $ff,
					'total' => round( $ftotal, 2 ),
				);
				$subtotal    += $ftotal;
			}
		}

		$extra_after_ext = ! empty( $args['extra_day_after_extended'] );
		if ( $extra_after_ext && ! empty( $settings['boarding_charge_extra_day_after_extended'] ) ) {
			$xd_fee       = $base * $pet_count;
			$line_items[] = array(
				'code'  => 'late_pickup_day',
				'label' => __( 'Additional day (late pick-up)', 'kennelflow-boarding' ),
				'qty'   => 1,
				'unit'  => $xd_fee,
				'total' => round( $xd_fee, 2 ),
			);
			$subtotal    += $xd_fee;
		}

		$discount      = 0.0;
		$discount_note = '';
		$tiers         = isset( $settings['boarding_discount_tiers'] ) && is_array( $settings['boarding_discount_tiers'] ) ? $settings['boarding_discount_tiers'] : array();
		usort(
			$tiers,
			function ( $a, $b ) {
				$ma = isset( $a['min_nights'] ) ? (int) $a['min_nights'] : 0;
				$mb = isset( $b['min_nights'] ) ? (int) $b['min_nights'] : 0;
				return $mb <=> $ma;
			}
		);
		foreach ( $tiers as $tier ) {
			if ( ! is_array( $tier ) ) {
				continue;
			}
			$min_n = isset( $tier['min_nights'] ) ? (int) $tier['min_nights'] : 0;
			if ( $min_n < 1 || $nights < $min_n ) {
				continue;
			}
			$type  = isset( $tier['type'] ) ? sanitize_key( (string) $tier['type'] ) : 'percent';
			$value = isset( $tier['value'] ) ? (float) $tier['value'] : 0;
			if ( 'flat' === $type ) {
				$discount = min( $subtotal, $value );
			} else {
				$discount = round( $subtotal * min( 100, max( 0, $value ) ) / 100, 2 );
			}
			$discount_note = sprintf(
				/* translators: %d: minimum nights */
				__( 'Stay discount (%d+ nights)', 'kennelflow-boarding' ),
				$min_n
			);
			break;
		}

		$total = max( 0, round( $subtotal - $discount, 2 ) );

		$out = array(
			'location_id'       => $location_post_id,
			'nights'            => $nights,
			'pet_size'          => $pet_size,
			'pet_count'         => $pet_count,
			'subtotal'          => round( $subtotal, 2 ),
			'discount_total'    => round( $discount, 2 ),
			'discount_label'    => $discount_note,
			'total'             => $total,
			'line_items'        => $line_items,
			'price_application' => isset( $settings['boarding_price_application'] ) ? sanitize_key( (string) $settings['boarding_price_application'] ) : 'quote_only',
			'currency'          => '',
		);
		if ( function_exists( 'get_woocommerce_currency' ) ) {
			$out['currency'] = (string) get_woocommerce_currency();
		}
		if ( '' === $out['currency'] ) {
			$out['currency'] = 'USD';
		}

		return $out;
	}

	/**
	 * @param array  $settings Settings.
	 * @param string $size    small|medium|large.
	 * @return float
	 */
	protected static function pick_fee_for_size( $settings, $size ) {
		$key = 'boarding_fee_' . $size;
		if ( isset( $settings[ $key ] ) ) {
			$v = (float) $settings[ $key ];
			if ( $v > 0 ) {
				return $v;
			}
		}
		return (float) ( isset( $settings['boarding_daily_fee'] ) ? $settings['boarding_daily_fee'] : 0 );
	}
}
