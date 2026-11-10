<?php
/**
 * Fired during plugin deactivation.
 *
 * @package Customer_Tagger
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deactivation routines.
 */
class Customer_Tagger_Deactivator {

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate(): void {
		require_once CUSTOMER_TAGGER_PLUGIN_DIR . 'includes/autoload.php';
		CT\Scheduler\ActionSchedulerBridge::deactivate();
	}
}
