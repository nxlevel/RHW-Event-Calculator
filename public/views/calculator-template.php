<?php
/**
 * Public Calculator Template.
 *
 * Renders the multi-step interactive event calculator, full-width progress bar,
 * live loan-style estimate card, file uploads, and lead capture form.
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
$estimate_label  = ! empty( $pricing['estimate_label'] ) ? $pricing['estimate_label'] : __( 'Estimated Cost', 'rhw-calculator' );
if ( 'Estimated Investment' === $estimate_label ) {
	$estimate_label = __( 'Estimated Cost', 'rhw-calculator' );
}
$disclaimer_text = ! empty( $pricing['disclaimer_text'] ) ? $pricing['disclaimer_text'] : '';

// Brand styling inline variables
$primary_color = ! empty( $appearance['primary_color'] ) ? $appearance['primary_color'] : '#7B112D';
$accent_color  = ! empty( $appearance['accent_color'] ) ? $appearance['accent_color'] : '#C5A059';
$bg_color      = ! empty( $appearance['background_color'] ) ? $appearance['background_color'] : '#FDFBF7';
$radius        = ! empty( $appearance['border_radius'] ) ? $appearance['border_radius'] : '8px';

if ( ! function_exists( 'rhw_format_clickable_text' ) ) {
	/**
	 * Convert URLs in text to clickable links with target="_blank"
	 *
	 * @param string $text Plain or HTML text.
	 * @return string Sanitized HTML with clickable links.
	 */
	function rhw_format_clickable_text( $text ) {
		if ( empty( $text ) ) {
			return '';
		}
		// Convert bare URLs into links
		$text = make_clickable( $text );
		// Ensure target="_blank" and rel="noopener noreferrer"
		$text = preg_replace( '/<a\s+(?!.*?target=)([^>]+)>/i', '<a target="_blank" rel="noopener noreferrer" $1>', $text );
		$text = preg_replace( '/target=[\'"][^\'"]*[\'"]/i', 'target="_blank" rel="noopener noreferrer"', $text );
		return wp_kses(
			$text,
			array(
				'a'      => array(
					'href'   => array(),
					'title'  => array(),
					'target' => array(),
					'rel'    => array(),
					'class'  => array(),
				),
				'strong' => array(),
				'b'      => array(),
				'em'     => array(),
				'i'      => array(),
				'span'   => array( 'class' => array() ),
				'br'     => array(),
			)
		);
	}
}
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
		<?php $header_image_url = get_option( 'rhw_header_image_url', '' ); ?>
		<?php if ( ! empty( $header_image_url ) ) : ?>
			<div class="rhw-calc-header-image-wrap">
				<img src="<?php echo esc_url( $header_image_url ); ?>" alt="<?php echo esc_attr( $form['title'] ); ?>" class="rhw-calc-header-image" width="130" height="130">
			</div>
		<?php endif; ?>
		<h2 class="rhw-calc-title"><?php echo esc_html( $form['title'] ); ?></h2>
		<?php if ( ! empty( $form['description'] ) ) : ?>
			<p class="rhw-calc-description"><?php echo esc_html( $form['description'] ); ?></p>
		<?php endif; ?>
	</header>

	<!-- Step Progress Indicator: Full Width Across Both Columns -->
	<nav class="rhw-wizard-nav rhw-full-width-nav" aria-label="<?php esc_attr_e( 'Calculator Steps', 'rhw-calculator' ); ?>">
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

	<!-- Main Calculator Layout: 2 Columns (Form Steps + Sticky Estimate Summary) -->
	<div class="rhw-calc-grid">
		<!-- Left: Form Wizard Steps -->
		<div class="rhw-calc-main">
			<form class="rhw-calculator-form" enctype="multipart/form-data" novalidate>
				<!-- Anti-spam Honeypot -->
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
								<?php
								$sec_desc = $sec['description'];
								if ( false !== stripos( $sec_desc, 'Tell us about your envisioned celebration date' ) || false !== stripos( $sec_desc, 'Select your preferred date, guest count, and exquisite Rose Hall venue' ) ) {
									$sec_desc = __( 'Tell us about your upcoming event date, guest count, preferred venue, décor, etc.', 'rhw-calculator' );
								}
								?>
								<p class="rhw-step-subheading"><?php echo rhw_format_clickable_text( $sec_desc ); ?></p>
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
								$has_other = ! empty( $q['has_other_text'] );
								?>
								<div class="rhw-form-group rhw-q-field"
								     id="rhw-group-<?php echo $qid; ?>"
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
										<div class="rhw-field-help"><?php echo rhw_format_clickable_text( $q['help_text'] ); ?></div>
									<?php endif; ?>

									<!-- Date & Time Picker (Weekday / Weekend) all-in-one -->
									<?php if ( 'datetime' === $q['type'] || ( 'date' === $q['type'] && ( false !== stripos( $q['label'], 'time' ) || ! empty( $q['has_time'] ) ) ) ) : ?>
										<div class="rhw-datetime-picker-wrap" data-qid="<?php echo $qid; ?>">
											<div class="rhw-datetime-grid">
												<div class="rhw-datetime-col">
													<label class="rhw-input-sublabel" for="rhw_date_<?php echo $qid; ?>">
														<span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e( 'Date', 'rhw-calculator' ); ?>
													</label>
													<input type="date"
													       name="<?php echo $qid; ?>_date"
													       id="rhw_date_<?php echo $qid; ?>"
													       class="rhw-text-input rhw-date-input"
													       min="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>"
													       <?php echo $is_req ? 'required' : ''; ?>>
												</div>
												<div class="rhw-datetime-col">
													<label class="rhw-input-sublabel" for="rhw_time_<?php echo $qid; ?>">
														<span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Time', 'rhw-calculator' ); ?>
													</label>
													<input type="time"
													       name="<?php echo $qid; ?>_time"
													       id="rhw_time_<?php echo $qid; ?>"
													       class="rhw-text-input rhw-time-input"
													       value="16:00"
													       <?php echo $is_req ? 'required' : ''; ?>>
												</div>
											</div>
											<input type="hidden"
											       name="<?php echo $qid; ?>"
											       id="rhw_field_<?php echo $qid; ?>"
											       class="rhw-datetime-combined"
											       value="">
											<?php if ( 'event_date' === $qid ) : ?>
												<input type="hidden" name="event_time" class="rhw-datetime-time-mirror" value="16:00">
											<?php endif; ?>
											<div class="rhw-date-rate-pill" id="rhw-rate-indicator-<?php echo $qid; ?>" style="display:none;">
												<span class="dashicons dashicons-calendar-alt"></span>
												<span class="rhw-rate-pill-text"></span>
											</div>
										</div>

									<!-- Date Picker with Weekday / Weekend Live Indicator -->
									<?php elseif ( 'date' === $q['type'] ) : ?>
										<div class="rhw-date-picker-wrap">
											<input type="date"
											       name="<?php echo $qid; ?>"
											       id="rhw_field_<?php echo $qid; ?>"
											       class="rhw-text-input rhw-date-input"
											       min="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>"
											       <?php echo $is_req ? 'required' : ''; ?>>
											<div class="rhw-date-rate-pill" id="rhw-rate-indicator-<?php echo $qid; ?>" style="display:none;">
												<span class="dashicons dashicons-calendar-alt"></span>
												<span class="rhw-rate-pill-text"></span>
											</div>
										</div>

									<!-- Time Picker -->
									<?php elseif ( 'time' === $q['type'] ) : ?>
										<div class="rhw-time-picker-wrap">
											<input type="time"
											       name="<?php echo $qid; ?>"
											       id="rhw_field_<?php echo $qid; ?>"
											       class="rhw-text-input rhw-time-input"
											       value="16:00"
											       <?php echo $is_req ? 'required' : ''; ?>>
										</div>

									<!-- File Upload for Décor & Bouquet Images -->
									<?php elseif ( 'file' === $q['type'] ) : ?>
										<div class="rhw-file-upload-wrap">
											<label class="rhw-file-dropzone" for="rhw_field_<?php echo $qid; ?>">
												<div class="rhw-dropzone-content">
													<svg class="rhw-upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
														<path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
													</svg>
													<span class="rhw-upload-prompt"><strong><?php esc_html_e( 'Click to choose image', 'rhw-calculator' ); ?></strong> <?php esc_html_e( 'or drag & drop here', 'rhw-calculator' ); ?></span>
													<span class="rhw-upload-subtext"><?php esc_html_e( 'JPG, PNG, WEBP, or PDF up to 10MB', 'rhw-calculator' ); ?></span>
												</div>
												<input type="file"
												       name="answers_files[<?php echo $qid; ?>]"
												       id="rhw_field_<?php echo $qid; ?>"
												       class="rhw-file-input"
												       accept="<?php echo esc_attr( isset( $q['accept'] ) ? $q['accept'] : 'image/*' ); ?>"
												       <?php echo $is_req ? 'required' : ''; ?>>
											</label>
											<div class="rhw-file-preview-box" id="rhw-preview-<?php echo $qid; ?>" style="display:none;">
												<div class="rhw-preview-info">
													<img class="rhw-preview-thumb" src="" alt="Preview">
													<span class="rhw-preview-filename"></span>
												</div>
												<button type="button" class="rhw-file-remove-btn">&times;</button>
											</div>
										</div>

									<!-- Radio Group with "Other" Text Support & Venue Pricing -->
									<?php elseif ( 'radio' === $q['type'] ) : ?>
										<div class="rhw-radio-group">
											<?php if ( ! empty( $q['options'] ) ) : ?>
												<?php foreach ( $q['options'] as $o_idx => $opt ) : ?>
													<?php
													$opt_val = isset( $opt['value'] ) ? $opt['value'] : $opt['label'];
													$opt_id  = 'rhw_rad_' . $qid . '_' . $o_idx;
													$is_opt_other = ( 'other' === $opt_val || false !== stripos( $opt_val, 'other' ) );
													$p = isset( $opt['pricing'] ) ? $opt['pricing'] : array();
													$weekday_amt = isset( $p['weekday_amount'] ) ? $p['weekday_amount'] : ( isset( $p['amount'] ) ? $p['amount'] : 0 );
													$weekend_amt = isset( $p['weekend_amount'] ) ? $p['weekend_amount'] : ( isset( $p['amount'] ) ? $p['amount'] : 0 );
													$is_venue = ! empty( $p['is_venue'] );
													?>
													<label class="rhw-choice-tile rhw-radio-tile <?php echo $is_opt_other ? 'rhw-has-other-tile' : ''; ?>" for="<?php echo esc_attr( $opt_id ); ?>">
														<input type="radio"
														       name="<?php echo $qid; ?>"
														       id="<?php echo esc_attr( $opt_id ); ?>"
														       value="<?php echo esc_attr( $opt_val ); ?>"
														       data-is-other="<?php echo $is_opt_other ? '1' : '0'; ?>"
														       <?php if ( $is_venue ) : ?>
														           data-is-venue="1"
														           data-weekday-price="<?php echo esc_attr( $weekday_amt ); ?>"
														           data-weekend-price="<?php echo esc_attr( $weekend_amt ); ?>"
														       <?php endif; ?>
														       <?php echo $is_req ? 'required' : ''; ?>>
														<span class="rhw-choice-indicator"></span>
														<span class="rhw-choice-text">
															<span class="rhw-opt-main-label"><?php echo esc_html( $opt['label'] ); ?></span>
															<?php if ( $is_venue ) : ?>
																<span class="rhw-venue-dynamic-price" data-weekday="<?php echo esc_attr( $weekday_amt ); ?>" data-weekend="<?php echo esc_attr( $weekend_amt ); ?>">
																	($<?php echo esc_html( number_format( $weekend_amt, 2 ) ); ?>)
																</span>
															<?php endif; ?>
														</span>
													</label>

													<?php if ( $is_opt_other || ( $has_other && 'other' === $opt_val ) ) : ?>
														<div class="rhw-other-input-wrap" id="rhw-other-wrap-<?php echo esc_attr( $qid ); ?>" style="display:none;">
															<input type="text"
															       name="answers_other[<?php echo $qid; ?>]"
															       class="rhw-text-input rhw-other-text-input"
															       placeholder="<?php esc_attr_e( 'Please specify other...', 'rhw-calculator' ); ?>">
														</div>
													<?php endif; ?>
												<?php endforeach; ?>
											<?php endif; ?>
										</div>

									<!-- Checkboxes -->
									<?php elseif ( 'checkboxes' === $q['type'] || 'checkbox' === $q['type'] ) : ?>
										<div class="rhw-checkbox-group">
											<?php if ( ! empty( $q['options'] ) ) : ?>
												<?php foreach ( $q['options'] as $o_idx => $opt ) : ?>
													<?php
													$opt_val = isset( $opt['value'] ) ? $opt['value'] : $opt['label'];
													$opt_id  = 'rhw_chk_' . $qid . '_' . $o_idx;
													$is_chk_other = ( 'other' === $opt_val || false !== stripos( $opt_val, 'other' ) );
													?>
													<label class="rhw-choice-tile rhw-checkbox-tile" for="<?php echo esc_attr( $opt_id ); ?>">
														<input type="checkbox"
														       name="<?php echo $qid; ?>[]"
														       id="<?php echo esc_attr( $opt_id ); ?>"
														       value="<?php echo esc_attr( $opt_val ); ?>"
														       data-is-other="<?php echo $is_chk_other ? '1' : '0'; ?>">
														<span class="rhw-choice-indicator"></span>
														<span class="rhw-choice-text"><?php echo esc_html( $opt['label'] ); ?></span>
													</label>
													<?php if ( $is_chk_other || ( $has_other && 'other' === $opt_val ) ) : ?>
														<div class="rhw-other-input-wrap" id="rhw-other-chk-wrap-<?php echo esc_attr( $qid ); ?>" style="display:none;">
															<input type="text"
															       name="answers_other[<?php echo $qid; ?>]"
															       class="rhw-text-input rhw-other-text-input"
															       placeholder="<?php esc_attr_e( 'Please specify other...', 'rhw-calculator' ); ?>">
														</div>
													<?php endif; ?>
												<?php endforeach; ?>
											<?php endif; ?>
										</div>

									<!-- Country Select Dropdown (Item 8) -->
									<?php elseif ( 'country' === $q['type'] || 'lead_country' === $qid ) : ?>
										<select name="<?php echo $qid; ?>"
										        id="rhw_field_<?php echo $qid; ?>"
										        class="rhw-select-input rhw-country-select"
										        <?php echo $is_req ? 'required' : ''; ?>>
											<option value="">-- <?php esc_html_e( 'Select Country of Residence', 'rhw-calculator' ); ?> --</option>
											<?php
											$country_list = class_exists( 'RHW_Form_Manager' ) ? RHW_Form_Manager::get_countries_list() : array();
											foreach ( $country_list as $c_code => $c_name ) :
											?>
												<option value="<?php echo esc_attr( $c_name ); ?>">
													<?php echo esc_html( $c_name ); ?>
												</option>
											<?php endforeach; ?>
										</select>

									<!-- Select Dropdown with "Other" Support -->
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
										<?php if ( $has_other ) : ?>
											<div class="rhw-other-input-wrap" id="rhw-other-sel-wrap-<?php echo esc_attr( $qid ); ?>" style="display:none; margin-top: 8px;">
												<input type="text"
												       name="answers_other[<?php echo $qid; ?>]"
												       class="rhw-text-input rhw-other-text-input"
												       placeholder="<?php esc_attr_e( 'Please specify other...', 'rhw-calculator' ); ?>">
											</div>
										<?php endif; ?>

									<!-- Number Stepper -->
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

									<!-- Textarea -->
									<?php elseif ( 'textarea' === $q['type'] ) : ?>
										<textarea name="<?php echo $qid; ?>"
										          id="rhw_field_<?php echo $qid; ?>"
										          class="rhw-textarea-input"
										          rows="3"
										          placeholder="<?php echo esc_attr( isset( $q['placeholder'] ) ? $q['placeholder'] : '' ); ?>"
										          <?php echo $is_req ? 'required' : ''; ?>></textarea>

									<!-- Generic Text / Email / Tel -->
									<?php else : ?>
										<input type="<?php echo esc_attr( in_array( $q['type'], array( 'email', 'tel' ), true ) ? $q['type'] : 'text' ); ?>"
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

						<?php
						$cal_settings = class_exists( 'RHW_Calendar_Manager' ) ? RHW_Calendar_Manager::get_settings() : array( 'enabled' => 1, 'required' => 0 );
						if ( $s_idx === count( $sections ) - 1 && ! empty( $cal_settings['enabled'] ) ) :
							$cal_tz        = RHW_Calendar_Manager::get_timezone( $cal_settings );
							$cal_now       = new DateTimeImmutable( 'now', $cal_tz );
							$cal_init_year = (int) $cal_now->format( 'Y' );
							$cal_init_mon  = (int) $cal_now->format( 'n' );
							$cal_required  = ! empty( $cal_settings['required'] );
						?>
							<!-- Google Calendar Synced Month Grid Consultation Scheduler -->
							<div class="rhw-consultation-booking-card" data-init-year="<?php echo esc_attr( $cal_init_year ); ?>" data-init-month="<?php echo esc_attr( $cal_init_mon ); ?>" data-cal-required="<?php echo $cal_required ? '1' : '0'; ?>">
								<div class="rhw-cal-card-header">
									<span class="dashicons dashicons-calendar-alt"></span>
									<div>
										<h4>
											<?php esc_html_e( 'Schedule Your Wedding Consultation Call', 'rhw-calculator' ); ?>
											<?php if ( $cal_required ) : ?>
												<span class="rhw-required-star" aria-hidden="true">*</span>
											<?php endif; ?>
										</h4>
										<p><?php esc_html_e( 'Choose an available date square on the calendar below and pick a meeting time synced live with our team Google Calendar.', 'rhw-calculator' ); ?></p>
									</div>
								</div>

								<div class="rhw-pub-cal-layout">
									<!-- Left: Month Calendar Grid with Date Squares -->
									<div class="rhw-pub-cal-month-box">
										<div class="rhw-pub-cal-nav">
											<button type="button" class="rhw-pub-cal-nav-btn rhw-pub-cal-prev" aria-label="<?php esc_attr_e( 'Previous Month', 'rhw-calculator' ); ?>">&lsaquo;</button>
											<strong class="rhw-pub-cal-month-title" id="rhw-pub-cal-month-title"><?php echo esc_html( $cal_now->format( 'F Y' ) ); ?></strong>
											<button type="button" class="rhw-pub-cal-nav-btn rhw-pub-cal-next" aria-label="<?php esc_attr_e( 'Next Month', 'rhw-calculator' ); ?>">&rsaquo;</button>
										</div>

										<div class="rhw-pub-cal-weekdays">
											<div><?php esc_html_e( 'Sun', 'rhw-calculator' ); ?></div>
											<div><?php esc_html_e( 'Mon', 'rhw-calculator' ); ?></div>
											<div><?php esc_html_e( 'Tue', 'rhw-calculator' ); ?></div>
											<div><?php esc_html_e( 'Wed', 'rhw-calculator' ); ?></div>
											<div><?php esc_html_e( 'Thu', 'rhw-calculator' ); ?></div>
											<div><?php esc_html_e( 'Fri', 'rhw-calculator' ); ?></div>
											<div><?php esc_html_e( 'Sat', 'rhw-calculator' ); ?></div>
										</div>

										<div class="rhw-pub-cal-grid" id="rhw-public-cal-grid">
											<div class="rhw-cal-grid-loading"><?php esc_html_e( 'Loading calendar...', 'rhw-calculator' ); ?></div>
										</div>

										<div class="rhw-pub-cal-legend">
											<span><i class="rhw-dot-avail"></i> <?php esc_html_e( 'Available', 'rhw-calculator' ); ?></span>
											<span><i class="rhw-dot-selected"></i> <?php esc_html_e( 'Selected', 'rhw-calculator' ); ?></span>
											<span><i class="rhw-dot-unavail"></i> <?php esc_html_e( 'Unavailable', 'rhw-calculator' ); ?></span>
										</div>
									</div>

									<!-- Right: Available Time Slots for Selected Date -->
									<div class="rhw-pub-cal-times-box">
										<div class="rhw-pub-times-header">
											<span class="dashicons dashicons-clock"></span>
											<span id="rhw-selected-date-heading"><?php esc_html_e( 'Select a date on the calendar', 'rhw-calculator' ); ?></span>
										</div>

										<div class="rhw-slots-container" id="rhw-slots-container">
											<div class="rhw-slots-loading">
												<span class="dashicons dashicons-update rhw-spin"></span> <?php esc_html_e( 'Checking live availability...', 'rhw-calculator' ); ?>
											</div>
										</div>

										<input type="hidden" id="rhw_consultation_date" name="consultation_date" value="">
										<input type="hidden" id="rhw_consultation_time" name="consultation_time" value="">
										<div class="rhw-cal-error-msg" role="alert" style="display:none;"></div>
									</div>
								</div>

								<div class="rhw-selected-slot-preview" style="display: none;">
									<span class="dashicons dashicons-yes-alt"></span>
									<strong><?php esc_html_e( 'Selected Consultation:', 'rhw-calculator' ); ?></strong>
									<span class="rhw-selected-slot-text"></span>
								</div>
							</div>
						<?php endif; ?>

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
									<span class="rhw-btn-text"><?php esc_html_e( 'Request Consultation & Quote', 'rhw-calculator' ); ?></span>
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
					<h3 class="rhw-success-heading"><?php esc_html_e( 'Your Consultation Request was Sent!', 'rhw-calculator' ); ?></h3>
					<p class="rhw-success-lead">
						<?php esc_html_e( 'Thank you, ', 'rhw-calculator' ); ?><strong class="rhw-lead-name-text"></strong>!
						<?php esc_html_e( 'A preliminary quote has been generated and sent to your email.', 'rhw-calculator' ); ?>
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
							<span><?php esc_html_e( 'Estimated Cost:', 'rhw-calculator' ); ?></span>
							<strong class="rhw-lead-total-text"></strong>
						</div>
						<div class="rhw-success-sum-row rhw-success-consultation-row" style="display:none;">
							<span><?php esc_html_e( 'Consultation Appointment:', 'rhw-calculator' ); ?></span>
							<strong class="rhw-lead-consultation-text"></strong>
						</div>
						<div class="rhw-success-sum-row rhw-success-meet-row" style="display:none;">
							<span><?php esc_html_e( 'Google Meet Video Link:', 'rhw-calculator' ); ?></span>
							<strong><a href="#" target="_blank" rel="noopener noreferrer" class="rhw-lead-meet-link" style="color:#0f9d58;">Join Video Meeting</a></strong>
						</div>
					</div>

					<div class="rhw-gcal-sync-wrap" style="display:none; margin: 18px 0; text-align: center;">
						<a href="#" target="_blank" rel="noopener noreferrer" class="button button-primary rhw-btn-gcal" style="display: inline-flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; padding: 12px 22px; font-weight: 700; font-size: 14px; border-radius: 6px; background: #4285F4; color: #ffffff; border: none; box-shadow: 0 2px 6px rgba(66, 133, 244, 0.3);">
							<span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e( 'Add Appointment to Google Calendar', 'rhw-calculator' ); ?>
						</a>
					</div>

					<div class="rhw-next-steps-box">
						<h4><?php esc_html_e( 'What Happens Next?', 'rhw-calculator' ); ?></h4>
						<p><?php esc_html_e( 'Our Destination Relationship Specialists will review your vision and preliminary estimate. We look forward to your scheduled consultation to discuss your vision and generate a formal proposal.', 'rhw-calculator' ); ?></p>
					</div>
				</div>
			</div>
		</div>

		<!-- Right: Live Cost Calculator Card (Sticky Sidebar that scrolls with the page) -->
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

				<!-- Line Items Breakdown Drawer -->
				<div class="rhw-summary-body">
					<h4 class="rhw-breakdown-heading"><?php esc_html_e( 'Line Items', 'rhw-calculator' ); ?></h4>
					<ul class="rhw-line-items-list" id="rhw-line-items-container">
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

	<!-- Mobile Sticky Bottom Bar -->
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
