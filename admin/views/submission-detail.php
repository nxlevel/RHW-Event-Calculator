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
	<hr class="wp-header-end">

	<?php if ( isset( $_GET['status_updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Lead status and notes updated.', 'rhw-calculator' ); ?></p></div>
	<?php endif; ?>

	<div class="rhw-detail-layout" style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-top: 20px;">
		<!-- Main Column: Quote Breakdown & Client Answers -->
		<div class="rhw-detail-main">
			<!-- Quote Calculation Summary Card -->
			<div class="rhw-card">
				<h2 class="rhw-card-title"><?php esc_html_e( 'Line Items', 'rhw-calculator' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Calculated from client questionnaire answers, Weekday/Weekend venue selection, and Rose Hall venue pricing rules.', 'rhw-calculator' ); ?></p>

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
								<td colspan="4"><?php esc_html_e( 'No line items recorded.', 'rhw-calculator' ); ?></td>
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
							<th colspan="3" style="text-align: right; color: #7B112D;"><?php esc_html_e( 'Estimated Cost:', 'rhw-calculator' ); ?></th>
							<th style="text-align: right; color: #7B112D; font-size: 18px;">$<?php echo esc_html( number_format( (float) $submission['grand_total'], 2 ) ); ?></th>
						</tr>
					</tfoot>
				</table>
			</div>

			<!-- Full Submitted Answers Card -->
			<div class="rhw-card" style="margin-top: 24px;">
				<h2 class="rhw-card-title"><?php esc_html_e( 'Full Questionnaire Responses', 'rhw-calculator' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Detailed answers provided by the client during questionnaire submission.', 'rhw-calculator' ); ?></p>

				<?php
				$form_questions = ( ! empty( $form['questions'] ) && is_array( $form['questions'] ) ) ? $form['questions'] : array();
				$questions_map  = array();
				foreach ( $form_questions as $fq ) {
					if ( ! empty( $fq['id'] ) ) {
						$questions_map[ $fq['id'] ] = $fq;
					}
				}
				?>

				<table class="form-table rhw-answers-table" style="margin-top: 15px;">
					<?php foreach ( $answers as $key => $val ) : ?>
						<?php
						$matched_q = isset( $questions_map[ $key ] ) ? $questions_map[ $key ] : null;
						$suffix    = '';
						if ( ! $matched_q && '_date' === substr( $key, -5 ) ) {
							$base_key  = substr( $key, 0, -5 );
							$matched_q = isset( $questions_map[ $base_key ] ) ? $questions_map[ $base_key ] : null;
							$suffix    = ' (' . __( 'Date', 'rhw-calculator' ) . ')';
						} elseif ( ! $matched_q && '_time' === substr( $key, -5 ) ) {
							$base_key  = substr( $key, 0, -5 );
							$matched_q = isset( $questions_map[ $base_key ] ) ? $questions_map[ $base_key ] : null;
							$suffix    = ' (' . __( 'Time', 'rhw-calculator' ) . ')';
						}

						if ( $matched_q && ! empty( $matched_q['label'] ) ) {
							$display_label = $matched_q['label'] . $suffix;
						} else {
							$display_label = ucwords( str_replace( '_', ' ', $key ) );
						}

						// Map option keys to their actual choice labels when available
						$opt_map = array();
						if ( $matched_q && ! empty( $matched_q['options'] ) && is_array( $matched_q['options'] ) ) {
							foreach ( $matched_q['options'] as $opt ) {
								if ( isset( $opt['value'] ) && '' !== $opt['value'] && ! empty( $opt['label'] ) ) {
									$opt_map[ (string) $opt['value'] ] = $opt['label'];
								}
							}
						}

						$is_url   = is_string( $val ) && filter_var( $val, FILTER_VALIDATE_URL );
						$is_image = $is_url && preg_match( '/\.(jpg|jpeg|png|webp|gif)(\?.*)?$/i', $val );
						?>
						<tr>
							<th scope="row" style="width: 38%; font-weight: 600; color: #555;"><?php echo esc_html( $display_label ); ?></th>
							<td>
								<?php if ( $is_image ) : ?>
									<div>
										<a href="<?php echo esc_url( $val ); ?>" target="_blank" rel="noopener noreferrer">
											<img src="<?php echo esc_url( $val ); ?>" style="max-width: 200px; max-height: 150px; border-radius: 4px; border: 1px solid #ddd; padding: 3px;" alt="Client Upload">
										</a>
										<div><a href="<?php echo esc_url( $val ); ?>" target="_blank" rel="noopener noreferrer" class="button button-small" style="margin-top: 6px;"><?php esc_html_e( 'View Full Image', 'rhw-calculator' ); ?> &rarr;</a></div>
									</div>
								<?php elseif ( $is_url ) : ?>
									<a href="<?php echo esc_url( $val ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $val ); ?></a>
								<?php elseif ( is_array( $val ) ) : ?>
									<?php
									$mapped_vals = array_map(
										function( $v ) use ( $opt_map ) {
											return isset( $opt_map[ (string) $v ] ) ? $opt_map[ (string) $v ] : $v;
										},
										$val
									);
									echo nl2br( esc_html( implode( ', ', $mapped_vals ) ) );
									?>
								<?php else : ?>
									<?php
									$display_val = isset( $opt_map[ (string) $val ] ) ? $opt_map[ (string) $val ] : $val;
									echo nl2br( esc_html( $display_val ) );
									?>
								<?php endif; ?>
							</td>
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
					<?php
					global $wpdb;
					$rhw_bk = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}rhw_bookings WHERE submission_id = %d LIMIT 1", $submission['id'] ) );
					if ( $rhw_bk ) :
					?>
						<li style="background: #FAF5EB; padding: 6px 8px; border-radius: 4px; margin: 4px 0;">
							<strong><?php esc_html_e( 'Booked Consultation:', 'rhw-calculator' ); ?></strong>
							<span style="color: #7B112D; font-weight: 700;"><?php echo esc_html( date( 'D, M j, Y', strtotime( $rhw_bk->booking_date ) ) . ' at ' . $rhw_bk->booking_time ); ?></span>
							<br><a href="<?php echo esc_url( admin_url( 'admin.php?page=rhw-calculator-calendar' ) ); ?>" style="font-size: 11px;">View in Calendar &rarr;</a>
						</li>
					<?php endif; ?>
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
