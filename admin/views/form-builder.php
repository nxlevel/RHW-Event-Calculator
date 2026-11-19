<?php
/**
 * Form Builder Admin View.
 *
 * @package RoseHallWeddings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$form_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
$form    = false;

if ( $form_id > 0 ) {
	$form = RHW_Form_Manager::get_form( $form_id );
}

if ( ! $form ) {
	$form_manager = new RHW_Form_Manager();
	$default_data = $form_manager->get_default_rose_hall_form_data();
	$form         = array(
		'id'                    => 0,
		'title'                 => 'Rose Hall Weddings & Events Calculator',
		'description'           => 'Interactive wedding cost calculator and quote consultation request.',
		'status'                => 'published',
		'sections'              => $default_data['sections'],
		'questions'             => $default_data['questions'],
		'pricing_settings'      => $default_data['pricing_settings'],
		'notification_settings' => $default_data['notification_settings'],
		'appearance_settings'   => $default_data['appearance_settings'],
	);
}

$shortcode = $form_id > 0 ? '[event_cost_calculator id="' . esc_attr( $form_id ) . '"]' : esc_html__( '(Will be generated after saving)', 'rhw-calculator' );
?>
<div class="wrap rhw-admin-wrap">
	<div class="rhw-header-bar">
		<div>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rhw-calculator' ) ); ?>" class="rhw-back-link">
				&larr; <?php esc_html_e( 'Back to All Calculators', 'rhw-calculator' ); ?>
			</a>
			<h1 class="wp-heading-inline">
				<?php echo $form_id > 0 ? sprintf( esc_html__( 'Edit Calculator: %s', 'rhw-calculator' ), esc_html( $form['title'] ) ) : esc_html__( 'Create New Event Calculator', 'rhw-calculator' ); ?>
			</h1>
		</div>
		<div class="rhw-header-actions">
			<?php if ( $form_id > 0 ) : ?>
				<div class="rhw-shortcode-pill">
					<code><?php echo esc_html( $shortcode ); ?></code>
					<button type="button" class="button button-small rhw-copy-shortcode-btn" data-clipboard="<?php echo esc_attr( $shortcode ); ?>">
						<span class="dashicons dashicons-admin-page"></span> <?php esc_html_e( 'Copy Shortcode', 'rhw-calculator' ); ?>
					</button>
				</div>
			<?php endif; ?>
			<button type="button" class="button button-secondary" id="rhw-preview-trigger-btn">
				<span class="dashicons dashicons-visibility" style="vertical-align: middle;"></span> <?php esc_html_e( 'Live Preview', 'rhw-calculator' ); ?>
			</button>
			<button type="button" class="button button-primary rhw-btn-accent" id="rhw-save-form-btn">
				<span class="dashicons dashicons-saved" style="vertical-align: middle;"></span> <?php esc_html_e( 'Save Calculator', 'rhw-calculator' ); ?>
			</button>
		</div>
	</div>

	<?php if ( isset( $_GET['updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Calculator configuration saved successfully.', 'rhw-calculator' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['duplicated'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Calculator duplicated successfully. You are now editing the duplicate copy.', 'rhw-calculator' ); ?></p></div>
	<?php endif; ?>

	<!-- Main Form Editor Wrapper -->
	<form id="rhw-form-editor" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="rhw_save_form">
		<input type="hidden" name="form_id" value="<?php echo esc_attr( $form_id ); ?>">
		<?php wp_nonce_field( 'rhw_save_form_nonce', 'rhw_nonce' ); ?>

		<!-- JSON Serialized State Fields -->
		<input type="hidden" name="sections_json" id="rhw_sections_json">
		<input type="hidden" name="questions_json" id="rhw_questions_json">
		<input type="hidden" name="pricing_settings_json" id="rhw_pricing_settings_json">
		<input type="hidden" name="notification_settings_json" id="rhw_notification_settings_json">
		<input type="hidden" name="appearance_settings_json" id="rhw_appearance_settings_json">

		<!-- Primary Metadata Card -->
		<div class="rhw-card" style="margin-top: 15px;">
			<div class="rhw-grid-2">
				<div>
					<label class="rhw-label"><?php esc_html_e( 'Calculator Title', 'rhw-calculator' ); ?> *</label>
					<input type="text" name="title" id="rhw_title_field" class="widefat" value="<?php echo esc_attr( $form['title'] ); ?>" required>
				</div>
				<div>
					<label class="rhw-label"><?php esc_html_e( 'Form Status', 'rhw-calculator' ); ?></label>
					<select name="status" class="widefat">
						<option value="published" <?php selected( $form['status'], 'published' ); ?>><?php esc_html_e( 'Published (Visible on site)', 'rhw-calculator' ); ?></option>
						<option value="draft" <?php selected( $form['status'], 'draft' ); ?>><?php esc_html_e( 'Draft (Hidden from public)', 'rhw-calculator' ); ?></option>
					</select>
				</div>
			</div>
			<div style="margin-top: 12px;">
				<label class="rhw-label"><?php esc_html_e( 'Subtitle / Header Description', 'rhw-calculator' ); ?></label>
				<textarea name="description" rows="2" class="widefat"><?php echo esc_textarea( $form['description'] ); ?></textarea>
			</div>
		</div>

		<!-- Tab Navigation -->
		<div class="rhw-tabs-nav">
			<button type="button" class="rhw-tab-btn active" data-tab="tab-questions">
				<span class="dashicons dashicons-editor-ul"></span> <?php esc_html_e( 'Sections & Questions', 'rhw-calculator' ); ?>
			</button>
			<button type="button" class="rhw-tab-btn" data-tab="tab-pricing">
				<span class="dashicons dashicons-money-alt"></span> <?php esc_html_e( 'Calculations & Taxes', 'rhw-calculator' ); ?>
			</button>
			<button type="button" class="rhw-tab-btn" data-tab="tab-notifications">
				<span class="dashicons dashicons-email-alt"></span> <?php esc_html_e( 'Email Notifications', 'rhw-calculator' ); ?>
			</button>
			<button type="button" class="rhw-tab-btn" data-tab="tab-appearance">
				<span class="dashicons dashicons-admin-appearance"></span> <?php esc_html_e( 'Styling & Custom CSS', 'rhw-calculator' ); ?>
			</button>
		</div>

		<!-- TAB 1: Sections & Questions Builder -->
		<div class="rhw-tab-pane active" id="tab-questions">
			<div class="rhw-builder-layout">
				<!-- Left Column: Sections & Questions Tree -->
				<div class="rhw-builder-main">
					<div class="rhw-action-row">
						<div>
							<strong><?php esc_html_e( 'Form Question Blueprint', 'rhw-calculator' ); ?></strong>
							<span class="description" style="margin-left: 8px;"><?php esc_html_e( 'Drag or reorder questions. Configure pricing and conditional rules on each question.', 'rhw-calculator' ); ?></span>
						</div>
						<div class="rhw-btn-group">
							<button type="button" class="button" id="rhw-add-section-btn">
								<span class="dashicons dashicons-category"></span> <?php esc_html_e( 'Add Section', 'rhw-calculator' ); ?>
							</button>
							<button type="button" class="button button-primary rhw-btn-accent" id="rhw-add-question-btn">
								<span class="dashicons dashicons-plus"></span> <?php esc_html_e( 'Add Question', 'rhw-calculator' ); ?>
							</button>
						</div>
					</div>

					<div id="rhw-sections-container">
						<!-- Rendered dynamically by rhw-admin.js -->
					</div>
				</div>
			</div>
		</div>

		<!-- TAB 2: Pricing Settings -->
		<div class="rhw-tab-pane" id="tab-pricing">
			<div class="rhw-card">
				<h2 class="rhw-card-title"><?php esc_html_e( 'Tax, Service Charge & Currency Rules', 'rhw-calculator' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Derived from standard Jamaican venue and hospitality pricing policies.', 'rhw-calculator' ); ?></p>

				<table class="form-table">
					<tr>
						<th scope="row"><label for="rhw_pricing_currency"><?php esc_html_e( 'Currency Symbol', 'rhw-calculator' ); ?></label></th>
						<td>
							<input type="text" id="rhw_pricing_currency" style="width: 80px;" value="<?php echo esc_attr( isset( $form['pricing_settings']['currency_symbol'] ) ? $form['pricing_settings']['currency_symbol'] : '$' ); ?>">
							<span class="description"><?php esc_html_e( 'e.g. $ or JMD', 'rhw-calculator' ); ?></span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rhw_pricing_tax"><?php esc_html_e( 'Government Tax Rate (%)', 'rhw-calculator' ); ?></label></th>
						<td>
							<input type="number" step="0.1" min="0" max="100" id="rhw_pricing_tax" style="width: 100px;" value="<?php echo esc_attr( isset( $form['pricing_settings']['tax_rate'] ) ? $form['pricing_settings']['tax_rate'] : 15 ); ?>"> %
							<p class="description"><?php esc_html_e( 'Applicable Government Tax (15% in standard Rose Hall wedding proposals).', 'rhw-calculator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rhw_pricing_service_charge"><?php esc_html_e( 'F&B Service Charge (%)', 'rhw-calculator' ); ?></label></th>
						<td>
							<input type="number" step="0.1" min="0" max="100" id="rhw_pricing_service_charge" style="width: 100px;" value="<?php echo esc_attr( isset( $form['pricing_settings']['service_charge_rate'] ) ? $form['pricing_settings']['service_charge_rate'] : 20 ); ?>"> %
							<p class="description"><?php esc_html_e( 'Service charge applied to Food & Beverage items and Signature Bar packages (20% on proposal).', 'rhw-calculator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rhw_pricing_estimate_label"><?php esc_html_e( 'Estimate Grand Total Label', 'rhw-calculator' ); ?></label></th>
						<td>
							<input type="text" id="rhw_pricing_estimate_label" class="regular-text" value="<?php echo esc_attr( isset( $form['pricing_settings']['estimate_label'] ) ? $form['pricing_settings']['estimate_label'] : 'Estimated Investment' ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rhw_pricing_disclaimer"><?php esc_html_e( 'Customer Explanatory / Disclaimer Text', 'rhw-calculator' ); ?></label></th>
						<td>
							<textarea id="rhw_pricing_disclaimer" rows="4" class="large-text"><?php echo esc_textarea( isset( $form['pricing_settings']['disclaimer_text'] ) ? $form['pricing_settings']['disclaimer_text'] : '' ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Explains clearly to prospective couples that the displayed amount is an estimate, not a confirmed contract quote.', 'rhw-calculator' ); ?></p>
						</td>
					</tr>
				</table>
			</div>
		</div>

		<!-- TAB 3: Notifications -->
		<div class="rhw-tab-pane" id="tab-notifications">
			<div class="rhw-card">
				<h2 class="rhw-card-title"><?php esc_html_e( 'Lead Capture & Notification Settings', 'rhw-calculator' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Configure where submissions are sent and customize email templates.', 'rhw-calculator' ); ?></p>

				<table class="form-table">
					<tr>
						<th scope="row"><label for="rhw_notif_recipients"><?php esc_html_e( 'Venue Notification Recipients', 'rhw-calculator' ); ?> *</label></th>
						<td>
							<input type="text" id="rhw_notif_recipients" class="large-text" value="<?php echo esc_attr( isset( $form['notification_settings']['recipient_emails'] ) ? $form['notification_settings']['recipient_emails'] : get_option( 'admin_email' ) ); ?>">
							<p class="description"><?php esc_html_e( 'Comma-separated email addresses (e.g. weddings@rosehall.com, sales@rosehall.com).', 'rhw-calculator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rhw_notif_subject"><?php esc_html_e( 'Notification Subject Template', 'rhw-calculator' ); ?></label></th>
						<td>
							<input type="text" id="rhw_notif_subject" class="large-text" value="<?php echo esc_attr( isset( $form['notification_settings']['email_subject'] ) ? $form['notification_settings']['email_subject'] : 'New Wedding Quote & Consultation Request: {customer_name} ({event_date})' ); ?>">
							<p class="description"><?php esc_html_e( 'Available tags: {customer_name}, {event_date}, {submission_id}', 'rhw-calculator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rhw_notif_sender_name"><?php esc_html_e( 'Sender Name', 'rhw-calculator' ); ?></label></th>
						<td>
							<input type="text" id="rhw_notif_sender_name" class="regular-text" value="<?php echo esc_attr( isset( $form['notification_settings']['sender_name'] ) ? $form['notification_settings']['sender_name'] : 'Rose Hall Weddings & Events' ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rhw_notif_sender_email"><?php esc_html_e( 'Sender Email Address', 'rhw-calculator' ); ?></label></th>
						<td>
							<input type="email" id="rhw_notif_sender_email" class="regular-text" value="<?php echo esc_attr( isset( $form['notification_settings']['sender_email'] ) ? $form['notification_settings']['sender_email'] : get_option( 'admin_email' ) ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Customer Receipt Email', 'rhw-calculator' ); ?></th>
						<td>
							<label>
								<input type="checkbox" id="rhw_notif_send_receipt" value="1" <?php checked( ! empty( $form['notification_settings']['send_customer_receipt'] ) ); ?>>
								<?php esc_html_e( 'Automatically send a copy of the estimate and consultation next steps to the customer upon submission.', 'rhw-calculator' ); ?>
							</label>
						</td>
					</tr>
				</table>
			</div>
		</div>

		<!-- TAB 4: Appearance & Custom CSS -->
		<div class="rhw-tab-pane" id="tab-appearance">
			<div class="rhw-card">
				<h2 class="rhw-card-title"><?php esc_html_e( 'Brand Styling & Presentation', 'rhw-calculator' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Tailor the calculator to your site aesthetics or the signature Rose Hall blush, wine and gold palette.', 'rhw-calculator' ); ?></p>

				<table class="form-table">
					<tr>
						<th scope="row"><label for="rhw_app_primary"><?php esc_html_e( 'Primary Brand Color', 'rhw-calculator' ); ?></label></th>
						<td>
							<input type="color" id="rhw_app_primary" value="<?php echo esc_attr( isset( $form['appearance_settings']['primary_color'] ) ? $form['appearance_settings']['primary_color'] : '#7B112D' ); ?>">
							<span class="description"><?php esc_html_e( 'Buttons, active indicators, section headers (Default: #7B112D Rose Hall Wine).', 'rhw-calculator' ); ?></span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rhw_app_accent"><?php esc_html_e( 'Accent Color', 'rhw-calculator' ); ?></label></th>
						<td>
							<input type="color" id="rhw_app_accent" value="<?php echo esc_attr( isset( $form['appearance_settings']['accent_color'] ) ? $form['appearance_settings']['accent_color'] : '#C5A059' ); ?>">
							<span class="description"><?php esc_html_e( 'Badges, borders, highlights (Default: #C5A059 Rose Hall Gold).', 'rhw-calculator' ); ?></span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rhw_app_bg"><?php esc_html_e( 'Container Background Color', 'rhw-calculator' ); ?></label></th>
						<td>
							<input type="color" id="rhw_app_bg" value="<?php echo esc_attr( isset( $form['appearance_settings']['background_color'] ) ? $form['appearance_settings']['background_color'] : '#FDFBF7' ); ?>">
							<span class="description"><?php esc_html_e( 'Default: #FDFBF7 Luxury Warm Ivory.', 'rhw-calculator' ); ?></span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rhw_app_radius"><?php esc_html_e( 'Border Radius', 'rhw-calculator' ); ?></label></th>
						<td>
							<input type="text" id="rhw_app_radius" style="width: 100px;" value="<?php echo esc_attr( isset( $form['appearance_settings']['border_radius'] ) ? $form['appearance_settings']['border_radius'] : '8px' ); ?>">
							<span class="description"><?php esc_html_e( 'e.g. 4px, 8px, 16px', 'rhw-calculator' ); ?></span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rhw_app_custom_css"><?php esc_html_e( 'Custom CSS', 'rhw-calculator' ); ?></label></th>
						<td>
							<textarea id="rhw_app_custom_css" rows="6" class="large-text code" placeholder=".rhw-calculator-wrapper { /* custom styles */ }"><?php echo esc_textarea( isset( $form['appearance_settings']['custom_css'] ) ? $form['appearance_settings']['custom_css'] : '' ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Additional CSS overrides. Sanitized with wp_strip_all_tags for security.', 'rhw-calculator' ); ?></p>
						</td>
					</tr>
				</table>
			</div>
		</div>
	</form>
</div>

<!-- Modal for Live Preview -->
<div id="rhw-preview-modal" class="rhw-modal">
	<div class="rhw-modal-content">
		<div class="rhw-modal-header">
			<h2><?php esc_html_e( 'Calculator Live Test & Estimate Preview', 'rhw-calculator' ); ?></h2>
			<button type="button" class="rhw-modal-close">&times;</button>
		</div>
		<div class="rhw-modal-body" id="rhw-preview-container">
			<p style="text-align: center; color: #777; padding: 40px;"><?php esc_html_e( 'Loading interactive preview...', 'rhw-calculator' ); ?></p>
		</div>
	</div>
</div>

<!-- Raw Seed Data for JavaScript initialization -->
<script type="text/javascript">
	window.rhwInitialFormState = {
		sections: <?php echo wp_json_encode( $form['sections'] ); ?>,
		questions: <?php echo wp_json_encode( $form['questions'] ); ?>,
		pricing_settings: <?php echo wp_json_encode( $form['pricing_settings'] ); ?>,
		notification_settings: <?php echo wp_json_encode( $form['notification_settings'] ); ?>,
		appearance_settings: <?php echo wp_json_encode( $form['appearance_settings'] ); ?>
	};
</script>
