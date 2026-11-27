<?php
/**
 * Plugin Name:       Rose Hall Weddings - Event Cost Calculator & Lead Capture
 * Plugin URI:        https://rosehall.com/weddings
 * Description:       Interactive real-time event cost calculator and lead capture system for Rose Hall Weddings & Events.
 * Version:           2.5.1
 * Author:            Rose Hall Developments Ltd.
 * Author URI:        https://rosehall.com
 * Text Domain:       rhw-calculator
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'RHW_CALCULATOR_VERSION', '2.5.1' );
define( 'RHW_CALCULATOR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RHW_CALCULATOR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RHW_CALCULATOR_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoload / include required class files.
 */
require_once RHW_CALCULATOR_PLUGIN_DIR . 'includes/class-rhw-activator.php';
require_once RHW_CALCULATOR_PLUGIN_DIR . 'includes/class-rhw-deactivator.php';
require_once RHW_CALCULATOR_PLUGIN_DIR . 'includes/class-rhw-pricing-engine.php';
require_once RHW_CALCULATOR_PLUGIN_DIR . 'includes/class-rhw-form-manager.php';
require_once RHW_CALCULATOR_PLUGIN_DIR . 'includes/class-rhw-google-calendar.php';
require_once RHW_CALCULATOR_PLUGIN_DIR . 'includes/class-rhw-calendar-manager.php';
require_once RHW_CALCULATOR_PLUGIN_DIR . 'includes/class-rhw-pdf-generator.php';
require_once RHW_CALCULATOR_PLUGIN_DIR . 'includes/class-rhw-email.php';
require_once RHW_CALCULATOR_PLUGIN_DIR . 'includes/class-rhw-submission-handler.php';
require_once RHW_CALCULATOR_PLUGIN_DIR . 'includes/class-rhw-exporter.php';
require_once RHW_CALCULATOR_PLUGIN_DIR . 'includes/class-rhw-shortcode.php';

if ( is_admin() ) {
	require_once RHW_CALCULATOR_PLUGIN_DIR . 'admin/class-rhw-admin.php';
}

require_once RHW_CALCULATOR_PLUGIN_DIR . 'public/class-rhw-public.php';

/**
 * Plugin activation hook.
 */
function rhw_activate_calculator() {
	RHW_Activator::activate();
}
register_activation_hook( __FILE__, 'rhw_activate_calculator' );

/**
 * Plugin deactivation hook.
 */
function rhw_deactivate_calculator() {
	RHW_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'rhw_deactivate_calculator' );

/**
 * Initialize the plugin execution.
 */
function rhw_init_calculator() {
	RHW_Activator::maybe_upgrade();

	// Initialize public side
	$public = new RHW_Public();
	$public->init();

	// Initialize shortcode
	$shortcode = new RHW_Shortcode();
	$shortcode->init();

	// Initialize submission handler
	$submissions = new RHW_Submission_Handler();
	$submissions->init();

	// Initialize admin if in admin panel
	if ( is_admin() ) {
		$admin = new RHW_Admin();
		$admin->init();

		$exporter = new RHW_Exporter();
		$exporter->init();
	}
}
add_action( 'plugins_loaded', 'rhw_init_calculator' );
