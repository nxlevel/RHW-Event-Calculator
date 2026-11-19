<?php
/**
 * CSV Lead Exporter.
 *
 * Allows venue administrators to export submitted leads and their calculated estimates
 * to CSV for CRM or spreadsheet analysis.
 *
 * @package RoseHallWeddings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RHW_Exporter {

	/**
	 * Register export handler.
	 */
	public function init() {
		add_action( 'admin_init', array( $this, 'process_export' ) );
	}

	/**
	 * Process CSV export request.
	 */
	public function process_export() {
		if ( ! isset( $_GET['rhw_action'] ) || 'export_csv' !== $_GET['rhw_action'] ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized user.', 'rhw-calculator' ) );
		}

		check_admin_referer( 'rhw_export_leads', 'rhw_nonce' );

		global $wpdb;
		$table = $wpdb->prefix . 'rhw_submissions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$results = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC", ARRAY_A );

		$filename = 'rose-hall-wedding-leads-' . gmdate( 'Y-m-d-His' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );

		// UTF-8 BOM for Excel
		fputs( $output, "\xEF\xBB\xBF" );

		// Header Row
		fputcsv(
			$output,
			array(
				'ID',
				'Date Submitted',
				'Client Name',
				'Email',
				'Phone',
				'Country',
				'Event Date',
				'Guest Count',
				'Event Type',
				'Venue Selected',
				'Subtotal ($)',
				'F&B Service Charge ($)',
				'Gov Tax ($)',
				'Grand Total ($)',
				'Status',
				'IP Address',
			)
		);

		if ( ! empty( $results ) ) {
			foreach ( $results as $row ) {
				fputcsv(
					$output,
					array(
						$row['id'],
						$row['created_at'],
						$row['customer_name'],
						$row['customer_email'],
						$row['customer_phone'],
						$row['country_of_residence'],
						$row['event_date'],
						$row['guest_count'],
						$row['event_type'],
						$row['venue_name'],
						number_format( (float) $row['subtotal'], 2, '.', '' ),
						number_format( (float) $row['service_charge'], 2, '.', '' ),
						number_format( (float) $row['tax_amount'], 2, '.', '' ),
						number_format( (float) $row['grand_total'], 2, '.', '' ),
						$row['status'],
						$row['user_ip'],
					)
				);
			}
		}

		fclose( $output );
		exit;
	}
}
