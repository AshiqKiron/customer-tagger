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
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		// Placeholder: clear scheduled events or transient caches.
	}
}
