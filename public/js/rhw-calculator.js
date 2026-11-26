/**
 * Rose Hall Weddings Event Calculator Public JavaScript
 *
 * Real-time dynamic calculation, Weekday/Weekend venue rates, full-width progress bar,
 * file uploads, auto-scroll to missed fields, and "Other" choice inputs.
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

		let interactionCounter = 0;
		const itemOrderMap = {};

		function trackInteraction(target) {
			const $target = $(target);
			const $qField = $target.closest('.rhw-q-field');
			const qid = $qField.data('qid');
			if (!qid) return;

			interactionCounter++;
			itemOrderMap[qid] = interactionCounter;

			if ($target.is('input[type="radio"]') || $target.is('select')) {
				const val = $target.val();
				if (val) {
					itemOrderMap[qid + '_' + val] = interactionCounter;
				}
			} else if ($target.is('input[type="checkbox"]')) {
				const val = $target.val();
				if (val) {
					itemOrderMap[qid + '_' + val] = interactionCounter;
				}
			}
		}

		function updateStickyTopOffset() {
			let topOffset = 165; // Accommodates 80px menu + 69px alert banner + buffer
			if ($('#wpadminbar').length && $('#wpadminbar').is(':visible')) {
				topOffset += $('#wpadminbar').outerHeight() || 32;
			}
			$wrapper[0].style.setProperty('--rhw-sticky-top', topOffset + 'px');
		}
		updateStickyTopOffset();
		$(window).on('resize scroll', updateStickyTopOffset);

		// Initial setup
		initNumberSteppers();
		initFileUploads();
		initOtherTextInputs();
		initDateTimePicker();
		initDatePickerRates();
		evaluateConditionalLogic();
		runLiveCalculation();

		// Event Bindings
		$wrapper.on('change input', 'input, select, textarea', function() {
			trackInteraction(this);
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
		 * Date & Time Picker All-In-One Synchronization (Admin Change 5)
		 */
		function initDateTimePicker() {
			$wrapper.find('.rhw-datetime-picker-wrap').each(function() {
				const $wrap = $(this);
				const $dateInp = $wrap.find('.rhw-date-input');
				const $timeInp = $wrap.find('.rhw-time-input');
				const $combined = $wrap.find('.rhw-datetime-combined');
				const $mirror = $wrap.find('.rhw-datetime-time-mirror');

				function syncDateTime() {
					const d = $dateInp.val();
					const t = $timeInp.val();
					if (d && t) {
						let formattedTime = t;
						try {
							const parts = t.split(':');
							let h = parseInt(parts[0], 10);
							const m = parts[1];
							const ampm = h >= 12 ? 'PM' : 'AM';
							h = h % 12 || 12;
							formattedTime = `${h}:${m} ${ampm}`;
						} catch(e) {}
						$combined.val(`${d} at ${formattedTime}`);
					} else if (d) {
						$combined.val(d);
					} else {
						$combined.val('');
					}
					if ($mirror.length && t) {
						$mirror.val(t);
					}
				}

				$dateInp.on('change input', syncDateTime);
				$timeInp.on('change input', syncDateTime);
				syncDateTime();
			});
		}

		/**
		 * Date Picker Weekday / Weekend Live Detection (Requirement 1 & 2)
		 */
		function initDatePickerRates() {
			$wrapper.find('.rhw-date-input').each(function() {
				const $input = $(this);
				const $group = $input.closest('.rhw-form-group');
				const qid = $group.data('qid') || 'event_date';

				$input.on('change input', function() {
					updateDateRateDisplay($(this).val(), qid);
					runLiveCalculation();
				});

				if ($input.val()) {
					updateDateRateDisplay($input.val(), qid);
				}
			});
		}

		function isDateWeekend(dateVal) {
			if (!dateVal) return true; // Default to weekend standard
			const datePart = (String(dateVal).split(' ')[0] || '').split('T')[0];
			const parts = datePart.split('-');
			if (parts.length === 3) {
				const d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
				const day = d.getDay(); // 0 = Sun, 1 = Mon, ..., 5 = Fri, 6 = Sat
				return (day === 0 || day === 5 || day === 6); // Fri, Sat, Sun
			}
			return true;
		}

		function updateDateRateDisplay(dateVal, qid) {
			qid = qid || 'event_date';
			const $pill = $wrapper.find('#rhw-rate-indicator-' + qid).length
				? $wrapper.find('#rhw-rate-indicator-' + qid)
				: $wrapper.find('.rhw-date-rate-pill').first();

			if (!dateVal) {
				$pill.hide();
				return;
			}

			const isWeekend = isDateWeekend(dateVal);
			const datePart = (String(dateVal).split(' ')[0] || '').split('T')[0];
			const parts = datePart.split('-');
			const d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
			const dayName = isNaN(d.getTime()) ? '' : d.toLocaleDateString('en-US', { weekday: 'long' });

			$pill.removeClass('rhw-rate-pill-weekend rhw-rate-pill-weekday');
			if (isWeekend) {
				$pill.addClass('rhw-rate-pill-weekend');
				$pill.find('.rhw-rate-pill-text').text(`${dayName}: Weekend / Holiday Rate`);
			} else {
				$pill.addClass('rhw-rate-pill-weekday');
				$pill.find('.rhw-rate-pill-text').text(`${dayName}: Weekday Special Rate`);
			}
			$pill.fadeIn(200);

			// Update prices displayed on venue choice tiles
			$wrapper.find('input[data-is-venue="1"]').each(function() {
				const weekdayPrice = parseFloat($(this).data('weekday-price')) || 0;
				const weekendPrice = parseFloat($(this).data('weekend-price')) || 0;
				const activePrice = isWeekend ? weekendPrice : weekdayPrice;
				const $priceSpan = $(this).closest('.rhw-choice-tile').find('.rhw-venue-dynamic-price');
				$priceSpan.text(`($${activePrice.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })})`);
			});
		}

		/**
		 * "Other" Choice Text Inputs (Requirement 4)
		 */
		function initOtherTextInputs() {
			$wrapper.find('input[type="radio"]').on('change', function() {
				const $group = $(this).closest('.rhw-radio-group');
				const $otherWrap = $group.find('.rhw-other-input-wrap');
				if ($(this).data('is-other') === 1 || $(this).val() === 'other') {
					$otherWrap.slideDown(200).find('input').focus();
				} else {
					$otherWrap.slideUp(200);
				}
			});

			$wrapper.find('input[type="checkbox"]').on('change', function() {
				const $group = $(this).closest('.rhw-checkbox-group');
				const $otherTile = $group.find('input[data-is-other="1"], input[value="other"]');
				const $otherWrap = $group.find('.rhw-other-input-wrap');
				if ($otherTile.is(':checked')) {
					$otherWrap.slideDown(200).find('input').focus();
				} else {
					$otherWrap.slideUp(200);
				}
			});

			$wrapper.find('select').on('change', function() {
				const $group = $(this).closest('.rhw-form-group');
				const $otherWrap = $group.find('.rhw-other-input-wrap');
				if ($(this).val() === 'other') {
					$otherWrap.slideDown(200).find('input').focus();
				} else {
					$otherWrap.slideUp(200);
				}
			});
		}

		/**
		 * Image / File Upload UI (Requirement 9)
		 */
		function initFileUploads() {
			$wrapper.find('.rhw-file-input').on('change', function(e) {
				const file = this.files && this.files[0];
				const $wrap = $(this).closest('.rhw-file-upload-wrap');
				const $dropzone = $wrap.find('.rhw-file-dropzone');
				const $previewBox = $wrap.find('.rhw-file-preview-box');

				if (file) {
					$previewBox.find('.rhw-preview-filename').text(file.name);
					if (file.type.match('image.*')) {
						const reader = new FileReader();
						reader.onload = function(evt) {
							$previewBox.find('.rhw-preview-thumb').attr('src', evt.target.result).show();
						};
						reader.readAsDataURL(file);
					} else {
						$previewBox.find('.rhw-preview-thumb').hide();
					}
					$dropzone.hide();
					$previewBox.slideDown(200);
				}
			});

			$wrapper.find('.rhw-file-remove-btn').on('click', function() {
				const $wrap = $(this).closest('.rhw-file-upload-wrap');
				const $fileInput = $wrap.find('.rhw-file-input');
				const $dropzone = $wrap.find('.rhw-file-dropzone');
				const $previewBox = $wrap.find('.rhw-file-preview-box');

				$fileInput.val('');
				$previewBox.hide();
				$dropzone.slideDown(200);
			});
		}

		/**
		 * Navigate between steps
		 */
		function goToStep(step) {
			if (step < 0 || step >= totalSteps) return;

			$panes.removeClass('active').hide();
			$panes.filter(`[data-step-index="${step}"]`).addClass('active').fadeIn(250);

			currentStep = step;

			// Update Full-Width Progress Indicator (Requirement 7)
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

			// Smooth scroll to top of wizard (clearing 80px menu + 69px alert)
			$('html, body').animate({
				scrollTop: $wrapper.offset().top - 170
			}, 300);
		}

		/**
		 * Validate required fields in the active step & Jump to missed question (Requirement 5)
		 */
		function validateCurrentStep() {
			let isValid = true;
			const $activePane = $panes.filter(`[data-step-index="${currentStep}"]`);

			$activePane.find('.rhw-form-group').removeClass('has-error');
			$activePane.find('.rhw-error-message').hide();

			let $firstErrorGroup = null;

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
				} else if (q.type === 'datetime' || (q.type === 'date' && $group.find('.rhw-datetime-picker-wrap').length)) {
					const dVal = $group.find('.rhw-date-input').val();
					const tVal = $group.find('.rhw-time-input').val();
					fieldVal = (dVal && tVal) ? `${dVal} at ${tVal}` : (dVal || '');
				} else {
					fieldVal = $.trim($group.find('input, textarea').val());
				}

				if (!fieldVal) {
					isValid = false;
					$group.addClass('has-error');
					$group.find('.rhw-error-message').text(q.label + ' is required.').fadeIn(200);
					if (!$firstErrorGroup) {
						$firstErrorGroup = $group;
					}
				} else if (q.type === 'email' && !isValidEmail(fieldVal)) {
					isValid = false;
					$group.addClass('has-error');
					$group.find('.rhw-error-message').text('Please enter a valid email address.').fadeIn(200);
					if (!$firstErrorGroup) {
						$firstErrorGroup = $group;
					}
				}
			});

			// Jump / scroll to missed question (clearing 80px menu + 69px alert)
			if (!isValid && $firstErrorGroup) {
				$('html, body').animate({
					scrollTop: $firstErrorGroup.offset().top - 170
				}, 350, function() {
					$firstErrorGroup.find('input, select, textarea').first().focus();
				});
			}

			return isValid;
		}

		/**
		 * Validate all steps before submitting to prevent false submission alerts
		 */
		function validateAllSteps() {
			let isValid = true;
			let firstErrorGroup = null;
			let errorStep = -1;

			$wrapper.find('.rhw-form-group').removeClass('has-error');
			$wrapper.find('.rhw-error-message').hide();

			const answers = collectAnswers();
			const serviceType = answers.service_type || '';
			const isVenueOnly = (serviceType === 'venue_only');

			$panes.each(function(stepIdx) {
				const $pane = $(this);
				$pane.find('.rhw-form-group').each(function() {
					const $group = $(this);
					if ($group.hasClass('rhw-hidden')) return;

					const qid = $group.data('qid');
					const q = questions.find(item => item.id === qid);
					if (!q || !q.required) return;
					if (isVenueOnly && q.requires_full_service) return;

					let fieldVal = '';
					if (q.type === 'radio') {
						fieldVal = $group.find('input[type="radio"]:checked').val() || '';
					} else if (q.type === 'checkboxes' || q.type === 'checkbox') {
						fieldVal = $group.find('input[type="checkbox"]:checked').length > 0 ? 'checked' : '';
					} else if (q.type === 'select') {
						fieldVal = $group.find('select').val() || '';
					} else if (q.type === 'datetime' || (q.type === 'date' && $group.find('.rhw-datetime-picker-wrap').length)) {
						const dVal = $group.find('.rhw-date-input').val();
						const tVal = $group.find('.rhw-time-input').val();
						fieldVal = (dVal && tVal) ? `${dVal} at ${tVal}` : (dVal || '');
					} else {
						fieldVal = $.trim($group.find('input, textarea').val());
					}

					if (!fieldVal) {
						isValid = false;
						$group.addClass('has-error');
						$group.find('.rhw-error-message').text(q.label + ' is required.').fadeIn(200);
						if (!firstErrorGroup) {
							firstErrorGroup = $group;
							errorStep = stepIdx;
						}
					} else if (q.type === 'email' && !isValidEmail(fieldVal)) {
						isValid = false;
						$group.addClass('has-error');
						$group.find('.rhw-error-message').text('Please enter a valid email address.').fadeIn(200);
						if (!firstErrorGroup) {
							firstErrorGroup = $group;
							errorStep = stepIdx;
						}
					}
				});
			});

			if (!isValid && firstErrorGroup && errorStep !== -1) {
				goToStep(errorStep);
				$('html, body').animate({
					scrollTop: firstErrorGroup.offset().top - 170
				}, 350, function() {
					firstErrorGroup.find('input, select, textarea').first().focus();
				});
				return false;
			}

			return true;
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

			$wrapper.find('input[type="number"], input[type="date"], input[type="time"], .rhw-datetime-combined').each(function() {
				const name = $(this).attr('name');
				const val = $(this).val();
				if (name && val !== '' && val !== undefined) {
					answers[name] = val;
				}
			});

			// If event_date has a datetime-combined field, make sure answers.event_date is set
			const $eventDateCombined = $wrapper.find('.rhw-datetime-picker-wrap[data-qid="event_date"] .rhw-datetime-combined');
			if ($eventDateCombined.length && $eventDateCombined.val()) {
				answers.event_date = $eventDateCombined.val();
			}

			return answers;
		}

		/**
		 * Real-Time Live Calculation Engine with Weekday/Weekend and Tour Guests (Requirement 2 & 10)
		 */
		function runLiveCalculation() {
			const answers = collectAnswers();
			const guestCount = parseInt(answers.guest_count, 10) || 75;
			$wrapper.find('#rhw-live-guest-display').text(guestCount);

			const isWeekend = isDateWeekend(answers.event_date);
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
						if (selectedOpt && selectedOpt.pricing) {
							const item = evaluateOptionPricing(selectedOpt, guestCount, answers, q, isWeekend);
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
							if (vals.includes(opt.value || opt.label) && opt.pricing) {
								const item = evaluateOptionPricing(opt, guestCount, answers, q, isWeekend);
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
							order: itemOrderMap[q.id] || 0,
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

			// 2. Automated Full Service Rental Package
			if (!isVenueOnly && serviceType === 'full_service') {
				applyStandardProposalRentals(answers, guestCount, lineItems, subtotal);
				subtotal = lineItems.reduce((acc, curr) => acc + curr.amount, 0);
			}

			// Sort line items descending by selection order so newly answered questions appear at the top (Frontend Change 2)
			lineItems.sort((a, b) => {
				const orderA = a.order || 0;
				const orderB = b.order || 0;
				return orderB - orderA;
			});

			// 3. Taxes & Service Charges
			const taxRate = parseFloat(pricingSettings.tax_rate) || 15.0;
			const serviceChargeRate = parseFloat(pricingSettings.service_charge_rate) || 20.0;

			const serviceCharge = fbSubtotal > 0 ? (fbSubtotal * serviceChargeRate) / 100 : 0.0;
			const taxAmount = subtotal > 0 ? (subtotal * taxRate) / 100 : 0.0;
			const grandTotal = subtotal + serviceCharge + taxAmount;

			// 4. Update UI Line Items and Totals (Requirement 8)
			updateCalculationDisplay(lineItems, subtotal, serviceCharge, taxAmount, grandTotal, serviceChargeRate, taxRate);
		}

		/**
		 * Calculate option pricing item with Weekday/Weekend & Tour Participant Count (Requirement 2 & 10)
		 */
		function evaluateOptionPricing(opt, guestCount, answers, question, isWeekend) {
			const p = opt.pricing || {};
			let amount = parseFloat(p.amount) || 0.0;

			// Check for weekday/weekend pricing on venue options (Requirement 2)
			if (p.is_venue || p.type === 'venue' || p.weekend_amount) {
				if (isWeekend && p.weekend_amount) {
					amount = parseFloat(p.weekend_amount);
				} else if (!isWeekend && p.weekday_amount) {
					amount = parseFloat(p.weekday_amount);
				}
			}

			if (amount <= 0 && p.type === 'none') {
				return null;
			}

			let qty = 1;
			let total = 0.0;
			let note = '';
			let isFb = !!p.is_fb;

			switch (p.type) {
				case 'venue':
				case 'fixed':
					total = amount;
					note = isWeekend ? 'Weekend / Holiday Rate' : 'Weekday Rate';
					break;

				case 'per_guest':
					// Attraction Participant Count (Requirement 10)
					let count = guestCount;
					if (question.id === 'attraction_selection') {
						const tourGuests = parseInt(answers.attraction_guests, 10);
						if (tourGuests > 0) {
							count = tourGuests;
						}
					}
					qty = count;
					total = amount * count;
					note = `${count} persons × $${amount.toFixed(2)}`;
					break;

				case 'table_ratio_10':
					qty = Math.ceil(guestCount / 10);
					total = qty * amount;
					note = `${qty} tables × $${amount.toFixed(2)}`;
					break;

				case 'table_ratio_15':
					qty = Math.ceil(guestCount / 15);
					total = qty * amount;
					note = `${qty} tables × $${amount.toFixed(2)}`;
					break;

				case 'bar_package':
					let hours = parseInt(answers.bar_duration, 10) || 2;
					hours = Math.max(2, hours);
					const base2 = amount;
					const addl = p.addl_hour_rate || 0.0;
					const addlHours = Math.max(0, hours - 2);
					const perPerson = base2 + (addlHours * addl);
					qty = guestCount;
					total = perPerson * guestCount;
					isFb = true;
					note = `${guestCount} guests × ${hours} hrs ($${perPerson.toFixed(2)}/person)`;
					break;

				default:
					total = amount;
			}

			const optKey = question.id + '_' + (opt.value || opt.label);
			const order = itemOrderMap[optKey] || itemOrderMap[question.id] || 0;

			return {
				key: optKey,
				order: order,
				title: opt.label || question.label,
				category: p.category || question.category || 'General',
				quantity: qty,
				unit_price: amount,
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
		 * Render line items and animate totals (Requirement 8: Line Items)
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
		 * Submit quote request and lead details via AJAX with FormData support
		 */
		function handleFormSubmit() {
			if (isSubmitting) return;

			// Validate ALL wizard steps before submitting (Requirement 5)
			if (!validateAllSteps()) {
				return;
			}

			isSubmitting = true;
			const $submitBtn = $wrapper.find('.rhw-btn-submit');
			$submitBtn.addClass('loading').prop('disabled', true);

			const formElement = $wrapper.find('.rhw-calculator-form')[0];
			const formData = new FormData(formElement);

			formData.append('action', 'rhw_submit_quote');
			formData.append('nonce', config.nonce);
			formData.append('form_id', formId);

			// Explicitly append structured answers dictionary
			const answers = collectAnswers();
			for (const [k, v] of Object.entries(answers)) {
				if (Array.isArray(v)) {
					v.forEach(val => formData.append(`answers[${k}][]`, val));
				} else {
					formData.append(`answers[${k}]`, v);
				}
			}

			$.ajax({
				url: config.ajax_url,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				dataType: 'json',
				success: function(response) {
					if (response.success) {
						showSuccessState(response.data);
					} else {
						if (response.data && response.data.errors) {
							const firstErrQid = Object.keys(response.data.errors)[0];
							const $errField = $wrapper.find(`.rhw-form-group[data-qid="${firstErrQid}"]`);
							if ($errField.length) {
								const stepIdx = $errField.closest('.rhw-step-pane').data('step-index');
								if (stepIdx !== undefined) {
									goToStep(parseInt(stepIdx, 10));
								}
								$errField.addClass('has-error');
								$errField.find('.rhw-error-message').text(response.data.errors[firstErrQid]).fadeIn(200);
								$('html, body').animate({
									scrollTop: $errField.offset().top - 170
								}, 350, function() {
									$errField.find('input, select, textarea').first().focus();
								});
							}
						}
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
				scrollTop: $wrapper.offset().top - 170
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
