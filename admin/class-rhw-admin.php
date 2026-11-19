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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_post_rhw_save_form', array( $this, 'handle_save_form' ) );
		add_action( 'admin_post_rhw_delete_form', array( $this, 'handle_delete_form' ) );
		add_action( 'admin_post_rhw_duplicate_form', array( $this, 'handle_duplicate_form' ) );
		add_action( 'admin_post_rhw_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_rhw_update_submission_status', array( $this, 'handle_update_submission_status' ) );
		add_action( 'admin_post_rhw_delete_submission', array( $this, 'handle_delete_submission' ) );
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
}
