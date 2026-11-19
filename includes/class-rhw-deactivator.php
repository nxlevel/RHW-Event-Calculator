<?php
/**
 * Fired during plugin deactivation.
 *
 * @package RoseHallWeddings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RHW_Deactivator {

	/**
	 * Deactivation routine.
	 */
	public static function deactivate() {
		// Clean up temporary transients if any
		delete_transient( 'rhw_temp_stats' );
	}
}
