<?php
/**
 * Custom database tables (dbDelta) for audit, transactional logging, and booking index.
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_Install
 */
class KennelFlow_Boarding_Install {

	/**
	 * Schema version for migrations.
	 */
	const DB_VERSION = '4';

	/**
	 * Option key storing installed DB version.
	 */
	const OPTION_DB_VERSION = 'kennelflow_boarding_db_version';

	/**
	 * Legacy option key (Kennel Press).
	 */
	const OPTION_DB_VERSION_LEGACY = 'kennelpress_db_version';

	/**
	 * Run on activation and when schema needs upgrade.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$prev_version    = get_option( self::OPTION_DB_VERSION, get_option( self::OPTION_DB_VERSION_LEGACY, '' ) );

		$audit_table = self::audit_table();

		$sql_audit = "CREATE TABLE {$audit_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			entity_type varchar(32) NOT NULL,
			entity_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			action varchar(32) NOT NULL,
			old_value longtext NULL,
			new_value longtext NULL,
			created_gmt datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY entity (entity_type, entity_id),
			KEY created_gmt (created_gmt),
			KEY user_id (user_id)
		) {$charset_collate};";

		dbDelta( $sql_audit );

		$bookings_table = self::bookings_table();

		$sql_bookings = "CREATE TABLE {$bookings_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			pet_id bigint(20) unsigned NOT NULL DEFAULT 0,
			kennel_id bigint(20) unsigned NOT NULL DEFAULT 0,
			location_id bigint(20) unsigned NOT NULL DEFAULT 0,
			start_gmt datetime NOT NULL,
			end_gmt datetime NOT NULL,
			status varchar(32) NOT NULL DEFAULT 'pending',
			booking_kind varchar(32) NOT NULL DEFAULT '',
			created_gmt datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY post_id (post_id),
			KEY location_id (location_id),
			KEY start_gmt (start_gmt),
			KEY end_gmt (end_gmt),
			KEY kennel_time (kennel_id, start_gmt, end_gmt),
			KEY status_created (status, created_gmt)
		) {$charset_collate};";

		dbDelta( $sql_bookings );

		$session_media = $wpdb->prefix . 'kennelflow_boarding_session_media';
		$sql_session   = "CREATE TABLE {$session_media} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			booking_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			media_kind varchar(32) NOT NULL DEFAULT 'check_in',
			attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
			staff_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_gmt datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY booking_post_id (booking_post_id)
		) {$charset_collate};";

		dbDelta( $sql_session );

		update_option( self::OPTION_DB_VERSION, self::DB_VERSION );
		delete_option( self::OPTION_DB_VERSION_LEGACY );

		if ( version_compare( (string) $prev_version, '2', '<' ) ) {
			KennelFlow_Boarding_Booking_Index::backfill_all();
		}

		if ( version_compare( (string) $prev_version, '3', '<' ) ) {
			KennelFlow_Boarding_Booking_Index::backfill_all();
		}
	}

	/**
	 * Table name for immutable audit rows.
	 *
	 * @return string
	 */
	public static function audit_table() {
		global $wpdb;
		return $wpdb->prefix . 'kennelpress_audit_log';
	}

	/**
	 * Fast booking index: mirrors overlapping-query fields from kennelpress_booking posts.
	 *
	 * Full name is typically wp_kf_bookings when $table_prefix is wp_.
	 *
	 * @return string
	 */
	public static function bookings_table() {
		global $wpdb;
		return $wpdb->prefix . 'kf_bookings';
	}

	/**
	 * Upgrade if needed.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		self::migrate_options_from_kennelpress();
		$v = get_option( self::OPTION_DB_VERSION, '' );
		if ( '' === $v ) {
			$v = get_option( self::OPTION_DB_VERSION_LEGACY, '' );
		}
		if ( self::DB_VERSION !== $v ) {
			self::install();
		}
	}

	/**
	 * Copy options from pre-rename “Kennel Press” keys.
	 *
	 * @return void
	 */
	public static function migrate_options_from_kennelpress() {
		$dbv = get_option( self::OPTION_DB_VERSION, '' );
		$old = get_option( self::OPTION_DB_VERSION_LEGACY, '' );
		if ( '' === $dbv && '' !== $old ) {
			update_option( self::OPTION_DB_VERSION, $old );
		}

		$val = get_option( KennelFlow_Boarding_Facility_Settings::OPTION_KEY, null );
		if ( null === $val ) {
			$legacy_facility = get_option( KennelFlow_Boarding_Facility_Settings::OPTION_KEY_LEGACY, null );
			if ( null !== $legacy_facility && false !== $legacy_facility ) {
				update_option( KennelFlow_Boarding_Facility_Settings::OPTION_KEY, $legacy_facility );
			}
		}

		$bust = get_option( 'kennelflow_boarding_query_cache_bust', null );
		if ( null === $bust ) {
			$legacy_bust = get_option( 'kennelpress_query_cache_bust', false );
			if ( false !== $legacy_bust ) {
				update_option( 'kennelflow_boarding_query_cache_bust', $legacy_bust );
			}
		}

		$pwa = get_option( 'kennelflow_boarding_pwa_rewrite_rules_version', null );
		if ( null === $pwa ) {
			$legacy_pwa = get_option( 'kennelpress_pwa_rewrite_rules_version', false );
			if ( false !== $legacy_pwa ) {
				update_option( 'kennelflow_boarding_pwa_rewrite_rules_version', $legacy_pwa );
			}
		}
	}
}
