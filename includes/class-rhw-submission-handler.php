<?php
/**
 * Frontend Submission Handler.
 *
 * Processes lead submissions, validates input on server, recalculates estimate,
 * prevents duplicate submissions, and dispatches notifications.
 *
 * @package RoseHallWeddings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RHW_Submission_Handler {

	/**
	 * Register AJAX hooks.
	 */
	public function init() {
		add_action( 'wp_ajax_rhw_submit_quote', array( $this, 'handle_submission' ) );
		add_action( 'wp_ajax_nopriv_rhw_submit_quote', array( $this, 'handle_submission' ) );

		add_action( 'wp_ajax_rhw_calculate_quote', array( $this, 'handle_live_calculation' ) );
		add_action( 'wp_ajax_nopriv_rhw_calculate_quote', array( $this, 'handle_live_calculation' ) );
	}

	/**
	 * Handle live AJAX calculation if requested by frontend.
	 */
	public function handle_live_calculation() {
		check_ajax_referer( 'rhw_calculator_nonce', 'nonce' );

		$form_id = isset( $_POST['form_id'] ) ? intval( $_POST['form_id'] ) : 0;
		$form    = RHW_Form_Manager::get_form( $form_id );

		if ( ! $form ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Form not found.', 'rhw-calculator' ) ) );
		}

		$raw_answers = isset( $_POST['answers'] ) ? (array) $_POST['answers'] : array();
		$answers     = $this->sanitize_answers( $raw_answers );

		$calc = RHW_Pricing_Engine::calculate( $form, $answers );

		wp_send_json_success( array( 'calc' => $calc ) );
	}

	/**
	 * Handle full quote request submission.
	 */
	public function handle_submission() {
		check_ajax_referer( 'rhw_calculator_nonce', 'nonce' );

		// 1. Anti-Spam Honeypot Check
		if ( ! empty( $_POST['rhw_website_hp'] ) ) {
			// Silently reject bot submissions
			wp_send_json_success( array(
				'message' => esc_html__( 'Thank you. Your request has been received.', 'rhw-calculator' ),
			) );
		}

		// 2. Fetch Form Configuration
		$form_id = isset( $_POST['form_id'] ) ? intval( $_POST['form_id'] ) : 0;
		$form    = RHW_Form_Manager::get_form( $form_id );

		if ( ! $form ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Form configuration could not be loaded.', 'rhw-calculator' ) ) );
		}

		// 3. Extract & Sanitize Answers
		$raw_answers = isset( $_POST['answers'] ) ? (array) $_POST['answers'] : array();
		$answers     = $this->sanitize_answers( $raw_answers );

		// 4. Server-Side Required Field Validation
		$errors = $this->validate_required_fields( $form, $answers );
		if ( ! empty( $errors ) ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Please complete all required fields.', 'rhw-calculator' ),
				'errors'  => $errors,
			) );
		}

		// Extract Core Lead Fields
		$customer_name   = isset( $answers['lead_name'] ) ? sanitize_text_field( $answers['lead_name'] ) : '';
		$customer_email  = isset( $answers['lead_email'] ) ? sanitize_email( $answers['lead_email'] ) : '';
		$customer_phone  = isset( $answers['lead_phone'] ) ? sanitize_text_field( $answers['lead_phone'] ) : '';
		$customer_res    = isset( $answers['lead_country'] ) ? sanitize_text_field( $answers['lead_country'] ) : '';
		$event_date      = isset( $answers['event_date'] ) ? sanitize_text_field( $answers['event_date'] ) : '';
		$event_type      = isset( $answers['event_type'] ) ? sanitize_text_field( $answers['event_type'] ) : '';
		$venue_selection = isset( $answers['venue_selection'] ) ? sanitize_text_field( $answers['venue_selection'] ) : '';

		if ( empty( $customer_name ) || empty( $customer_email ) || ! is_email( $customer_email ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'A valid full name and email address are required.', 'rhw-calculator' ) ) );
		}

		// 5. Anti-Duplicate Submission Lock (30-second window for same email & form)
		$ip_address      = sanitize_text_field( wp_unslash( isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '' ) );
		$duplicate_hash  = 'rhw_sub_' . md5( $customer_email . '_' . $form_id . '_' . $ip_address );
		if ( get_transient( $duplicate_hash ) ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'You recently submitted a quote request. Our specialists are already processing your details!', 'rhw-calculator' ),
			) );
		}
		set_transient( $duplicate_hash, true, 30 ); // 30-second anti-duplicate guard

		// 6. Authoritative Server-Side Calculation (Never trust client prices)
		$calc = RHW_Pricing_Engine::calculate( $form, $answers );

		// 7. Store Submission in Database
		global $wpdb;
		$submissions_table = $wpdb->prefix . 'rhw_submissions';

		$submission_data = array(
			'form_id'              => $form_id,
			'customer_name'        => $customer_name,
			'customer_email'       => $customer_email,
			'customer_phone'       => $customer_phone,
			'country_of_residence' => $customer_res,
			'event_date'           => $event_date,
			'guest_count'          => $calc['guest_count'],
			'event_type'           => $event_type,
			'venue_name'           => $venue_selection,
			'subtotal'             => $calc['subtotal'],
			'tax_amount'           => $calc['tax_amount'],
			'service_charge'       => $calc['service_charge'],
			'grand_total'          => $calc['grand_total'],
			'answers'              => wp_json_encode( $answers ),
			'itemized_breakdown'   => wp_json_encode( $calc['line_items'] ),
			'status'               => 'new',
			'user_ip'              => $ip_address,
			'created_at'           => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			$submissions_table,
			$submission_data,
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to record your submission. Please try again.', 'rhw-calculator' ) ) );
		}

		$submission_id = $wpdb->insert_id;

		// 8. Send Notification Emails
		RHW_Email::send_admin_notification( $submission_id, $submission_data, $form, $calc );
		RHW_Email::send_customer_confirmation( $submission_id, $submission_data, $form, $calc );

		// 9. Return Success Response
		wp_send_json_success( array(
			'submission_id'   => $submission_id,
			'customer_name'   => $customer_name,
			'customer_email'  => $customer_email,
			'event_date'      => $event_date,
			'guest_count'     => $calc['guest_count'],
			'grand_total'     => $calc['formatted']['grand_total'],
			'message'         => esc_html__( 'Thank you! Your wedding consultation request and preliminary estimate have been submitted. Our Destination Relationship Specialists will contact you within 3 to 5 business days.', 'rhw-calculator' ),
		) );
	}

	/**
	 * Validate required fields on server.
	 */
	private function validate_required_fields( $form, $answers ) {
		$errors    = array();
		$questions = isset( $form['questions'] ) ? $form['questions'] : array();

		foreach ( $questions as $q ) {
			if ( empty( $q['required'] ) ) {
				continue;
			}

			// Check conditional visibility: if question is conditionally hidden, do not require it!
			if ( ! RHW_Pricing_Engine::is_question_visible( $q, $answers ) ) {
				continue;
			}

			$qid = isset( $q['id'] ) ? $q['id'] : '';
			$val = isset( $answers[ $qid ] ) ? $answers[ $qid ] : null;

			if ( null === $val || '' === $val || ( is_array( $val ) && empty( $val ) ) ) {
				$errors[ $qid ] = sprintf(
					/* translators: %s: Field label */
					esc_html__( '%s is required.', 'rhw-calculator' ),
					isset( $q['label'] ) ? $q['label'] : $qid
				);
			}
		}

		return $errors;
	}

	/**
	 * Deeply sanitize user-submitted answers.
	 */
	private function sanitize_answers( $raw ) {
		$sanitized = array();
		foreach ( $raw as $k => $v ) {
			$clean_key = sanitize_key( $k );
			if ( is_array( $v ) ) {
				$clean_arr = array();
				foreach ( $v as $sub_k => $sub_v ) {
					$clean_arr[ sanitize_key( $sub_k ) ] = sanitize_text_field( wp_unslash( $sub_v ) );
				}
				$sanitized[ $clean_key ] = $clean_arr;
			} elseif ( is_numeric( $v ) ) {
				$sanitized[ $clean_key ] = $v;
			} else {
				$sanitized[ $clean_key ] = sanitize_textarea_field( wp_unslash( $v ) );
			}
		}
		return $sanitized;
	}
}
