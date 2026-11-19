<?php
/**
 * Single Submission Detail Admin View.
 *
 * @package RoseHallWeddings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
global $wpdb;
$table = $wpdb->prefix . 'rhw_submissions';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
$submission = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

if ( ! $submission ) {
	wp_die( esc_html__( 'Lead submission not found.', 'rhw-calculator' ) );
}

$answers   = json_decode( $submission['answers'], true ) ?: array();
$breakdown = json_decode( $submission['itemized_breakdown'], true ) ?: array();
$form      = RHW_Form_Manager::get_form( $submission['form_id'] );
?>
<div class="wrap rhw-admin-wrap">
	<div class="rhw-header-bar">
		<div>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rhw-calculator-submissions' ) ); ?>" class="rhw-back-link">
				&larr; <?php esc_html_e( 'Back to Leads List', 'rhw-calculator' ); ?>
			</a>
			<h1 class="wp-heading-inline">
				<?php echo sprintf( esc_html__( 'Lead #%1$d: %2$s', 'rhw-calculator' ), intval( $submission['id'] ), esc_html( $submission['customer_name'] ) ); ?>
			</h1>
		</div>
		<div class="rhw-header-actions">
			<button type="button" class="button button-secondary" onclick="window.print();">
				<span class="dashicons dashicons-printer" style="vertical-align: middle;"></span> <?php esc_html_e( 'Print Lead Sheet', 'rhw-calculator' ); ?>
			</button>
			<a href="mailto:<?php echo esc_attr( $submission['customer_email'] ); ?>" class="button button-primary rhw-btn-accent">
				<span class="dashicons dashicons-email" style="vertical-align: middle;"></span> <?php esc_html_e( 'Email Client', 'rhw-calculator' ); ?>
			</a>
		</div>
	</div>

	<?php if ( isset( $_GET['status_updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Lead status and notes updated.', 'rhw-calculator' ); ?></p></div>
	<?php endif; ?>

	<div class="rhw-detail-layout" style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-top: 20px;">
		<!-- Main Column: Quote Breakdown & Client Answers -->
		<div class="rhw-detail-main">
			<!-- Quote Calculation Summary Card -->
			<div class="rhw-card">
				<h2 class="rhw-card-title"><?php esc_html_e( 'Estimated Cost Breakdown', 'rhw-calculator' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Itemized line items calculated from client questionnaire answers and Rose Hall venue pricing rules.', 'rhw-calculator' ); ?></p>

				<table class="wp-list-table widefat striped" style="margin-top: 15px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Description of Charges', 'rhw-calculator' ); ?></th>
							<th><?php esc_html_e( 'Category', 'rhw-calculator' ); ?></th>
							<th><?php esc_html_e( 'Quantity / Notes', 'rhw-calculator' ); ?></th>
							<th style="text-align: right;"><?php esc_html_e( 'Amount (USD)', 'rhw-calculator' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( ! empty( $breakdown ) ) : ?>
							<?php foreach ( $breakdown as $item ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $item['title'] ); ?></strong></td>
									<td><?php echo esc_html( $item['category'] ); ?></td>
									<td><?php echo esc_html( ! empty( $item['note'] ) ? $item['note'] : $item['quantity'] ); ?></td>
									<td style="text-align: right; font-weight: 600;">$<?php echo esc_html( number_format( (float) $item['amount'], 2 ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php else : ?>
							<tr>
								<td colspan="4"><?php esc_html_e( 'No breakdown items recorded.', 'rhw-calculator' ); ?></td>
							</tr>
						<?php endif; ?>
					</tbody>
					<tfoot>
						<tr>
							<th colspan="3" style="text-align: right; font-size: 14px;"><?php esc_html_e( 'Subtotal:', 'rhw-calculator' ); ?></th>
							<th style="text-align: right; font-size: 14px;">$<?php echo esc_html( number_format( (float) $submission['subtotal'], 2 ) ); ?></th>
						</tr>
						<?php if ( (float) $submission['service_charge'] > 0 ) : ?>
							<tr>
								<th colspan="3" style="text-align: right; font-weight: normal;"><?php esc_html_e( '20% Service Charge on F&B:', 'rhw-calculator' ); ?></th>
								<th style="text-align: right; font-weight: normal;">$<?php echo esc_html( number_format( (float) $submission['service_charge'], 2 ) ); ?></th>
							</tr>
						<?php endif; ?>
						<?php if ( (float) $submission['tax_amount'] > 0 ) : ?>
							<tr>
								<th colspan="3" style="text-align: right; font-weight: normal;"><?php esc_html_e( '15% Government Tax:', 'rhw-calculator' ); ?></th>
								<th style="text-align: right; font-weight: normal;">$<?php echo esc_html( number_format( (float) $submission['tax_amount'], 2 ) ); ?></th>
							</tr>
						<?php endif; ?>
						<tr style="background: #FAF5EB; font-size: 16px;">
							<th colspan="3" style="text-align: right; color: #7B112D;"><?php esc_html_e( 'Grand Total Estimate:', 'rhw-calculator' ); ?></th>
							<th style="text-align: right; color: #7B112D; font-size: 18px;">$<?php echo esc_html( number_format( (float) $submission['grand_total'], 2 ) ); ?></th>
						</tr>
					</tfoot>
				</table>
			</div>

			<!-- Full Submitted Answers Card -->
			<div class="rhw-card" style="margin-top: 24px;">
				<h2 class="rhw-card-title"><?php esc_html_e( 'Full Questionnaire Responses', 'rhw-calculator' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Detailed answers provided by the client during questionnaire submission.', 'rhw-calculator' ); ?></p>

				<table class="form-table rhw-answers-table" style="margin-top: 15px;">
					<?php foreach ( $answers as $key => $val ) : ?>
						<?php
						$display_label = ucwords( str_replace( '_', ' ', $key ) );
						$display_value = is_array( $val ) ? implode( ', ', $val ) : $val;
						?>
						<tr>
							<th scope="row" style="width: 35%; font-weight: 600; color: #555;"><?php echo esc_html( $display_label ); ?></th>
							<td><?php echo nl2br( esc_html( $display_value ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</table>
			</div>
		</div>

		<!-- Right Column: Lead Metadata & Specialist Management -->
		<div class="rhw-detail-sidebar">
			<!-- Lead Overview Card -->
			<div class="rhw-card">
				<h3 class="rhw-card-title"><?php esc_html_e( 'Contact Profile', 'rhw-calculator' ); ?></h3>
				<ul class="rhw-sidebar-info">
					<li><strong><?php esc_html_e( 'Client Name:', 'rhw-calculator' ); ?></strong> <?php echo esc_html( $submission['customer_name'] ); ?></li>
					<li><strong><?php esc_html_e( 'Email:', 'rhw-calculator' ); ?></strong> <a href="mailto:<?php echo esc_attr( $submission['customer_email'] ); ?>"><?php echo esc_html( $submission['customer_email'] ); ?></a></li>
					<li><strong><?php esc_html_e( 'Phone / WhatsApp:', 'rhw-calculator' ); ?></strong> <?php echo esc_html( $submission['customer_phone'] ); ?></li>
					<li><strong><?php esc_html_e( 'Country:', 'rhw-calculator' ); ?></strong> <?php echo esc_html( $submission['country_of_residence'] ); ?></li>
					<li><strong><?php esc_html_e( 'Event Date:', 'rhw-calculator' ); ?></strong> <?php echo esc_html( $submission['event_date'] ); ?></li>
					<li><strong><?php esc_html_e( 'Guest Count:', 'rhw-calculator' ); ?></strong> <?php echo esc_html( $submission['guest_count'] ); ?></li>
					<li><strong><?php esc_html_e( 'Submitted:', 'rhw-calculator' ); ?></strong> <?php echo esc_html( $submission['created_at'] ); ?></li>
					<li><strong><?php esc_html_e( 'IP Address:', 'rhw-calculator' ); ?></strong> <?php echo esc_html( $submission['user_ip'] ); ?></li>
				</ul>
			</div>

			<!-- Status & Internal Notes Card -->
			<div class="rhw-card" style="margin-top: 20px;">
				<h3 class="rhw-card-title"><?php esc_html_e( 'Lead Status & Notes', 'rhw-calculator' ); ?></h3>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="rhw_update_submission_status">
					<input type="hidden" name="submission_id" value="<?php echo esc_attr( $submission['id'] ); ?>">
					<?php wp_nonce_field( 'rhw_update_submission_status' ); ?>

					<p>
						<label class="rhw-label"><?php esc_html_e( 'Pipeline Status', 'rhw-calculator' ); ?></label>
						<select name="status" class="widefat">
							<option value="new" <?php selected( $submission['status'], 'new' ); ?>><?php esc_html_e( 'New Lead', 'rhw-calculator' ); ?></option>
							<option value="contacted" <?php selected( $submission['status'], 'contacted' ); ?>><?php esc_html_e( 'Contacted / Email Sent', 'rhw-calculator' ); ?></option>
							<option value="consultation_booked" <?php selected( $submission['status'], 'consultation_booked' ); ?>><?php esc_html_e( 'Consultation Scheduled', 'rhw-calculator' ); ?></option>
							<option value="quote_formalized" <?php selected( $submission['status'], 'quote_formalized' ); ?>><?php esc_html_e( 'Formal Contract Sent', 'rhw-calculator' ); ?></option>
							<option value="booked" <?php selected( $submission['status'], 'booked' ); ?>><?php esc_html_e( 'Booked / Deposit Paid', 'rhw-calculator' ); ?></option>
							<option value="archived" <?php selected( $submission['status'], 'archived' ); ?>><?php esc_html_e( 'Archived / Lost', 'rhw-calculator' ); ?></option>
						</select>
					</p>

					<p>
						<label class="rhw-label"><?php esc_html_e( 'Internal Notes (Specialists Only)', 'rhw-calculator' ); ?></label>
						<textarea name="notes" rows="6" class="widefat" placeholder="<?php esc_attr_e( 'Notes regarding client preferences, call history, catering adjustments...', 'rhw-calculator' ); ?>"><?php echo esc_textarea( $submission['notes'] ); ?></textarea>
					</p>

					<p>
						<button type="submit" class="button button-primary widefat rhw-btn-accent">
							<?php esc_html_e( 'Update Status & Notes', 'rhw-calculator' ); ?>
						</button>
					</p>
				</form>
			</div>
		</div>
	</div>
</div>
