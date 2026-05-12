<?php
/**
 * Per-location kennel rules (hours, holidays, blackouts).
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_Facility_Settings
 */
class KennelFlow_Boarding_Facility_Settings {

	const OPTION_KEY = 'kennelflow_boarding_facility_settings';

	/**
	 * @var string
	 */
	const OPTION_KEY_LEGACY = 'kennelpress_facility_settings';

	/**
	 * Weekday keys (Monday–Sunday).
	 *
	 * @return string[]
	 */
	public static function weekday_keys() {
		return array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' );
	}

	/**
	 * Default structure for one location (all rules off until enabled).
	 *
	 * @return array<string,mixed>
	 */
	public static function default_location_settings() {
		$weekly = array();
		foreach ( self::weekday_keys() as $k ) {
			$weekly[ $k ] = array(
				'closed' => false,
				'open'   => '09:00',
				'close'  => '17:00',
			);
		}
		$boarding_daily = array();
		foreach ( self::weekday_keys() as $k ) {
			$boarding_daily[ $k ] = array(
				'closed'     => false,
				'drop_start' => '07:00',
				'drop_end'   => '10:00',
				'pick_start' => '12:00',
				'pick_end'   => '18:00',
				'ext_end'    => '',
			);
		}

		return array(
			'enabled'                                  => false,
			'timezone'                                 => self::default_timezone_string(),
			'weekly'                                   => $weekly,
			'holidays'                                 => array(),
			'blackouts'                                => array(),
			'boarding_rules_enabled'                   => false,
			'boarding_daily'                           => $boarding_daily,
			'boarding_extended_hours_enabled'          => false,
			'boarding_extended_fee'                    => 0,
			'boarding_charge_extra_day_after_extended' => true,
			'boarding_emergency_drop_enabled'          => false,
			'boarding_emergency_drop_fee'              => 0,
			'boarding_daily_fee'                       => 0,
			'boarding_fee_small'                       => 0,
			'boarding_fee_medium'                      => 0,
			'boarding_fee_large'                       => 0,
			'boarding_additional_pet_fee'              => 0,
			'boarding_food_enabled'                    => false,
			'boarding_food_fee'                        => 0,
			'boarding_discount_tiers'                  => array(),
			'boarding_price_application'               => 'quote_only',
			'boarding_wc_product_id'                   => 0,
			'boarding_wc_variation_small'              => 0,
			'boarding_wc_variation_medium'             => 0,
			'boarding_wc_variation_large'              => 0,
			'boarding_intake_form_enabled'             => false,
			'boarding_interview_enabled'               => false,
			'boarding_interview_instructions'          => '',
		);
	}

	/**
	 * Site default timezone string.
	 *
	 * @return string
	 */
	public static function default_timezone_string() {
		$s = (string) wp_timezone_string();
		if ( '' === $s ) {
			return 'UTC';
		}
		return $s;
	}

	/**
	 * Full option array (all locations).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_all_raw() {
		$raw = get_option( self::OPTION_KEY, null );
		if ( ! is_array( $raw ) ) {
			$raw = get_option( self::OPTION_KEY_LEGACY, array() );
		}
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Settings for one Hub location post (merged with defaults).
	 *
	 * @param int $location_post_id Hub `kf_location` post ID.
	 * @return array<string,mixed>
	 */
	public static function get_for_location( $location_post_id ) {
		$location_post_id = absint( $location_post_id );
		$all              = self::get_all_raw();
		$base             = self::default_location_settings();
		if ( $location_post_id < 1 || empty( $all[ $location_post_id ] ) || ! is_array( $all[ $location_post_id ] ) ) {
			return $base;
		}
		return array_replace_recursive( $base, self::sanitize_location_payload( $all[ $location_post_id ] ) );
	}

