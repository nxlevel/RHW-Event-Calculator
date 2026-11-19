<?php
/**
 * Public Calculator Template.
 *
 * Renders the multi-step interactive event calculator, live loan-style estimate card,
 * and lead capture form.
 *
 * @package RoseHallWeddings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sections    = isset( $form['sections'] ) ? $form['sections'] : array();
$questions   = isset( $form['questions'] ) ? $form['questions'] : array();
$pricing     = isset( $form['pricing_settings'] ) ? $form['pricing_settings'] : array();
$appearance  = isset( $form['appearance_settings'] ) ? $form['appearance_settings'] : array();

$currency_symbol = ! empty( $pricing['currency_symbol'] ) ? $pricing['currency_symbol'] : '$';
$estimate_label  = ! empty( $pricing['estimate_label'] ) ? $pricing['estimate_label'] : __( 'Estimated Investment', 'rhw-calculator' );
$disclaimer_text = ! empty( $pricing['disclaimer_text'] ) ? $pricing['disclaimer_text'] : '';

// Brand styling inline variables
$primary_color = ! empty( $appearance['primary_color'] ) ? $appearance['primary_color'] : '#7B112D';
$accent_color  = ! empty( $appearance['accent_color'] ) ? $appearance['accent_color'] : '#C5A059';
$bg_color      = ! empty( $appearance['background_color'] ) ? $appearance['background_color'] : '#FDFBF7';
$radius        = ! empty( $appearance['border_radius'] ) ? $appearance['border_radius'] : '8px';
?>

<div id="rhw-calc-container-<?php echo esc_attr( $form_id ); ?>"
     class="rhw-calc-wrapper"
     data-form-id="<?php echo esc_attr( $form_id ); ?>"
     style="--rhw-primary: <?php echo esc_attr( $primary_color ); ?>; --rhw-accent: <?php echo esc_attr( $accent_color ); ?>; --rhw-bg: <?php echo esc_attr( $bg_color ); ?>; --rhw-radius: <?php echo esc_attr( $radius ); ?>;">

	<?php if ( ! empty( $appearance['custom_css'] ) ) : ?>
		<style><?php echo wp_strip_all_tags( $appearance['custom_css'] ); ?></style>
	<?php endif; ?>

	<!-- Header Banner -->
	<header class="rhw-calc-header">
		<div class="rhw-calc-badge"><?php esc_html_e( 'Rose Hall Weddings & Events', 'rhw-calculator' ); ?></div>
		<h2 class="rhw-calc-title"><?php echo esc_html( $form['title'] ); ?></h2>
		<?php if ( ! empty( $form['description'] ) ) : ?>
			<p class="rhw-calc-description"><?php echo esc_html( $form['description'] ); ?></p>
		<?php endif; ?>
	</header>

	<!-- Main Calculator Layout: 2 Columns (Form Steps + Sticky Estimate Summary) -->
	<div class="rhw-calc-grid">
		<!-- Left: Form Wizard Steps -->
		<div class="rhw-calc-main">
			<!-- Step Progress Indicator -->
			<nav class="rhw-wizard-nav" aria-label="<?php esc_attr_e( 'Calculator Steps', 'rhw-calculator' ); ?>">
				<div class="rhw-progress-bar-track">
					<div class="rhw-progress-bar-fill" style="width: 16.66%;"></div>
				</div>
				<ul class="rhw-steps-list">
					<?php foreach ( $sections as $s_idx => $sec ) : ?>
						<li class="rhw-step-item <?php echo 0 === $s_idx ? 'active' : ''; ?>" data-step-index="<?php echo esc_attr( $s_idx ); ?>">
							<span class="rhw-step-number"><?php echo esc_html( $s_idx + 1 ); ?></span>
							<span class="rhw-step-title"><?php echo esc_html( $sec['title'] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</nav>

			<!-- Wizard Form Form -->
			<form class="rhw-calculator-form" novalidate>
				<!-- Anti-spam Honeypot (hidden from human visitors) -->
				<div style="display:none !important;" aria-hidden="true">
					<label for="rhw_hp_<?php echo esc_attr( $form_id ); ?>">Leave this field blank</label>
					<input type="text" name="rhw_website_hp" id="rhw_hp_<?php echo esc_attr( $form_id ); ?>" tabindex="-1" autocomplete="off">
				</div>

				<?php foreach ( $sections as $s_idx => $sec ) : ?>
					<fieldset class="rhw-step-pane <?php echo 0 === $s_idx ? 'active' : ''; ?>" data-step-index="<?php echo esc_attr( $s_idx ); ?>">
						<legend class="rhw-screen-reader-text"><?php echo esc_html( $sec['title'] ); ?></legend>
						<div class="rhw-step-header">
							<h3 class="rhw-step-heading"><?php echo esc_html( $sec['title'] ); ?></h3>
							<?php if ( ! empty( $sec['description'] ) ) : ?>
								<p class="rhw-step-subheading"><?php echo esc_html( $sec['description'] ); ?></p>
							<?php endif; ?>
						</div>

						<div class="rhw-questions-wrapper">
							<?php
							$sec_questions = array_filter(
								$questions,
								function( $q ) use ( $sec ) {
									return isset( $q['section_id'] ) && $q['section_id'] === $sec['id'];
								}
							);
							?>

							<?php foreach ( $sec_questions as $q ) : ?>
								<?php
								$qid       = esc_attr( $q['id'] );
								$is_req    = ! empty( $q['required'] );
								$cond_data = ! empty( $q['conditional'] ) ? wp_json_encode( $q['conditional'] ) : '';
								?>
								<div class="rhw-form-group rhw-q-field"
								     data-qid="<?php echo $qid; ?>"
								     <?php if ( $cond_data ) : ?>data-conditional='<?php echo esc_attr( $cond_data ); ?>'<?php endif; ?>
								     <?php if ( ! empty( $q['requires_full_service'] ) ) : ?>data-full-service-only="1"<?php endif; ?>>

									<label class="rhw-field-label" for="rhw_field_<?php echo $qid; ?>">
										<?php echo esc_html( $q['label'] ); ?>
										<?php if ( $is_req ) : ?>
											<span class="rhw-required-star" aria-hidden="true">*</span>
										<?php endif; ?>
									</label>

									<?php if ( ! empty( $q['help_text'] ) ) : ?>
										<div class="rhw-field-help"><?php echo esc_html( $q['help_text'] ); ?></div>
									<?php endif; ?>

									<!-- Field Inputs depending on Type -->
									<?php if ( 'radio' === $q['type'] ) : ?>
										<div class="rhw-radio-group">
											<?php if ( ! empty( $q['options'] ) ) : ?>
												<?php foreach ( $q['options'] as $o_idx => $opt ) : ?>
													<?php
													$opt_val = isset( $opt['value'] ) ? $opt['value'] : $opt['label'];
													$opt_id  = 'rhw_rad_' . $qid . '_' . $o_idx;
													?>
													<label class="rhw-choice-tile rhw-radio-tile" for="<?php echo esc_attr( $opt_id ); ?>">
														<input type="radio"
														       name="<?php echo $qid; ?>"
														       id="<?php echo esc_attr( $opt_id ); ?>"
														       value="<?php echo esc_attr( $opt_val ); ?>"
														       <?php echo $is_req ? 'required' : ''; ?>>
														<span class="rhw-choice-indicator"></span>
														<span class="rhw-choice-text"><?php echo esc_html( $opt['label'] ); ?></span>
													</label>
												<?php endforeach; ?>
											<?php endif; ?>
										</div>

									<?php elseif ( 'checkboxes' === $q['type'] || 'checkbox' === $q['type'] ) : ?>
										<div class="rhw-checkbox-group">
											<?php if ( ! empty( $q['options'] ) ) : ?>
												<?php foreach ( $q['options'] as $o_idx => $opt ) : ?>
													<?php
													$opt_val = isset( $opt['value'] ) ? $opt['value'] : $opt['label'];
													$opt_id  = 'rhw_chk_' . $qid . '_' . $o_idx;
													?>
													<label class="rhw-choice-tile rhw-checkbox-tile" for="<?php echo esc_attr( $opt_id ); ?>">
														<input type="checkbox"
														       name="<?php echo $qid; ?>[]"
														       id="<?php echo esc_attr( $opt_id ); ?>"
														       value="<?php echo esc_attr( $opt_val ); ?>">
														<span class="rhw-choice-indicator"></span>
														<span class="rhw-choice-text"><?php echo esc_html( $opt['label'] ); ?></span>
													</label>
												<?php endforeach; ?>
											<?php endif; ?>
										</div>

									<?php elseif ( 'select' === $q['type'] ) : ?>
										<select name="<?php echo $qid; ?>"
										        id="rhw_field_<?php echo $qid; ?>"
										        class="rhw-select-input"
										        <?php echo $is_req ? 'required' : ''; ?>>
											<?php if ( ! empty( $q['options'] ) ) : ?>
												<?php foreach ( $q['options'] as $opt ) : ?>
													<option value="<?php echo esc_attr( isset( $opt['value'] ) ? $opt['value'] : $opt['label'] ); ?>">
														<?php echo esc_html( $opt['label'] ); ?>
													</option>
												<?php endforeach; ?>
											<?php endif; ?>
										</select>

									<?php elseif ( 'number' === $q['type'] ) : ?>
										<div class="rhw-number-input-wrap">
											<button type="button" class="rhw-num-btn rhw-num-minus" aria-label="Decrease">&minus;</button>
											<input type="number"
											       name="<?php echo $qid; ?>"
											       id="rhw_field_<?php echo $qid; ?>"
											       class="rhw-number-input"
											       value="<?php echo esc_attr( isset( $q['default_value'] ) ? $q['default_value'] : 1 ); ?>"
											       min="<?php echo esc_attr( isset( $q['min'] ) ? $q['min'] : 1 ); ?>"
											       max="<?php echo esc_attr( isset( $q['max'] ) ? $q['max'] : 1000 ); ?>"
											       <?php echo $is_req ? 'required' : ''; ?>>
											<button type="button" class="rhw-num-btn rhw-num-plus" aria-label="Increase">&plus;</button>
										</div>

									<?php elseif ( 'textarea' === $q['type'] ) : ?>
										<textarea name="<?php echo $qid; ?>"
										          id="rhw_field_<?php echo $qid; ?>"
										          class="rhw-textarea-input"
										          rows="3"
										          placeholder="<?php echo esc_attr( isset( $q['placeholder'] ) ? $q['placeholder'] : '' ); ?>"
										          <?php echo $is_req ? 'required' : ''; ?>></textarea>

									<?php else : ?>
										<input type="<?php echo esc_attr( in_array( $q['type'], array( 'email', 'tel', 'date' ), true ) ? $q['type'] : 'text' ); ?>"
										       name="<?php echo $qid; ?>"
										       id="rhw_field_<?php echo $qid; ?>"
										       class="rhw-text-input"
										       placeholder="<?php echo esc_attr( isset( $q['placeholder'] ) ? $q['placeholder'] : '' ); ?>"
										       <?php echo $is_req ? 'required' : ''; ?>>
									<?php endif; ?>

									<div class="rhw-error-message" role="alert"></div>
								</div>
							<?php endforeach; ?>
						</div>

						<!-- Wizard Navigation Buttons -->
						<div class="rhw-wizard-buttons">
							<?php if ( $s_idx > 0 ) : ?>
								<button type="button" class="rhw-btn rhw-btn-prev">
									&larr; <?php esc_html_e( 'Previous', 'rhw-calculator' ); ?>
								</button>
							<?php else : ?>
								<div></div>
							<?php endif; ?>

							<?php if ( $s_idx < count( $sections ) - 1 ) : ?>
								<button type="button" class="rhw-btn rhw-btn-next">
									<?php esc_html_e( 'Continue', 'rhw-calculator' ); ?> &rarr;
								</button>
							<?php else : ?>
								<button type="submit" class="rhw-btn rhw-btn-submit">
									<span class="rhw-btn-spinner" aria-hidden="true"></span>
									<span class="rhw-btn-text"><?php esc_html_e( 'Request Consultation & Estimate', 'rhw-calculator' ); ?></span>
								</button>
							<?php endif; ?>
						</div>
					</fieldset>
				<?php endforeach; ?>
			</form>

			<!-- Submission Success View -->
			<div class="rhw-submission-success-view" style="display:none;" role="region" aria-live="polite">
				<div class="rhw-success-card">
					<div class="rhw-success-icon-wrap">
						<svg class="rhw-success-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
							<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
						</svg>
					</div>
					<h3 class="rhw-success-heading"><?php esc_html_e( 'Your Dream Wedding Request is Received!', 'rhw-calculator' ); ?></h3>
					<p class="rhw-success-lead">
						<?php esc_html_e( 'Thank you, ', 'rhw-calculator' ); ?><strong class="rhw-lead-name-text"></strong>!
						<?php esc_html_e( 'A preliminary quote has been generated and dispatched to your email.', 'rhw-calculator' ); ?>
					</p>

					<div class="rhw-success-summary-box">
						<div class="rhw-success-sum-row">
							<span><?php esc_html_e( 'Preferred Event Date:', 'rhw-calculator' ); ?></span>
							<strong class="rhw-lead-date-text"></strong>
						</div>
						<div class="rhw-success-sum-row">
							<span><?php esc_html_e( 'Guest Attendance:', 'rhw-calculator' ); ?></span>
							<strong class="rhw-lead-guests-text"></strong>
						</div>
						<div class="rhw-success-sum-row rhw-sum-highlight">
							<span><?php esc_html_e( 'Preliminary Grand Total:', 'rhw-calculator' ); ?></span>
							<strong class="rhw-lead-total-text"></strong>
						</div>
					</div>

					<div class="rhw-next-steps-box">
						<h4><?php esc_html_e( 'What Happens Next?', 'rhw-calculator' ); ?></h4>
						<p><?php esc_html_e( 'Our Destination Relationship Specialists will review your vision and preliminary estimate. Within 3 to 5 business days, you will receive a consultation invite and formal proposal.', 'rhw-calculator' ); ?></p>
					</div>
				</div>
			</div>
		</div>

		<!-- Right: Live Cost Calculator Card (Loan-style real-time updating) -->
		<aside class="rhw-calc-sidebar" aria-label="<?php esc_attr_e( 'Live Cost Summary', 'rhw-calculator' ); ?>">
			<div class="rhw-summary-card rhw-sticky-card">
				<div class="rhw-summary-header">
					<span class="rhw-summary-subtitle"><?php echo esc_html( $estimate_label ); ?></span>
					<div class="rhw-summary-total">
						<span class="rhw-curr-symbol"><?php echo esc_html( $currency_symbol ); ?></span>
						<span class="rhw-total-number" id="rhw-live-total-display">0.00</span>
					</div>
					<div class="rhw-guest-count-pill">
						<span class="dashicons dashicons-groups"></span>
						<span id="rhw-live-guest-display">75</span> <?php esc_html_e( 'Guests', 'rhw-calculator' ); ?>
					</div>
				</div>

				<!-- Itemized Breakdown Drawer -->
				<div class="rhw-summary-body">
					<h4 class="rhw-breakdown-heading"><?php esc_html_e( 'Itemized Line Items', 'rhw-calculator' ); ?></h4>
					<ul class="rhw-line-items-list" id="rhw-line-items-container">
						<!-- Dynamically populated in real time by JS -->
						<li class="rhw-empty-item"><?php esc_html_e( 'Select options to view live calculation.', 'rhw-calculator' ); ?></li>
					</ul>

					<div class="rhw-subtotals-wrap">
						<div class="rhw-subtotal-row">
							<span><?php esc_html_e( 'Subtotal', 'rhw-calculator' ); ?></span>
							<strong id="rhw-live-subtotal-display">$0.00</strong>
						</div>
						<div class="rhw-subtotal-row rhw-row-service-charge" style="display:none;">
							<span><?php esc_html_e( '20% Service Charge (F&B)', 'rhw-calculator' ); ?></span>
							<strong id="rhw-live-service-display">$0.00</strong>
						</div>
						<div class="rhw-subtotal-row rhw-row-tax" style="display:none;">
							<span><?php esc_html_e( '15% Government Tax', 'rhw-calculator' ); ?></span>
							<strong id="rhw-live-tax-display">$0.00</strong>
						</div>
					</div>
				</div>

				<!-- Customer Explanatory / Disclaimer Footer -->
				<div class="rhw-summary-footer">
					<div class="rhw-estimate-badge">
						<svg class="rhw-info-icon" viewBox="0 0 20 20" fill="currentColor">
							<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
						</svg>
						<span><?php esc_html_e( 'Preliminary Estimate Only', 'rhw-calculator' ); ?></span>
					</div>
					<?php if ( ! empty( $disclaimer_text ) ) : ?>
						<p class="rhw-disclaimer-text"><?php echo esc_html( $disclaimer_text ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</aside>
	</div>

	<!-- Mobile Sticky Bottom Bar (Quick total peek for small screens) -->
	<div class="rhw-mobile-summary-bar">
		<div>
			<div class="rhw-mobile-label"><?php echo esc_html( $estimate_label ); ?></div>
			<div class="rhw-mobile-total">
				<?php echo esc_html( $currency_symbol ); ?><span class="rhw-mobile-total-number">0.00</span>
			</div>
		</div>
		<button type="button" class="rhw-mobile-toggle-btn" aria-expanded="false">
			<?php esc_html_e( 'View Breakdown', 'rhw-calculator' ); ?> &uarr;
		</button>
	</div>
</div>
