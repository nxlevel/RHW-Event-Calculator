<?php
/**
 * Frontend Submission Handler.
 *
 * Processes lead submissions, validates input on server, handles image uploads,
 * recalculates estimate, prevents duplicate submissions, and dispatches notifications.
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
		if ( isset( $_POST['answers'] ) && is_array( $_POST['answers'] ) ) {
			$raw_answers = (array) $_POST['answers'];
		} else {
			$raw_answers = array();
			$system_keys = array( 'action', 'nonce', 'form_id', 'rhw_website_hp', 'answers_other', 'answers_files' );
			foreach ( $_POST as $k => $v ) {
				if ( ! in_array( $k, $system_keys, true ) && 0 !== strpos( $k, '_' ) ) {
					$raw_answers[ $k ] = $v;
				}
			}
		}
		if ( isset( $_POST['answers'] ) && is_array( $_POST['answers'] ) ) {
			foreach ( $_POST['answers'] as $ak => $av ) {
				$raw_answers[ $ak ] = $av;
			}
		}
		$answers = $this->sanitize_answers( $raw_answers );

		// 4. Handle File Uploads (Décor design, bouquet inspiration)
		$uploaded_files = $this->handle_file_uploads();
		foreach ( $uploaded_files as $file_qid => $file_url ) {
			$answers[ $file_qid ] = esc_url_raw( $file_url );
		}

		// Handle "Other" custom text overrides
		if ( isset( $_POST['answers_other'] ) && is_array( $_POST['answers_other'] ) ) {
			foreach ( $_POST['answers_other'] as $other_key => $other_val ) {
				$clean_key = sanitize_key( $other_key );
				$clean_val = sanitize_text_field( wp_unslash( $other_val ) );
				if ( ! empty( $clean_val ) && isset( $answers[ $clean_key ] ) && 'other' === $answers[ $clean_key ] ) {
					$answers[ $clean_key ] = 'Other: ' . $clean_val;
				}
			}
		}

		// 5. Server-Side Required Field Validation
		$errors = $this->validate_required_fields( $form, $answers );
		if ( ! empty( $errors ) ) {
			$missing = array();
			foreach ( $errors as $msg ) {
				$missing[] = rtrim( $msg, '.' );
			}
			wp_send_json_error( array(
				'message' => sprintf(
					/* translators: %s: Missing fields */
					esc_html__( 'Please complete all required fields: %s', 'rhw-calculator' ),
					implode( ', ', $missing )
				),
				'errors'  => $errors,
			) );
		}

		// Extract Core Lead Fields
		$customer_name   = isset( $answers['lead_name'] ) ? sanitize_text_field( $answers['lead_name'] ) : '';
		$customer_email  = isset( $answers['lead_email'] ) ? sanitize_email( $answers['lead_email'] ) : '';
		$customer_phone  = isset( $answers['lead_phone'] ) ? sanitize_text_field( $answers['lead_phone'] ) : '';
		$customer_res    = isset( $answers['lead_country'] ) ? sanitize_text_field( $answers['lead_country'] ) : '';
		$raw_event_date  = isset( $answers['event_date'] ) ? sanitize_text_field( $answers['event_date'] ) : '';
		$raw_event_time  = isset( $answers['event_time'] ) ? sanitize_text_field( $answers['event_time'] ) : '';
		if ( ! empty( $raw_event_time ) && false === stripos( $raw_event_date, ' at ' ) ) {
			$event_date_comb = $raw_event_date . ' at ' . $raw_event_time;
		} else {
			$event_date_comb = $raw_event_date;
		}
		$event_type      = isset( $answers['event_type'] ) ? sanitize_text_field( $answers['event_type'] ) : '';
		$venue_selection = isset( $answers['venue_selection'] ) ? sanitize_text_field( $answers['venue_selection'] ) : '';

		if ( empty( $customer_name ) || empty( $customer_email ) || ! is_email( $customer_email ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'A valid full name and email address are required.', 'rhw-calculator' ) ) );
		}

		// 6. Anti-Duplicate Submission Lock (30-second window for same email & form)
		$ip_address      = sanitize_text_field( wp_unslash( isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '' ) );
		$duplicate_hash  = 'rhw_sub_' . md5( $customer_email . '_' . $form_id . '_' . $ip_address );
		if ( get_transient( $duplicate_hash ) ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'You recently submitted a quote request. Our specialists are already processing your details!', 'rhw-calculator' ),
			) );
		}
		set_transient( $duplicate_hash, true, 30 );

		// 7. Authoritative Server-Side Calculation (Never trust client prices)
		$calc = RHW_Pricing_Engine::calculate( $form, $answers );

		// 8. Store Submission in Database
		global $wpdb;
		$submissions_table = $wpdb->prefix . 'rhw_submissions';

		$submission_data = array(
			'form_id'              => $form_id,
			'customer_name'        => $customer_name,
			'customer_email'       => $customer_email,
			'customer_phone'       => $customer_phone,
			'country_of_residence' => $customer_res,
			'event_date'           => $event_date_comb,
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

		// 9. Send Notification Emails
		RHW_Email::send_admin_notification( $submission_id, $submission_data, $form, $calc );
		RHW_Email::send_customer_confirmation( $submission_id, $submission_data, $form, $calc );

		// 10. Return Success Response
		wp_send_json_success( array(
			'submission_id'   => $submission_id,
			'customer_name'   => $customer_name,
			'customer_email'  => $customer_email,
			'event_date'      => $event_date_comb,
			'guest_count'     => $calc['guest_count'],
			'grand_total'     => $calc['formatted']['grand_total'],
			'message'         => esc_html__( 'Thank you! Your wedding consultation request and estimated cost have been submitted. Our Destination Relationship Specialists will contact you within 3 to 5 business days.', 'rhw-calculator' ),
		) );
	}

	/**
	 * Handle file uploads securely.
	 */
	private function handle_file_uploads() {
		$uploaded = array();

		if ( empty( $_FILES ) ) {
			return $uploaded;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		// Support $_FILES['answers_files'] or direct keys
		$files_source = isset( $_FILES['answers_files'] ) ? $_FILES['answers_files'] : $_FILES;

		foreach ( $files_source['name'] as $field_key => $filename ) {
			if ( empty( $filename ) ) {
				continue;
			}

			$file = array(
				'name'     => $files_source['name'][ $field_key ],
				'type'     => $files_source['type'][ $field_key ],
				'tmp_name' => $files_source['tmp_name'][ $field_key ],
				'error'    => $files_source['error'][ $field_key ],
				'size'     => $files_source['size'][ $field_key ],
			);

			// Check file size (10MB max)
			if ( $file['size'] > 10 * 1024 * 1024 ) {
				continue;
			}

			// Validate MIME type
			$file_type = wp_check_filetype( $file['name'] );
			$allowed   = array( 'jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf' );
			if ( ! in_array( strtolower( $file_type['ext'] ), $allowed, true ) ) {
				continue;
			}

			$upload_overrides = array( 'test_form' => false );
			$movefile         = wp_handle_upload( $file, $upload_overrides );

			if ( $movefile && empty( $movefile['error'] ) ) {
				$uploaded[ sanitize_key( $field_key ) ] = $movefile['url'];
			}
		}

		return $uploaded;
	}

	/**
	 * Validate required fields on server.
	 */
	private function validate_required_fields( $form, $answers ) {
		$errors       = array();
		$questions    = isset( $form['questions'] ) ? $form['questions'] : array();
		$service_type = isset( $answers['service_type'] ) ? $answers['service_type'] : '';
		$is_venue_only = ( 'venue_only' === $service_type || false !== stripos( (string) $service_type, 'Venue Only' ) );

		foreach ( $questions as $q ) {
			if ( empty( $q['required'] ) ) {
				continue;
			}

			// Questions requiring full service are skipped/not required when venue-only is selected
			if ( $is_venue_only && ! empty( $q['requires_full_service'] ) ) {
				continue;
			}

			if ( ! RHW_Pricing_Engine::is_question_visible( $q, $answers ) ) {
				continue;
			}

			$qid = isset( $q['id'] ) ? $q['id'] : '';
			$val = isset( $answers[ $qid ] ) ? $answers[ $qid ] : null;

			// If empty, also check if answers has {$qid}_date (for datetime inputs)
			if ( ( null === $val || '' === $val || ( is_array( $val ) && empty( $val ) ) ) && ! empty( $answers[ $qid . '_date' ] ) ) {
				$val = $answers[ $qid . '_date' ];
			}

			if ( null === $val || '' === $val || ( is_array( $val ) && empty( $val ) ) ) {
				$label = isset( $q['label'] ) ? $q['label'] : $qid;
				$errors[ $qid ] = sprintf(
					/* translators: %s: Field label */
					esc_html__( '%s is required.', 'rhw-calculator' ),
					$label
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
