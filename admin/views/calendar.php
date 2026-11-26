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

// Fetch filter
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
	 ORDER BY b.booking_date ASC, b.booking_time ASC
	 LIMIT 200"
);

// Summary stats
$total_bookings = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$bookings_table} WHERE status = 'confirmed'" );
$total_blocked  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$bookings_table} WHERE status = 'blocked'" );
$upcoming_count = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$bookings_table} WHERE booking_date >= %s AND status = 'confirmed'",
		date( 'Y-m-d' )
	)
);
?>

<div class="wrap rhw-admin-wrap">
	<div class="rhw-header-bar">
		<div class="rhw-header-branding">
			<span class="dashicons dashicons-calendar-alt rhw-brand-icon"></span>
			<div>
				<h1 class="rhw-main-title"><?php esc_html_e( 'Consultations & Google Calendar Sync', 'rhw-calculator' ); ?></h1>
				<p class="rhw-main-subtitle"><?php esc_html_e( 'Manage booked couple consultation calls, block unavailable time slots, and sync directly with Google Calendar.', 'rhw-calculator' ); ?></p>
			</div>
		</div>
		<div class="rhw-header-actions">
			<a href="https://calendar.google.com/calendar" target="_blank" rel="noopener noreferrer" class="button button-secondary">
				<span class="dashicons dashicons-external" style="margin-top: 3px;"></span> <?php esc_html_e( 'Open Google Calendar', 'rhw-calculator' ); ?>
			</a>
			<button type="button" class="button button-primary rhw-btn-accent" onclick="document.getElementById('rhw-block-slot-card').scrollIntoView({behavior:'smooth'}); document.getElementById('rhw_block_date').focus();">
				<span class="dashicons dashicons-lock" style="margin-top: 3px;"></span> <?php esc_html_e( 'Block Date / Slot', 'rhw-calculator' ); ?>
			</button>
		</div>
	</div>
	<hr class="wp-header-end">

	<?php if ( isset( $_GET['blocked'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Time slot successfully blocked. It will no longer show as available on the calculator form.', 'rhw-calculator' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( isset( $_GET['unblocked'] ) ) : ?>
		<div class="notice notice-info is-dismissible">
			<p><?php esc_html_e( 'Booking / blocked slot was successfully removed and freed up.', 'rhw-calculator' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( isset( $_GET['error'] ) ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php esc_html_e( 'Please provide both a date and time slot to block.', 'rhw-calculator' ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Stats Overview -->
	<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin: 20px 0;">
		<div class="rhw-card" style="padding: 16px; text-align: center; border-left: 4px solid #7B112D;">
			<div style="font-size: 28px; font-weight: 700; color: #7B112D;"><?php echo esc_html( $upcoming_count ); ?></div>
			<div style="font-size: 13px; color: #666; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;"><?php esc_html_e( 'Upcoming Consultations', 'rhw-calculator' ); ?></div>
		</div>
		<div class="rhw-card" style="padding: 16px; text-align: center; border-left: 4px solid #C5A059;">
			<div style="font-size: 28px; font-weight: 700; color: #C5A059;"><?php echo esc_html( $total_bookings ); ?></div>
			<div style="font-size: 13px; color: #666; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;"><?php esc_html_e( 'Total Consultations Booked', 'rhw-calculator' ); ?></div>
		</div>
		<div class="rhw-card" style="padding: 16px; text-align: center; border-left: 4px solid #555;">
			<div style="font-size: 28px; font-weight: 700; color: #555;"><?php echo esc_html( $total_blocked ); ?></div>
			<div style="font-size: 13px; color: #666; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;"><?php esc_html_e( 'Admin Blocked Slots', 'rhw-calculator' ); ?></div>
		</div>
	</div>

	<!-- Main Layout: Table and Block Form -->
	<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; align-items: start;">
		<!-- Left: Bookings Table -->
		<div class="rhw-card">
			<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
				<h2 class="rhw-card-title" style="margin: 0;"><?php esc_html_e( 'Scheduled Consultations & Blocked Slots', 'rhw-calculator' ); ?></h2>
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
						<th style="width: 140px;"><?php esc_html_e( 'Date & Time', 'rhw-calculator' ); ?></th>
						<th style="width: 160px;"><?php esc_html_e( 'Lead / Couple', 'rhw-calculator' ); ?></th>
						<th><?php esc_html_e( 'Contact Info', 'rhw-calculator' ); ?></th>
						<th style="width: 100px;"><?php esc_html_e( 'Status', 'rhw-calculator' ); ?></th>
						<th style="width: 170px; text-align: right;"><?php esc_html_e( 'Actions', 'rhw-calculator' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! empty( $bookings ) ) : ?>
						<?php foreach ( $bookings as $b ) : ?>
							<?php
							$is_blocked = 'blocked' === $b->status || 'blocked_admin' === $b->booking_type;
							$slot_label = 'all_day' === $b->booking_time ? 'All Day' : $b->booking_time;

							// Google Calendar TEMPLATE url
							$gcal_url = '';
							if ( ! $is_blocked ) {
								$start_ts   = strtotime( $b->booking_date . ' ' . $b->booking_time );
								$end_ts     = $start_ts + ( 45 * 60 );
								$start_iso  = gmdate( 'Ymd\THis\Z', $start_ts );
								$end_iso    = gmdate( 'Ymd\THis\Z', $end_ts );
								$title      = rawurlencode( 'Rose Hall Wedding Consultation: ' . $b->customer_name );
								$desc       = rawurlencode( "Consultation Call with Rose Hall Wedding Specialist.\nCustomer: {$b->customer_name} ({$b->customer_email}, {$b->customer_phone})\nNotes: {$b->notes}" );
								$loc        = rawurlencode( 'Rose Hall Weddings & Events (Phone / Google Meet), Montego Bay, Jamaica' );
								$gcal_url   = "https://calendar.google.com/calendar/render?action=TEMPLATE&text={$title}&dates={$start_iso}/{$end_iso}&details={$desc}&location={$loc}";
							}
							?>
							<tr>
								<td>
									<strong><?php echo esc_html( date( 'M j, Y', strtotime( $b->booking_date ) ) ); ?></strong><br>
									<span class="rhw-badge" style="background: <?php echo $is_blocked ? '#888' : '#7B112D'; ?>; color: #fff; font-size: 11px;">
										<?php echo esc_html( $slot_label ); ?>
									</span>
								</td>
								<td>
									<?php if ( $is_blocked ) : ?>
										<em style="color: #666;"><?php echo esc_html( $b->notes ? $b->notes : 'Blocked by Admin' ); ?></em>
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
									<?php else : ?>
										<span style="color: #999;">&mdash;</span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $is_blocked ) : ?>
										<span class="rhw-badge" style="background: #e2e8f0; color: #475569;"><?php esc_html_e( 'Blocked', 'rhw-calculator' ); ?></span>
									<?php else : ?>
										<span class="rhw-badge" style="background: #dcfce7; color: #166534;"><?php esc_html_e( 'Confirmed', 'rhw-calculator' ); ?></span>
									<?php endif; ?>
								</td>
								<td style="text-align: right;">
									<?php if ( ! $is_blocked && ! empty( $gcal_url ) ) : ?>
										<a href="<?php echo esc_url( $gcal_url ); ?>" target="_blank" rel="noopener noreferrer" class="button button-small" title="<?php esc_attr_e( 'Add directly to Google Calendar', 'rhw-calculator' ); ?>" style="margin-right: 4px;">
											<span class="dashicons dashicons-calendar" style="font-size: 14px; width: 14px; height: 14px; vertical-align: text-top;"></span> GCal
										</a>
									<?php endif; ?>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=rhw_delete_booking&id=' . $b->id ), 'rhw_delete_booking_' . $b->id ) ); ?>"
									   class="button button-small button-link-delete"
									   onclick="return confirm('<?php echo esc_js( $is_blocked ? __( 'Unblock this slot and make it available again on the website form?', 'rhw-calculator' ) : __( 'Cancel this booking and free the slot?', 'rhw-calculator' ) ); ?>');">
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

		<!-- Right: Block Date / Time Slot Form -->
		<div class="rhw-card" id="rhw-block-slot-card">
			<h2 class="rhw-card-title"><?php esc_html_e( 'Block a Date / Time Slot', 'rhw-calculator' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Blocked dates or time slots immediately become unavailable on the website consultation booking calendar.', 'rhw-calculator' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="rhw_block_slot">
				<?php wp_nonce_field( 'rhw_block_time_slot' ); ?>

				<div style="margin-bottom: 14px;">
					<label for="rhw_block_date" class="rhw-label"><strong><?php esc_html_e( 'Date to Block', 'rhw-calculator' ); ?> *</strong></label>
					<input type="date" id="rhw_block_date" name="block_date" class="widefat" min="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>" required>
				</div>

				<div style="margin-bottom: 14px;">
					<label for="rhw_block_time" class="rhw-label"><strong><?php esc_html_e( 'Time Slot to Block', 'rhw-calculator' ); ?> *</strong></label>
					<select id="rhw_block_time" name="block_time" class="widefat" required>
						<option value="all_day"><?php esc_html_e( 'All Day (Block entire day)', 'rhw-calculator' ); ?></option>
						<option value="09:00 AM">09:00 AM</option>
						<option value="10:00 AM">10:00 AM</option>
						<option value="11:00 AM">11:00 AM</option>
						<option value="01:00 PM">01:00 PM</option>
						<option value="02:00 PM">02:00 PM</option>
						<option value="03:00 PM">03:00 PM</option>
						<option value="04:00 PM">04:00 PM</option>
					</select>
				</div>

				<div style="margin-bottom: 16px;">
					<label for="rhw_block_notes" class="rhw-label"><strong><?php esc_html_e( 'Reason / Internal Note', 'rhw-calculator' ); ?></strong></label>
					<input type="text" id="rhw_block_notes" name="block_notes" class="widefat" placeholder="<?php esc_attr_e( 'e.g. Google Calendar busy, staff holiday, venue closed', 'rhw-calculator' ); ?>" value="Blocked by Admin">
				</div>

				<button type="submit" class="button button-primary rhw-btn-accent" style="width: 100%;">
					<span class="dashicons dashicons-lock" style="margin-top: 3px;"></span> <?php esc_html_e( 'Block Selected Slot', 'rhw-calculator' ); ?>
				</button>
			</form>
		</div>
	</div>
</div>
