<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package KennelFlow_Boarding
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Only delete data if the site owner explicitly opted in.
if ( ! get_option( 'kennelflow_boarding_delete_data_on_uninstall' ) ) {
	// Always remove the opt-in flag itself and version markers (non-clinical config).
	delete_option( 'kennelflow_boarding_db_version' );
	delete_option( 'kennelpress_db_version' );
	return;
}

// Drop custom tables.
$kennelflow_boarding_tables = array(
	$wpdb->prefix . 'kennelpress_audit_log',
	$wpdb->prefix . 'kf_bookings',
	$wpdb->prefix . 'kennelflow_boarding_session_media',
);
foreach ( $kennelflow_boarding_tables as $kennelflow_boarding_table ) {
	$wpdb->query( "DROP TABLE IF EXISTS `" . esc_sql( $kennelflow_boarding_table ) . "`" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table names are internal constants, not user input.
}

// Remove plugin options.
delete_option( 'kennelflow_boarding_db_version' );
delete_option( 'kennelflow_boarding_facility_settings' );
delete_option( 'kennelflow_boarding_query_cache_bust' );
delete_option( 'kennelflow_boarding_pwa_rewrite_rules_version' );
delete_option( 'kennelflow_boarding_delete_data_on_uninstall' );
delete_option( 'ltkf_locations_list_bust' );
// Legacy option keys from kennelpress era.
delete_option( 'kennelpress_db_version' );
delete_option( 'kennelpress_facility_settings' );
delete_option( 'kennelpress_query_cache_bust' );
delete_option( 'kennelpress_pwa_rewrite_rules_version' );
