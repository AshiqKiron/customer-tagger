<?php
/**
 * Fired during plugin activation.
 *
 * @package Customer_Tagger
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Activation routines.
 */
class Customer_Tagger_Activator {

	/**
	 * Run on plugin activation.
	 */
	public static function activate(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		// Placeholder: options, custom tables, or capabilities.
		add_option( 'customer_tagger_version', CUSTOMER_TAGGER_VERSION );
	}
}
