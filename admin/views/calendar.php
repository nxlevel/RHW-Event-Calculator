<?php
/**
 * Admin Calendar & Consultation Bookings View
 *
 * @package RoseHallWeddings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$bookings_table    = $wpdb->prefix . 'rhw_bookings';
$submissions_table = $wpdb->prefix . 'rhw_submissions';

$schedule_settings = RHW_Calendar_Manager::get_settings();
$gcal_connected    = RHW_Google_Calendar::is_connected();

$tz         = RHW_Calendar_Manager::get_timezone( $schedule_settings );
$now_dt     = new DateTimeImmutable( 'now', $tz );
$init_year  = (int) $now_dt->format( 'Y' );
$init_month = (int) $now_dt->format( 'n' );
$today_ymd  = $now_dt->format( 'Y-m-d' );

// Fetch filter for table below
$filter_date = isset( $_GET['filter_date'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_date'] ) ) : '';
$where_sql   = '1=1';
if ( ! empty( $filter_date ) ) {
	$where_sql .= $wpdb->prepare( ' AND b.booking_date = %s', $filter_date );
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
$bookings = $wpdb->get_results(
	"SELECT b.*, s.venue_name, s.grand_total, s.event_date as quote_event_date
	 FROM {$bookings_table} b
	 LEFT JOIN {$submissions_table} s ON b.submission_id = s.id
	 WHERE {$where_sql}
	 ORDER BY b.booking_date DESC, b.booking_time ASC
	 LIMIT 200"
);
?>

<div class="wrap rhw-admin-wrap">
	<div class="rhw-header-bar">
		<div>
			<h1 class="rhw-main-title"><?php esc_html_e( 'Calendar & Bookings', 'rhw-calculator' ); ?></h1>
			<p class="rhw-header-subtitle"><?php esc_html_e( 'Manage daily consultation availability, block specific time slots or full days, and review scheduled client meetings.', 'rhw-calculator' ); ?></p>
		</div>
		<div class="rhw-header-actions">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rhw-calculator-settings&tab=schedule' ) ); ?>" class="button button-secondary">
				<span class="dashicons dashicons-admin-settings" style="margin-top: 3px;"></span> <?php esc_html_e( 'Hours & Google Sync Settings', 'rhw-calculator' ); ?>
			</a>
			<a href="https://calendar.google.com/calendar" target="_blank" rel="noopener noreferrer" class="button button-secondary">
				<span class="dashicons dashicons-external" style="margin-top: 3px;"></span> <?php esc_html_e( 'Open Google Calendar', 'rhw-calculator' ); ?>
			</a>
		</div>
	</div>
	<hr class="wp-header-end">

	<?php if ( isset( $_GET['blocked'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Date / time slot successfully blocked.', 'rhw-calculator' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['unblocked'] ) ) : ?>
		<div class="notice notice-info is-dismissible"><p><?php esc_html_e( 'Slot unblocked / booking removed successfully.', 'rhw-calculator' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['error'] ) ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Please provide both a date and time slot.', 'rhw-calculator' ); ?></p></div>
	<?php endif; ?>

	<!-- Date Navigation Toolbar -->
	<div class="rhw-cal-top-nav">
		<div class="rhw-cal-date-controls">
			<button type="button" class="rhw-today-pill-btn" id="rhw-admin-cal-today"><?php esc_html_e( 'Today', 'rhw-calculator' ); ?></button>
			<div class="rhw-day-nav-group">
				<button type="button" class="rhw-icon-circle-btn" id="rhw-day-prev-btn" title="<?php esc_attr_e( 'Previous Day', 'rhw-calculator' ); ?>">&lsaquo;</button>
				<span id="rhw-selected-day-display"><?php echo esc_html( $now_dt->format( 'j F Y' ) ); ?></span>
				<button type="button" class="rhw-icon-circle-btn" id="rhw-day-next-btn" title="<?php esc_attr_e( 'Next Day', 'rhw-calculator' ); ?>">&rsaquo;</button>
				<label style="display:inline-flex; align-items:center; cursor:pointer; margin-left:4px;" title="<?php esc_attr_e( 'Jump to Date', 'rhw-calculator' ); ?>">
					<input type="date" id="rhw-day-jump-picker" value="<?php echo esc_attr( $today_ymd ); ?>" style="width: 32px; padding: 2px; border: 1px solid #e2e8f0; border-radius: 6px; cursor: pointer;">
				</label>
			</div>
		</div>
		<?php if ( $gcal_connected ) : ?>
			<span class="rhw-badge" style="background: #dcfce7; color: #166534; padding: 5px 10px;">
				<span class="dashicons dashicons-yes-alt" style="font-size: 15px; width: 15px; height: 15px; vertical-align: text-bottom;"></span>
				<?php esc_html_e( 'Google Calendar Synced', 'rhw-calculator' ); ?>
			</span>
		<?php endif; ?>
	</div>

	<!-- Two-Column Calendar Layout matching reference design -->
	<div class="rhw-admin-cal-split">
		<!-- Left: Time Slots Status & Toggle Table -->
		<div class="rhw-slots-panel-card">
			<table class="rhw-slots-admin-table">
				<thead>
					<tr>
						<th style="cursor: pointer;" id="rhw-sort-time-th">
							<?php esc_html_e( 'Time', 'rhw-calculator' ); ?> <span id="rhw-sort-time-arrow">&uarr;</span>
						</th>
						<th style="cursor: pointer;" id="rhw-sort-status-th">
							<?php esc_html_e( 'Status', 'rhw-calculator' ); ?> <span style="color:#94a3b8;">&#8597;</span>
						</th>
						<th style="text-align: right;">
							<div class="rhw-block-day-th">
								<span class="rhw-block-day-label"><?php esc_html_e( 'Block Entire Day', 'rhw-calculator' ); ?></span>
								<label class="rhw-switch" title="<?php esc_attr_e( 'Toggle block entire day', 'rhw-calculator' ); ?>">
									<input type="checkbox" id="rhw-block-entire-day-toggle">
									<span class="rhw-switch-slider"></span>
								</label>
							</div>
						</th>
					</tr>
				</thead>
				<tbody id="rhw-slots-table-body">
					<tr>
						<td colspan="3" style="text-align: center; padding: 30px; color: #64748b;">
							<?php esc_html_e( 'Loading time slots...', 'rhw-calculator' ); ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- Right: Compact Calendar Month Grid & Legend -->
		<div class="rhw-compact-cal-card">
			<div class="rhw-compact-cal-header">
				<button type="button" class="rhw-icon-circle-btn" id="rhw-admin-cal-prev">&lsaquo;</button>
				<h3 class="rhw-compact-cal-title" id="rhw-admin-cal-month-label"><?php echo esc_html( $now_dt->format( 'F Y' ) ); ?></h3>
				<button type="button" class="rhw-icon-circle-btn" id="rhw-admin-cal-next">&rsaquo;</button>
			</div>

			<div class="rhw-compact-cal-weekdays">
				<div><?php esc_html_e( 'Su', 'rhw-calculator' ); ?></div>
				<div><?php esc_html_e( 'Mo', 'rhw-calculator' ); ?></div>
				<div><?php esc_html_e( 'Tu', 'rhw-calculator' ); ?></div>
				<div><?php esc_html_e( 'We', 'rhw-calculator' ); ?></div>
				<div><?php esc_html_e( 'Th', 'rhw-calculator' ); ?></div>
				<div><?php esc_html_e( 'Fr', 'rhw-calculator' ); ?></div>
				<div><?php esc_html_e( 'Sa', 'rhw-calculator' ); ?></div>
			</div>

			<div class="rhw-compact-cal-grid" id="rhw-admin-cal-grid">
				<div style="grid-column: 1 / -1; text-align: center; padding: 30px; color: #64748b;">
					<?php esc_html_e( 'Loading calendar...', 'rhw-calculator' ); ?>
				</div>
			</div>

			<div class="rhw-compact-cal-legend">
				<div class="rhw-legend-row">
					<span class="rhw-legend-swatch-selected"></span>
					<span><?php esc_html_e( 'Selected date', 'rhw-calculator' ); ?></span>
				</div>
				<div class="rhw-legend-row">
					<span class="rhw-legend-tri" style="border-color: transparent #6EE787 transparent transparent;"></span>
					<span><?php esc_html_e( 'All times open', 'rhw-calculator' ); ?></span>
				</div>
				<div class="rhw-legend-row">
					<span class="rhw-legend-tri" style="border-color: transparent #FCD34D transparent transparent;"></span>
					<span><?php esc_html_e( 'Has some times closed', 'rhw-calculator' ); ?></span>
				</div>
				<div class="rhw-legend-row">
					<span class="rhw-legend-tri" style="border-color: transparent #FCA5A5 transparent transparent;"></span>
					<span><?php esc_html_e( 'All times closed', 'rhw-calculator' ); ?></span>
				</div>
			</div>
		</div>
	</div>

	<!-- Bookings & Blocks Log Table -->
	<div class="rhw-card" style="margin-top: 24px;">
		<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
			<h2 class="rhw-card-title" style="margin: 0;"><?php esc_html_e( 'Scheduled Consultations & Blocked Slots Log', 'rhw-calculator' ); ?></h2>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display: flex; gap: 8px; align-items: center;">
				<input type="hidden" name="page" value="rhw-calculator-calendar">
				<input type="date" name="filter_date" value="<?php echo esc_attr( $filter_date ); ?>" style="height: 30px;">
				<button type="submit" class="button button-small"><?php esc_html_e( 'Filter Date', 'rhw-calculator' ); ?></button>
				<?php if ( ! empty( $filter_date ) ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=rhw-calculator-calendar' ) ); ?>" class="button button-small"><?php esc_html_e( 'Clear', 'rhw-calculator' ); ?></a>
				<?php endif; ?>
			</form>
		</div>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th style="width: 150px;"><?php esc_html_e( 'Date & Time', 'rhw-calculator' ); ?></th>
					<th style="width: 180px;"><?php esc_html_e( 'Lead / Block Note', 'rhw-calculator' ); ?></th>
					<th><?php esc_html_e( 'Contact / Sync Details', 'rhw-calculator' ); ?></th>
					<th style="width: 110px;"><?php esc_html_e( 'Status', 'rhw-calculator' ); ?></th>
					<th style="width: 190px; text-align: right;"><?php esc_html_e( 'Actions', 'rhw-calculator' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! empty( $bookings ) ) : ?>
					<?php foreach ( $bookings as $b ) : ?>
						<?php
						$is_blocked = 'blocked' === $b->status || 'blocked_admin' === $b->booking_type;
						$slot_label = 'all_day' === $b->booking_time ? __( 'All Day', 'rhw-calculator' ) : $b->booking_time;

						$gcal_url = '';
						if ( ! $is_blocked ) {
							$start_ts = strtotime( $b->booking_date . ' ' . $b->booking_time );
							$end_ts   = $start_ts ? $start_ts + ( intval( $schedule_settings['interval_minutes'] ) * 60 ) : 0;
							if ( $start_ts ) {
								$gcal_url = RHW_Google_Calendar::build_manual_gcal_url(
									'Rose Hall Wedding Consultation: ' . $b->customer_name,
									$start_ts,
									$end_ts,
									"Consultation Call with Rose Hall Wedding Specialist.\nCustomer: {$b->customer_name} ({$b->customer_email}, {$b->customer_phone})\nNotes: {$b->notes}"
								);
							}
						}
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( date_i18n( 'M j, Y', strtotime( $b->booking_date ) ) ); ?></strong><br>
								<span class="rhw-badge" style="background: <?php echo $is_blocked ? '#C81E1E' : '#7B112D'; ?>; color: #fff; font-size: 11px;">
									<?php echo esc_html( $slot_label ); ?>
								</span>
							</td>
							<td>
								<?php if ( $is_blocked ) : ?>
									<em style="color: #666;"><?php echo esc_html( $b->notes ? $b->notes : __( 'Blocked by Admin', 'rhw-calculator' ) ); ?></em>
								<?php else : ?>
									<strong><?php echo esc_html( $b->customer_name ); ?></strong>
									<?php if ( ! empty( $b->submission_id ) ) : ?>
										<br><a href="<?php echo esc_url( admin_url( 'admin.php?page=rhw-calculator-submissions&action=view&id=' . $b->submission_id ) ); ?>" style="font-size: 11px;">
											<?php esc_html_e( 'View Quote #', 'rhw-calculator' ); ?><?php echo esc_html( $b->submission_id ); ?>
										</a>
									<?php endif; ?>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( ! $is_blocked ) : ?>
									<a href="mailto:<?php echo esc_attr( $b->customer_email ); ?>"><?php echo esc_html( $b->customer_email ); ?></a>
									<?php if ( ! empty( $b->customer_phone ) ) : ?>
										<br><span style="font-size: 12px; color: #555;"><?php echo esc_html( $b->customer_phone ); ?></span>
									<?php endif; ?>
									<?php if ( ! empty( $b->google_event_id ) ) : ?>
										<br><span class="rhw-badge" style="background: #dbeafe; color: #1e40af; font-size: 10px; margin-top: 3px; display: inline-block;"><?php esc_html_e( 'Synced to Google Calendar', 'rhw-calculator' ); ?></span>
									<?php endif; ?>
									<?php if ( ! empty( $b->google_meet_link ) ) : ?>
										<a href="<?php echo esc_url( $b->google_meet_link ); ?>" target="_blank" rel="noopener noreferrer" style="font-size: 11px; margin-left: 6px;"><?php esc_html_e( 'Join Meet', 'rhw-calculator' ); ?></a>
									<?php endif; ?>
								<?php else : ?>
									<span style="color: #999;">&mdash;</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $is_blocked ) : ?>
									<span class="rhw-badge" style="background: #fee2e2; color: #991b1b;"><?php esc_html_e( 'Blocked', 'rhw-calculator' ); ?></span>
								<?php else : ?>
									<span class="rhw-badge" style="background: #dcfce7; color: #166534;"><?php esc_html_e( 'Confirmed', 'rhw-calculator' ); ?></span>
								<?php endif; ?>
							</td>
							<td style="text-align: right;">
								<?php if ( ! $is_blocked && ! empty( $gcal_url ) ) : ?>
									<a href="<?php echo esc_url( $gcal_url ); ?>" target="_blank" rel="noopener noreferrer" class="button button-small" style="margin-right: 4px;">
										<span class="dashicons dashicons-calendar" style="font-size: 14px; width: 14px; height: 14px; vertical-align: text-top;"></span> GCal
									</a>
								<?php endif; ?>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=rhw_delete_booking&id=' . $b->id ), 'rhw_delete_booking_' . $b->id ) ); ?>"
								   class="button button-small button-link-delete"
								   onclick="return confirm('<?php echo esc_js( $is_blocked ? __( 'Unblock this slot and make it available again on the website form?', 'rhw-calculator' ) : __( 'Cancel this consultation (also removes synced Google Calendar event) and free the slot?', 'rhw-calculator' ) ); ?>');">
									<?php echo $is_blocked ? esc_html__( 'Unblock', 'rhw-calculator' ) : esc_html__( 'Cancel', 'rhw-calculator' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr>
						<td colspan="5" style="text-align: center; padding: 24px; color: #777;">
							<?php esc_html_e( 'No booked consultations or blocked slots found.', 'rhw-calculator' ); ?>
						</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	let curYear = <?php echo (int) $init_year; ?>;
	let curMonth = <?php echo (int) $init_month; ?>;
	let selectedDate = '<?php echo esc_js( $today_ymd ); ?>';
	const todayYmd = '<?php echo esc_js( $today_ymd ); ?>';
	let lastDayData = null;
	let sortBy = 'time'; // 'time' or 'status'
	let sortAsc = true;

	function formatNiceDate(ymd) {
		const parts = ymd.split('-');
		if (parts.length !== 3) return ymd;
		const dt = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
		const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
		return `${dt.getDate()} ${months[dt.getMonth()]} ${dt.getFullYear()}`;
	}

	function addDays(ymd, delta) {
		const parts = ymd.split('-');
		const dt = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
		dt.setDate(dt.getDate() + delta);
		const y = dt.getFullYear();
		const m = String(dt.getMonth() + 1).padStart(2, '0');
		const d = String(dt.getDate()).padStart(2, '0');
		return `${y}-${m}-${d}`;
	}

	function loadAdminMonth(year, month) {
		curYear = year;
		curMonth = month;
		$('#rhw-admin-cal-grid').css('opacity', 0.6);

		$.get(rhw_admin_vars.ajax_url, {
			action: 'rhw_admin_get_month_data',
			nonce: rhw_admin_vars.nonce,
			year: year,
			month: month
		}, function(res) {
			$('#rhw-admin-cal-grid').css('opacity', 1);
			if (res && res.success && res.data) {
				renderCompactMonthGrid(res.data);
			}
		});
	}

	function renderCompactMonthGrid(data) {
		$('#rhw-admin-cal-month-label').text(data.month_label);
		const $grid = $('#rhw-admin-cal-grid');
		$grid.empty();

		for (let i = 0; i < data.first_weekday; i++) {
			$grid.append('<div class="rhw-compact-day-btn is-empty"></div>');
		}

		for (let d = 1; d <= data.days_in_month; d++) {
			const mm = String(data.month).padStart(2, '0');
			const dd = String(d).padStart(2, '0');
			const dateStr = `${data.year}-${mm}-${dd}`;
			const info = data.days[dateStr] || { day_enabled: false, all_day_blocked: false, available_count: 0, total_slots: 0 };

			let triClass = 'rhw-tri-open';
			if (!info.day_enabled || info.all_day_blocked || info.available_count === 0) {
				triClass = 'rhw-tri-closed';
			} else if (info.available_count < info.total_slots) {
				triClass = 'rhw-tri-partial';
			}

			const classes = ['rhw-compact-day-btn'];
			if (dateStr === todayYmd) classes.push('is-today');
			if (dateStr === selectedDate) classes.push('is-selected');

			$grid.append(`
				<button type="button" class="${classes.join(' ')}" data-date="${dateStr}">
					<span>${d}</span>
					<span class="rhw-tri-badge ${triClass}"></span>
				</button>
			`);
		}
	}

	function loadAdminDaySlots(dateStr) {
		selectedDate = dateStr;
		$('#rhw-selected-day-display').text(formatNiceDate(dateStr));
		$('#rhw-day-jump-picker').val(dateStr);

		$('.rhw-compact-day-btn').removeClass('is-selected');
		$(`.rhw-compact-day-btn[data-date="${dateStr}"]`).addClass('is-selected');

		const parts = dateStr.split('-');
		const y = parseInt(parts[0], 10);
		const m = parseInt(parts[1], 10);
		if (y !== curYear || m !== curMonth) {
			loadAdminMonth(y, m);
		}

		$('#rhw-slots-table-body').html('<tr><td colspan="3" style="text-align:center; padding:24px; color:#64748b;">Loading slots...</td></tr>');
		$.get(rhw_admin_vars.ajax_url, {
			action: 'rhw_admin_get_day_slots',
			nonce: rhw_admin_vars.nonce,
			date: dateStr
		}, function(res) {
			if (res && res.success && res.data) {
				lastDayData = res.data;
				renderSlotsTable(res.data);
			}
		});
	}

	function renderSlotsTable(day) {
		const $dayToggle = $('#rhw-block-entire-day-toggle');
		if (day.all_day_blocked && day.all_day_booking) {
			$dayToggle.prop('checked', true).prop('disabled', false)
				.data('op', 'unblock')
				.data('booking-id', day.all_day_booking.id);
		} else if (!day.day_enabled) {
			$dayToggle.prop('checked', true).prop('disabled', true);
		} else {
			$dayToggle.prop('checked', false).prop('disabled', false)
				.data('op', 'block_day')
				.data('booking-id', 0);
		}

		const $tbody = $('#rhw-slots-table-body');
		$tbody.empty();

		if (!day.day_enabled) {
			$tbody.html(`<tr><td colspan="3" style="text-align:center; padding:24px; color:#64748b;">${escapeHtml(day.message || 'Configured as Closed in Settings -> Weekly Hours.')}</td></tr>`);
			return;
		}

		if (!day.slots || !day.slots.length) {
			$tbody.html('<tr><td colspan="3" style="text-align:center; padding:24px; color:#64748b;">No time slots configured for this day.</td></tr>');
			return;
		}

		const rows = day.slots.slice();
		if (sortBy === 'status') {
			rows.sort((a, b) => {
				const cmp = String(a.status).localeCompare(String(b.status));
				return sortAsc ? cmp : -cmp;
			});
		} else if (!sortAsc) {
			rows.reverse();
		}

		rows.forEach(s => {
			let badgeHtml = '<span class="rhw-status-pill is-open">Open</span>';
			let toggleHtml = '';

			if (s.status === 'booked' && s.booking) {
				badgeHtml = `<span class="rhw-status-pill is-booked">Booked: ${escapeHtml(s.booking.customer_name)}</span>`;
				toggleHtml = `
					<div style="display:inline-flex; gap:6px; align-items:center;">
						${s.booking.submission_id ? `<a href="admin.php?page=rhw-calculator-submissions&action=view&id=${s.booking.submission_id}" class="button button-small">View Quote</a>` : ''}
						<button type="button" class="button button-small button-link-delete rhw-cancel-booking-btn" data-booking-id="${s.booking.id}">Cancel</button>
					</div>
				`;
			} else if (s.status === 'google_busy') {
				badgeHtml = '<span class="rhw-status-pill is-gcal">GCal Busy</span>';
				toggleHtml = '<span style="font-size:11px; color:#64748b;">Synced from Google</span>';
			} else if (s.status === 'blocked_admin') {
				badgeHtml = '<span class="rhw-status-pill is-closed">Closed</span>';
				if (day.all_day_blocked) {
					toggleHtml = `
						<label class="rhw-switch" title="Day blocked">
							<input type="checkbox" checked disabled>
							<span class="rhw-switch-slider"></span>
						</label>
					`;
				} else {
					const bId = s.booking ? s.booking.id : 0;
					toggleHtml = `
						<label class="rhw-switch">
							<input type="checkbox" class="rhw-slot-switch" checked data-time="${escapeHtml(s.time)}" data-booking-id="${bId}">
							<span class="rhw-switch-slider"></span>
						</label>
					`;
				}
			} else {
				toggleHtml = `
					<label class="rhw-switch">
						<input type="checkbox" class="rhw-slot-switch" data-time="${escapeHtml(s.time)}" data-booking-id="0">
						<span class="rhw-switch-slider"></span>
					</label>
				`;
			}

			$tbody.append(`
				<tr>
					<td><strong>${escapeHtml(s.time)}</strong></td>
					<td>${badgeHtml}</td>
					<td style="text-align: right;">${toggleHtml}</td>
				</tr>
			`);
		});
	}

	function toggleAdminSlotBlock(op, time, bookingId) {
		$.post(rhw_admin_vars.ajax_url, {
			action: 'rhw_admin_toggle_block',
			nonce: rhw_admin_vars.nonce,
			op: op,
			date: selectedDate,
			time: time || '',
			booking_id: bookingId || 0,
			notes: 'Blocked by Admin'
		}, function(res) {
			if (res && res.success && res.data) {
				lastDayData = res.data.day;
				renderCompactMonthGrid(res.data.month);
				renderSlotsTable(res.data.day);
			}
		});
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

	$('#rhw-admin-cal-grid').on('click', '.rhw-compact-day-btn:not(.is-empty)', function() {
		const dateStr = $(this).data('date');
		if (dateStr) {
			loadAdminDaySlots(dateStr);
		}
	});

	$('#rhw-slots-table-body').on('change', '.rhw-slot-switch', function() {
		const isChecked = $(this).is(':checked');
		const time = $(this).data('time');
		const bookingId = $(this).data('booking-id') || 0;
		if (isChecked) {
			toggleAdminSlotBlock('block_slot', time, 0);
		} else {
			toggleAdminSlotBlock('unblock', time, bookingId);
		}
	});

	$('#rhw-slots-table-body').on('click', '.rhw-cancel-booking-btn', function() {
		const bId = $(this).data('booking-id');
		if (bId && confirm('Cancel this client consultation (and remove synced Google Calendar event) to free up this time slot?')) {
			toggleAdminSlotBlock('unblock', '', bId);
		}
	});

	$('#rhw-block-entire-day-toggle').on('change', function() {
		const isChecked = $(this).is(':checked');
		const bookingId = $(this).data('booking-id') || 0;
		if (isChecked) {
			toggleAdminSlotBlock('block_day', 'all_day', 0);
		} else {
			toggleAdminSlotBlock('unblock', 'all_day', bookingId);
		}
	});

	$('#rhw-day-prev-btn').on('click', function() {
		loadAdminDaySlots(addDays(selectedDate, -1));
	});

	$('#rhw-day-next-btn').on('click', function() {
		loadAdminDaySlots(addDays(selectedDate, 1));
	});

	$('#rhw-day-jump-picker').on('change', function() {
		if ($(this).val()) {
			loadAdminDaySlots($(this).val());
		}
	});

	$('#rhw-admin-cal-prev').on('click', function() {
		let m = curMonth - 1;
		let y = curYear;
		if (m < 1) { m = 12; y--; }
		loadAdminMonth(y, m);
	});

	$('#rhw-admin-cal-next').on('click', function() {
		let m = curMonth + 1;
		let y = curYear;
		if (m > 12) { m = 1; y++; }
		loadAdminMonth(y, m);
	});

	$('#rhw-admin-cal-today').on('click', function() {
		loadAdminDaySlots(todayYmd);
	});

	$('#rhw-sort-time-th').on('click', function() {
		if (sortBy === 'time') { sortAsc = !sortAsc; } else { sortBy = 'time'; sortAsc = true; }
		$('#rhw-sort-time-arrow').html(sortAsc ? '&uarr;' : '&darr;');
		if (lastDayData) renderSlotsTable(lastDayData);
	});

	$('#rhw-sort-status-th').on('click', function() {
		if (sortBy === 'status') { sortAsc = !sortAsc; } else { sortBy = 'status'; sortAsc = true; }
		if (lastDayData) renderSlotsTable(lastDayData);
	});

	loadAdminMonth(curYear, curMonth);
	loadAdminDaySlots(selectedDate);
});
</script>
