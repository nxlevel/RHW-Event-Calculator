<?php
/**
 * Frontend Public Assets & Setup.
 *
 * @package RoseHallWeddings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RHW_Public {

	/**
	 * Register hooks.
	 */
	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Register styles and scripts.
	 */
	public function register_assets() {
		wp_register_style(
			'rhw-calculator-css',
			RHW_CALCULATOR_PLUGIN_URL . 'public/css/rhw-calculator.css',
			array(),
			RHW_CALCULATOR_VERSION
		);

		wp_register_script(
			'rhw-calculator-js',
			RHW_CALCULATOR_PLUGIN_URL . 'public/js/rhw-calculator.js',
			array( 'jquery' ),
			RHW_CALCULATOR_VERSION,
			true
		);
	}
}
