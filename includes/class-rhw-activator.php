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

		// Bookings table (Requirement 9: Google Calendar Synced Consultations & Time Slot Blocking)
		self::ensure_bookings_table_exists();

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

	/**
	 * Ensure wp_rhw_bookings table exists and is up to date.
	 */
	public static function ensure_bookings_table_exists( $force = false ) {
		static $checked = false;
		if ( $checked && ! $force ) {
			return;
		}
		$checked = true;

		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$bookings_table = $wpdb->prefix . 'rhw_bookings';
		$sql_bookings   = "CREATE TABLE {$bookings_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			submission_id bigint(20) unsigned DEFAULT NULL,
			customer_name varchar(255) NOT NULL,
			customer_email varchar(255) NOT NULL,
			customer_phone varchar(100) DEFAULT '',
			booking_date date NOT NULL,
			booking_time varchar(50) NOT NULL,
			booking_type varchar(50) NOT NULL DEFAULT 'consultation',
			notes text DEFAULT NULL,
			status varchar(50) NOT NULL DEFAULT 'confirmed',
			google_event_id varchar(255) DEFAULT NULL,
			google_meet_link varchar(500) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY booking_date (booking_date),
			KEY booking_slot (booking_date, booking_time),
			KEY status (status)
		) {$charset_collate};";
		dbDelta( $sql_bookings );
	}

	/**
	 * Automatic plugin upgrade routine when plugin version advances without reactivation.
	 */
	public static function maybe_upgrade() {
		$installed_ver = get_option( 'rhw_calculator_version', '1.0.0' );
		if ( version_compare( $installed_ver, RHW_CALCULATOR_VERSION, '>=' ) ) {
			return;
		}

		// Ensure bookings table has new Google Calendar sync columns
		self::ensure_bookings_table_exists( true );

		// Upgrade existing saved forms to v2.3.0 Section 5 attraction_guests rule (if previously pointing to include_attractions)
		if ( version_compare( $installed_ver, '2.3.0', '<' ) ) {
			global $wpdb;
			$forms_table = $wpdb->prefix . 'rhw_forms';
			if ( $wpdb->get_var( "SHOW TABLES LIKE '{$forms_table}'" ) === $forms_table ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$forms = $wpdb->get_results( "SELECT id, questions FROM {$forms_table}" );
				if ( $forms ) {
					foreach ( $forms as $f ) {
						$qs = json_decode( $f->questions, true );
						if ( is_array( $qs ) ) {
							$changed = false;
							foreach ( $qs as &$q_item ) {
								if ( isset( $q_item['id'] ) && 'attraction_guests' === $q_item['id'] ) {
									if ( ! isset( $q_item['conditional'] ) || ( isset( $q_item['conditional']['field'] ) && 'include_attractions' === $q_item['conditional']['field'] ) ) {
										$q_item['conditional'] = array(
											'enabled'  => true,
											'field'    => 'attraction_selection',
											'operator' => 'not_empty',
											'value'    => '',
										);
										$q_item['max']           = 1000;
										$q_item['default_value'] = 75;
										$q_item['help_text']     = 'Specify how many persons will be taking the tour or golfing (defaults to your Section 1 guest count or less; pricing updates per person).';
										$changed                 = true;
									}
								}
							}
							if ( $changed ) {
								// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
								$wpdb->update(
									$forms_table,
									array( 'questions' => wp_json_encode( $qs ) ),
									array( 'id' => $f->id ),
									array( '%s' ),
									array( '%d' )
								);
							}
						}
					}
				}
			}
		}

		update_option( 'rhw_calculator_version', RHW_CALCULATOR_VERSION );
	}
}
