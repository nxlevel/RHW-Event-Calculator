<?php
/**
 * Admin Calendar, Schedule Settings & Google Calendar Sync View
 *
 * @package RoseHallWeddings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$bookings_table    = $wpdb->prefix . 'rhw_bookings';
$submissions_table = $wpdb->prefix . 'rhw_submissions';

$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'calendar';
if ( ! in_array( $active_tab, array( 'calendar', 'schedule', 'google_sync' ), true ) ) {
	$active_tab = 'calendar';
}

$schedule_settings = RHW_Calendar_Manager::get_settings();
$gcal_settings     = RHW_Google_Calendar::get_settings();
$gcal_connected    = RHW_Google_Calendar::is_connected();
$gcal_account      = RHW_Google_Calendar::get_connected_account_label();
$gcal_calendars    = ( $gcal_connected && 'oauth' === $gcal_settings['mode'] ) ? RHW_Google_Calendar::list_calendars() : array();
$redirect_uri      = RHW_Google_Calendar::get_redirect_uri();

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

// Summary stats
$total_bookings = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$bookings_table} WHERE status = 'confirmed'" );
$total_blocked  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$bookings_table} WHERE status = 'blocked'" );
$upcoming_count = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$bookings_table} WHERE booking_date >= %s AND status = 'confirmed'",
		$today_ymd
	)
);
?>

<div class="wrap rhw-admin-wrap">
	<div class="rhw-header-bar">
		<div>
			<h1 class="rhw-main-title"><?php esc_html_e( 'Consultations Calendar & Google Sync', 'rhw-calculator' ); ?></h1>
			<p class="rhw-header-subtitle"><?php esc_html_e( 'Manage booked consultation calls, configure weekly open hours & intervals, block dates/times, and sync with your team Google Calendar.', 'rhw-calculator' ); ?></p>
		</div>
		<div class="rhw-header-actions">
			<span class="rhw-badge" style="padding: 6px 12px; font-size: 12px; background: <?php echo $gcal_connected ? '#dcfce7' : '#fef3c7'; ?>; color: <?php echo $gcal_connected ? '#166534' : '#92400e'; ?>;">
				<span class="dashicons <?php echo $gcal_connected ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>" style="font-size: 16px; width: 16px; height: 16px; vertical-align: text-bottom;"></span>
				<?php echo $gcal_connected ? esc_html__( 'Google Calendar Connected', 'rhw-calculator' ) : esc_html__( 'Google Sync Not Connected', 'rhw-calculator' ); ?>
			</span>
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
	<?php if ( isset( $_GET['schedule_saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Weekly schedule and time intervals updated.', 'rhw-calculator' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['gcal_saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Google Calendar sync settings saved.', 'rhw-calculator' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['gcal_connected'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Successfully connected your Google Account Calendar!', 'rhw-calculator' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['gcal_disconnected'] ) ) : ?>
		<div class="notice notice-info is-dismissible"><p><?php esc_html_e( 'Disconnected Google Account.', 'rhw-calculator' ); ?></p></div>
	<?php endif; ?>
	<?php if ( ! empty( $_GET['gcal_error'] ) ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( wp_unslash( $_GET['gcal_error'] ) ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['error'] ) ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Please provide both a date and time slot.', 'rhw-calculator' ); ?></p></div>
	<?php endif; ?>

	<!-- Stats Overview -->
	<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 16px; margin: 16px 0 8px;">
		<div class="rhw-card" style="padding: 16px; text-align: center; border-left: 4px solid #7B112D;">
			<div style="font-size: 26px; font-weight: 700; color: #7B112D;"><?php echo esc_html( $upcoming_count ); ?></div>
			<div style="font-size: 12px; color: #666; font-weight: 600; text-transform: uppercase;"><?php esc_html_e( 'Upcoming Consultations', 'rhw-calculator' ); ?></div>
		</div>
		<div class="rhw-card" style="padding: 16px; text-align: center; border-left: 4px solid #C5A059;">
			<div style="font-size: 26px; font-weight: 700; color: #C5A059;"><?php echo esc_html( $total_bookings ); ?></div>
			<div style="font-size: 12px; color: #666; font-weight: 600; text-transform: uppercase;"><?php esc_html_e( 'Total Consultations', 'rhw-calculator' ); ?></div>
		</div>
		<div class="rhw-card" style="padding: 16px; text-align: center; border-left: 4px solid #dc2626;">
			<div style="font-size: 26px; font-weight: 700; color: #dc2626;"><?php echo esc_html( $total_blocked ); ?></div>
			<div style="font-size: 12px; color: #666; font-weight: 600; text-transform: uppercase;"><?php esc_html_e( 'Blocked Dates / Slots', 'rhw-calculator' ); ?></div>
		</div>
		<div class="rhw-card" style="padding: 16px; text-align: center; border-left: 4px solid #2563eb;">
			<div style="font-size: 26px; font-weight: 700; color: #2563eb;"><?php echo esc_html( $schedule_settings['interval_minutes'] ); ?>m</div>
			<div style="font-size: 12px; color: #666; font-weight: 600; text-transform: uppercase;"><?php esc_html_e( 'Slot Interval', 'rhw-calculator' ); ?></div>
		</div>
	</div>

	<!-- Tabs Navigation -->
	<div class="rhw-tabs-nav">
		<button type="button" class="rhw-tab-btn <?php echo 'calendar' === $active_tab ? 'active' : ''; ?>" data-tab="tab-cal-overview">
			<span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e( 'Calendar & Block Dates/Times', 'rhw-calculator' ); ?>
		</button>
		<button type="button" class="rhw-tab-btn <?php echo 'schedule' === $active_tab ? 'active' : ''; ?>" data-tab="tab-cal-schedule">
			<span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Weekly Hours & Time Intervals', 'rhw-calculator' ); ?>
		</button>
		<button type="button" class="rhw-tab-btn <?php echo 'google_sync' === $active_tab ? 'active' : ''; ?>" data-tab="tab-cal-gcal">
			<span class="dashicons dashicons-google"></span> <?php esc_html_e( 'Google Calendar Sync Setup', 'rhw-calculator' ); ?>
		</button>
	</div>

	<!-- TAB 1: Interactive Visual Month Calendar + Day Inspector + Table -->
	<div class="rhw-tab-pane <?php echo 'calendar' === $active_tab ? 'active' : ''; ?>" id="tab-cal-overview">
		<div class="rhw-admin-cal-layout">
			<!-- Left: Interactive Month Grid -->
			<div class="rhw-card">
				<div class="rhw-admin-cal-toolbar">
					<div>
						<h2 class="rhw-admin-cal-month-title" id="rhw-admin-cal-month-label"><?php echo esc_html( $now_dt->format( 'F Y' ) ); ?></h2>
						<span class="description"><?php esc_html_e( 'Click any date square to view appointments or block specific times / the entire day.', 'rhw-calculator' ); ?></span>
					</div>
					<div style="display: flex; gap: 6px;">
						<button type="button" class="button" id="rhw-admin-cal-prev">&larr; <?php esc_html_e( 'Prev', 'rhw-calculator' ); ?></button>
						<button type="button" class="button" id="rhw-admin-cal-today"><?php esc_html_e( 'Today', 'rhw-calculator' ); ?></button>
						<button type="button" class="button" id="rhw-admin-cal-next"><?php esc_html_e( 'Next', 'rhw-calculator' ); ?> &rarr;</button>
					</div>
				</div>

				<div class="rhw-admin-cal-weekdays">
					<div><?php esc_html_e( 'Sun', 'rhw-calculator' ); ?></div>
					<div><?php esc_html_e( 'Mon', 'rhw-calculator' ); ?></div>
					<div><?php esc_html_e( 'Tue', 'rhw-calculator' ); ?></div>
					<div><?php esc_html_e( 'Wed', 'rhw-calculator' ); ?></div>
					<div><?php esc_html_e( 'Thu', 'rhw-calculator' ); ?></div>
					<div><?php esc_html_e( 'Fri', 'rhw-calculator' ); ?></div>
					<div><?php esc_html_e( 'Sat', 'rhw-calculator' ); ?></div>
				</div>

				<div class="rhw-admin-cal-grid" id="rhw-admin-cal-grid">
					<div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #64748b;">
						<?php esc_html_e( 'Loading interactive calendar...', 'rhw-calculator' ); ?>
					</div>
				</div>
			</div>

			<!-- Right: Day Inspector & Slot / Whole-Day Blocker -->
			<div class="rhw-card" id="rhw-admin-day-inspector">
				<div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px;">
					<div>
						<h3 class="rhw-card-title" id="rhw-inspector-date-title"><?php echo esc_html( $now_dt->format( 'l, F j, Y' ) ); ?></h3>
						<p class="description" id="rhw-inspector-date-sub" style="margin: 2px 0 0;"><?php esc_html_e( 'Select slots below to block/unblock or inspect bookings.', 'rhw-calculator' ); ?></p>
					</div>
				</div>

				<div id="rhw-inspector-block-day-bar" style="margin: 14px 0; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
					<label for="rhw_admin_block_note" style="font-size: 11px; font-weight: 600; color: #475569; display: block; margin-bottom: 4px;">
						<?php esc_html_e( 'Block Reason / Note (Optional)', 'rhw-calculator' ); ?>
					</label>
					<div style="display: flex; gap: 8px;">
						<input type="text" id="rhw_admin_block_note" class="regular-text" style="flex: 1;" placeholder="<?php esc_attr_e( 'e.g. Private event, staff unavailable...', 'rhw-calculator' ); ?>" value="Blocked by Admin">
						<button type="button" class="button button-secondary" id="rhw-inspector-block-all-btn" style="white-space: nowrap;">
							<span class="dashicons dashicons-lock" style="margin-top: 3px;"></span> <?php esc_html_e( 'Block Entire Day', 'rhw-calculator' ); ?>
						</button>
					</div>
				</div>

				<div id="rhw-inspector-slots-list" class="rhw-slot-manage-list">
					<p class="description"><?php esc_html_e( 'Loading day slots...', 'rhw-calculator' ); ?></p>
				</div>
			</div>
		</div>

		<!-- Bookings & Blocks Log Table -->
		<div class="rhw-card" style="margin-top: 24px;">
			<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
				<h2 class="rhw-card-title" style="margin: 0;"><?php esc_html_e( 'All Scheduled Consultations & Blocked Slots', 'rhw-calculator' ); ?></h2>
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
									<span class="rhw-badge" style="background: <?php echo $is_blocked ? '#dc2626' : '#7B112D'; ?>; color: #fff; font-size: 11px;">
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

	<!-- TAB 2: Weekly Schedule & Time Intervals -->
	<div class="rhw-tab-pane <?php echo 'schedule' === $active_tab ? 'active' : ''; ?>" id="tab-cal-schedule">
		<div class="rhw-card">
			<h2 class="rhw-card-title"><?php esc_html_e( 'Availability Rules, Days of Week & Time Intervals', 'rhw-calculator' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Configure which days of the week are bookable, opening/closing hours per day, and slot durations.', 'rhw-calculator' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="rhw_save_calendar_schedule">
				<?php wp_nonce_field( 'rhw_save_calendar_schedule' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Booking Calendar Display', 'rhw-calculator' ); ?></th>
						<td>
							<label style="display: block; margin-bottom: 8px;">
								<input type="checkbox" name="calendar_enabled" value="1" <?php checked( ! empty( $schedule_settings['enabled'] ) ); ?>>
								<strong><?php esc_html_e( 'Show Consultation Booking Calendar on Final Step', 'rhw-calculator' ); ?></strong>
							</label>
							<label style="display: block;">
								<input type="checkbox" name="calendar_required" value="1" <?php checked( ! empty( $schedule_settings['required'] ) ); ?>>
								<?php esc_html_e( 'Require users to select a consultation date & time slot before submitting their quote request', 'rhw-calculator' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rhw_interval_minutes"><?php esc_html_e( 'Time Slot Interval', 'rhw-calculator' ); ?></label></th>
						<td>
							<select id="rhw_interval_minutes" name="interval_minutes" class="regular-text">
								<option value="15" <?php selected( (int) $schedule_settings['interval_minutes'], 15 ); ?>><?php esc_html_e( '15 minutes', 'rhw-calculator' ); ?></option>
								<option value="30" <?php selected( (int) $schedule_settings['interval_minutes'], 30 ); ?>><?php esc_html_e( '30 minutes', 'rhw-calculator' ); ?></option>
								<option value="45" <?php selected( (int) $schedule_settings['interval_minutes'], 45 ); ?>><?php esc_html_e( '45 minutes', 'rhw-calculator' ); ?></option>
								<option value="60" <?php selected( (int) $schedule_settings['interval_minutes'], 60 ); ?>><?php esc_html_e( '60 minutes (1 Hour - Default)', 'rhw-calculator' ); ?></option>
								<option value="90" <?php selected( (int) $schedule_settings['interval_minutes'], 90 ); ?>><?php esc_html_e( '90 minutes (1.5 Hours)', 'rhw-calculator' ); ?></option>
								<option value="120" <?php selected( (int) $schedule_settings['interval_minutes'], 120 ); ?>><?php esc_html_e( '120 minutes (2 Hours)', 'rhw-calculator' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Length of each consultation time slot shown on the booking calendar.', 'rhw-calculator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rhw_buffer_minutes"><?php esc_html_e( 'Break / Buffer Between Slots', 'rhw-calculator' ); ?></label></th>
						<td>
							<select id="rhw_buffer_minutes" name="buffer_minutes" class="regular-text">
								<option value="0" <?php selected( (int) $schedule_settings['buffer_minutes'], 0 ); ?>><?php esc_html_e( '0 minutes (Back-to-back slots)', 'rhw-calculator' ); ?></option>
								<option value="15" <?php selected( (int) $schedule_settings['buffer_minutes'], 15 ); ?>><?php esc_html_e( '15 minutes', 'rhw-calculator' ); ?></option>
								<option value="30" <?php selected( (int) $schedule_settings['buffer_minutes'], 30 ); ?>><?php esc_html_e( '30 minutes', 'rhw-calculator' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rhw_min_notice_hours"><?php esc_html_e( 'Booking Window Notice', 'rhw-calculator' ); ?></label></th>
						<td>
							<?php esc_html_e( 'Minimum advance notice:', 'rhw-calculator' ); ?>
							<input type="number" id="rhw_min_notice_hours" name="min_notice_hours" min="0" max="720" style="width: 80px;" value="<?php echo esc_attr( $schedule_settings['min_notice_hours'] ); ?>"> <?php esc_html_e( 'hours', 'rhw-calculator' ); ?>
							&nbsp;&nbsp;&bull;&nbsp;&nbsp;
							<?php esc_html_e( 'Max days ahead:', 'rhw-calculator' ); ?>
							<input type="number" name="max_days_ahead" min="7" max="730" style="width: 80px;" value="<?php echo esc_attr( $schedule_settings['max_days_ahead'] ); ?>"> <?php esc_html_e( 'days', 'rhw-calculator' ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rhw_calendar_timezone"><?php esc_html_e( 'Calendar Timezone', 'rhw-calculator' ); ?></label></th>
						<td>
							<select id="rhw_calendar_timezone" name="timezone" class="regular-text">
								<?php echo wp_timezone_choice( $schedule_settings['timezone'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</select>
							<p class="description"><?php esc_html_e( 'Timezone used for all slot times and Google Calendar events (e.g. America/Jamaica).', 'rhw-calculator' ); ?></p>
						</td>
					</tr>
				</table>

				<h3 style="margin-top: 24px; padding-top: 18px; border-top: 1px solid #e2e8f0; font-size: 16px; color: #7B112D;">
					<?php esc_html_e( 'Weekly Available Days & Hours', 'rhw-calculator' ); ?>
				</h3>
				<p class="description"><?php esc_html_e( 'Enable the days of the week couples can schedule calls and configure the start/end hours for each day.', 'rhw-calculator' ); ?></p>

				<table class="rhw-schedule-table">
					<thead>
						<tr>
							<th style="width: 180px;"><?php esc_html_e( 'Day of Week', 'rhw-calculator' ); ?></th>
							<th style="width: 140px;"><?php esc_html_e( 'Available?', 'rhw-calculator' ); ?></th>
							<th style="width: 180px;"><?php esc_html_e( 'Start Hour', 'rhw-calculator' ); ?></th>
							<th style="width: 180px;"><?php esc_html_e( 'End Hour', 'rhw-calculator' ); ?></th>
							<th><?php esc_html_e( 'Preview', 'rhw-calculator' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $schedule_settings['weekly_hours'] as $day_key => $day_cfg ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $day_cfg['label'] ); ?></strong></td>
								<td>
									<label>
										<input type="checkbox" name="weekly_hours[<?php echo esc_attr( $day_key ); ?>][enabled]" value="1" <?php checked( ! empty( $day_cfg['enabled'] ) ); ?>>
										<?php esc_html_e( 'Open', 'rhw-calculator' ); ?>
									</label>
								</td>
								<td>
									<input type="time" name="weekly_hours[<?php echo esc_attr( $day_key ); ?>][start]" value="<?php echo esc_attr( $day_cfg['start'] ); ?>" step="900">
								</td>
								<td>
									<input type="time" name="weekly_hours[<?php echo esc_attr( $day_key ); ?>][end]" value="<?php echo esc_attr( $day_cfg['end'] ); ?>" step="900">
								</td>
								<td style="color: #64748b; font-size: 12px;">
									<?php
									if ( ! empty( $day_cfg['enabled'] ) ) {
										echo esc_html( date( 'g:i A', strtotime( $day_cfg['start'] ) ) . ' - ' . date( 'g:i A', strtotime( $day_cfg['end'] ) ) );
									} else {
										esc_html_e( 'Closed (unavailable on calendar)', 'rhw-calculator' );
									}
									?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary rhw-btn-accent button-large">
						<?php esc_html_e( 'Save Schedule & Time Intervals', 'rhw-calculator' ); ?>
					</button>
				</p>
			</form>
		</div>
	</div>

	<!-- TAB 3: Google Account Calendar Sync Setup -->
	<div class="rhw-tab-pane <?php echo 'google_sync' === $active_tab ? 'active' : ''; ?>" id="tab-cal-gcal">
		<div class="rhw-card">
			<div class="rhw-gcal-status-banner <?php echo $gcal_connected ? 'connected' : 'disconnected'; ?>">
				<div>
					<strong style="font-size: 15px;">
						<?php if ( $gcal_connected ) : ?>
							&#10004; <?php esc_html_e( 'Google Calendar Sync Active:', 'rhw-calculator' ); ?> <?php echo esc_html( $gcal_account ); ?>
						<?php else : ?>
							&#9888; <?php esc_html_e( 'Google Calendar Sync Not Connected Yet', 'rhw-calculator' ); ?>
						<?php endif; ?>
					</strong>
					<div style="font-size: 12px; margin-top: 4px;">
						<?php esc_html_e( 'When connected, busy events on your Google Calendar automatically block those times on the website calculator, and new consultations are added automatically to Google Calendar with Google Meet links.', 'rhw-calculator' ); ?>
					</div>
				</div>
				<?php if ( $gcal_connected && 'oauth' === $gcal_settings['mode'] ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="rhw_disconnect_gcal">
						<?php wp_nonce_field( 'rhw_disconnect_gcal' ); ?>
						<button type="submit" class="button button-secondary" onclick="return confirm('<?php esc_attr_e( 'Disconnect your Google Account from the calculator?', 'rhw-calculator' ); ?>');">
							<?php esc_html_e( 'Disconnect Google Account', 'rhw-calculator' ); ?>
						</button>
					</form>
				<?php endif; ?>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="rhw_save_gcal_settings">
				<?php wp_nonce_field( 'rhw_save_gcal_settings' ); ?>

				<h2 class="rhw-card-title"><?php esc_html_e( '1. Google Account Authentication Method', 'rhw-calculator' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Connection Mode', 'rhw-calculator' ); ?></th>
						<td>
							<label style="margin-right: 20px;">
								<input type="radio" name="mode" value="oauth" <?php checked( $gcal_settings['mode'], 'oauth' ); ?> class="rhw-gcal-mode-radio">
								<strong><?php esc_html_e( 'Google Account Sign-In (OAuth 2.0 - Recommended)', 'rhw-calculator' ); ?></strong>
							</label>
							<label>
								<input type="radio" name="mode" value="service_account" <?php checked( $gcal_settings['mode'], 'service_account' ); ?> class="rhw-gcal-mode-radio">
								<strong><?php esc_html_e( 'Google Service Account JSON', 'rhw-calculator' ); ?></strong>
							</label>
						</td>
					</tr>
				</table>

				<!-- OAuth Credentials Section -->
				<div id="rhw-gcal-oauth-box" style="<?php echo 'oauth' === $gcal_settings['mode'] ? '' : 'display:none;'; ?> background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px; margin-bottom: 20px;">
					<h3 style="margin-top: 0; font-size: 14px;"><?php esc_html_e( 'Google OAuth 2.0 App Setup Guide', 'rhw-calculator' ); ?></h3>
					<ol style="font-size: 13px; color: #475569; margin: 0 0 14px 18px; line-height: 1.6;">
						<li><?php esc_html_e( 'Go to Google Cloud Console (console.cloud.google.com) -> Enable "Google Calendar API".', 'rhw-calculator' ); ?></li>
						<li><?php esc_html_e( 'Under "APIs & Services -> Credentials", click "Create Credentials -> OAuth client ID" (Web application).', 'rhw-calculator' ); ?></li>
						<li>
							<?php esc_html_e( 'Add this Authorized Redirect URI:', 'rhw-calculator' ); ?>
							<div style="margin-top: 4px; display: flex; align-items: center; gap: 8px;">
								<code style="background: #ffffff; padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 4px; user-select: all;"><?php echo esc_html( $redirect_uri ); ?></code>
								<button type="button" class="button button-small rhw-copy-shortcode-btn" data-clipboard="<?php echo esc_attr( $redirect_uri ); ?>"><?php esc_html_e( 'Copy URI', 'rhw-calculator' ); ?></button>
							</div>
						</li>
						<li><?php esc_html_e( 'Paste the Client ID and Client Secret below, then click "Save & Connect Google Account".', 'rhw-calculator' ); ?></li>
					</ol>

					<table class="form-table" style="margin: 0;">
						<tr>
							<th scope="row"><label for="rhw_gcal_client_id"><?php esc_html_e( 'Google Client ID', 'rhw-calculator' ); ?></label></th>
							<td><input type="text" id="rhw_gcal_client_id" name="client_id" class="large-text" value="<?php echo esc_attr( $gcal_settings['client_id'] ); ?>" placeholder="xxxxxxxxxxxx-xxxxxxxxxxxxxxxx.apps.googleusercontent.com"></td>
						</tr>
						<tr>
							<th scope="row"><label for="rhw_gcal_client_secret"><?php esc_html_e( 'Google Client Secret', 'rhw-calculator' ); ?></label></th>
							<td><input type="password" id="rhw_gcal_client_secret" name="client_secret" class="regular-text" value="<?php echo esc_attr( $gcal_settings['client_secret'] ); ?>" placeholder="GOCSPX-xxxxxxxxxxxxxxxx"></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Authorize Account', 'rhw-calculator' ); ?></th>
							<td>
								<button type="submit" name="connect_google_oauth" value="1" class="button button-primary" style="background: #4285F4; border-color: #4285F4; display: inline-flex; align-items: center; gap: 6px;">
									<span class="dashicons dashicons-google"></span>
									<?php echo $gcal_connected ? esc_html__( 'Save & Reconnect Google Account', 'rhw-calculator' ) : esc_html__( 'Save & Connect Google Account', 'rhw-calculator' ); ?>
								</button>
							</td>
						</tr>
					</table>
				</div>

				<!-- Service Account Section -->
				<div id="rhw-gcal-sa-box" style="<?php echo 'service_account' === $gcal_settings['mode'] ? '' : 'display:none;'; ?> background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px; margin-bottom: 20px;">
					<h3 style="margin-top: 0; font-size: 14px;"><?php esc_html_e( 'Google Service Account Setup', 'rhw-calculator' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Create a Service Account key (JSON) in Google Cloud Console, then share your team Google Calendar with the service account email ("Make changes to events" permission).', 'rhw-calculator' ); ?></p>
					<table class="form-table" style="margin: 0;">
						<tr>
							<th scope="row"><label for="rhw_gcal_service_json"><?php esc_html_e( 'Service Account JSON Key', 'rhw-calculator' ); ?></label></th>
							<td>
								<textarea id="rhw_gcal_service_json" name="service_json" rows="6" class="large-text code" placeholder='{"type": "service_account", "client_email": "...", "private_key": "-----BEGIN PRIVATE KEY-----..."}'><?php echo esc_textarea( $gcal_settings['service_json'] ); ?></textarea>
							</td>
						</tr>
					</table>
				</div>

				<h2 class="rhw-card-title"><?php esc_html_e( '2. Target Calendar & Two-Way Sync Behavior', 'rhw-calculator' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="rhw_gcal_calendar_id"><?php esc_html_e( 'Team Google Calendar to Sync', 'rhw-calculator' ); ?></label></th>
						<td>
							<?php if ( ! empty( $gcal_calendars ) ) : ?>
								<select id="rhw_gcal_calendar_id" name="calendar_id" class="regular-text">
									<?php foreach ( $gcal_calendars as $cal ) : ?>
										<option value="<?php echo esc_attr( $cal['id'] ); ?>" <?php selected( $gcal_settings['calendar_id'], $cal['id'] ); ?>>
											<?php echo esc_html( $cal['summary'] . ( $cal['primary'] ? ' (Primary)' : '' ) ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							<?php else : ?>
								<input type="text" id="rhw_gcal_calendar_id" name="calendar_id" class="regular-text" value="<?php echo esc_attr( $gcal_settings['calendar_id'] ); ?>" placeholder="primary or team@domain.com">
							<?php endif; ?>
							<p class="description"><?php esc_html_e( 'Use "primary" for the main connected account calendar, or paste a shared Calendar ID.', 'rhw-calculator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Sync Options', 'rhw-calculator' ); ?></th>
						<td>
							<label style="display: block; margin-bottom: 8px;">
								<input type="checkbox" name="sync_busy_times" value="1" <?php checked( ! empty( $gcal_settings['sync_busy_times'] ) ); ?>>
								<strong><?php esc_html_e( 'Block conflicting Google Calendar busy times from appearing on website form', 'rhw-calculator' ); ?></strong>
							</label>
							<label style="display: block; margin-bottom: 8px;">
								<input type="checkbox" name="auto_create_event" value="1" <?php checked( ! empty( $gcal_settings['auto_create_event'] ) ); ?>>
								<strong><?php esc_html_e( 'Automatically add new lead consultation bookings to Google Calendar', 'rhw-calculator' ); ?></strong>
							</label>
							<label style="display: block; margin-bottom: 8px;">
								<input type="checkbox" name="create_meet_link" value="1" <?php checked( ! empty( $gcal_settings['create_meet_link'] ) ); ?>>
								<?php esc_html_e( 'Automatically generate & attach a Google Meet video call link', 'rhw-calculator' ); ?>
							</label>
							<label style="display: block;">
								<input type="checkbox" name="invite_attendee" value="1" <?php checked( ! empty( $gcal_settings['invite_attendee'] ) ); ?>>
								<?php esc_html_e( 'Send Google Calendar invitation to customer email address', 'rhw-calculator' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary rhw-btn-accent button-large">
						<?php esc_html_e( 'Save Google Sync Settings', 'rhw-calculator' ); ?>
					</button>
				</p>
			</form>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	// Toggle Google Sync Auth Panels
	$('.rhw-gcal-mode-radio').on('change', function() {
		if ($(this).val() === 'oauth') {
			$('#rhw-gcal-sa-box').hide();
			$('#rhw-gcal-oauth-box').fadeIn(150);
		} else {
			$('#rhw-gcal-oauth-box').hide();
			$('#rhw-gcal-sa-box').fadeIn(150);
		}
	});

	// Interactive Admin Calendar Month Grid & Day Inspector
	let curYear = <?php echo (int) $init_year; ?>;
	let curMonth = <?php echo (int) $init_month; ?>;
	let selectedDate = '<?php echo esc_js( $today_ymd ); ?>';
	const todayYmd = '<?php echo esc_js( $today_ymd ); ?>';

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
				renderAdminMonthGrid(res.data);
			}
		});
	}

	function renderAdminMonthGrid(data) {
		$('#rhw-admin-cal-month-label').text(data.month_label);
		const $grid = $('#rhw-admin-cal-grid');
		$grid.empty();

		for (let i = 0; i < data.first_weekday; i++) {
			$grid.append('<div class="rhw-admin-day-square is-empty"></div>');
		}

		for (let d = 1; d <= data.days_in_month; d++) {
			const mm = String(data.month).padStart(2, '0');
			const dd = String(d).padStart(2, '0');
			const dateStr = `${data.year}-${mm}-${dd}`;
			const info = data.days[dateStr] || { status: 'closed', available_count: 0, booked_count: 0, blocked_count: 0, events: [] };

			const classes = ['rhw-admin-day-square'];
			if (!info.day_enabled) classes.push('is-closed');
			if (info.all_day_blocked) classes.push('is-blocked');
			if (dateStr === todayYmd) classes.push('is-today');
			if (dateStr === selectedDate) classes.push('is-selected');

			let pillsHtml = '';
			if (!info.day_enabled) {
				pillsHtml += '<span class="rhw-day-pill rhw-day-pill-closed">Closed</span>';
			} else if (info.all_day_blocked) {
				pillsHtml += '<span class="rhw-day-pill rhw-day-pill-blocked">Day Blocked</span>';
			} else {
				if (info.booked_count > 0) {
					pillsHtml += `<span class="rhw-day-pill rhw-day-pill-booked">${info.booked_count} Booked</span>`;
				}
				if (info.blocked_count > 0) {
					pillsHtml += `<span class="rhw-day-pill rhw-day-pill-blocked">${info.blocked_count} Blocked</span>`;
				}
				pillsHtml += `<span class="rhw-day-pill rhw-day-pill-avail">${info.available_count} open</span>`;
			}

			const $sq = $(`
				<button type="button" class="${classes.join(' ')}" data-date="${dateStr}">
					<div class="rhw-day-top">
						<span class="rhw-day-num">${d}</span>
					</div>
					<div class="rhw-day-meta">${pillsHtml}</div>
				</button>
			`);

			$grid.append($sq);
		}
	}

	function loadAdminDaySlots(dateStr) {
		selectedDate = dateStr;
		$('.rhw-admin-day-square').removeClass('is-selected');
		$(`.rhw-admin-day-square[data-date="${dateStr}"]`).addClass('is-selected');

		$('#rhw-inspector-slots-list').html('<p class="description">Loading slots...</p>');
		$.get(rhw_admin_vars.ajax_url, {
			action: 'rhw_admin_get_day_slots',
			nonce: rhw_admin_vars.nonce,
			date: dateStr
		}, function(res) {
			if (res && res.success && res.data) {
				renderAdminDayInspector(res.data);
			}
		});
	}

	function renderAdminDayInspector(day) {
		$('#rhw-inspector-date-title').text(day.formatted_date || day.date);

		const $blockDayBtn = $('#rhw-inspector-block-all-btn');
		if (day.all_day_blocked && day.all_day_booking) {
			$blockDayBtn.html('<span class="dashicons dashicons-unlock" style="margin-top:3px;"></span> Unblock Entire Day')
				.data('op', 'unblock')
				.data('booking-id', day.all_day_booking.id);
			$('#rhw-inspector-date-sub').html('<strong style="color:#dc2626;">This entire day is currently blocked.</strong>');
		} else {
			$blockDayBtn.html('<span class="dashicons dashicons-lock" style="margin-top:3px;"></span> Block Entire Day')
				.data('op', 'block_day')
				.data('booking-id', 0);
			if (!day.day_enabled) {
				$('#rhw-inspector-date-sub').text('Configured as Closed in Weekly Hours.');
			} else {
				$('#rhw-inspector-date-sub').text(`${day.available_count} of ${day.slots.length} slots open (${day.interval_mins}m intervals)`);
			}
		}

		const $list = $('#rhw-inspector-slots-list');
		$list.empty();

		if (!day.day_enabled) {
			$list.html(`<div style="padding:16px; background:#f1f5f9; border-radius:6px; color:#64748b; text-align:center;">${day.message || 'Closed on this day of the week.'}</div>`);
			return;
		}

		if (!day.slots || !day.slots.length) {
			$list.html('<div style="padding:16px; background:#f1f5f9; border-radius:6px; color:#64748b; text-align:center;">No time slots configured for this day.</div>');
			return;
		}

		day.slots.forEach(s => {
			let metaHtml = '';
			let actionHtml = '';

			if (s.status === 'booked' && s.booking) {
				metaHtml = `
					<div style="font-size:12px; color:#7B112D; font-weight:600;">${escapeHtml(s.booking.customer_name)}</div>
					<div style="font-size:11px; color:#64748b;">${escapeHtml(s.booking.customer_email)}${s.booking.customer_phone ? ' &bull; ' + escapeHtml(s.booking.customer_phone) : ''}</div>
				`;
				actionHtml = `
					${s.booking.submission_id ? `<a href="admin.php?page=rhw-calculator-submissions&action=view&id=${s.booking.submission_id}" class="button button-small">Quote</a>` : ''}
					<button type="button" class="button button-small button-link-delete rhw-slot-action-btn" data-op="unblock" data-booking-id="${s.booking.id}">Cancel</button>
				`;
			} else if (s.status === 'blocked_admin') {
				const reason = s.booking && s.booking.notes ? s.booking.notes : 'Blocked';
				metaHtml = `<div style="font-size:12px; color:#991b1b; font-weight:600;">Blocked: ${escapeHtml(reason)}</div>`;
				if (!day.all_day_blocked && s.booking) {
					actionHtml = `<button type="button" class="button button-small rhw-slot-action-btn" data-op="unblock" data-booking-id="${s.booking.id}">Unblock</button>`;
				}
			} else if (s.status === 'google_busy') {
				metaHtml = `<div style="font-size:12px; color:#1e40af; font-weight:600;">Busy on Team Google Calendar</div>`;
				actionHtml = `<span class="rhw-badge" style="background:#dbeafe; color:#1e40af;">GCal Busy</span>`;
			} else {
				metaHtml = `<div style="font-size:12px; color:#065f46; font-weight:600;">Available</div>`;
				actionHtml = `<button type="button" class="button button-small rhw-slot-action-btn" data-op="block_slot" data-time="${escapeHtml(s.time)}">Block Time</button>`;
			}

			$list.append(`
				<div class="rhw-slot-manage-row status-${escapeHtml(s.status)}">
					<div>
						<div style="font-weight:700; font-size:13px; color:#1e293b;">${escapeHtml(s.range_label || s.time)}</div>
						${metaHtml}
					</div>
					<div style="display:flex; gap:4px; align-items:center;">
						${actionHtml}
					</div>
				</div>
			`);
		});
	}

	function toggleAdminSlotBlock(op, time, bookingId) {
		const notes = $('#rhw_admin_block_note').val() || 'Blocked by Admin';
		$.post(rhw_admin_vars.ajax_url, {
			action: 'rhw_admin_toggle_block',
			nonce: rhw_admin_vars.nonce,
			op: op,
			date: selectedDate,
			time: time || '',
			booking_id: bookingId || 0,
			notes: notes
		}, function(res) {
			if (res && res.success && res.data) {
				renderAdminMonthGrid(res.data.month);
				renderAdminDayInspector(res.data.day);
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

	$('#rhw-admin-cal-grid').on('click', '.rhw-admin-day-square:not(.is-empty)', function() {
		const dateStr = $(this).data('date');
		if (dateStr) {
			loadAdminDaySlots(dateStr);
		}
	});

	$('#rhw-inspector-slots-list').on('click', '.rhw-slot-action-btn', function() {
		const op = $(this).data('op');
		const time = $(this).data('time');
		const bookingId = $(this).data('booking-id');
		if (op === 'unblock' && $(this).text() === 'Cancel') {
			if (!confirm('Cancel this consultation booking and free the time slot?')) return;
		}
		toggleAdminSlotBlock(op, time, bookingId);
	});

	$('#rhw-inspector-block-all-btn').on('click', function() {
		const op = $(this).data('op') || 'block_day';
		const bookingId = $(this).data('booking-id') || 0;
		toggleAdminSlotBlock(op, 'all_day', bookingId);
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
		selectedDate = todayYmd;
		loadAdminMonth(<?php echo (int) $init_year; ?>, <?php echo (int) $init_month; ?>);
		loadAdminDaySlots(todayYmd);
	});

	loadAdminMonth(curYear, curMonth);
	loadAdminDaySlots(selectedDate);
});
</script>
