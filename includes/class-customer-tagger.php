<?php
/**
 * Core plugin class.
 *
 * @package Customer_Tagger
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CUSTOMER_TAGGER_PLUGIN_DIR . 'includes/class-customer-tagger-activator.php';
require_once CUSTOMER_TAGGER_PLUGIN_DIR . 'includes/class-customer-tagger-deactivator.php';

/**
 * Main Customer Tagger plugin orchestrator.
 */
class Customer_Tagger {

	/**
	 * Register activation and deactivation hooks, then wire components.
	 */
	public function run(): void {
		register_activation_hook( CUSTOMER_TAGGER_PLUGIN_FILE, array( 'Customer_Tagger_Activator', 'activate' ) );
		register_deactivation_hook( CUSTOMER_TAGGER_PLUGIN_FILE, array( 'Customer_Tagger_Deactivator', 'deactivate' ) );

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'plugins_loaded', array( $this, 'boot' ), 20 );
	}

	/**
	 * Load plugin translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'customer-tagger',
			false,
			dirname( CUSTOMER_TAGGER_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Bootstrap CT plugin services.
	 */
	public function boot(): void {
		CT\Plugin::instance()->init();
	}
}
