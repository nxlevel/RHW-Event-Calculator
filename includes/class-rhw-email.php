<?php
/**
 * Email Notification Engine.
 *
 * Sends HTML notification emails to venue administrators and confirmation receipts to customers.
 *
 * @package RoseHallWeddings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RHW_Email {

	/**
	 * Send venue administrator notification email.
	 */
	public static function send_admin_notification( $submission_id, $submission_data, $form, $calc ) {
		$notification_settings = isset( $form['notification_settings'] ) ? $form['notification_settings'] : array();
		if ( is_string( $notification_settings ) ) {
			$notification_settings = json_decode( $notification_settings, true ) ?: array();
		}

		$recipient_str = ! empty( $notification_settings['recipient_emails'] )
			? $notification_settings['recipient_emails']
			: get_option( 'admin_email' );

		$recipients = array_map( 'trim', explode( ',', $recipient_str ) );
		$recipients = array_filter( $recipients, 'is_email' );

		if ( empty( $recipients ) ) {
			$recipients = array( get_option( 'admin_email' ) );
		}

		$subject_template = ! empty( $notification_settings['email_subject'] )
			? $notification_settings['email_subject']
			: 'New Wedding Quote & Consultation Request: {customer_name} ({event_date})';

		$subject = str_replace(
			array( '{customer_name}', '{event_date}', '{submission_id}' ),
			array( $submission_data['customer_name'], $submission_data['event_date'], $submission_id ),
			$subject_template
		);

		$sender_name  = ! empty( $notification_settings['sender_name'] ) ? $notification_settings['sender_name'] : get_bloginfo( 'name' );
		$sender_email = ! empty( $notification_settings['sender_email'] ) ? $notification_settings['sender_email'] : get_option( 'admin_email' );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $sender_name . ' <' . $sender_email . '>',
			'Reply-To: ' . $submission_data['customer_name'] . ' <' . $submission_data['customer_email'] . '>',
		);

		$body = self::get_admin_email_html( $submission_id, $submission_data, $form, $calc );

		return wp_mail( $recipients, $subject, $body, $headers );
	}

	/**
	 * Send customer confirmation receipt email.
	 */
	public static function send_customer_confirmation( $submission_id, $submission_data, $form, $calc ) {
		if ( empty( $submission_data['customer_email'] ) || ! is_email( $submission_data['customer_email'] ) ) {
			return false;
		}

		$notification_settings = isset( $form['notification_settings'] ) ? $form['notification_settings'] : array();
		if ( is_string( $notification_settings ) ) {
			$notification_settings = json_decode( $notification_settings, true ) ?: array();
		}

		if ( empty( $notification_settings['send_customer_receipt'] ) ) {
			return false;
		}

		$subject      = 'Your Preliminary Wedding Quote & Consultation Request - Rose Hall Weddings';
		$sender_name  = ! empty( $notification_settings['sender_name'] ) ? $notification_settings['sender_name'] : 'Rose Hall Weddings & Events';
		$sender_email = ! empty( $notification_settings['sender_email'] ) ? $notification_settings['sender_email'] : get_option( 'admin_email' );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $sender_name . ' <' . $sender_email . '>',
		);

		$body = self::get_customer_email_html( $submission_id, $submission_data, $form, $calc );

		return wp_mail( $submission_data['customer_email'], $subject, $body, $headers );
	}

	/**
	 * Build HTML for admin notification email.
	 */
	private static function get_admin_email_html( $submission_id, $submission, $form, $calc ) {
		$admin_url = admin_url( 'admin.php?page=rhw-calculator-submissions&action=view&id=' . $submission_id );
		$symbol    = isset( $calc['currency_symbol'] ) ? $calc['currency_symbol'] : '$';
		$pos       = isset( $calc['currency_position'] ) ? $calc['currency_position'] : 'before';

		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="utf-8">
			<style>
				body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f7f7f7; color: #2C2523; line-height: 1.6; margin: 0; padding: 20px; }
				.email-wrapper { max-width: 680px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
				.email-header { background: #7B112D; color: #ffffff; padding: 28px; text-align: center; }
				.email-header h1 { margin: 0 0 6px 0; font-size: 24px; font-weight: normal; letter-spacing: 1px; color: #ffffff; }
				.email-header p { margin: 0; color: #EAD0A8; font-size: 14px; text-transform: uppercase; letter-spacing: 2px; }
				.email-body { padding: 32px; }
				.section-title { font-size: 18px; color: #7B112D; border-bottom: 2px solid #C5A059; padding-bottom: 6px; margin-top: 24px; margin-bottom: 16px; font-weight: bold; }
				.lead-meta { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
				.lead-meta td { padding: 8px 12px; border-bottom: 1px solid #f0f0f0; }
				.lead-meta td.label { font-weight: 600; color: #555; width: 35%; }
				.breakdown-table { width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 20px; }
				.breakdown-table th { background: #f9f6f0; color: #7B112D; text-align: left; padding: 10px 12px; font-size: 13px; border-bottom: 1px solid #e0d8c8; }
				.breakdown-table td { padding: 10px 12px; border-bottom: 1px solid #eee; font-size: 14px; }
				.breakdown-table td.amount { text-align: right; font-weight: 600; }
				.total-row { font-size: 16px; font-weight: bold; color: #7B112D; background: #FAF5EB; }
				.button { display: inline-block; background: #7B112D; color: #ffffff !important; text-decoration: none; padding: 12px 24px; border-radius: 4px; font-weight: 600; margin-top: 20px; }
				.email-footer { background: #fcfbf9; padding: 20px; text-align: center; font-size: 12px; color: #888; border-top: 1px solid #eee; }
			</style>
		</head>
		<body>
			<div class="email-wrapper">
				<div class="email-header">
					<h1>Rose Hall Weddings & Events</h1>
					<p>New Quote Request #<?php echo esc_html( $submission_id ); ?></p>
				</div>
				<div class="email-body">
					<p>A prospective client has submitted an interactive quote request and requested a consultation:</p>

					<div class="section-title">Client Contact Details</div>
					<table class="lead-meta">
						<tr><td class="label">Client Name:</td><td><strong><?php echo esc_html( $submission['customer_name'] ); ?></strong></td></tr>
						<tr><td class="label">Email Address:</td><td><a href="mailto:<?php echo esc_attr( $submission['customer_email'] ); ?>"><?php echo esc_html( $submission['customer_email'] ); ?></a></td></tr>
						<tr><td class="label">Phone / WhatsApp:</td><td><?php echo esc_html( $submission['customer_phone'] ); ?></td></tr>
						<tr><td class="label">Country of Residence:</td><td><?php echo esc_html( $submission['country_of_residence'] ); ?></td></tr>
						<tr><td class="label">Preferred Date:</td><td><?php echo esc_html( $submission['event_date'] ); ?></td></tr>
						<tr><td class="label">Guest Count:</td><td><?php echo esc_html( $submission['guest_count'] ); ?> guests</td></tr>
						<tr><td class="label">Venue Selected:</td><td><?php echo esc_html( $submission['venue_name'] ); ?></td></tr>
					</table>

					<div class="section-title">Estimated Cost Breakdown</div>
					<table class="breakdown-table">
						<thead>
							<tr>
								<th>Item Description</th>
								<th>Category</th>
								<th>Quantity / Note</th>
								<th style="text-align: right;">Amount</th>
							</tr>
						</thead>
						<tbody>
							<?php if ( ! empty( $calc['line_items'] ) ) : ?>
								<?php foreach ( $calc['line_items'] as $item ) : ?>
									<tr>
										<td><strong><?php echo esc_html( $item['title'] ); ?></strong></td>
										<td><?php echo esc_html( $item['category'] ); ?></td>
										<td><?php echo esc_html( $item['note'] ? $item['note'] : $item['quantity'] ); ?></td>
										<td class="amount"><?php echo esc_html( RHW_Pricing_Engine::format_money( $item['amount'], $symbol, $pos ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
							<tr>
								<td colspan="3" style="text-align: right; font-weight: bold;">Subtotal:</td>
								<td class="amount"><?php echo esc_html( RHW_Pricing_Engine::format_money( $calc['subtotal'], $symbol, $pos ) ); ?></td>
							</tr>
							<?php if ( ! empty( $calc['service_charge'] ) && $calc['service_charge'] > 0 ) : ?>
								<tr>
									<td colspan="3" style="text-align: right;">F&B Service Charge (<?php echo esc_html( $calc['service_charge_rate'] ); ?>% on F&B):</td>
									<td class="amount"><?php echo esc_html( RHW_Pricing_Engine::format_money( $calc['service_charge'], $symbol, $pos ) ); ?></td>
								</tr>
							<?php endif; ?>
							<?php if ( ! empty( $calc['tax_amount'] ) && $calc['tax_amount'] > 0 ) : ?>
								<tr>
									<td colspan="3" style="text-align: right;">Government Tax (<?php echo esc_html( $calc['tax_rate'] ); ?>%):</td>
									<td class="amount"><?php echo esc_html( RHW_Pricing_Engine::format_money( $calc['tax_amount'], $symbol, $pos ) ); ?></td>
								</tr>
							<?php endif; ?>
							<tr class="total-row">
								<td colspan="3" style="text-align: right; padding: 12px;">Grand Total Estimate:</td>
								<td class="amount" style="padding: 12px;"><?php echo esc_html( RHW_Pricing_Engine::format_money( $calc['grand_total'], $symbol, $pos ) ); ?></td>
							</tr>
						</tbody>
					</table>

					<div style="text-align: center;">
						<a href="<?php echo esc_url( $admin_url ); ?>" class="button">View Full Lead & Client Answers in WordPress</a>
					</div>
				</div>
				<div class="email-footer">
					Rose Hall Weddings & Events &bull; Montego Bay, Jamaica &bull; <a href="https://rosehall.com" style="color:#7B112D;">rosehall.com</a>
				</div>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Build HTML for customer confirmation receipt.
	 */
	private static function get_customer_email_html( $submission_id, $submission, $form, $calc ) {
		$symbol = isset( $calc['currency_symbol'] ) ? $calc['currency_symbol'] : '$';
		$pos    = isset( $calc['currency_position'] ) ? $calc['currency_position'] : 'before';

		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="utf-8">
			<style>
				body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f7f7f7; color: #2C2523; line-height: 1.6; margin: 0; padding: 20px; }
				.email-wrapper { max-width: 650px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
				.email-header { background: #7B112D; color: #ffffff; padding: 32px; text-align: center; }
				.email-header h1 { margin: 0 0 8px 0; font-size: 26px; font-weight: normal; letter-spacing: 1px; color: #ffffff; }
				.email-header p { margin: 0; color: #EAD0A8; font-size: 13px; text-transform: uppercase; letter-spacing: 2px; }
				.email-body { padding: 32px; }
				.highlight-box { background: #FAF5EB; border-left: 4px solid #C5A059; padding: 16px 20px; margin: 20px 0; border-radius: 4px; }
				.breakdown-table { width: 100%; border-collapse: collapse; margin-top: 15px; margin-bottom: 20px; }
				.breakdown-table th { background: #f9f6f0; color: #7B112D; text-align: left; padding: 10px 12px; font-size: 13px; border-bottom: 1px solid #e0d8c8; }
				.breakdown-table td { padding: 10px 12px; border-bottom: 1px solid #eee; font-size: 14px; }
				.breakdown-table td.amount { text-align: right; font-weight: 600; }
				.total-row { font-size: 16px; font-weight: bold; color: #7B112D; background: #FAF5EB; }
				.disclaimer { font-size: 12px; color: #777; line-height: 1.5; margin-top: 24px; padding-top: 16px; border-top: 1px solid #eee; }
				.email-footer { background: #fcfbf9; padding: 24px; text-align: center; font-size: 13px; color: #666; border-top: 1px solid #eee; }
			</style>
		</head>
		<body>
			<div class="email-wrapper">
				<div class="email-header">
					<h1>Rose Hall Weddings & Events</h1>
					<p>Your Preliminary Estimate & Next Steps</p>
				</div>
				<div class="email-body">
					<p>Dear <?php echo esc_html( $submission['customer_name'] ); ?>,</p>
					<p>Thank you for considering Rose Hall for your upcoming wedding celebration. We are delighted to assist you in making your special day truly unforgettable.</p>

					<div class="highlight-box">
						<strong>What Happens Next?</strong><br>
						Our Destination Relationship Specialists are reviewing your questionnaire and preliminary estimate. Within <strong>3 to 5 business days</strong>, a dedicated specialist will reach out to discuss your vision in depth and tailor your wedding package.
					</div>

					<h3 style="color: #7B112D; margin-top: 24px;">Your Selected Event Overview</h3>
					<p>
						<strong>Event Date:</strong> <?php echo esc_html( $submission['event_date'] ); ?><br>
						<strong>Expected Guests:</strong> <?php echo esc_html( $submission['guest_count'] ); ?><br>
						<strong>Selected Venue:</strong> <?php echo esc_html( $submission['venue_name'] ); ?>
					</p>

					<h3 style="color: #7B112D; margin-top: 24px;">Preliminary Cost Summary</h3>
					<table class="breakdown-table">
						<thead>
							<tr>
								<th>Item</th>
								<th>Category</th>
								<th style="text-align: right;">Estimated Amount</th>
							</tr>
						</thead>
						<tbody>
							<?php if ( ! empty( $calc['line_items'] ) ) : ?>
								<?php foreach ( $calc['line_items'] as $item ) : ?>
									<tr>
										<td><strong><?php echo esc_html( $item['title'] ); ?></strong></td>
										<td><?php echo esc_html( $item['category'] ); ?></td>
										<td class="amount"><?php echo esc_html( RHW_Pricing_Engine::format_money( $item['amount'], $symbol, $pos ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
							<tr>
								<td colspan="2" style="text-align: right; font-weight: bold;">Subtotal:</td>
								<td class="amount"><?php echo esc_html( RHW_Pricing_Engine::format_money( $calc['subtotal'], $symbol, $pos ) ); ?></td>
							</tr>
							<?php if ( ! empty( $calc['service_charge'] ) && $calc['service_charge'] > 0 ) : ?>
								<tr>
									<td colspan="2" style="text-align: right;">F&B Service Charge (<?php echo esc_html( $calc['service_charge_rate'] ); ?>%):</td>
									<td class="amount"><?php echo esc_html( RHW_Pricing_Engine::format_money( $calc['service_charge'], $symbol, $pos ) ); ?></td>
								</tr>
							<?php endif; ?>
							<?php if ( ! empty( $calc['tax_amount'] ) && $calc['tax_amount'] > 0 ) : ?>
								<tr>
									<td colspan="2" style="text-align: right;">Government Tax (<?php echo esc_html( $calc['tax_rate'] ); ?>%):</td>
									<td class="amount"><?php echo esc_html( RHW_Pricing_Engine::format_money( $calc['tax_amount'], $symbol, $pos ) ); ?></td>
								</tr>
							<?php endif; ?>
							<tr class="total-row">
								<td colspan="2" style="text-align: right; padding: 12px;">Preliminary Grand Total:</td>
								<td class="amount" style="padding: 12px;"><?php echo esc_html( RHW_Pricing_Engine::format_money( $calc['grand_total'], $symbol, $pos ) ); ?></td>
							</tr>
						</tbody>
					</table>

					<div class="disclaimer">
						<strong>Disclaimer:</strong> This preliminary quote is an estimated guide based on the information provided and standard rates. Bespoke options (tents, custom florals, transport, and custom menus) are quoted upon detailed consultation. In accordance with the Data and Privacy Protection Act, your contact information will be used exclusively by Rose Hall Weddings and Events.
					</div>
				</div>
				<div class="email-footer">
					Rose Hall Weddings & Events &bull; Montego Bay, Jamaica<br>
					Email: <a href="mailto:weddings@rosehall.com" style="color: #7B112D;">weddings@rosehall.com</a> &bull; Web: <a href="https://rosehall.com" style="color: #7B112D;">rosehall.com</a>
				</div>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}
}
