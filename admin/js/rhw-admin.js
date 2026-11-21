/**
 * Rose Hall Weddings Event Calculator Admin JavaScript
 */

(function($) {
	'use strict';

	// Internal State initialized from PHP window.rhwInitialFormState
	let formState = {
		sections: [],
		questions: [],
		pricing_settings: {},
		notification_settings: {},
		appearance_settings: {}
	};

	$(document).ready(function() {
		if (window.rhwInitialFormState) {
			formState = $.extend(true, {}, window.rhwInitialFormState);
			renderSectionsAndQuestions();
		}

		initClipboard();
		initTabs();
		initEventListeners();
	});

	/**
	 * Copy shortcode to clipboard
	 */
	function initClipboard() {
		$('.rhw-copy-shortcode-btn').on('click', function(e) {
			e.preventDefault();
			const text = $(this).data('clipboard') || $(this).prev('code').text();
			navigator.clipboard.writeText(text).then(() => {
				const originalText = $(this).html();
				$(this).html('<span class="dashicons dashicons-yes"></span> ' + (rhw_admin_vars.i18n.copied || 'Copied!'));
				setTimeout(() => {
					$(this).html(originalText);
				}, 2000);
			});
		});
	}

	/**
	 * Admin Tab Switching
	 */
	function initTabs() {
		$('.rhw-tab-btn').on('click', function(e) {
			e.preventDefault();
			const target = $(this).data('tab');
			$('.rhw-tab-btn').removeClass('active');
			$('.rhw-tab-pane').removeClass('active');
			$(this).addClass('active');
			$('#' + target).addClass('active');
		});
	}

	/**
	 * Event listeners for builder interactions
	 */
	function initEventListeners() {
		// Save button click
		$('#rhw-save-form-btn').on('click', function(e) {
			e.preventDefault();
			serializeAndSubmit();
		});

		// Add Section
		$('#rhw-add-section-btn').on('click', function() {
			const secTitle = prompt('Enter New Section Title:');
			if (secTitle && secTitle.trim()) {
				const newId = 'sec_' + Date.now();
				formState.sections.push({
					id: newId,
					title: secTitle.trim(),
					description: '',
					icon: 'calendar'
				});
				renderSectionsAndQuestions();
			}
		});

		// Add Question
		$('#rhw-add-question-btn').on('click', function() {
			if (!formState.sections.length) {
				alert('Please create at least one section first.');
				return;
			}
			const qLabel = prompt('Enter Question Label:');
			if (qLabel && qLabel.trim()) {
				const newQId = 'q_' + Date.now();
				formState.questions.push({
					id: newQId,
					section_id: formState.sections[0].id,
					label: qLabel.trim(),
					type: 'radio',
					required: false,
					category: 'General',
					options: [
						{ label: 'Option 1', value: 'opt_1', pricing: { type: 'fixed', amount: 0 } },
						{ label: 'Option 2', value: 'opt_2', pricing: { type: 'fixed', amount: 0 } }
					]
				});
				renderSectionsAndQuestions();
			}
		});

		// Live Preview Modal
		$('#rhw-preview-trigger-btn').on('click', function() {
			openPreviewModal();
		});

		$('.rhw-modal-close, #rhw-preview-modal').on('click', function(e) {
			if (e.target === this) {
				$('#rhw-preview-modal').fadeOut(200);
			}
		});
	}

	/**
	 * Render Sections and their nested questions
	 */
	function renderSectionsAndQuestions() {
		const $container = $('#rhw-sections-container');
		$container.empty();

		if (!formState.sections || !formState.sections.length) {
			$container.html('<div class="rhw-card" style="text-align:center; padding:30px; color:#666;">No sections created yet. Click "Add Section" to begin.</div>');
			return;
		}

		formState.sections.forEach((sec, sIdx) => {
			const $secBlock = $('<div class="rhw-section-block" data-section-id="' + sec.id + '"></div>');
			const $secHeader = $(`
				<div class="rhw-section-header">
					<div class="rhw-section-title-wrap">
						<span class="dashicons dashicons-menu rhw-q-drag-handle"></span>
						<h3 contenteditable="true" class="rhw-sec-title-edit" title="Click to edit section title">${escapeHtml(sec.title)}</h3>
					</div>
					<div>
						<button type="button" class="button button-small rhw-del-sec-btn" title="Delete section">&times;</button>
					</div>
				</div>
			`);

			$secHeader.find('.rhw-sec-title-edit').on('blur', function() {
				sec.title = $(this).text().trim();
			});

			$secHeader.find('.rhw-del-sec-btn').on('click', function() {
				if (confirm('Delete this section and move its questions?')) {
					formState.sections.splice(sIdx, 1);
					renderSectionsAndQuestions();
				}
			});

			$secBlock.append($secHeader);

			const $qList = $('<div class="rhw-questions-list"></div>');
			const secQuestions = formState.questions.filter(q => q.section_id === sec.id);

			if (secQuestions.length) {
				secQuestions.forEach(q => {
					$qList.append(createQuestionCard(q));
				});
			} else {
				$qList.append('<p class="description" style="margin: 8px 0;">No questions in this section yet. Drag questions here or click "Add Question".</p>');
			}

			$secBlock.append($qList);
			$container.append($secBlock);
		});

		if ($.fn.sortable) {
			$('.rhw-questions-list').sortable({
				connectWith: '.rhw-questions-list',
				handle: '.rhw-q-drag-handle',
				placeholder: 'rhw-sortable-placeholder',
				update: function() {
					syncOrderFromDOM();
				}
			});
		}
	}

	/**
	 * Build HTML element for a single question card
	 */
	function createQuestionCard(q) {
		const hasPrice = (q.options && q.options.some(o => o.pricing && (o.pricing.amount > 0 || o.pricing.weekend_amount > 0))) || (q.unit_price && q.unit_price > 0);
		const hasCond = q.conditional && q.conditional.enabled;

		const $card = $(`
			<div class="rhw-question-item" data-qid="${escapeHtml(q.id)}">
				<div class="rhw-question-header">
					<div class="rhw-q-title-area">
						<span class="dashicons dashicons-move rhw-q-drag-handle"></span>
						<span class="rhw-q-title">${escapeHtml(q.label || 'Untitled Question')}</span>
						<span class="rhw-badge">${escapeHtml(q.type)}</span>
						${q.required ? '<span class="rhw-badge rhw-badge-required">Required</span>' : ''}
						${hasPrice ? '<span class="rhw-badge rhw-badge-price">Priced</span>' : ''}
						${hasCond ? '<span class="rhw-badge rhw-badge-cond">Conditional</span>' : ''}
					</div>
					<div>
						<button type="button" class="button button-small rhw-toggle-q-btn"><span class="dashicons dashicons-arrow-down-alt2"></span></button>
						<button type="button" class="button button-small button-link-delete rhw-del-q-btn">&times;</button>
					</div>
				</div>
				<div class="rhw-question-body" style="display:none;">
					<div class="rhw-grid-2" style="margin-bottom: 12px;">
						<div>
							<label class="rhw-label">Question Label</label>
							<input type="text" class="widefat rhw-edit-q-label" value="${escapeHtml(q.label || '')}">
						</div>
						<div>
							<label class="rhw-label">Field Type</label>
							<select class="widefat rhw-edit-q-type">
								<option value="radio" ${q.type === 'radio' ? 'selected' : ''}>Radio Buttons</option>
								<option value="checkboxes" ${q.type === 'checkboxes' || q.type === 'checkbox' ? 'selected' : ''}>Checkboxes</option>
								<option value="select" ${q.type === 'select' ? 'selected' : ''}>Dropdown Select</option>
								<option value="date" ${q.type === 'date' ? 'selected' : ''}>Date Picker (Weekday/Weekend)</option>
								<option value="time" ${q.type === 'time' ? 'selected' : ''}>Time Picker</option>
								<option value="file" ${q.type === 'file' ? 'selected' : ''}>Image / File Upload</option>
								<option value="number" ${q.type === 'number' ? 'selected' : ''}>Number / Counter</option>
								<option value="text" ${q.type === 'text' ? 'selected' : ''}>Text (Single line)</option>
								<option value="textarea" ${q.type === 'textarea' ? 'selected' : ''}>Textarea (Multi-line)</option>
								<option value="email" ${q.type === 'email' ? 'selected' : ''}>Email Address</option>
								<option value="tel" ${q.type === 'tel' ? 'selected' : ''}>Phone Number</option>
							</select>
						</div>
					</div>

					<div style="margin-bottom: 12px;">
						<label class="rhw-label">Help Text / Subtitle</label>
						<input type="text" class="widefat rhw-edit-q-help" value="${escapeHtml(q.help_text || '')}">
					</div>

					<div style="display:flex; gap:16px; margin-bottom: 12px; align-items:center;">
						<label><input type="checkbox" class="rhw-edit-q-req" ${q.required ? 'checked' : ''}> <strong>Required Field</strong></label>
						<label><input type="checkbox" class="rhw-edit-q-gc" ${q.is_guest_count ? 'checked' : ''}> Controls Event Guest Count</label>
					</div>

					<!-- Options & Pricing Table for choice types -->
					<div class="rhw-options-editor" style="${['radio', 'select', 'checkboxes', 'checkbox'].includes(q.type) ? '' : 'display:none;'}">
						<label class="rhw-label" style="display:flex; justify-content:space-between; align-items:center;">
							<span>Answer Choices & Associated Pricing Rules</span>
							<button type="button" class="button button-small rhw-add-opt-btn">+ Add Choice</button>
						</label>
						<table class="rhw-options-table">
							<thead>
								<tr>
									<th>Choice Label</th>
									<th>Value Key</th>
									<th>Pricing Type</th>
									<th>Weekday / Base ($)</th>
									<th>Weekend ($)</th>
									<th>Addl Hr ($)</th>
									<th>Action</th>
								</tr>
							</thead>
							<tbody class="rhw-options-tbody">
								<!-- Rendered below -->
							</tbody>
						</table>
					</div>

					<!-- Numeric formula options -->
					<div class="rhw-number-pricing-editor" style="${q.type === 'number' ? '' : 'display:none;'}">
						<div style="display:flex; gap:12px; align-items:center; background:#f8fafc; padding:10px; border-radius:4px;">
							<div>
								<label class="rhw-label">Unit Price ($)</label>
								<input type="number" step="0.5" class="rhw-edit-unit-price" style="width:100px;" value="${q.unit_price || 0}">
							</div>
							<div>
								<label class="rhw-label">Category</label>
								<input type="text" class="rhw-edit-q-cat" value="${escapeHtml(q.category || 'General')}">
							</div>
						</div>
					</div>
				</div>
			</div>
		`);

		// Render options rows
		const $tbody = $card.find('.rhw-options-tbody');
		if (q.options && q.options.length) {
			q.options.forEach((opt, oIdx) => {
				const p = opt.pricing || { type: 'none', amount: 0 };
				const weekdayVal = p.weekday_amount !== undefined ? p.weekday_amount : p.amount;
				const weekendVal = p.weekend_amount !== undefined ? p.weekend_amount : p.amount;
				const $tr = $(`
					<tr>
						<td><input type="text" class="widefat rhw-opt-label" value="${escapeHtml(opt.label || '')}"></td>
						<td><input type="text" class="widefat rhw-opt-value" value="${escapeHtml(opt.value || '')}"></td>
						<td>
							<select class="rhw-opt-ptype">
								<option value="none" ${p.type === 'none' ? 'selected' : ''}>No Price</option>
								<option value="venue" ${p.type === 'venue' ? 'selected' : ''}>Venue (Weekday/Weekend)</option>
								<option value="fixed" ${p.type === 'fixed' ? 'selected' : ''}>Fixed ($)</option>
								<option value="per_guest" ${p.type === 'per_guest' ? 'selected' : ''}>Per Guest ($/person)</option>
								<option value="table_ratio_10" ${p.type === 'table_ratio_10' ? 'selected' : ''}>10-Guest Tables ($/table)</option>
								<option value="table_ratio_15" ${p.type === 'table_ratio_15' ? 'selected' : ''}>Bistro Tables ($/table)</option>
								<option value="bar_package" ${p.type === 'bar_package' ? 'selected' : ''}>Bar Package</option>
							</select>
						</td>
						<td><input type="number" step="0.5" class="rhw-opt-amount" style="width:75px;" value="${weekdayVal || 0}"></td>
						<td><input type="number" step="0.5" class="rhw-opt-weekend" style="width:75px;" value="${weekendVal || 0}"></td>
						<td><input type="number" step="0.5" class="rhw-opt-addl" style="width:70px;" value="${p.addl_hour_rate || 0}"></td>
						<td><button type="button" class="button button-small rhw-del-opt-btn">&times;</button></td>
					</tr>
				`);

				$tr.find('.rhw-opt-label').on('input', function() { opt.label = $(this).val(); });
				$tr.find('.rhw-opt-value').on('input', function() { opt.value = $(this).val(); });
				$tr.find('.rhw-opt-ptype').on('change', function() {
					if (!opt.pricing) opt.pricing = {};
					opt.pricing.type = $(this).val();
					if ($(this).val() === 'venue') {
						opt.pricing.is_venue = true;
					}
				});
				$tr.find('.rhw-opt-amount').on('input', function() {
					if (!opt.pricing) opt.pricing = {};
					const val = parseFloat($(this).val()) || 0;
					opt.pricing.amount = val;
					opt.pricing.weekday_amount = val;
				});
				$tr.find('.rhw-opt-weekend').on('input', function() {
					if (!opt.pricing) opt.pricing = {};
					opt.pricing.weekend_amount = parseFloat($(this).val()) || 0;
				});
				$tr.find('.rhw-opt-addl').on('input', function() {
					if (!opt.pricing) opt.pricing = {};
					opt.pricing.addl_hour_rate = parseFloat($(this).val()) || 0;
				});
				$tr.find('.rhw-del-opt-btn').on('click', function() {
					q.options.splice(oIdx, 1);
					$tr.remove();
				});

				$tbody.append($tr);
			});
		}

		$card.find('.rhw-add-opt-btn').on('click', function() {
			if (!q.options) q.options = [];
			const newOpt = {
				label: 'New Option',
				value: 'opt_' + (q.options.length + 1),
				pricing: { type: 'none', amount: 0 }
			};
			q.options.push(newOpt);
			renderSectionsAndQuestions();
		});

		$card.find('.rhw-question-header, .rhw-toggle-q-btn').on('click', function(e) {
			if ($(e.target).closest('.rhw-del-q-btn').length || $(e.target).hasClass('rhw-q-drag-handle')) {
				return;
			}
			$card.find('.rhw-question-body').slideToggle(200);
		});

		$card.find('.rhw-del-q-btn').on('click', function(e) {
			e.stopPropagation();
			if (confirm('Delete this question?')) {
				const idx = formState.questions.findIndex(item => item.id === q.id);
				if (idx !== -1) {
					formState.questions.splice(idx, 1);
					renderSectionsAndQuestions();
				}
			}
		});

		$card.find('.rhw-edit-q-label').on('input', function() {
			q.label = $(this).val();
			$card.find('.rhw-q-title').text(q.label || 'Untitled Question');
		});

		$card.find('.rhw-edit-q-type').on('change', function() {
			q.type = $(this).val();
			renderSectionsAndQuestions();
		});

		$card.find('.rhw-edit-q-help').on('input', function() {
			q.help_text = $(this).val();
		});

		$card.find('.rhw-edit-q-req').on('change', function() {
			q.required = $(this).is(':checked');
		});

		$card.find('.rhw-edit-q-gc').on('change', function() {
			q.is_guest_count = $(this).is(':checked');
		});

		$card.find('.rhw-edit-unit-price').on('input', function() {
			q.unit_price = parseFloat($(this).val()) || 0;
		});

		$card.find('.rhw-edit-q-cat').on('input', function() {
			q.category = $(this).val();
		});

		return $card;
	}

	/**
	 * Sync questions order and sections from DOM after dragging
	 */
	function syncOrderFromDOM() {
		const newQuestions = [];
		$('.rhw-section-block').each(function() {
			const secId = $(this).data('section-id');
			$(this).find('.rhw-question-item').each(function() {
				const qId = $(this).data('qid');
				const found = formState.questions.find(q => q.id === qId);
				if (found) {
					found.section_id = secId;
					newQuestions.push(found);
				}
			});
		});

		if (newQuestions.length === formState.questions.length) {
			formState.questions = newQuestions;
		}
	}

	/**
	 * Serialize state and submit main form
	 */
	function serializeAndSubmit() {
		syncOrderFromDOM();

		formState.pricing_settings = {
			currency_symbol: $('#rhw_pricing_currency').val() || '$',
			currency_position: 'before',
			tax_rate: parseFloat($('#rhw_pricing_tax').val()) || 15.0,
			service_charge_rate: parseFloat($('#rhw_pricing_service_charge').val()) || 20.0,
			estimate_label: $('#rhw_pricing_estimate_label').val() || 'Estimated Cost',
			disclaimer_text: $('#rhw_pricing_disclaimer').val() || ''
		};

		formState.notification_settings = {
			recipient_emails: $('#rhw_notif_recipients').val() || '',
			email_subject: $('#rhw_notif_subject').val() || '',
			sender_name: $('#rhw_notif_sender_name').val() || '',
			sender_email: $('#rhw_notif_sender_email').val() || '',
			send_customer_receipt: $('#rhw_notif_send_receipt').is(':checked') ? 1 : 0
		};

		formState.appearance_settings = {
			primary_color: $('#rhw_app_primary').val() || '#7B112D',
			accent_color: $('#rhw_app_accent').val() || '#C5A059',
			background_color: $('#rhw_app_bg').val() || '#FDFBF7',
			border_radius: $('#rhw_app_radius').val() || '8px',
			custom_css: $('#rhw_app_custom_css').val() || ''
		};

		$('#rhw_sections_json').val(JSON.stringify(formState.sections));
		$('#rhw_questions_json').val(JSON.stringify(formState.questions));
		$('#rhw_pricing_settings_json').val(JSON.stringify(formState.pricing_settings));
		$('#rhw_notification_settings_json').val(JSON.stringify(formState.notification_settings));
		$('#rhw_appearance_settings_json').val(JSON.stringify(formState.appearance_settings));

		$('#rhw-form-editor').submit();
	}

	/**
	 * Open interactive Live Preview Modal
	 */
	function openPreviewModal() {
		syncOrderFromDOM();
		$('#rhw-preview-modal').fadeIn(200);

		const $container = $('#rhw-preview-container');
		$container.html(`
			<div class="rhw-card" style="border: 2px dashed #C5A059;">
				<h3 style="color:#7B112D; margin-top:0;">${escapeHtml($('#rhw_title_field').val() || 'Event Calculator Preview')}</h3>
				<p class="description">Live interactive test using current configuration and pricing rules:</p>
				<div style="background:#FAF5EB; padding:16px; border-radius:6px; margin:16px 0;">
					<strong>Configured Sections:</strong> ${formState.sections.length} &bull;
					<strong>Active Questions:</strong> ${formState.questions.length} &bull;
					<strong>Tax Rate:</strong> ${$('#rhw_pricing_tax').val() || 15}% &bull;
					<strong>F&B Service Charge:</strong> ${$('#rhw_pricing_service_charge').val() || 20}%
				</div>
				<p><em>To test full customer multi-step execution, save the form and insert <code>[event_cost_calculator id="${$('input[name=form_id]').val()}"]</code> on any page.</em></p>
			</div>
		`);
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

})(jQuery);
