<?php
/**
 * Plugin Global Settings Admin View.
 *
 * @package RoseHallWeddings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$forms                  = RHW_Form_Manager::get_all_forms();
$default_form_id        = get_option( 'rhw_default_form_id', 0 );
$currency_symbol        = get_option( 'rhw_currency_symbol', '$' );
$default_tax_rate       = get_option( 'rhw_default_tax_rate', 15.0 );
$default_service_charge = get_option( 'rhw_default_service_charge', 20.0 );
$default_notif_emails   = get_option( 'rhw_default_notification_emails', get_option( 'admin_email' ) . ', weddings@rosehall.com' );
$delete_on_uninstall    = get_option( 'rhw_delete_data_on_uninstall', '0' );
?>
<div class="wrap rhw-admin-wrap">
	<div class="rhw-header-bar">
		<div>
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Event Calculator Global Settings', 'rhw-calculator' ); ?></h1>
			<p class="rhw-header-subtitle"><?php esc_html_e( 'Configure system-wide defaults, taxes, and data safety preferences.', 'rhw-calculator' ); ?></p>
		</div>
	</div>
	<hr class="wp-header-end">

	<?php if ( isset( $_GET['updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Global settings saved.', 'rhw-calculator' ); ?></p></div>
	<?php endif; ?>

	<div class="rhw-card" style="margin-top: 20px;">
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
				<button type="submit" class="button button-primary rhw-btn-accent">
					<?php esc_html_e( 'Save Global Settings', 'rhw-calculator' ); ?>
				</button>
			</p>
		</form>
	</div>
</div>
