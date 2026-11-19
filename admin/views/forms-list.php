<?php
/**
 * Forms List Admin View.
 *
 * @package RoseHallWeddings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$forms = RHW_Form_Manager::get_all_forms();
global $wpdb;
$submissions_table = $wpdb->prefix . 'rhw_submissions';
?>
<div class="wrap rhw-admin-wrap">
	<div class="rhw-header-bar">
		<div>
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Event Cost Calculators', 'rhw-calculator' ); ?></h1>
			<p class="rhw-header-subtitle"><?php esc_html_e( 'Manage your interactive wedding & event cost calculators and shortcodes.', 'rhw-calculator' ); ?></p>
		</div>
		<div>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rhw-calculator-builder' ) ); ?>" class="button button-primary button-hero rhw-btn-accent">
				<span class="dashicons dashicons-plus-alt2" style="vertical-align: middle;"></span> <?php esc_html_e( 'Create New Form', 'rhw-calculator' ); ?>
			</a>
		</div>
	</div>

	<?php if ( isset( $_GET['deleted'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Form deleted successfully.', 'rhw-calculator' ); ?></p></div>
	<?php endif; ?>

	<div class="rhw-card" style="margin-top: 20px;">
		<table class="wp-list-table widefat fixed striped table-view-list">
			<thead>
				<tr>
					<th style="width: 50px;"><?php esc_html_e( 'ID', 'rhw-calculator' ); ?></th>
					<th><?php esc_html_e( 'Form Title & Description', 'rhw-calculator' ); ?></th>
					<th style="width: 280px;"><?php esc_html_e( 'Shortcode (Copy to any Post/Page)', 'rhw-calculator' ); ?></th>
					<th style="width: 120px;"><?php esc_html_e( 'Submissions', 'rhw-calculator' ); ?></th>
					<th style="width: 100px;"><?php esc_html_e( 'Status', 'rhw-calculator' ); ?></th>
					<th style="width: 180px;"><?php esc_html_e( 'Actions', 'rhw-calculator' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! empty( $forms ) ) : ?>
					<?php foreach ( $forms as $f ) : ?>
						<?php
						$shortcode = '[event_cost_calculator id="' . esc_attr( $f->id ) . '"]';
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
						$sub_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$submissions_table} WHERE form_id = %d", $f->id ) );
						?>
						<tr>
							<td><strong>#<?php echo esc_html( $f->id ); ?></strong></td>
							<td>
								<strong>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=rhw-calculator-builder&id=' . $f->id ) ); ?>" class="row-title">
										<?php echo esc_html( $f->title ); ?>
									</a>
								</strong>
								<?php if ( ! empty( $f->description ) ) : ?>
									<p class="description"><?php echo esc_html( $f->description ); ?></p>
								<?php endif; ?>
							</td>
							<td>
								<div class="rhw-shortcode-copy-box">
									<code><?php echo esc_html( $shortcode ); ?></code>
									<button type="button" class="button button-small rhw-copy-shortcode-btn" data-clipboard="<?php echo esc_attr( $shortcode ); ?>" title="<?php esc_attr_e( 'Copy Shortcode', 'rhw-calculator' ); ?>">
										<span class="dashicons dashicons-admin-page"></span> <?php esc_html_e( 'Copy', 'rhw-calculator' ); ?>
									</button>
								</div>
							</td>
							<td>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=rhw-calculator-submissions&form_id=' . $f->id ) ); ?>" class="badge-count">
									<?php echo esc_html( $sub_count ); ?> <?php esc_html_e( 'leads', 'rhw-calculator' ); ?>
								</a>
							</td>
							<td>
								<span class="rhw-status-badge rhw-status-<?php echo esc_attr( $f->status ); ?>">
									<?php echo esc_html( ucfirst( $f->status ) ); ?>
								</span>
							</td>
							<td>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=rhw-calculator-builder&id=' . $f->id ) ); ?>" class="button button-small">
									<?php esc_html_e( 'Edit', 'rhw-calculator' ); ?>
								</a>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=rhw_duplicate_form&id=' . $f->id ), 'rhw_duplicate_form_' . $f->id ) ); ?>" class="button button-small" title="<?php esc_attr_e( 'Duplicate this form', 'rhw-calculator' ); ?>">
									<?php esc_html_e( 'Duplicate', 'rhw-calculator' ); ?>
								</a>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=rhw_delete_form&id=' . $f->id ), 'rhw_delete_form_' . $f->id ) ); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this form? Leads submitted under this form will remain in the database.', 'rhw-calculator' ); ?>');">
									<?php esc_html_e( 'Delete', 'rhw-calculator' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr>
						<td colspan="6" style="text-align: center; padding: 40px;">
							<p><?php esc_html_e( 'No calculators found. Click "Create New Form" or re-activate the plugin to seed default Rose Hall template.', 'rhw-calculator' ); ?></p>
						</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
