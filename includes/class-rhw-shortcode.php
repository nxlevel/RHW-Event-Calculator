<?php
/**
 * Shortcode Handler.
 *
 * Implements [event_cost_calculator id="123"] to render the calculator on any page or post.
 *
 * @package RoseHallWeddings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RHW_Shortcode {

	/**
	 * Register shortcode.
	 */
	public function init() {
		add_shortcode( 'event_cost_calculator', array( $this, 'render_shortcode' ) );
		add_shortcode( 'rhw_event_calculator', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Render shortcode output.
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id' => 0,
			),
			$atts,
			'event_cost_calculator'
		);

		$form_id = intval( $atts['id'] );

		// Fallback to default form if no ID passed
		if ( empty( $form_id ) ) {
			$form_id = intval( get_option( 'rhw_default_form_id', 0 ) );
		}

		if ( empty( $form_id ) ) {
			global $wpdb;
			$table   = $wpdb->prefix . 'rhw_forms';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$form_id = (int) $wpdb->get_var( "SELECT id FROM {$table} WHERE status = 'published' ORDER BY id ASC LIMIT 1" );
		}

		$form = RHW_Form_Manager::get_form( $form_id );

		if ( ! $form ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<div class="rhw-error-notice">' . esc_html__( 'Rose Hall Event Calculator: Form not found. Please verify the shortcode ID or configure a form in WordPress Admin.', 'rhw-calculator' ) . '</div>';
			}
			return '';
		}

		// Enqueue scripts & styles
		wp_enqueue_style( 'rhw-calculator-css' );
		wp_enqueue_script( 'rhw-calculator-js' );

		$recaptcha_enabled    = get_option( 'rhw_recaptcha_enabled', '0' );
		$recaptcha_type       = get_option( 'rhw_recaptcha_type', 'v2_checkbox' );
		$recaptcha_site_key   = trim( (string) get_option( 'rhw_recaptcha_site_key', '' ) );
		$recaptcha_secret_key = trim( (string) get_option( 'rhw_recaptcha_secret_key', '' ) );
		$recaptcha_active     = ( '1' === (string) $recaptcha_enabled && '' !== $recaptcha_site_key && '' !== $recaptcha_secret_key );

		if ( $recaptcha_active ) {
			if ( 'v3_invisible' === $recaptcha_type ) {
				wp_enqueue_script( 'rhw-google-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $recaptcha_site_key ), array(), null, true );
			} else {
				wp_enqueue_script( 'rhw-google-recaptcha', 'https://www.google.com/recaptcha/api.js?onload=rhwOnRecaptchaLoad&render=explicit', array(), null, true );
			}
		}

		// Pass form configuration to JS
		$script_data = array(
			'ajax_url'     => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( 'rhw_calculator_nonce' ),
			'form_id'      => $form_id,
			'form_data'    => $form,
			'recaptcha'    => array(
				'enabled'  => $recaptcha_active,
				'type'     => $recaptcha_type,
				'site_key' => $recaptcha_active ? $recaptcha_site_key : '',
			),
			'i18n'         => array(
				'calculating'        => esc_html__( 'Updating estimate...', 'rhw-calculator' ),
				'submitting'         => esc_html__( 'Submitting your request...', 'rhw-calculator' ),
				'required_warning'   => esc_html__( 'Please complete all required fields in this step before proceeding.', 'rhw-calculator' ),
				'recaptcha_required' => esc_html__( 'Please complete the reCAPTCHA verification before submitting.', 'rhw-calculator' ),
				'success_title'      => esc_html__( 'Estimate Request Received!', 'rhw-calculator' ),
				'network_error'      => esc_html__( 'A network error occurred. Please try again.', 'rhw-calculator' ),
			),
		);
		wp_localize_script( 'rhw-calculator-js', 'rhw_calc_data_' . $form_id, $script_data );

		// Render template
		ob_start();
		$template_path = RHW_CALCULATOR_PLUGIN_DIR . 'public/views/calculator-template.php';
		if ( file_exists( $template_path ) ) {
			include $template_path;
		}
		return ob_get_clean();
	}
}