	/**
	 * Replace stored settings for one location.
	 *
	 * @param int   $location_post_id Hub `kf_location` post ID.
	 * @param array $payload          Raw payload.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function update_location( $location_post_id, $payload ) {
		$location_post_id = absint( $location_post_id );
		if ( $location_post_id < 1 ) {
			return new WP_Error( 'kennelpress_bad_location', __( 'Invalid location.', 'kennelflow-boarding' ), array( 'status' => 400 ) );
		}
		$post = get_post( $location_post_id );
		$pt   = function_exists( 'ltkf_get_location_post_type' ) ? ltkf_get_location_post_type() : 'kf_location';
		if ( ! $post instanceof WP_Post || $pt !== $post->post_type ) {
			return new WP_Error( 'kennelpress_unknown_location', __( 'Unknown location.', 'kennelflow-boarding' ), array( 'status' => 404 ) );
		}

		$sanitized                = self::sanitize_location_payload( $payload );
		$all                      = self::get_all_raw();
		$all[ $location_post_id ] = $sanitized;
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- option array update.
		update_option( self::OPTION_KEY, $all, false );

		return self::get_for_location( $location_post_id );
	}

	/**
	 * Sanitize and merge onto defaults.
	 *
	 * @param array $payload Raw.
	 * @return array<string,mixed>
	 */
	public static function sanitize_location_payload( $payload ) {
		if ( ! is_array( $payload ) ) {
			return self::default_location_settings();
		}

		$defaults = self::default_location_settings();
		$out      = $defaults;

		if ( isset( $payload['enabled'] ) ) {
			$out['enabled'] = (bool) $payload['enabled'];
		}

		if ( isset( $payload['timezone'] ) ) {
			$tz = sanitize_text_field( (string) $payload['timezone'] );
			if ( self::is_valid_timezone( $tz ) ) {
				$out['timezone'] = $tz;
			}
		}

		if ( isset( $payload['weekly'] ) && is_array( $payload['weekly'] ) ) {
			foreach ( self::weekday_keys() as $day ) {
				if ( empty( $payload['weekly'][ $day ] ) || ! is_array( $payload['weekly'][ $day ] ) ) {
					continue;
				}
				$row = $payload['weekly'][ $day ];
				if ( isset( $row['closed'] ) ) {
					$out['weekly'][ $day ]['closed'] = (bool) $row['closed'];
				}
				if ( isset( $row['open'] ) ) {
					$t = self::sanitize_hhmm( $row['open'] );
					if ( null !== $t ) {
						$out['weekly'][ $day ]['open'] = $t;
					}
				}
				if ( isset( $row['close'] ) ) {
					$t = self::sanitize_hhmm( $row['close'] );
					if ( null !== $t ) {
						$out['weekly'][ $day ]['close'] = $t;
					}
				}
			}
		}

		if ( isset( $payload['holidays'] ) && is_array( $payload['holidays'] ) ) {
			$h = array();
			foreach ( $payload['holidays'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$hs = isset( $row['start'] ) ? self::sanitize_ymd( $row['start'] ) : '';
				$he = isset( $row['end'] ) ? self::sanitize_ymd( $row['end'] ) : '';
				if ( '' === $hs || '' === $he ) {
					continue;
				}
				if ( strcmp( $hs, $he ) > 0 ) {
					$t  = $hs;
					$hs = $he;
					$he = $t;
				}
				$h[] = array(
					'start' => $hs,
					'end'   => $he,
					'label' => isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '',
				);
			}
			$out['holidays'] = $h;
		}

		if ( isset( $payload['blackouts'] ) && is_array( $payload['blackouts'] ) ) {
			$b = array();
			foreach ( $payload['blackouts'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$sg = isset( $row['start_gmt'] ) ? self::sanitize_gmt_mysql( $row['start_gmt'] ) : '';
				$eg = isset( $row['end_gmt'] ) ? self::sanitize_gmt_mysql( $row['end_gmt'] ) : '';
				if ( '' === $sg || '' === $eg ) {
					continue;
				}
				$b[] = array(
					'start_gmt' => $sg,
					'end_gmt'   => $eg,
					'label'     => isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '',
				);
			}
			$out['blackouts'] = $b;
		}

		$out = self::sanitize_boarding_payload( $payload, $out );

		return $out;
	}

	/**
	 * Merge boarding-specific keys from payload.
	 *
	 * @param array<string,mixed> $payload Raw.
	 * @param array<string,mixed> $out     Current sanitized row.
	 * @return array<string,mixed>
	 */
	protected static function sanitize_boarding_payload( $payload, array $out ) {
		if ( ! is_array( $payload ) ) {
			return $out;
		}

		$bool_keys = array(
			'boarding_rules_enabled',
			'boarding_extended_hours_enabled',
			'boarding_charge_extra_day_after_extended',
			'boarding_emergency_drop_enabled',
			'boarding_food_enabled',
			'boarding_intake_form_enabled',
			'boarding_interview_enabled',
		);
		foreach ( $bool_keys as $bk ) {
			if ( array_key_exists( $bk, $payload ) ) {
				$out[ $bk ] = (bool) $payload[ $bk ];
			}
		}

		$float_keys = array(
			'boarding_extended_fee',
			'boarding_emergency_drop_fee',
			'boarding_daily_fee',
			'boarding_fee_small',
			'boarding_fee_medium',
			'boarding_fee_large',
			'boarding_additional_pet_fee',
			'boarding_food_fee',
		);
		foreach ( $float_keys as $fk ) {
			if ( array_key_exists( $fk, $payload ) ) {
				$out[ $fk ] = round( (float) $payload[ $fk ], 2 );
			}
		}

		$int_keys = array(
			'boarding_wc_product_id',
			'boarding_wc_variation_small',
			'boarding_wc_variation_medium',
			'boarding_wc_variation_large',
		);
		foreach ( $int_keys as $ik ) {
			if ( array_key_exists( $ik, $payload ) ) {
				$out[ $ik ] = absint( $payload[ $ik ] );
			}
		}

		if ( isset( $payload['boarding_price_application'] ) ) {
			$apm = sanitize_key( (string) $payload['boarding_price_application'] );
			if ( in_array( $apm, array( 'quote_only', 'woocommerce', 'both' ), true ) ) {
				$out['boarding_price_application'] = $apm;
			}
		}

		if ( isset( $payload['boarding_interview_instructions'] ) ) {
			$out['boarding_interview_instructions'] = wp_kses_post( (string) $payload['boarding_interview_instructions'] );
		}

		if ( isset( $payload['boarding_discount_tiers'] ) && is_array( $payload['boarding_discount_tiers'] ) ) {
			$tiers = array();
			foreach ( $payload['boarding_discount_tiers'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$min = isset( $row['min_nights'] ) ? absint( $row['min_nights'] ) : 0;
				if ( $min < 1 ) {
					continue;
				}
				$type = isset( $row['type'] ) ? sanitize_key( (string) $row['type'] ) : 'percent';
				if ( ! in_array( $type, array( 'percent', 'flat' ), true ) ) {
					$type = 'percent';
				}
				$tiers[] = array(
					'min_nights' => $min,
					'type'       => $type,
					'value'      => round( (float) ( isset( $row['value'] ) ? $row['value'] : 0 ), 4 ),
				);
			}
			$out['boarding_discount_tiers'] = array_slice( $tiers, 0, 20 );
		}

		if ( isset( $payload['boarding_daily'] ) && is_array( $payload['boarding_daily'] ) ) {
			foreach ( self::weekday_keys() as $day ) {
				if ( empty( $payload['boarding_daily'][ $day ] ) || ! is_array( $payload['boarding_daily'][ $day ] ) ) {
					continue;
				}
				$row = $payload['boarding_daily'][ $day ];
				if ( isset( $row['closed'] ) ) {
					$out['boarding_daily'][ $day ]['closed'] = (bool) $row['closed'];
				}
				foreach ( array( 'drop_start', 'drop_end', 'pick_start', 'pick_end' ) as $hk ) {
					if ( ! isset( $row[ $hk ] ) ) {
						continue;
					}
					$t = self::sanitize_hhmm( $row[ $hk ] );
					if ( null !== $t ) {
						$out['boarding_daily'][ $day ][ $hk ] = $t;
					}
				}
				if ( array_key_exists( 'ext_end', $row ) ) {
					if ( '' === (string) $row['ext_end'] ) {
						$out['boarding_daily'][ $day ]['ext_end'] = '';
					} else {
						$te                                       = self::sanitize_hhmm( $row['ext_end'] );
						$out['boarding_daily'][ $day ]['ext_end'] = ( null !== $te ) ? $te : '';
					}
				}
			}
		}

		return $out;
	}

	/**
	 * @param mixed $v Raw.
	 * @return string|null
	 */
	protected static function sanitize_hhmm( $v ) {
		$s = sanitize_text_field( (string) $v );
		if ( preg_match( '/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $s ) ) {
			return substr( $s, 0, 5 );
		}
		return null;
	}

	/**
	 * @param mixed $v Raw.
	 * @return string
	 */
	protected static function sanitize_ymd( $v ) {
		$s = sanitize_text_field( (string) $v );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $s ) ) {
			return '';
		}
		return $s;
	}

	/**
	 * @param mixed $v Raw.
	 * @return string
	 */
	protected static function sanitize_gmt_mysql( $v ) {
		$r = KennelFlow_Boarding_Availability::parse_gmt_mysql( $v );
		if ( is_wp_error( $r ) ) {
			return '';
		}
		return $r;
	}

	/**
	 * @param string $tz Timezone string.
	 * @return bool
	 */
	protected static function is_valid_timezone( $tz ) {
		if ( '' === $tz || 'UTC' === $tz ) {
			return true;
		}
		try {
			new DateTimeZone( $tz );
			return true;
		} catch ( Exception $e ) {
			unset( $e );
			return false;
		}
	}

	/**
	 * Validate boarding interval against rules (drop-off / pick-up local times, holidays, blackouts).
	 *
	 * @param int    $location_post_id Hub `kf_location` post ID.
	 * @param string $start_gmt        Start UTC.
	 * @param string $end_gmt          End UTC.
	 * @return true|WP_Error
	 */
	public static function validate_booking_interval( $location_post_id, $start_gmt, $end_gmt ) {
		$location_post_id = absint( $location_post_id );
		if ( $location_post_id < 1 ) {
			return true;
		}

		$settings = self::get_for_location( $location_post_id );
		if ( empty( $settings['enabled'] ) ) {
			return true;
		}

		$interval = KennelFlow_Boarding_Availability::validate_interval( $start_gmt, $end_gmt );
		if ( is_wp_error( $interval ) ) {
			return $interval;
		}

		/**
		 * Allow extensions to validate or skip kennel rules.
		 *
		 * @since 0.1.0
		 *
		 * @param true|WP_Error $result           Default pass.
		 * @param int           $location_post_id Hub location post ID.
		 * @param string        $start_gmt        Start UTC.
		 * @param string        $end_gmt          End UTC.
		 * @param array         $settings         Sanitized settings.
		 */
		$pre = kennelflow_boarding_apply_filters( 'facility_validate_interval', true, $location_post_id, $start_gmt, $end_gmt, $settings );
		if ( is_wp_error( $pre ) ) {
			return $pre;
		}
		if ( true !== $pre ) {
			return true;
		}

		try {
			$tz = new DateTimeZone( $settings['timezone'] );
		} catch ( Exception $e ) {
			unset( $e );
			$tz = new DateTimeZone( 'UTC' );
		}

		$start_utc = new DateTimeImmutable( $start_gmt, new DateTimeZone( 'UTC' ) );
		$end_utc   = new DateTimeImmutable( $end_gmt, new DateTimeZone( 'UTC' ) );

		foreach ( $settings['blackouts'] as $b ) {
			if ( empty( $b['start_gmt'] ) || empty( $b['end_gmt'] ) ) {
				continue;
			}
			$bs = new DateTimeImmutable( $b['start_gmt'], new DateTimeZone( 'UTC' ) );
			$be = new DateTimeImmutable( $b['end_gmt'], new DateTimeZone( 'UTC' ) );
			if ( $start_utc < $be && $bs < $end_utc ) {
				return new WP_Error(
					'kennelpress_facility_blackout',
					__( 'That time range is blocked for this location.', 'kennelflow-boarding' ),
					array( 'status' => 409 )
				);
			}
		}

		$start_local = $start_utc->setTimezone( $tz );
		$end_local   = $end_utc->setTimezone( $tz );
		$ds          = $start_local->format( 'Y-m-d' );
		$de          = $end_local->format( 'Y-m-d' );
		$cursor      = new DateTimeImmutable( $ds . ' 00:00:00', $tz );
		$last_day    = new DateTimeImmutable( $de . ' 00:00:00', $tz );
		$guard       = 0;
		while ( $cursor <= $last_day && $guard < 370 ) {
			$d = $cursor->format( 'Y-m-d' );
			foreach ( $settings['holidays'] as $h ) {
				if ( empty( $h['start'] ) || empty( $h['end'] ) ) {
					continue;
				}
				if ( strcmp( $d, $h['start'] ) >= 0 && strcmp( $d, $h['end'] ) <= 0 ) {
					return new WP_Error(
						'kennelpress_facility_holiday',
						__( 'That stay touches a holiday closure for this location.', 'kennelflow-boarding' ),
						array( 'status' => 409 )
					);
				}
			}
			$cursor = $cursor->modify( '+1 day' );
			++$guard;
		}

		$keys = array( 'sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat' );

		$dow_s = (int) $start_local->format( 'w' );
		$day_s = $keys[ $dow_s ];
		$dow_e = (int) $end_local->format( 'w' );
		$day_e = $keys[ $dow_e ];

		if ( ! empty( $settings['enabled'] ) && ! empty( $settings['boarding_rules_enabled'] ) ) {
			$bd_s = isset( $settings['boarding_daily'][ $day_s ] ) && is_array( $settings['boarding_daily'][ $day_s ] )
				? $settings['boarding_daily'][ $day_s ]
				: array();
			$bd_e = isset( $settings['boarding_daily'][ $day_e ] ) && is_array( $settings['boarding_daily'][ $day_e ] )
				? $settings['boarding_daily'][ $day_e ]
				: array();
			$err  = self::check_boarding_drop_window( $bd_s, $start_local, $settings );
			if ( is_wp_error( $err ) ) {
				return $err;
			}
			$err = self::check_boarding_pick_window( $bd_e, $end_local, $settings );
			if ( is_wp_error( $err ) ) {
				return $err;
			}
		} else {
			$err = self::check_weekly_instant( $settings['weekly'][ $day_s ], $start_local, 'start' );
			if ( is_wp_error( $err ) ) {
				return $err;
			}
			$err = self::check_weekly_instant( $settings['weekly'][ $day_e ], $end_local, 'end' );
			if ( is_wp_error( $err ) ) {
				return $err;
			}
		}

		return true;
	}

	/**
	 * Validate drop-off local instant against boarding windows.
	 *
	 * @param array             $bd       Day row from boarding_daily.
	 * @param DateTimeImmutable $local    Local instant.
	 * @param array             $settings Full settings.
	 * @return true|WP_Error
	 */
	protected static function check_boarding_drop_window( $bd, $local, $settings ) {
		if ( ! is_array( $bd ) ) {
			$bd = array();
		}
		if ( ! empty( $bd['closed'] ) ) {
			return new WP_Error(
				'kennelpress_boarding_closed',
				__( 'The facility is closed on the drop-off day.', 'kennelflow-boarding' ),
				array( 'status' => 409 )
			);
		}

		$ds = isset( $bd['drop_start'] ) ? self::sanitize_hhmm( $bd['drop_start'] ) : null;
		$de = isset( $bd['drop_end'] ) ? self::sanitize_hhmm( $bd['drop_end'] ) : null;
		if ( null === $ds ) {
			$ds = '07:00';
		}
		if ( null === $de ) {
			$de = '10:00';
		}

		$dsm = self::hhmm_to_minutes( $ds );
		$dem = self::hhmm_to_minutes( $de );
		$m   = (int) $local->format( 'H' ) * 60 + (int) $local->format( 'i' );

		if ( $m >= $dsm && $m <= $dem ) {
			return true;
		}

		if ( ! empty( $settings['boarding_emergency_drop_enabled'] ) && $m < $dsm ) {
			return true;
		}

		return new WP_Error(
			'kennelpress_boarding_drop',
			__( 'Drop-off is outside the configured boarding window.', 'kennelflow-boarding' ),
			array( 'status' => 409 )
		);
	}

	/**
	 * Validate pick-up local instant against boarding windows.
	 *
	 * @param array             $bd       Day row.
	 * @param DateTimeImmutable $local    Local instant.
	 * @param array             $settings Full settings.
	 * @return true|WP_Error
	 */
	protected static function check_boarding_pick_window( $bd, $local, $settings ) {
		if ( ! is_array( $bd ) ) {
			$bd = array();
		}
		if ( ! empty( $bd['closed'] ) ) {
			return new WP_Error(
				'kennelpress_boarding_closed',
				__( 'The facility is closed on the pick-up day.', 'kennelflow-boarding' ),
				array( 'status' => 409 )
			);
		}

		$ps = isset( $bd['pick_start'] ) ? self::sanitize_hhmm( $bd['pick_start'] ) : null;
		$pe = isset( $bd['pick_end'] ) ? self::sanitize_hhmm( $bd['pick_end'] ) : null;
		if ( null === $ps ) {
			$ps = '12:00';
		}
		if ( null === $pe ) {
			$pe = '18:00';
		}

		$psm = self::hhmm_to_minutes( $ps );
		$pem = self::hhmm_to_minutes( $pe );
		$m   = (int) $local->format( 'H' ) * 60 + (int) $local->format( 'i' );

		if ( $m < $psm ) {
			return new WP_Error(
				'kennelpress_boarding_pick',
				__( 'Pick-up is before the configured pick-up window.', 'kennelflow-boarding' ),
				array( 'status' => 409 )
			);
		}

		if ( $m >= $psm && $m <= $pem ) {
			return true;
		}

		$allow_late = ! empty( $settings['boarding_charge_extra_day_after_extended'] );

		if ( ! empty( $settings['boarding_extended_hours_enabled'] ) ) {
			$ext_raw = isset( $bd['ext_end'] ) ? (string) $bd['ext_end'] : '';
			$ext     = '' !== $ext_raw ? self::sanitize_hhmm( $ext_raw ) : null;
			if ( null !== $ext ) {
				$extm = self::hhmm_to_minutes( $ext );
				if ( $m > $pem && $m <= $extm ) {
					return true;
				}
				if ( $m > $extm && $allow_late ) {
					return true;
				}
				if ( $m > $extm && ! $allow_late ) {
					return new WP_Error(
						'kennelpress_boarding_pick',
						__( 'Pick-up is after the latest allowed time for this location.', 'kennelflow-boarding' ),
						array( 'status' => 409 )
					);
				}
			}
		}

		if ( $m > $pem && $allow_late ) {
			return true;
		}

		if ( $m > $pem && ! $allow_late ) {
			return new WP_Error(
				'kennelpress_boarding_pick',
				__( 'Pick-up is outside the configured pick-up window.', 'kennelflow-boarding' ),
				array( 'status' => 409 )
			);
		}

		return true;
	}

	/**
	 * @param array             $daycfg Weekly row.
	 * @param DateTimeImmutable $local  Local instant.
	 * @param string            $which  start|end.
	 * @return true|WP_Error
	 */
	protected static function check_weekly_instant( $daycfg, $local, $which ) {
		if ( ! is_array( $daycfg ) ) {
			$daycfg = array(
				'closed' => false,
				'open'   => '09:00',
				'close'  => '17:00',
			);
		}
		if ( ! empty( $daycfg['closed'] ) ) {
			return new WP_Error(
				'kennelpress_facility_closed',
				'start' === $which
					? __( 'The facility is closed on the drop-off day.', 'kennelflow-boarding' )
					: __( 'The facility is closed on the pick-up day.', 'kennelflow-boarding' ),
				array( 'status' => 409 )
			);
		}

		$open_m  = self::hhmm_to_minutes( isset( $daycfg['open'] ) ? (string) $daycfg['open'] : '09:00' );
		$close_m = self::hhmm_to_minutes( isset( $daycfg['close'] ) ? (string) $daycfg['close'] : '17:00' );
		$now_m   = (int) $local->format( 'H' ) * 60 + (int) $local->format( 'i' );
		if ( $open_m > $close_m ) {
			return new WP_Error(
				'kennelpress_facility_hours',
				__( 'Invalid facility hours (open after close).', 'kennelflow-boarding' ),
				array( 'status' => 500 )
			);
		}
		if ( $now_m < $open_m || $now_m > $close_m ) {
			return new WP_Error(
				'kennelpress_facility_hours',
				'start' === $which
					? __( 'Drop-off is outside operating hours for this location.', 'kennelflow-boarding' )
					: __( 'Pick-up is outside operating hours for this location.', 'kennelflow-boarding' ),
				array( 'status' => 409 )
			);
		}
		return true;
	}

	/**
	 * @param string $hhmm HH:MM.
	 * @return int Minutes.
	 */
	protected static function hhmm_to_minutes( $hhmm ) {
		$parts = explode( ':', $hhmm );
		$h     = isset( $parts[0] ) ? (int) $parts[0] : 0;
		$m     = isset( $parts[1] ) ? (int) $parts[1] : 0;
		return max( 0, min( 24 * 60, $h * 60 + $m ) );
	}
}
