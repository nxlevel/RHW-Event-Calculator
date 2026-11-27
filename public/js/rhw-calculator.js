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

		/**
		 * Ensure CSS position: sticky works cleanly without altering sidebar default width.
		 * Replaces ancestor overflow: hidden with overflow-x: clip / overflow-y: visible
		 * because any ancestor with overflow: hidden disables browser native sticky positioning.
		 */
		function ensureNativeStickyAncestors() {
			const $sidebar = $wrapper.find('.rhw-calc-sidebar');
			if (!$sidebar.length) return;

			// Ensure no stale inline position/width/left overrides exist
			$sidebar.removeClass('rhw-sidebar-fixed rhw-sidebar-bottom').css({
				position: '',
				top: '',
				left: '',
				bottom: '',
				width: ''
			});

			const xClipVal = (window.CSS && window.CSS.supports && window.CSS.supports('overflow-x', 'clip')) ? 'clip' : 'visible';
			let el = $sidebar[0].parentElement;
			while (el) {
				const style = window.getComputedStyle(el);
				const tag = el.tagName ? el.tagName.toLowerCase() : '';

				if (tag !== 'body' && tag !== 'html') {
					if (style.overflowY === 'hidden' || style.overflowY === 'auto' || style.overflowY === 'scroll') {
						el.style.setProperty('overflow-y', 'visible', 'important');
					}
					if (style.overflowX === 'hidden' || style.overflowX === 'auto' || style.overflowX === 'scroll') {
						el.style.setProperty('overflow-x', xClipVal, 'important');
					}
				} else if (tag === 'body' || tag === 'html') {
					if (style.overflowX === 'hidden') {
						el.style.setProperty('overflow-x', xClipVal, 'important');
					}
				}

				el = el.parentElement;
			}
		}
		ensureNativeStickyAncestors();

		// Initial setup
		initNumberSteppers();
		initAttractionGuestSync();
		initFileUploads();
		initOtherTextInputs();
		initDateTimePicker();
		initDatePickerRates();
		initConsultationScheduler();
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
		 * Image / File Upload UI (Requirement 9) - Drag & Drop Supported
		 */
		function initFileUploads() {
			// Prevent browser from opening dragged files on window
			$(window).on('dragover dragenter drop', function(e) {
				if ($(e.target).closest('.rhw-file-dropzone').length === 0) {
					e.preventDefault();
				}
			});

			$wrapper.find('.rhw-file-dropzone').on('dragover dragenter', function(e) {
				e.preventDefault();
				e.stopPropagation();
				$(this).addClass('rhw-drag-over');
			}).on('dragleave dragend', function(e) {
				e.preventDefault();
				e.stopPropagation();
				$(this).removeClass('rhw-drag-over');
			}).on('drop', function(e) {
				e.preventDefault();
				e.stopPropagation();
				$(this).removeClass('rhw-drag-over');
				const dt = e.originalEvent && e.originalEvent.dataTransfer;
				if (dt && dt.files && dt.files.length > 0) {
					const $fileInput = $(this).closest('.rhw-file-upload-wrap').find('.rhw-file-input');
					try {
						$fileInput[0].files = dt.files;
					} catch (err) {
						if (window.DataTransfer) {
							const dataTransferObj = new DataTransfer();
							for (let i = 0; i < dt.files.length; i++) {
								dataTransferObj.items.add(dt.files[i]);
							}
							$fileInput[0].files = dataTransferObj.files;
						}
					}
					$fileInput.trigger('change');
				}
			});

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
				} else if (q.type === 'select' || q.type === 'country' || q.id === 'lead_country') {
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
			$wrapper.find('.rhw-cal-error-msg').hide();

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
					} else if (q.type === 'select' || q.type === 'country' || q.id === 'lead_country') {
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

			// Validate consultation booking slot if marked required in calendar settings
			const $calCard = $wrapper.find('.rhw-consultation-booking-card');
			if (isValid && $calCard.length && String($calCard.data('cal-required')) === '1') {
				const cDate = $wrapper.find('#rhw_consultation_date').val();
				const cTime = $wrapper.find('#rhw_consultation_time').val();
				if (!cDate || !cTime) {
					isValid = false;
					$calCard.find('.rhw-cal-error-msg').text('Please select a consultation date and available time slot before submitting.').fadeIn(200);
					$('html, body').animate({
						scrollTop: $calCard.offset().top - 170
					}, 350);
					return false;
				}
			}

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
		 * Sync Section 5 Attraction Participant Count & conditional number fields with Section 1 Guest Count
		 */
		function findGuestCountQuestion() {
			return questions.find(q => q.is_guest_count)
				|| questions.find(q => q.id === 'guest_count')
				|| questions.find(q => q.type === 'number' && /in attendance|guest count|number of guests/i.test(q.label || ''));
		}

		function getSection1GuestCount(answers) {
			const gcQuestion = findGuestCountQuestion();
			const qid = gcQuestion ? gcQuestion.id : 'guest_count';
			const raw = answers ? answers[qid] : $wrapper.find(`#rhw_field_${qid}`).val();
			return Math.max(1, parseInt(raw, 10) || 75);
		}

		function isParticipantCountQuestion(q) {
			if (!q || q.type !== 'number') return false;
			if (q.is_participant_count || q.id === 'attraction_guests') return true;
			if (/participat|tour or golf|golfing/i.test(q.label || '')) return true;
			if (q.conditional && q.conditional.enabled && q.conditional.field) {
				const parentQ = questions.find(pq => pq.id === q.conditional.field);
				if (parentQ && parentQ.options && parentQ.options.some(o => o.pricing && o.pricing.type === 'per_guest')) {
					return true;
				}
			}
			return false;
		}

		function findParticipantQuestionFor(parentQuestion) {
			if (!parentQuestion) return null;
			const pid = parentQuestion.id;
			const pLabel = parentQuestion.label || '';

			// 1. Direct conditional child of parentQuestion
			let match = questions.find(q =>
				q.type === 'number' && q.conditional && q.conditional.enabled && q.conditional.field === pid
			);
			if (match) return match;

			// 2. Question explicitly marked is_participant_count without a conflicting parent
			match = questions.find(q =>
				q.type === 'number' && q.is_participant_count && (!q.conditional || !q.conditional.enabled || q.conditional.field === pid)
			);
			if (match) return match;

			// 3. Attraction / Tour matching by ID or label
			if (pid === 'attraction_selection' || /attraction/i.test(pLabel)) {
				match = questions.find(q =>
					q.type === 'number' && (q.id === 'attraction_guests' || /participat|tour or golf|golfing/i.test(q.label || ''))
				);
				if (match) return match;
			}
			return null;
		}

		function syncAttractionGuestLimits(guestCount) {
			questions.forEach(q => {
				if (!isParticipantCountQuestion(q)) return;
				const $input = $wrapper.find(`#rhw_field_${q.id}`);
				if (!$input.length) return;

				const isFocused = (document.activeElement === $input[0]);
				const userEdited = $input.data('user-edited') === true;
				const currentVal = parseInt($input.val(), 10);

				if (!userEdited) {
					if (!isFocused) {
						$input.val(guestCount);
					}
				} else if (!isFocused && (isNaN(currentVal) || currentVal < 1)) {
					$input.val(1);
				}
			});
		}

		function initAttractionGuestSync() {
			questions.forEach(q => {
				if (!isParticipantCountQuestion(q)) return;
				const $input = $wrapper.find(`#rhw_field_${q.id}`);
				if (!$input.length) return;

				// Track manual changes by the user (typing or +/- steppers)
				$input.on('input', function() {
					$(this).data('user-edited', true);
				});

				$input.on('change blur', function() {
					let val = parseInt($(this).val(), 10);
					const maxAttr = parseInt($(this).attr('max'), 10) || 1000;
					if (isNaN(val) || val < 1) {
						val = 1;
					} else if (val > maxAttr) {
						val = maxAttr;
					}
					if (String(val) !== String($(this).val())) {
						$(this).val(val);
					}
				});
			});

			syncAttractionGuestLimits(getSection1GuestCount(collectAnswers()));
		}

		/**
		 * Dynamic Conditional Logic Router (supports chained conditionals & array values)
		 */
		function evaluateConditionalLogic() {
			const answers = collectAnswers();
			const serviceType = answers.service_type || '';
			const isVenueOnly = (serviceType === 'venue_only' || (typeof serviceType === 'string' && serviceType.indexOf('Venue Only') !== -1));
			const guestCount = getSection1GuestCount(answers);

			// Run up to 4 passes so nested/chained conditions (e.g., include_attractions -> attraction_selection -> attraction_guests) resolve cleanly
			for (let pass = 0; pass < 4; pass++) {
				let changed = false;

				$wrapper.find('.rhw-q-field').each(function() {
					const $field = $(this);
					const wasHidden = $field.hasClass('rhw-hidden');
					const isFullServiceOnly = $field.data('full-service-only');

					let shouldShow = true;
					if (isVenueOnly && isFullServiceOnly) {
						shouldShow = false;
					} else {
						const condRaw = $field.attr('data-conditional');
						if (condRaw) {
							try {
								const cond = JSON.parse(condRaw);
								if (cond.enabled && cond.field) {
									const $parentField = $wrapper.find(`.rhw-q-field[data-qid="${cond.field}"]`);
									if ($parentField.length && $parentField.hasClass('rhw-hidden')) {
										shouldShow = false;
									} else {
										const parentVal = answers[cond.field];
										switch (cond.operator) {
											case 'equals':
												shouldShow = String(parentVal) === String(cond.value);
												break;
											case 'not_equals':
												shouldShow = String(parentVal) !== String(cond.value);
												break;
											case 'contains':
												if (Array.isArray(parentVal)) {
													shouldShow = parentVal.includes(cond.value);
												} else {
													shouldShow = Boolean(parentVal && String(parentVal).indexOf(cond.value) !== -1);
												}
												break;
											case 'not_empty':
												if (Array.isArray(parentVal)) {
													shouldShow = parentVal.length > 0;
												} else {
													shouldShow = Boolean(parentVal !== undefined && parentVal !== null && String(parentVal).trim() !== '');
												}
												break;
											default:
												shouldShow = true;
										}
									}
								}
							} catch (e) {
								shouldShow = true;
							}
						}
					}

					if (shouldShow && wasHidden) {
						$field.removeClass('rhw-hidden').show();
						// When revealing a participant count field, default to Section 1 guest count if not edited yet
						const qid = $field.data('qid');
						const qObj = questions.find(item => item.id === qid);
						if (isParticipantCountQuestion(qObj)) {
							const $numInput = $field.find('.rhw-number-input');
							if ($numInput.length && $numInput.data('user-edited') !== true) {
								$numInput.val(guestCount);
							}
						}
						changed = true;
					} else if (!shouldShow && !wasHidden) {
						$field.addClass('rhw-hidden').hide();
						changed = true;
					}
				});

				if (!changed) break;
			}
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
			const guestCount = getSection1GuestCount(answers);
			$wrapper.find('#rhw-live-guest-display').text(guestCount);

			syncAttractionGuestLimits(guestCount);
			questions.forEach(q => {
				if (isParticipantCountQuestion(q)) {
					const syncedVal = parseInt($wrapper.find(`#rhw_field_${q.id}`).val(), 10);
					if (!isNaN(syncedVal) && syncedVal > 0) {
						answers[q.id] = String(syncedVal);
					}
				}
			});

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

			// 2. Automated Rental Package based on user opt-in (Item 3)
			if (!isVenueOnly) {
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
					// Check for linked conditional / participant count number field
					let count = guestCount;
					const linkedPq = findParticipantQuestionFor(question);
					if (linkedPq) {
						const $pqField = $wrapper.find(`.rhw-q-field[data-qid="${linkedPq.id}"]`);
						if (!$pqField.length || !$pqField.hasClass('rhw-hidden')) {
							const pqVal = parseInt(answers[linkedPq.id], 10);
							if (pqVal > 0) {
								count = pqVal;
							}
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
			const includeDecor = answers.include_standard_decor === 'yes' || answers.include_standard_decor === '1' || answers.include_standard_decor === true;
			if (!includeDecor) return;

			const serviceType = answers.service_type || '';
			const isVenueOnly = (serviceType === 'venue_only' || (typeof serviceType === 'string' && serviceType.indexOf('Venue Only') !== -1));
			if (isVenueOnly) return;

			const isElopement = (serviceType === 'elopement' || (typeof serviceType === 'string' && serviceType.indexOf('Elopement') !== -1));
			const decorOrder = itemOrderMap['include_standard_decor_yes'] || itemOrderMap['include_standard_decor'] || interactionCounter;

			const existingKeys = lineItems.map(i => i.key);

			// Chairs ($7 / guest)
			if (!existingKeys.includes('gold_chiavari_chairs')) {
				lineItems.push({
					key: 'gold_chiavari_chairs',
					order: decorOrder,
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
					order: decorOrder,
					title: 'Signing Table with Linen',
					category: 'Décor & Rentals',
					amount: 45.00,
					note: 'Flat fee'
				});
			}

			// For Elopement (Ceremony only up to 20 guests for 2 hrs): do NOT add dining reception tables/settings
			if (isElopement) {
				return;
			}

			// Bistro Cocktail Tables ($55 ea, 1 per 15 guests)
			if (!existingKeys.includes('bistro_tables')) {
				const bQty = Math.ceil(guestCount / 15);
				lineItems.push({
					key: 'bistro_tables',
					order: decorOrder,
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
					order: decorOrder,
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
					order: decorOrder,
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
					order: decorOrder,
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
					order: decorOrder,
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
					order: decorOrder,
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

			// Append consultation slot booking details (Item 9)
			const consultDate = $wrapper.find('#rhw_consultation_date').val() || '';
			const consultTime = $wrapper.find('#rhw_consultation_time').val() || '';
			if (consultDate) formData.append('consultation_date', consultDate);
			if (consultTime) formData.append('consultation_time', consultTime);

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
						if (response.data && response.data.slot_taken) {
							$wrapper.trigger('rhw:refresh_calendar');
						}
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
			$wrapper.addClass('rhw-submitted');
			$wrapper.find('.rhw-calculator-form').hide();
			$wrapper.find('.rhw-wizard-nav').hide();
			$wrapper.find('.rhw-calc-sidebar').hide();
			$wrapper.find('.rhw-mobile-summary-bar').hide();

			const $successView = $wrapper.find('.rhw-submission-success-view');
			$successView.find('.rhw-lead-name-text').text(data.customer_name);
			$successView.find('.rhw-lead-date-text').text(data.event_date || 'TBD');
			$successView.find('.rhw-lead-guests-text').text(data.guest_count + ' guests');
			$successView.find('.rhw-lead-total-text').text(data.grand_total);

			// Consultation booking, Google Meet & Google Calendar Link
			if (data.consultation_date && data.consultation_time) {
				$successView.find('.rhw-lead-consultation-text').text(`${data.consultation_date} at ${data.consultation_time}`);
				$successView.find('.rhw-success-consultation-row').show();
			} else {
				$successView.find('.rhw-success-consultation-row').hide();
			}

			if (data.google_meet_link) {
				$successView.find('.rhw-lead-meet-link').attr('href', data.google_meet_link);
				$successView.find('.rhw-success-meet-row').show();
			} else {
				$successView.find('.rhw-success-meet-row').hide();
			}

			if (data.gcal_link) {
				$successView.find('.rhw-btn-gcal').attr('href', data.gcal_link);
				$successView.find('.rhw-gcal-sync-wrap').show();
			} else {
				$successView.find('.rhw-gcal-sync-wrap').hide();
			}

			$successView.fadeIn(300);

			$('html, body').animate({
				scrollTop: $wrapper.offset().top - 170
			}, 300);
		}

		/**
		 * Consultation Month Grid Calendar & Time Slot Scheduler (Synced with Google Calendar)
		 */
		function initConsultationScheduler() {
			const $card = $wrapper.find('.rhw-consultation-booking-card');
			if (!$card.length) return;

			const $grid = $card.find('#rhw-public-cal-grid');
			const $monthTitle = $card.find('#rhw-pub-cal-month-title');
			const $dateHeading = $card.find('#rhw-selected-date-heading');
			const $slotsContainer = $card.find('#rhw-slots-container');
			const $dateInput = $card.find('#rhw_consultation_date');
			const $timeInput = $card.find('#rhw_consultation_time');
			const $preview = $card.find('.rhw-selected-slot-preview');
			const $previewText = $card.find('.rhw-selected-slot-text');

			let viewYear = parseInt($card.data('init-year'), 10) || new Date().getFullYear();
			let viewMonth = parseInt($card.data('init-month'), 10) || (new Date().getMonth() + 1);
			let activeDayStr = '';
			let activeDayFormatted = '';
			let triedNextMonthFallback = false;

			function loadMonthCalendar(year, month, autoFocusDay) {
				viewYear = year;
				viewMonth = month;
				$grid.css('opacity', 0.6);

				$.ajax({
					url: config.ajax_url,
					type: 'GET',
					data: {
						action: 'rhw_get_month_availability',
						nonce: config.nonce,
						year: year,
						month: month
					},
					dataType: 'json',
					success: function(res) {
						$grid.css('opacity', 1);
						if (!res || !res.success || !res.data) {
							$grid.html('<div class="rhw-cal-grid-loading">Unable to load calendar.</div>');
							return;
						}

						const data = res.data;
						$monthTitle.text(data.month_label);
						$grid.empty();

						// Empty cells before month starts
						for (let i = 0; i < data.first_weekday; i++) {
							$grid.append('<div class="rhw-pub-day-square is-empty" aria-hidden="true"></div>');
						}

						let firstAvailDate = '';
						for (let d = 1; d <= data.days_in_month; d++) {
							const mm = String(data.month).padStart(2, '0');
							const dd = String(d).padStart(2, '0');
							const dateStr = `${data.year}-${mm}-${dd}`;
							const info = data.days[dateStr] || { status: 'closed', available_count: 0 };

							const isAvail = info.status === 'available';
							if (isAvail && !firstAvailDate) {
								firstAvailDate = dateStr;
							}

							const classes = ['rhw-pub-day-square'];
							if (isAvail) {
								classes.push('is-available');
							} else {
								classes.push('is-disabled');
							}
							if (info.is_today) {
								classes.push('is-today');
							}
							if (dateStr === activeDayStr) {
								classes.push('is-selected');
							}

							const dotHtml = isAvail ? '<span class="rhw-day-avail-dot"></span>' : '';
							const titleAttr = isAvail ? `${info.available_count} time slots open` : 'Unavailable';

							const $sq = $(`
								<button type="button" class="${classes.join(' ')}" data-date="${dateStr}" ${isAvail ? '' : 'disabled'} title="${titleAttr}">
									<span>${d}</span>
									${dotHtml}
								</button>
							`);
							$grid.append($sq);
						}

						if (autoFocusDay) {
							if (firstAvailDate) {
								focusDateSquare(firstAvailDate);
							} else if (!triedNextMonthFallback) {
								// Automatically advance 1 month if current month has no open dates left
								triedNextMonthFallback = true;
								let ny = viewYear;
								let nm = viewMonth + 1;
								if (nm > 12) { nm = 1; ny++; }
								loadMonthCalendar(ny, nm, true);
							} else {
								$dateHeading.text('No available dates this month');
								$slotsContainer.html('<div class="rhw-slots-empty">Please check another month using the arrows.</div>');
							}
						}
					},
					error: function() {
						$grid.css('opacity', 1).html('<div class="rhw-cal-grid-loading">Unable to connect to calendar service.</div>');
					}
				});
			}

			function focusDateSquare(dateStr) {
				activeDayStr = dateStr;
				$grid.find('.rhw-pub-day-square').removeClass('is-selected');
				$grid.find(`.rhw-pub-day-square[data-date="${dateStr}"]`).addClass('is-selected');
				loadSlotsForDate(dateStr);
			}

			function loadSlotsForDate(selectedDate) {
				if (!selectedDate) return;
				$slotsContainer.html('<div class="rhw-slots-loading"><span class="dashicons dashicons-update rhw-spin"></span> Checking available slots...</div>');

				$.ajax({
					url: config.ajax_url,
					type: 'GET',
					data: {
						action: 'rhw_get_available_slots',
						nonce: config.nonce,
						date: selectedDate
					},
					dataType: 'json',
					success: function(res) {
						if (res && res.success && res.data) {
							activeDayFormatted = res.data.formatted_date || selectedDate;
							$dateHeading.text(`Available Times: ${activeDayFormatted}`);

							if (res.data.slots && res.data.slots.length) {
								let html = '';
								const chosenDate = $dateInput.val();
								const chosenTime = $timeInput.val();

								res.data.slots.forEach(s => {
									const disClass = s.available ? '' : 'disabled';
									const activeClass = (s.available && chosenDate === selectedDate && chosenTime === s.time) ? 'active' : '';
									const titleAttr = s.available ? `title="${escapeHtml(s.range_label || s.time)}"` : 'title="Unavailable"';
									html += `<button type="button" class="rhw-slot-pill ${disClass} ${activeClass}" data-time="${escapeHtml(s.time)}" ${s.available ? '' : 'disabled'} ${titleAttr}>${escapeHtml(s.label || s.time)}</button>`;
								});
								$slotsContainer.html(html);
							} else {
								const msg = res.data.message || 'No consultation slots available on this date.';
								$slotsContainer.html(`<div class="rhw-slots-empty">${escapeHtml(msg)}</div>`);
							}
						} else {
							const msg = (res && res.data && res.data.message) ? res.data.message : 'Unable to load time slots.';
							$slotsContainer.html(`<div class="rhw-slots-empty">${escapeHtml(msg)}</div>`);
						}
					},
					error: function() {
						$slotsContainer.html('<div class="rhw-slots-empty">Unable to check availability. Please choose another date.</div>');
					}
				});
			}

			function updateSelectionPreview() {
				const cDate = $dateInput.val();
				const cTime = $timeInput.val();
				if (cDate && cTime) {
					const dispDate = (cDate === activeDayStr && activeDayFormatted) ? activeDayFormatted : cDate;
					$previewText.text(`${dispDate} at ${cTime}`);
					$preview.slideDown(150);
					$card.find('.rhw-cal-error-msg').hide();
				} else {
					$preview.hide();
				}
			}

			$card.find('.rhw-pub-cal-prev').on('click', function() {
				let m = viewMonth - 1;
				let y = viewYear;
				if (m < 1) { m = 12; y--; }
				loadMonthCalendar(y, m, false);
			});

			$card.find('.rhw-pub-cal-next').on('click', function() {
				let m = viewMonth + 1;
				let y = viewYear;
				if (m > 12) { m = 1; y++; }
				loadMonthCalendar(y, m, false);
			});

			$grid.on('click', '.rhw-pub-day-square.is-available', function() {
				const dateStr = $(this).data('date');
				if (!dateStr) return;

				// Clicking a new date square clears previous time slot so the user selects a valid time on the new date
				if ($dateInput.val() !== dateStr) {
					$dateInput.val('');
					$timeInput.val('');
					updateSelectionPreview();
				}
				focusDateSquare(dateStr);
			});

			$slotsContainer.on('click', '.rhw-slot-pill:not(.disabled)', function(e) {
				e.preventDefault();
				const selectedTime = $(this).data('time');
				if (!activeDayStr || !selectedTime) return;

				$slotsContainer.find('.rhw-slot-pill').removeClass('active');
				$(this).addClass('active');
				$dateInput.val(activeDayStr);
				$timeInput.val(selectedTime);
				updateSelectionPreview();
			});

			$wrapper.on('rhw:refresh_calendar', function() {
				loadMonthCalendar(viewYear, viewMonth, false);
				if (activeDayStr) {
					$timeInput.val('');
					updateSelectionPreview();
					loadSlotsForDate(activeDayStr);
				}
			});

			// Load initial month grid cleanly
			loadMonthCalendar(viewYear, viewMonth, true);
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
