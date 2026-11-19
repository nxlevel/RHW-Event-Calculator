/**
 * Rose Hall Weddings Event Calculator Public JavaScript
 *
 * Real-time dynamic calculation, conditional question routing, multi-step wizard,
 * and reliable AJAX lead capture.
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		$('.rhw-calc-wrapper').each(function() {
			initCalculatorInstance($(this));
		});
	});

	function initCalculatorInstance($wrapper) {
		const formId = $wrapper.data('form-id');
		const configName = 'rhw_calc_data_' + formId;
		const config = window[configName] || {};

		const formConfig = config.form_data || {};
		const questions = formConfig.questions || [];
		const pricingSettings = formConfig.pricing_settings || {};
		const i18n = config.i18n || {};

		let currentStep = 0;
		const $panes = $wrapper.find('.rhw-step-pane');
		const totalSteps = $panes.length;
		let isSubmitting = false;

		// Initial setup
		initNumberSteppers();
		evaluateConditionalLogic();
		runLiveCalculation();

		// Event Bindings
		$wrapper.on('change input', 'input, select, textarea', function() {
			evaluateConditionalLogic();
			runLiveCalculation();
		});

		// Wizard Navigation
		$wrapper.find('.rhw-btn-next').on('click', function(e) {
			e.preventDefault();
			if (validateCurrentStep()) {
				goToStep(currentStep + 1);
			}
		});

		$wrapper.find('.rhw-btn-prev').on('click', function(e) {
			e.preventDefault();
			goToStep(currentStep - 1);
		});

		// Step pill navigation
		$wrapper.find('.rhw-step-item').on('click', function() {
			const targetStep = parseInt($(this).data('step-index'), 10);
			if (targetStep < currentStep) {
				goToStep(targetStep);
			} else if (targetStep === currentStep + 1 && validateCurrentStep()) {
				goToStep(targetStep);
			}
		});

		// Mobile drawer toggle
		$wrapper.find('.rhw-mobile-toggle-btn').on('click', function() {
			const $sidebar = $wrapper.find('.rhw-calc-sidebar');
			$sidebar.slideToggle(200);
			const expanded = $(this).attr('aria-expanded') === 'true';
			$(this).attr('aria-expanded', !expanded);
			$(this).html(expanded ? 'View Breakdown &uarr;' : 'Close Breakdown &darr;');
		});

		// Form Submission
		$wrapper.find('.rhw-calculator-form').on('submit', function(e) {
			e.preventDefault();
			if (isSubmitting) return;

			if (!validateCurrentStep()) {
				return;
			}

			handleFormSubmit();
		});

		/**
		 * Navigate between steps
		 */
		function goToStep(step) {
			if (step < 0 || step >= totalSteps) return;

			$panes.removeClass('active').hide();
			$panes.filter(`[data-step-index="${step}"]`).addClass('active').fadeIn(250);

			currentStep = step;

			// Update Progress Indicator
			const percent = ((step + 1) / totalSteps) * 100;
			$wrapper.find('.rhw-progress-bar-fill').css('width', percent + '%');

			$wrapper.find('.rhw-step-item').each(function(idx) {
				if (idx === step) {
					$(this).addClass('active').removeClass('completed');
				} else if (idx < step) {
					$(this).removeClass('active').addClass('completed');
				} else {
					$(this).removeClass('active completed');
				}
			});

			// Smooth scroll to top of calculator
			$('html, body').animate({
				scrollTop: $wrapper.offset().top - 40
			}, 300);
		}

		/**
		 * Validate required fields in the active step
		 */
		function validateCurrentStep() {
			let isValid = true;
			const $activePane = $panes.filter(`[data-step-index="${currentStep}"]`);

			$activePane.find('.rhw-form-group').removeClass('has-error');
			$activePane.find('.rhw-error-message').hide();

			$activePane.find('.rhw-form-group:visible').each(function() {
				const $group = $(this);
				const qid = $group.data('qid');
				const q = questions.find(item => item.id === qid);

				if (!q || !q.required) return;

				let fieldVal = '';
				if (q.type === 'radio') {
					fieldVal = $group.find('input[type="radio"]:checked').val() || '';
				} else if (q.type === 'checkboxes' || q.type === 'checkbox') {
					fieldVal = $group.find('input[type="checkbox"]:checked').length > 0 ? 'checked' : '';
				} else if (q.type === 'select') {
					fieldVal = $group.find('select').val() || '';
				} else {
					fieldVal = $.trim($group.find('input, textarea').val());
				}

				if (!fieldVal) {
					isValid = false;
					$group.addClass('has-error');
					$group.find('.rhw-error-message').text(q.label + ' is required.').fadeIn(200);
				} else if (q.type === 'email' && !isValidEmail(fieldVal)) {
					isValid = false;
					$group.addClass('has-error');
					$group.find('.rhw-error-message').text('Please enter a valid email address.').fadeIn(200);
				}
			});

			return isValid;
		}

		function isValidEmail(email) {
			return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
		}

		/**
		 * Number Steppers (+ / - buttons)
		 */
		function initNumberSteppers() {
			$wrapper.find('.rhw-num-minus').on('click', function() {
				const $input = $(this).siblings('.rhw-number-input');
				const min = parseInt($input.attr('min'), 10) || 1;
				let val = parseInt($input.val(), 10) || min;
				if (val > min) {
					$input.val(val - 1).trigger('input');
				}
			});

			$wrapper.find('.rhw-num-plus').on('click', function() {
				const $input = $(this).siblings('.rhw-number-input');
				const max = parseInt($input.attr('max'), 10) || 1000;
				let val = parseInt($input.val(), 10) || 1;
				if (val < max) {
					$input.val(val + 1).trigger('input');
				}
			});
		}

		/**
		 * Dynamic Conditional Logic Router
		 */
		function evaluateConditionalLogic() {
			const answers = collectAnswers();
			const serviceType = answers.service_type || '';
			const isVenueOnly = (serviceType === 'venue_only' || (typeof serviceType === 'string' && serviceType.indexOf('Venue Only') !== -1));

			$wrapper.find('.rhw-q-field').each(function() {
				const $field = $(this);
				const isFullServiceOnly = $field.data('full-service-only');

				if (isVenueOnly && isFullServiceOnly) {
					$field.addClass('rhw-hidden').hide();
					return;
				}

				const condRaw = $field.attr('data-conditional');
				if (!condRaw) {
					$field.removeClass('rhw-hidden').show();
					return;
				}

				try {
					const cond = JSON.parse(condRaw);
					if (!cond.enabled) {
						$field.removeClass('rhw-hidden').show();
						return;
					}

					const parentVal = answers[cond.field];
					let isMet = false;

					switch (cond.operator) {
						case 'equals':
							isMet = String(parentVal) === String(cond.value);
							break;
						case 'not_equals':
							isMet = String(parentVal) !== String(cond.value);
							break;
						case 'contains':
							if (Array.isArray(parentVal)) {
								isMet = parentVal.includes(cond.value);
							} else {
								isMet = parentVal && String(parentVal).indexOf(cond.value) !== -1;
							}
							break;
						case 'not_empty':
							isMet = parentVal && String(parentVal).trim() !== '';
							break;
						default:
							isMet = true;
					}

					if (isMet) {
						$field.removeClass('rhw-hidden').show();
					} else {
						$field.addClass('rhw-hidden').hide();
					}
				} catch (e) {
					$field.removeClass('rhw-hidden').show();
				}
			});
		}

		/**
		 * Collect current form answers into an object
		 */
		function collectAnswers() {
			const answers = {};
			$wrapper.find('.rhw-calculator-form').serializeArray().forEach(item => {
				const name = item.name.replace('[]', '');
				if (item.name.indexOf('[]') !== -1) {
					if (!answers[name]) answers[name] = [];
					answers[name].push(item.value);
				} else {
					answers[name] = item.value;
				}
			});

			// Include numeric inputs that might not have changed
			$wrapper.find('input[type="number"]').each(function() {
				const name = $(this).attr('name');
				if (name && !answers[name]) {
					answers[name] = $(this).val();
				}
			});

			return answers;
		}

		/**
		 * Real-Time Loan-Style Live Calculation Engine
		 */
		function runLiveCalculation() {
			const answers = collectAnswers();
			const guestCount = parseInt(answers.guest_count, 10) || 75;
			$wrapper.find('#rhw-live-guest-display').text(guestCount);

			const serviceType = answers.service_type || 'full_service';
			const isVenueOnly = (serviceType === 'venue_only');

			let lineItems = [];
			let subtotal = 0.0;
			let fbSubtotal = 0.0;

			// 1. Process Questions & Pricing
			questions.forEach(q => {
				const $field = $wrapper.find(`.rhw-q-field[data-qid="${q.id}"]`);
				if ($field.hasClass('rhw-hidden')) return;
				if (isVenueOnly && q.requires_full_service) return;

				const val = answers[q.id];
				if (val === undefined || val === null || val === '') return;

				// Radio & Select
				if (q.type === 'radio' || q.type === 'select') {
					if (q.options && q.options.length) {
						const selectedOpt = q.options.find(o => (o.value || o.label) === val);
						if (selectedOpt && selectedOpt.pricing && selectedOpt.pricing.amount > 0) {
							const item = evaluateOptionPricing(selectedOpt, guestCount, answers, q);
							if (item && item.amount > 0) {
								lineItems.push(item);
								subtotal += item.amount;
								if (item.is_fb) fbSubtotal += item.amount;
							}
						}
					}
				}

				// Checkboxes
				else if (q.type === 'checkboxes' || q.type === 'checkbox') {
					const vals = Array.isArray(val) ? val : [val];
					if (q.options && q.options.length) {
						q.options.forEach(opt => {
							if (vals.includes(opt.value || opt.label) && opt.pricing && opt.pricing.amount > 0) {
								const item = evaluateOptionPricing(opt, guestCount, answers, q);
								if (item && item.amount > 0) {
									lineItems.push(item);
									subtotal += item.amount;
									if (item.is_fb) fbSubtotal += item.amount;
								}
							}
						});
					}
				}

				// Numeric
				else if (q.type === 'number' && q.unit_price > 0) {
					const num = parseFloat(val) || 0;
					if (num > 0) {
						const cost = num * q.unit_price;
						lineItems.push({
							key: q.id,
							title: q.label,
							category: q.category || 'General',
							quantity: num,
							unit_price: q.unit_price,
							amount: cost,
							is_fb: !!q.is_fb,
							note: `${num} × $${q.unit_price.toFixed(2)}`
						});
						subtotal += cost;
						if (q.is_fb) fbSubtotal += cost;
					}
				}
			});

			// 2. Automated Full Service Rental Package (from standard proposal)
			if (!isVenueOnly && serviceType === 'full_service') {
				applyStandardProposalRentals(answers, guestCount, lineItems, subtotal);
				// Recalculate subtotal from line items
				subtotal = lineItems.reduce((acc, curr) => acc + curr.amount, 0);
			}

			// 3. Taxes & Service Charges
			const taxRate = parseFloat(pricingSettings.tax_rate) || 15.0;
			const serviceChargeRate = parseFloat(pricingSettings.service_charge_rate) || 20.0;

			const serviceCharge = fbSubtotal > 0 ? (fbSubtotal * serviceChargeRate) / 100 : 0.0;
			const taxAmount = subtotal > 0 ? (subtotal * taxRate) / 100 : 0.0;
			const grandTotal = subtotal + serviceCharge + taxAmount;

			// 4. Update UI displays
			updateCalculationDisplay(lineItems, subtotal, serviceCharge, taxAmount, grandTotal, serviceChargeRate, taxRate);
		}

		/**
		 * Calculate option pricing item
		 */
		function evaluateOptionPricing(opt, guestCount, answers, question) {
			const p = opt.pricing;
			let qty = 1;
			let total = 0.0;
			let note = '';
			let isFb = !!p.is_fb;

			switch (p.type) {
				case 'fixed':
					total = p.amount;
					note = 'Flat fee';
					break;
				case 'per_guest':
					qty = guestCount;
					total = p.amount * guestCount;
					note = `${guestCount} guests × $${p.amount.toFixed(2)}`;
					break;
				case 'table_ratio_10':
					qty = Math.ceil(guestCount / 10);
					total = qty * p.amount;
					note = `${qty} tables × $${p.amount.toFixed(2)}`;
					break;
				case 'table_ratio_15':
					qty = Math.ceil(guestCount / 15);
					total = qty * p.amount;
					note = `${qty} tables × $${p.amount.toFixed(2)}`;
					break;
				case 'bar_package':
					let hours = parseInt(answers.bar_duration, 10) || 2;
					hours = Math.max(2, hours);
					const base2 = p.amount;
					const addl = p.addl_hour_rate || 0.0;
					const addlHours = Math.max(0, hours - 2);
					const perPerson = base2 + (addlHours * addl);
					qty = guestCount;
					total = perPerson * guestCount;
					isFb = true;
					note = `${guestCount} guests × ${hours} hrs ($${perPerson.toFixed(2)}/person)`;
					break;
				default:
					total = p.amount;
			}

			return {
				key: opt.value || opt.label,
				title: opt.label || question.label,
				category: p.category || question.category || 'General',
				quantity: qty,
				unit_price: p.amount,
				amount: total,
				is_fb: isFb,
				note: note
			};
		}

		/**
		 * Apply standard décor & ceremony items from Proposal for Full Service
		 */
		function applyStandardProposalRentals(answers, guestCount, lineItems, currentSubtotal) {
			const includeDecor = answers.include_standard_decor !== 'no';
			if (!includeDecor) return;

			const existingKeys = lineItems.map(i => i.key);

			// Chairs ($7 / guest)
			if (!existingKeys.includes('gold_chiavari_chairs')) {
				lineItems.push({
					key: 'gold_chiavari_chairs',
					title: 'Gold Chiavari Chairs / Ivory Cushions',
					category: 'Décor & Rentals',
					amount: guestCount * 7.00,
					note: `${guestCount} chairs × $7.00`
				});
			}

			// Signing Table ($45)
			if (!existingKeys.includes('signing_table')) {
				lineItems.push({
					key: 'signing_table',
					title: 'Signing Table with Linen',
					category: 'Décor & Rentals',
					amount: 45.00,
					note: 'Flat fee'
				});
			}

			// Bistro Cocktail Tables ($55 ea, 1 per 15 guests)
			if (!existingKeys.includes('bistro_tables')) {
				const bQty = Math.ceil(guestCount / 15);
				lineItems.push({
					key: 'bistro_tables',
					title: 'Cocktail Bistro Tables w/ Linen',
					category: 'Décor & Rentals',
					amount: bQty * 55.00,
					note: `${bQty} tables × $55.00`
				});
			}

			// Sweetheart Table ($60)
			if (!existingKeys.includes('sweetheart_table')) {
				lineItems.push({
					key: 'sweetheart_table',
					title: 'Sweetheart Table with Linen',
					category: 'Décor & Rentals',
					amount: 60.00,
					note: 'Flat fee'
				});
			}

			// 10-seater Dining Tables ($55 ea, ceil(guests/10))
			if (!existingKeys.includes('dining_tables')) {
				const tQty = Math.ceil(guestCount / 10);
				lineItems.push({
					key: 'dining_tables',
					title: '10-Seater Rounds / Trestle Tables',
					category: 'Décor & Rentals',
					amount: tQty * 55.00,
					note: `${tQty} tables × $55.00`
				});
			}

			// Charger Plates ($8 / guest)
			if (!existingKeys.includes('charger_plates')) {
				lineItems.push({
					key: 'charger_plates',
					title: 'Charger Plates',
					category: 'Décor & Rentals',
					amount: guestCount * 8.00,
					note: `${guestCount} settings × $8.00`
				});
			}

			// Napkins ($5 / guest)
			if (!existingKeys.includes('napkins')) {
				lineItems.push({
					key: 'napkins',
					title: 'Dinner Napkins',
					category: 'Décor & Rentals',
					amount: guestCount * 5.00,
					note: `${guestCount} napkins × $5.00`
				});
			}

			// Setup & Cleanup Fee ($350)
			if (!existingKeys.includes('setup_cleanup_fee')) {
				lineItems.push({
					key: 'setup_cleanup_fee',
					title: 'Setup, Breakdown & Cleanup Fee',
					category: 'Planning & Service',
					amount: 350.00,
					note: 'Standard fee'
				});
			}
		}

		/**
		 * Render line items and animate totals
		 */
		function updateCalculationDisplay(items, subtotal, serviceCharge, tax, grandTotal, serviceRate, taxRate) {
			const $list = $wrapper.find('#rhw-line-items-container');
			$list.empty();

			if (!items.length) {
				$list.html('<li class="rhw-empty-item">Select options to view live calculation.</li>');
			} else {
				items.forEach(item => {
					$list.append(`
						<li class="rhw-line-item">
							<div class="rhw-item-title-wrap">
								<div class="rhw-item-title">${escapeHtml(item.title)}</div>
								<div class="rhw-item-sub">${escapeHtml(item.note || item.category)}</div>
							</div>
							<div class="rhw-item-amount">$${item.amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
						</li>
					`);
				});
			}

			// Subtotal & taxes
			$wrapper.find('#rhw-live-subtotal-display').text('$' + subtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));

			const $srvRow = $wrapper.find('.rhw-row-service-charge');
			if (serviceCharge > 0) {
				$srvRow.show().find('#rhw-live-service-display').text('$' + serviceCharge.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
			} else {
				$srvRow.hide();
			}

			const $taxRow = $wrapper.find('.rhw-row-tax');
			if (tax > 0) {
				$taxRow.show().find('#rhw-live-tax-display').text('$' + tax.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
			} else {
				$taxRow.hide();
			}

			// Animated Grand Total display
			animateValue($wrapper.find('#rhw-live-total-display'), grandTotal);
			$wrapper.find('.rhw-mobile-total-number').text(grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
		}

		/**
		 * Smooth counter animation
		 */
		function animateValue($elem, endVal) {
			const currentText = $elem.text().replace(/,/g, '');
			const startVal = parseFloat(currentText) || 0;
			const duration = 250;
			const startTime = performance.now();

			function update(currentTime) {
				const elapsed = currentTime - startTime;
				const progress = Math.min(elapsed / duration, 1);
				const current = startVal + (endVal - startVal) * progress;
				$elem.text(current.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
				if (progress < 1) {
					requestAnimationFrame(update);
				}
			}
			requestAnimationFrame(update);
		}

		/**
		 * Submit quote request and lead details via AJAX
		 */
		function handleFormSubmit() {
			isSubmitting = true;
			const $submitBtn = $wrapper.find('.rhw-btn-submit');
			$submitBtn.addClass('loading').prop('disabled', true);

			const answers = collectAnswers();

			$.ajax({
				url: config.ajax_url,
				type: 'POST',
				data: {
					action: 'rhw_submit_quote',
					nonce: config.nonce,
					form_id: formId,
					answers: answers,
					rhw_website_hp: $wrapper.find('input[name="rhw_website_hp"]').val() || ''
				},
				dataType: 'json',
				success: function(response) {
					if (response.success) {
						showSuccessState(response.data);
					} else {
						alert(response.data && response.data.message ? response.data.message : (i18n.network_error || 'An error occurred.'));
						$submitBtn.removeClass('loading').prop('disabled', false);
						isSubmitting = false;
					}
				},
				error: function() {
					alert(i18n.network_error || 'A network error occurred. Please try again.');
					$submitBtn.removeClass('loading').prop('disabled', false);
					isSubmitting = false;
				}
			});
		}

		/**
		 * Display confirmation receipt upon successful submission
		 */
		function showSuccessState(data) {
			$wrapper.find('.rhw-calculator-form').hide();
			$wrapper.find('.rhw-wizard-nav').hide();
			$wrapper.find('.rhw-calc-sidebar').hide();
			$wrapper.find('.rhw-mobile-summary-bar').hide();

			const $successView = $wrapper.find('.rhw-submission-success-view');
			$successView.find('.rhw-lead-name-text').text(data.customer_name);
			$successView.find('.rhw-lead-date-text').text(data.event_date || 'TBD');
			$successView.find('.rhw-lead-guests-text').text(data.guest_count + ' guests');
			$successView.find('.rhw-lead-total-text').text(data.grand_total);

			$successView.fadeIn(300);

			$('html, body').animate({
				scrollTop: $wrapper.offset().top - 30
			}, 300);
		}

		function escapeHtml(str) {
			if (!str) return '';
			return String(str)
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#039;');
		}
	}

})(jQuery);
