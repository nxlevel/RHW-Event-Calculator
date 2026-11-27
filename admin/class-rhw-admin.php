<?php
/**
 * Admin Panel & Controller.
 *
 * @package RoseHallWeddings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RHW_Admin {

	/**
	 * Register admin hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_admin_menus' ) );
		add_action( 'admin_init', array( $this, 'handle_gcal_oauth_callback' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_post_rhw_save_form', array( $this, 'handle_save_form' ) );
		add_action( 'admin_post_rhw_delete_form', array( $this, 'handle_delete_form' ) );
		add_action( 'admin_post_rhw_duplicate_form', array( $this, 'handle_duplicate_form' ) );
		add_action( 'admin_post_rhw_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_rhw_update_submission_status', array( $this, 'handle_update_submission_status' ) );
		add_action( 'admin_post_rhw_delete_submission', array( $this, 'handle_delete_submission' ) );
		add_action( 'admin_post_rhw_block_slot', array( $this, 'handle_block_slot' ) );
		add_action( 'admin_post_rhw_delete_booking', array( $this, 'handle_delete_booking' ) );
		add_action( 'admin_post_rhw_save_calendar_schedule', array( $this, 'handle_save_calendar_schedule' ) );
		add_action( 'admin_post_rhw_save_gcal_settings', array( $this, 'handle_save_gcal_settings' ) );
		add_action( 'admin_post_rhw_disconnect_gcal', array( $this, 'handle_disconnect_gcal' ) );

		// Admin Calendar interactive AJAX
		add_action( 'wp_ajax_rhw_admin_get_month_data', array( $this, 'ajax_admin_get_month_data' ) );
		add_action( 'wp_ajax_rhw_admin_get_day_slots', array( $this, 'ajax_admin_get_day_slots' ) );
		add_action( 'wp_ajax_rhw_admin_toggle_block', array( $this, 'ajax_admin_toggle_block' ) );
	}

	/**
	 * Register menu pages.
	 */
	public function register_admin_menus() {
		add_menu_page(
			__( 'Rose Hall Calculator', 'rhw-calculator' ),
			__( 'Event Calculator', 'rhw-calculator' ),
			'manage_options',
			'rhw-calculator',
			array( $this, 'render_forms_list_page' ),
			'dashicons-calculator',
			30
		);

		add_submenu_page(
			'rhw-calculator',
			__( 'All Forms', 'rhw-calculator' ),
			__( 'All Forms', 'rhw-calculator' ),
			'manage_options',
			'rhw-calculator',
			array( $this, 'render_forms_list_page' )
		);

		add_submenu_page(
			'rhw-calculator',
			__( 'Form Builder', 'rhw-calculator' ),
			__( 'Add New Form', 'rhw-calculator' ),
			'manage_options',
			'rhw-calculator-builder',
			array( $this, 'render_builder_page' )
		);

		add_submenu_page(
			'rhw-calculator',
			__( 'Submissions & Leads', 'rhw-calculator' ),
			__( 'Submissions / Leads', 'rhw-calculator' ),
			'manage_options',
			'rhw-calculator-submissions',
			array( $this, 'render_submissions_page' )
		);

		add_submenu_page(
			'rhw-calculator',
			__( 'Consultations Calendar', 'rhw-calculator' ),
			__( 'Calendar & Bookings', 'rhw-calculator' ),
			'manage_options',
			'rhw-calculator-calendar',
			array( $this, 'render_calendar_page' )
		);

		add_submenu_page(
			'rhw-calculator',
			__( 'Calculator Settings', 'rhw-calculator' ),
			__( 'Settings', 'rhw-calculator' ),
			'manage_options',
			'rhw-calculator-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin scripts & styles.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'rhw-calculator' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'rhw-admin-css',
			RHW_CALCULATOR_PLUGIN_URL . 'admin/css/rhw-admin.css',
			array(),
			RHW_CALCULATOR_VERSION
		);

		wp_enqueue_script(
			'rhw-admin-js',
			RHW_CALCULATOR_PLUGIN_URL . 'admin/js/rhw-admin.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			RHW_CALCULATOR_VERSION,
			true
		);

		wp_localize_script(
			'rhw-admin-js',
			'rhw_admin_vars',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'rhw_admin_nonce' ),
				'i18n'     => array(
					'confirm_delete' => esc_html__( 'Are you sure you want to delete this item? This action cannot be undone.', 'rhw-calculator' ),
					'copied'         => esc_html__( 'Copied to clipboard!', 'rhw-calculator' ),
				),
			)
		);
	}

	/**
	 * Render Forms List Page.
	 */
	public function render_forms_list_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'rhw-calculator' ) );
		}
		include RHW_CALCULATOR_PLUGIN_DIR . 'admin/views/forms-list.php';
	}

	/**
	 * Render Form Builder Page.
	 */
	public function render_builder_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'rhw-calculator' ) );
		}
		include RHW_CALCULATOR_PLUGIN_DIR . 'admin/views/form-builder.php';
	}

	/**
	 * Render Submissions / Leads Page.
	 */
	public function render_submissions_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'rhw-calculator' ) );
		}

		$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
		if ( 'view' === $action ) {
			include RHW_CALCULATOR_PLUGIN_DIR . 'admin/views/submission-detail.php';
		} else {
			include RHW_CALCULATOR_PLUGIN_DIR . 'admin/views/submissions-list.php';
		}
	}

	/**
	 * Render Settings Page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'rhw-calculator' ) );
		}
		include RHW_CALCULATOR_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/**
	 * Handle Save Form POST action.
	 */
	public function handle_save_form() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'rhw-calculator' ) );
		}

		check_admin_referer( 'rhw_save_form_nonce', 'rhw_nonce' );

		$form_id = isset( $_POST['form_id'] ) ? intval( $_POST['form_id'] ) : 0;
		$title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : 'Untitled Calculator';
		$desc    = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$status  = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'published';

		// Parse JSON structures
		$sections_raw   = isset( $_POST['sections_json'] ) ? wp_unslash( $_POST['sections_json'] ) : '[]';
		$questions_raw  = isset( $_POST['questions_json'] ) ? wp_unslash( $_POST['questions_json'] ) : '[]';
		$pricing_raw    = isset( $_POST['pricing_settings_json'] ) ? wp_unslash( $_POST['pricing_settings_json'] ) : '{}';
		$notif_raw      = isset( $_POST['notification_settings_json'] ) ? wp_unslash( $_POST['notification_settings_json'] ) : '{}';
		$appearance_raw = isset( $_POST['appearance_settings_json'] ) ? wp_unslash( $_POST['appearance_settings_json'] ) : '{}';

		$sections   = json_decode( $sections_raw, true ) ?: array();
		$questions  = json_decode( $questions_raw, true ) ?: array();
		$pricing    = json_decode( $pricing_raw, true ) ?: array();
		$notif      = json_decode( $notif_raw, true ) ?: array();
		$appearance = json_decode( $appearance_raw, true ) ?: array();

		// Sanitize Custom CSS if provided
		if ( isset( $appearance['custom_css'] ) ) {
			$appearance['custom_css'] = wp_strip_all_tags( $appearance['custom_css'] );
		}

		$payload = array(
			'id'                    => $form_id,
			'title'                 => $title,
			'description'           => $desc,
			'status'                => $status,
			'sections'              => $sections,
			'questions'             => $questions,
			'pricing_settings'      => $pricing,
			'notification_settings' => $notif,
			'appearance_settings'   => $appearance,
		);

		$saved_id = RHW_Form_Manager::save_form( $payload );

		wp_safe_redirect( admin_url( 'admin.php?page=rhw-calculator-builder&id=' . $saved_id . '&updated=1' ) );
		exit;
	}

	/**
	 * Handle Delete Form POST action.
	 */
	public function handle_delete_form() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'rhw-calculator' ) );
		}

		$form_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
		check_admin_referer( 'rhw_delete_form_' . $form_id );

		RHW_Form_Manager::delete_form( $form_id );

		wp_safe_redirect( admin_url( 'admin.php?page=rhw-calculator&deleted=1' ) );
		exit;
	}

	/**
	 * Handle Duplicate Form POST action.
	 */
	public function handle_duplicate_form() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'rhw-calculator' ) );
		}

		$form_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
		check_admin_referer( 'rhw_duplicate_form_' . $form_id );

		$new_id = RHW_Form_Manager::duplicate_form( $form_id );

		wp_safe_redirect( admin_url( 'admin.php?page=rhw-calculator-builder&id=' . $new_id . '&duplicated=1' ) );
		exit;
	}

	/**
	 * Handle Save Settings POST action.
	 */
	public function handle_save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'rhw-calculator' ) );
		}

		check_admin_referer( 'rhw_save_settings_nonce', 'rhw_nonce' );

		if ( isset( $_POST['rhw_default_form_id'] ) ) {
			update_option( 'rhw_default_form_id', intval( $_POST['rhw_default_form_id'] ) );
		}
		if ( isset( $_POST['rhw_currency_symbol'] ) ) {
			update_option( 'rhw_currency_symbol', sanitize_text_field( wp_unslash( $_POST['rhw_currency_symbol'] ) ) );
		}
		if ( isset( $_POST['rhw_default_tax_rate'] ) ) {
			update_option( 'rhw_default_tax_rate', floatval( $_POST['rhw_default_tax_rate'] ) );
		}
		if ( isset( $_POST['rhw_default_service_charge'] ) ) {
			update_option( 'rhw_default_service_charge', floatval( $_POST['rhw_default_service_charge'] ) );
		}
		if ( isset( $_POST['rhw_default_notification_emails'] ) ) {
			update_option( 'rhw_default_notification_emails', sanitize_text_field( wp_unslash( $_POST['rhw_default_notification_emails'] ) ) );
		}
		$delete_data = isset( $_POST['rhw_delete_data_on_uninstall'] ) ? '1' : '0';
		update_option( 'rhw_delete_data_on_uninstall', $delete_data );

		wp_safe_redirect( admin_url( 'admin.php?page=rhw-calculator-settings&updated=1' ) );
		exit;
	}

	/**
	 * Update Lead Status (e.g. New -> In Review -> Consultation Booked).
	 */
	public function handle_update_submission_status() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'rhw-calculator' ) );
		}

		check_admin_referer( 'rhw_update_submission_status' );

		$id     = isset( $_POST['submission_id'] ) ? intval( $_POST['submission_id'] ) : 0;
		$status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'new';
		$notes  = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';

		global $wpdb;
		$table = $wpdb->prefix . 'rhw_submissions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->update(
			$table,
			array(
				'status' => $status,
				'notes'  => $notes,
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		wp_safe_redirect( admin_url( 'admin.php?page=rhw-calculator-submissions&action=view&id=' . $id . '&status_updated=1' ) );
		exit;
	}

	/**
	 * Delete a single lead submission.
	 */
	public function handle_delete_submission() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'rhw-calculator' ) );
		}

		$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
		check_admin_referer( 'rhw_delete_submission_' . $id );

		global $wpdb;
		$table = $wpdb->prefix . 'rhw_submissions';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

		wp_safe_redirect( admin_url( 'admin.php?page=rhw-calculator-submissions&deleted=1' ) );
		exit;
	}

	/**
	 * Intercept Google OAuth2 callback on Calendar page before headers are sent.
	 */
	public function handle_gcal_oauth_callback() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( empty( $_GET['page'] ) || 'rhw-calculator-calendar' !== $_GET['page'] ) {
			return;
		}
		if ( empty( $_GET['rhw_gcal_action'] ) || 'oauth_callback' !== $_GET['rhw_gcal_action'] ) {
			return;
		}

		if ( ! empty( $_GET['error'] ) ) {
			$err_msg = sanitize_text_field( wp_unslash( $_GET['error'] ) );
			wp_safe_redirect( admin_url( 'admin.php?page=rhw-calculator-calendar&tab=google_sync&gcal_error=' . rawurlencode( $err_msg ) ) );
			exit;
		}

		$code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';

		$res = RHW_Google_Calendar::handle_oauth_callback( $code, $state );
		if ( is_wp_error( $res ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rhw-calculator-calendar&tab=google_sync&gcal_error=' . rawurlencode( $res->get_error_message() ) ) );
			exit;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=rhw-calculator-calendar&tab=google_sync&gcal_connected=1' ) );
		exit;
	}

	/**
	 * Render Consultations Calendar page.
	 */
	public function render_calendar_page() {
		RHW_Activator::ensure_bookings_table_exists();
		include RHW_CALCULATOR_PLUGIN_DIR . 'admin/views/calendar.php';
	}

	/**
	 * Handle admin manually blocking a date/time slot.
	 */
	public function handle_block_slot() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'rhw-calculator' ) );
		}

		check_admin_referer( 'rhw_block_time_slot' );

		$date  = isset( $_POST['block_date'] ) ? sanitize_text_field( wp_unslash( $_POST['block_date'] ) ) : '';
		$time  = isset( $_POST['block_time'] ) ? sanitize_text_field( wp_unslash( $_POST['block_time'] ) ) : '';
		$notes = isset( $_POST['block_notes'] ) ? sanitize_text_field( wp_unslash( $_POST['block_notes'] ) ) : 'Blocked by Admin';

		if ( empty( $date ) || empty( $time ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rhw-calculator-calendar&error=missing_fields' ) );
			exit;
		}

		RHW_Calendar_Manager::block_slot( $date, $time, $notes );

		wp_safe_redirect( admin_url( 'admin.php?page=rhw-calculator-calendar&blocked=1' ) );
		exit;
	}

	/**
	 * Handle admin deleting/unblocking a booking (and synced Google Calendar event).
	 */
	public function handle_delete_booking() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'rhw-calculator' ) );
		}

		$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
		check_admin_referer( 'rhw_delete_booking_' . $id );

		RHW_Calendar_Manager::delete_booking( $id );

		wp_safe_redirect( admin_url( 'admin.php?page=rhw-calculator-calendar&unblocked=1' ) );
		exit;
	}

	/**
	 * Save Weekly Schedule & Time Interval settings.
	 */
	public function handle_save_calendar_schedule() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'rhw-calculator' ) );
		}

		check_admin_referer( 'rhw_save_calendar_schedule' );

		RHW_Calendar_Manager::save_settings( $_POST );

		wp_safe_redirect( admin_url( 'admin.php?page=rhw-calculator-calendar&tab=schedule&schedule_saved=1' ) );
		exit;
	}

	/**
	 * Save Google Calendar Account Sync settings (and optionally trigger OAuth flow).
	 */
	public function handle_save_gcal_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'rhw-calculator' ) );
		}

		check_admin_referer( 'rhw_save_gcal_settings' );

		RHW_Google_Calendar::save_settings( $_POST );

		if ( ! empty( $_POST['connect_google_oauth'] ) ) {
			$auth_url = RHW_Google_Calendar::get_oauth_authorize_url();
			if ( ! empty( $auth_url ) ) {
				// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
				wp_redirect( $auth_url );
				exit;
			}
			wp_safe_redirect( admin_url( 'admin.php?page=rhw-calculator-calendar&tab=google_sync&gcal_error=' . rawurlencode( __( 'Please enter your Google Client ID and Client Secret before connecting.', 'rhw-calculator' ) ) ) );
			exit;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=rhw-calculator-calendar&tab=google_sync&gcal_saved=1' ) );
		exit;
	}

	/**
	 * Disconnect Google OAuth account.
	 */
	public function handle_disconnect_gcal() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'rhw-calculator' ) );
		}

		check_admin_referer( 'rhw_disconnect_gcal' );
		RHW_Google_Calendar::disconnect();

		wp_safe_redirect( admin_url( 'admin.php?page=rhw-calculator-calendar&tab=google_sync&gcal_disconnected=1' ) );
		exit;
	}

	/**
	 * AJAX: Return Admin Month Grid overview data.
	 */
	public function ajax_admin_get_month_data() {
		check_ajax_referer( 'rhw_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$year  = isset( $_GET['year'] ) ? intval( $_GET['year'] ) : (int) date( 'Y' );
		$month = isset( $_GET['month'] ) ? intval( $_GET['month'] ) : (int) date( 'n' );

		$data = RHW_Calendar_Manager::get_month_availability( $year, $month, true );
		wp_send_json_success( $data );
	}

	/**
	 * AJAX: Return Admin Day Inspector slots.
	 */
	public function ajax_admin_get_day_slots() {
		check_ajax_referer( 'rhw_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$date = isset( $_GET['date'] ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : '';
		$data = RHW_Calendar_Manager::get_day_slots( $date, true );
		wp_send_json_success( $data );
	}

	/**
	 * AJAX: Toggle block/unblock for a specific slot or entire day directly from the Admin Calendar.
	 */
	public function ajax_admin_toggle_block() {
		check_ajax_referer( 'rhw_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$op         = isset( $_POST['op'] ) ? sanitize_text_field( wp_unslash( $_POST['op'] ) ) : '';
		$date       = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		$time       = isset( $_POST['time'] ) ? sanitize_text_field( wp_unslash( $_POST['time'] ) ) : '';
		$booking_id = isset( $_POST['booking_id'] ) ? intval( $_POST['booking_id'] ) : 0;
		$notes      = isset( $_POST['notes'] ) ? sanitize_text_field( wp_unslash( $_POST['notes'] ) ) : 'Blocked by Admin';

		if ( 'unblock' === $op && $booking_id > 0 ) {
			RHW_Calendar_Manager::delete_booking( $booking_id );
		} elseif ( 'block_slot' === $op && ! empty( $date ) && ! empty( $time ) ) {
			RHW_Calendar_Manager::block_slot( $date, $time, $notes );
		} elseif ( 'block_day' === $op && ! empty( $date ) ) {
			RHW_Calendar_Manager::block_slot( $date, 'all_day', $notes );
		} else {
			wp_send_json_error( array( 'message' => 'Invalid action or parameters.' ) );
		}

		$day_data = RHW_Calendar_Manager::get_day_slots( $date, true );
		$ts       = strtotime( $date );
		$month    = RHW_Calendar_Manager::get_month_availability( (int) date( 'Y', $ts ), (int) date( 'n', $ts ), true );

		wp_send_json_success(
			array(
				'day'   => $day_data,
				'month' => $month,
			)
		);
	}
}
