<?php
/**
 * Fired during plugin activation.
 *
 * @package RoseHallWeddings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RHW_Activator {

	/**
	 * Run activation tasks: table creation, default form seeding.
	 */
	public static function activate() {
		global $wpdb;

		// Version check
		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			deactivate_plugins( RHW_CALCULATOR_BASENAME );
			wp_die( esc_html__( 'Rose Hall Event Calculator requires PHP 7.4 or higher.', 'rhw-calculator' ) );
		}

		global $wp_version;
		if ( version_compare( $wp_version, '5.8', '<' ) ) {
			deactivate_plugins( RHW_CALCULATOR_BASENAME );
			wp_die( esc_html__( 'Rose Hall Event Calculator requires WordPress 5.8 or higher.', 'rhw-calculator' ) );
		}

		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Forms table
		$forms_table = $wpdb->prefix . 'rhw_forms';
		$sql_forms   = "CREATE TABLE {$forms_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL,
			description text DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'published',
			sections longtext NOT NULL,
			questions longtext NOT NULL,
			pricing_settings longtext NOT NULL,
			notification_settings longtext NOT NULL,
			appearance_settings longtext NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id)
		) {$charset_collate};";
		dbDelta( $sql_forms );

		// Submissions table
		$submissions_table = $wpdb->prefix . 'rhw_submissions';
		$sql_submissions   = "CREATE TABLE {$submissions_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			form_id bigint(20) unsigned NOT NULL,
			customer_name varchar(255) NOT NULL,
			customer_email varchar(255) NOT NULL,
			customer_phone varchar(100) DEFAULT NULL,
			country_of_residence varchar(100) DEFAULT NULL,
			event_date varchar(50) DEFAULT NULL,
			guest_count int(11) DEFAULT 0,
			event_type varchar(100) DEFAULT NULL,
			venue_name varchar(255) DEFAULT NULL,
			subtotal decimal(12,2) NOT NULL DEFAULT 0.00,
			tax_amount decimal(12,2) NOT NULL DEFAULT 0.00,
			service_charge decimal(12,2) NOT NULL DEFAULT 0.00,
			grand_total decimal(12,2) NOT NULL DEFAULT 0.00,
			answers longtext NOT NULL,
			itemized_breakdown longtext NOT NULL,
			status varchar(50) NOT NULL DEFAULT 'new',
			notes text DEFAULT NULL,
			user_ip varchar(45) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY form_id (form_id),
			KEY created_at (created_at)
		) {$charset_collate};";
		dbDelta( $sql_submissions );

		// Check if we need to seed the default Rose Hall Form
		$form_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$forms_table}" );
		if ( 0 === (int) $form_count ) {
			self::seed_default_form();
		} else {
			// Update legacy section description in existing forms
			$existing_forms = $wpdb->get_results( "SELECT id, sections FROM {$forms_table}" );
			if ( $existing_forms ) {
				foreach ( $existing_forms as $f ) {
					$secs = json_decode( $f->sections, true );
					if ( is_array( $secs ) ) {
						$updated_secs = false;
						foreach ( $secs as &$sec_item ) {
							if ( isset( $sec_item['id'] ) && 'sec_event' === $sec_item['id'] ) {
								$sec_item['description'] = 'Tell us about your upcoming event date, guest count, preferred venue, décor, etc.';
								$updated_secs = true;
							}
						}
						if ( $updated_secs ) {
							$wpdb->update(
								$forms_table,
								array( 'sections' => wp_json_encode( $secs ) ),
								array( 'id' => $f->id ),
								array( '%s' ),
								array( '%d' )
							);
						}
					}
				}
			}
		}

		update_option( 'rhw_calculator_version', RHW_CALCULATOR_VERSION );
	}

	/**
	 * Seeds the default Rose Hall Weddings & Events calculator form based on Google Form & Real Cost Breakdown.
	 */
	public static function seed_default_form() {
		global $wpdb;

		$form_manager = new RHW_Form_Manager();
		$default_data = $form_manager->get_default_rose_hall_form_data();

		$wpdb->insert(
			$wpdb->prefix . 'rhw_forms',
			array(
				'title'                 => 'Rose Hall Weddings & Events - Official Calculator',
				'description'           => 'Complete our interactive wedding and event cost calculator to view your estimated investment and request a tailored consultation.',
				'status'                => 'published',
				'sections'              => wp_json_encode( $default_data['sections'] ),
				'questions'             => wp_json_encode( $default_data['questions'] ),
				'pricing_settings'      => wp_json_encode( $default_data['pricing_settings'] ),
				'notification_settings' => wp_json_encode( $default_data['notification_settings'] ),
				'appearance_settings'   => wp_json_encode( $default_data['appearance_settings'] ),
				'created_at'            => current_time( 'mysql' ),
				'updated_at'            => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		$new_id = $wpdb->insert_id;
		update_option( 'rhw_default_form_id', $new_id );
	}
}
