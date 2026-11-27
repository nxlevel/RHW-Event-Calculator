<?php
/**
 * Submissions / Leads List Admin View.
 *
 * @package RoseHallWeddings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$table = $wpdb->prefix . 'rhw_submissions';

// Filter by form ID or status if specified
$form_filter   = isset( $_GET['form_id'] ) ? intval( $_GET['form_id'] ) : 0;
$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';

$where_clauses = array( '1=1' );
if ( $form_filter > 0 ) {
	$where_clauses[] = $wpdb->prepare( 'form_id = %d', $form_filter );
}
if ( ! empty( $status_filter ) ) {
	$where_clauses[] = $wpdb->prepare( 'status = %s', $status_filter );
}

$where_sql = implode( ' AND ', $where_clauses );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
$submissions = $wpdb->get_results( "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC" );

$export_url = wp_nonce_url( admin_url( 'admin.php?rhw_action=export_csv' ), 'rhw_export_leads', 'rhw_nonce' );
?>
<div class="wrap rhw-admin-wrap">
	<div class="rhw-header-bar">
		<div>
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Wedding & Event Leads', 'rhw-calculator' ); ?></h1>
			<p class="rhw-header-subtitle"><?php esc_html_e( 'Review incoming leads and estimates', 'rhw-calculator' ); ?></p>
		</div>
		<div>
			<a href="<?php echo esc_url( $export_url ); ?>" class="button button-secondary">
				<span class="dashicons dashicons-download" style="vertical-align: middle;"></span> <?php esc_html_e( 'Export Leads to CSV', 'rhw-calculator' ); ?>
			</a>
		</div>
	</div>
	<hr class="wp-header-end">

	<!-- Filter Bar -->
	<div class="rhw-filter-bar">
		<ul class="subsubsub">
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=rhw-calculator-submissions' ) ); ?>" class="<?php echo empty( $status_filter ) ? 'current' : ''; ?>"><?php esc_html_e( 'All Leads', 'rhw-calculator' ); ?></a> |</li>
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=rhw-calculator-submissions&status=new' ) ); ?>" class="<?php echo 'new' === $status_filter ? 'current' : ''; ?>"><?php esc_html_e( 'New', 'rhw-calculator' ); ?></a> |</li>
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=rhw-calculator-submissions&status=contacted' ) ); ?>" class="<?php echo 'contacted' === $status_filter ? 'current' : ''; ?>"><?php esc_html_e( 'Contacted', 'rhw-calculator' ); ?></a> |</li>
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=rhw-calculator-submissions&status=consultation_booked' ) ); ?>" class="<?php echo 'consultation_booked' === $status_filter ? 'current' : ''; ?>"><?php esc_html_e( 'Consultation Booked', 'rhw-calculator' ); ?></a> |</li>
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=rhw-calculator-submissions&status=archived' ) ); ?>" class="<?php echo 'archived' === $status_filter ? 'current' : ''; ?>"><?php esc_html_e( 'Archived', 'rhw-calculator' ); ?></a></li>
		</ul>
	</div>

	<div class="rhw-card" style="margin-top: 20px;">
		<table class="wp-list-table widefat fixed striped table-view-list">
			<thead>
				<tr>
					<th style="width: 50px;"><?php esc_html_e( 'ID', 'rhw-calculator' ); ?></th>
					<th style="width: 140px;"><?php esc_html_e( 'Received', 'rhw-calculator' ); ?></th>
					<th><?php esc_html_e( 'Client Name & Contact', 'rhw-calculator' ); ?></th>
					<th style="width: 130px;"><?php esc_html_e( 'Event Date', 'rhw-calculator' ); ?></th>
					<th style="width: 70px;"><?php esc_html_e( 'Guests', 'rhw-calculator' ); ?></th>
					<th><?php esc_html_e( 'Venue Selected', 'rhw-calculator' ); ?></th>
					<th style="width: 130px; text-align: right;"><?php esc_html_e( 'Grand Total', 'rhw-calculator' ); ?></th>
					<th style="width: 110px;"><?php esc_html_e( 'Status', 'rhw-calculator' ); ?></th>
					<th style="width: 120px;"><?php esc_html_e( 'Actions', 'rhw-calculator' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! empty( $submissions ) ) : ?>
					<?php foreach ( $submissions as $sub ) : ?>
						<tr>
							<td><strong>#<?php echo esc_html( $sub->id ); ?></strong></td>
							<td><?php echo esc_html( date_i18n( 'M j, Y g:i a', strtotime( $sub->created_at ) ) ); ?></td>
							<td>
								<strong>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=rhw-calculator-submissions&action=view&id=' . $sub->id ) ); ?>">
										<?php echo esc_html( $sub->customer_name ); ?>
									</a>
								</strong>
								<div class="description">
									<a href="mailto:<?php echo esc_attr( $sub->customer_email ); ?>"><?php echo esc_html( $sub->customer_email ); ?></a>
									<?php if ( ! empty( $sub->customer_phone ) ) : ?>
										&bull; <?php echo esc_html( $sub->customer_phone ); ?>
									<?php endif; ?>
									<?php if ( ! empty( $sub->country_of_residence ) ) : ?>
										&bull; <strong><?php echo esc_html( $sub->country_of_residence ); ?></strong>
									<?php endif; ?>
								</div>
							</td>
							<td><?php echo esc_html( $sub->event_date ); ?></td>
							<td><?php echo esc_html( $sub->guest_count ); ?></td>
							<td><?php echo esc_html( str_replace( '_', ' ', ucwords( $sub->venue_name, '_' ) ) ); ?></td>
							<td style="text-align: right; font-weight: bold; color: #7B112D;">
								$<?php echo esc_html( number_format( (float) $sub->grand_total, 2 ) ); ?>
							</td>
							<td>
								<span class="rhw-status-badge rhw-status-<?php echo esc_attr( $sub->status ); ?>">
									<?php echo esc_html( ucwords( str_replace( '_', ' ', $sub->status ) ) ); ?>
								</span>
							</td>
							<td>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=rhw-calculator-submissions&action=view&id=' . $sub->id ) ); ?>" class="button button-small">
									<?php esc_html_e( 'View Details', 'rhw-calculator' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr>
						<td colspan="9" style="text-align: center; padding: 40px;">
							<p><?php esc_html_e( 'No submissions found.', 'rhw-calculator' ); ?></p>
						</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
