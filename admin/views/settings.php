<?php
/**
 * Plugin Global Settings, Weekly Schedule & Google Calendar Sync Admin View.
 *
 * @package RoseHallWeddings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
if ( ! in_array( $active_tab, array( 'general', 'schedule', 'google_sync' ), true ) ) {
	$active_tab = 'general';
}

$forms                  = RHW_Form_Manager::get_all_forms();
$default_form_id        = get_option( 'rhw_default_form_id', 0 );
$currency_symbol        = get_option( 'rhw_currency_symbol', '$' );
$default_tax_rate       = get_option( 'rhw_default_tax_rate', 15.0 );
$default_service_charge = get_option( 'rhw_default_service_charge', 20.0 );
$default_notif_emails   = get_option( 'rhw_default_notification_emails', get_option( 'admin_email' ) . ', weddings@rosehall.com' );
$header_image_url       = get_option( 'rhw_header_image_url', '' );
$delete_on_uninstall    = get_option( 'rhw_delete_data_on_uninstall', '0' );

$schedule_settings = RHW_Calendar_Manager::get_settings();
$gcal_settings     = RHW_Google_Calendar::get_settings();
$gcal_connected    = RHW_Google_Calendar::is_connected();
$gcal_account      = RHW_Google_Calendar::get_connected_account_label();
$gcal_calendars    = ( $gcal_connected && 'oauth' === $gcal_settings['mode'] ) ? RHW_Google_Calendar::list_calendars() : array();
$redirect_uri      = RHW_Google_Calendar::get_redirect_uri();
?>
<div class="wrap rhw-admin-wrap">
	<div class="rhw-header-bar">
		<div>
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Event Calculator Settings', 'rhw-calculator' ); ?></h1>
			<p class="rhw-header-subtitle"><?php esc_html_e( 'Configure system-wide defaults, header branding, weekly booking hours & time intervals, and Google Calendar sync.', 'rhw-calculator' ); ?></p>
		</div>
	</div>
	<hr class="wp-header-end">

	<?php if ( isset( $_GET['updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Global settings saved.', 'rhw-calculator' ); ?></p></div>
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

	<!-- Tabs Navigation -->
	<div class="rhw-tabs-nav">
		<button type="button" class="rhw-tab-btn <?php echo 'general' === $active_tab ? 'active' : ''; ?>" data-tab="tab-settings-general">
			<span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'General Settings', 'rhw-calculator' ); ?>
		</button>
		<button type="button" class="rhw-tab-btn <?php echo 'schedule' === $active_tab ? 'active' : ''; ?>" data-tab="tab-settings-schedule">
			<span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Weekly Hours & Time Intervals', 'rhw-calculator' ); ?>
		</button>
		<button type="button" class="rhw-tab-btn <?php echo 'google_sync' === $active_tab ? 'active' : ''; ?>" data-tab="tab-settings-gcal">
			<span class="dashicons dashicons-google"></span> <?php esc_html_e( 'Google Calendar Sync Setup', 'rhw-calculator' ); ?>
		</button>
	</div>

	<!-- TAB 1: General Global Settings & Header Image -->
	<div class="rhw-tab-pane <?php echo 'general' === $active_tab ? 'active' : ''; ?>" id="tab-settings-general">
		<div class="rhw-card">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="rhw_save_settings">
				<?php wp_nonce_field( 'rhw_save_settings_nonce', 'rhw_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row"><label for="rhw_default_form_id"><?php esc_html_e( 'Primary / Default Calculator Form', 'rhw-calculator' ); ?></label></th>
						<td>
							<select name="rhw_default_form_id" id="rhw_default_form_id" class="regular-text">
								<?php foreach ( $forms as $f ) : ?>
									<option value="<?php echo esc_attr( $f->id ); ?>" <?php selected( $default_form_id, $f->id ); ?>>
										#<?php echo esc_html( $f->id ); ?> - <?php echo esc_html( $f->title ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Used when [event_cost_calculator] shortcode is called without an id attribute.', 'rhw-calculator' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="rhw_header_image_url"><?php esc_html_e( 'Calculator Header Image URL (270px × 270px)', 'rhw-calculator' ); ?></label></th>
						<td>
							<div style="display: flex; gap: 8px; align-items: center; max-width: 650px;">
								<input type="url" name="rhw_header_image_url" id="rhw_header_image_url" value="<?php echo esc_attr( $header_image_url ); ?>" class="large-text" placeholder="https://yourdomain.com/wp-content/uploads/logo-270x270.png">
								<button type="button" class="button button-secondary" id="rhw_choose_header_image_btn"><?php esc_html_e( 'Media Library', 'rhw-calculator' ); ?></button>
							</div>
							<p class="description"><?php esc_html_e( 'Displays a 270px × 270px centered image above the calculator title (replaces the badge bubble).', 'rhw-calculator' ); ?></p>
							<div id="rhw_header_image_preview_wrap" style="margin-top: 10px; <?php echo empty( $header_image_url ) ? 'display:none;' : ''; ?>">
								<img id="rhw_header_image_preview" src="<?php echo esc_url( $header_image_url ); ?>" alt="" style="width: 135px; height: 135px; object-fit: contain; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; padding: 4px;">
							</div>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="rhw_currency_symbol"><?php esc_html_e( 'Global Currency Symbol', 'rhw-calculator' ); ?></label></th>
						<td>
							<input type="text" name="rhw_currency_symbol" id="rhw_currency_symbol" value="<?php echo esc_attr( $currency_symbol ); ?>" style="width: 80px;">
							<span class="description"><?php esc_html_e( 'Default is $ (USD).', 'rhw-calculator' ); ?></span>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="rhw_default_tax_rate"><?php esc_html_e( 'Default Government Tax Rate (%)', 'rhw-calculator' ); ?></label></th>
						<td>
							<input type="number" step="0.1" name="rhw_default_tax_rate" id="rhw_default_tax_rate" value="<?php echo esc_attr( $default_tax_rate ); ?>" style="width: 100px;"> %
							<p class="description"><?php esc_html_e( 'Standard Jamaican Government Tax rate (15% by default).', 'rhw-calculator' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="rhw_default_service_charge"><?php esc_html_e( 'Default F&B Service Charge (%)', 'rhw-calculator' ); ?></label></th>
						<td>
							<input type="number" step="0.1" name="rhw_default_service_charge" id="rhw_default_service_charge" value="<?php echo esc_attr( $default_service_charge ); ?>" style="width: 100px;"> %
							<p class="description"><?php esc_html_e( 'Standard hospitality service charge applied to catering and bar services (20% by default).', 'rhw-calculator' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="rhw_default_notification_emails"><?php esc_html_e( 'Default Notification Emails', 'rhw-calculator' ); ?></label></th>
						<td>
							<input type="text" name="rhw_default_notification_emails" id="rhw_default_notification_emails" value="<?php echo esc_attr( $default_notif_emails ); ?>" class="large-text">
							<p class="description"><?php esc_html_e( 'Comma-separated email addresses for venue sales & coordination team.', 'rhw-calculator' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Data Safety & Uninstall Policy', 'rhw-calculator' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="rhw_delete_data_on_uninstall" value="1" <?php checked( '1' === (string) $delete_on_uninstall ); ?>>
								<strong style="color: #b32d2e;"><?php esc_html_e( 'Delete all calculator forms and customer submissions upon plugin deletion', 'rhw-calculator' ); ?></strong>
							</label>
							<p class="description">
								<?php esc_html_e( 'By default (unchecked), your forms, pricing structures, and prospective client leads are permanently preserved in the WordPress database even if the plugin is deactivated or removed.', 'rhw-calculator' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary rhw-btn-accent button-large">
						<?php esc_html_e( 'Save Global Settings', 'rhw-calculator' ); ?>
					</button>
				</p>
			</form>
		</div>
	</div>

	<!-- TAB 2: Weekly Hours & Time Intervals -->
	<div class="rhw-tab-pane <?php echo 'schedule' === $active_tab ? 'active' : ''; ?>" id="tab-settings-schedule">
		<div class="rhw-card">
			<h2 class="rhw-card-title"><?php esc_html_e( 'Weekly Hours & Time Intervals', 'rhw-calculator' ); ?></h2>
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

	<!-- TAB 3: Google Calendar Sync Setup -->
	<div class="rhw-tab-pane <?php echo 'google_sync' === $active_tab ? 'active' : ''; ?>" id="tab-settings-gcal">
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

	// Live preview when Header Image URL changes
	$('#rhw_header_image_url').on('input change', function() {
		const url = $(this).val().trim();
		if (url) {
			$('#rhw_header_image_preview').attr('src', url);
			$('#rhw_header_image_preview_wrap').show();
		} else {
			$('#rhw_header_image_preview_wrap').hide();
		}
	});

	// WordPress Media Library picker for Header Image URL
	$('#rhw_choose_header_image_btn').on('click', function(e) {
		e.preventDefault();
		if (typeof wp === 'undefined' || !wp.media) return;
		const frame = wp.media({
			title: 'Select Calculator Header Image (270x270)',
			button: { text: 'Use Image' },
			multiple: false
		});
		frame.on('select', function() {
			const attachment = frame.state().get('selection').first().toJSON();
			if (attachment && attachment.url) {
				$('#rhw_header_image_url').val(attachment.url).trigger('change');
			}
		});
		frame.open();
	});
});
</script>
