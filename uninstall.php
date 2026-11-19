<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Safe uninstall: Does NOT delete submissions or settings unless the administrator
 * explicitly opted in via the "Delete all data upon uninstall" setting.
 *
 * @package RoseHallWeddings
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Check if venue administrator explicitly requested data deletion.
$delete_data = get_option( 'rhw_delete_data_on_uninstall', '0' );

if ( '1' === (string) $delete_data ) {
	global $wpdb;

	// Drop custom tables
	$forms_table       = $wpdb->prefix . 'rhw_forms';
	$submissions_table = $wpdb->prefix . 'rhw_submissions';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS {$submissions_table}" );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS {$forms_table}" );

	// Delete all plugin options
	delete_option( 'rhw_calculator_version' );
	delete_option( 'rhw_default_form_id' );
	delete_option( 'rhw_currency_symbol' );
	delete_option( 'rhw_currency_position' );
	delete_option( 'rhw_default_tax_rate' );
	delete_option( 'rhw_default_service_charge' );
	delete_option( 'rhw_default_notification_emails' );
	delete_option( 'rhw_delete_data_on_uninstall' );
}
