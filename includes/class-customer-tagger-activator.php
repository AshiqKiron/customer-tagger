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

		require_once CUSTOMER_TAGGER_PLUGIN_DIR . 'includes/autoload.php';

		CT\Database\Schema::create_tables();

		CT\PostTypes\WorkflowPostType::register();
		CT\PostTypes\OrderTagTaxonomy::register();
		flush_rewrite_rules();

		$defaults = array(
			'global_delay_seconds' => 0,
			'log_retention_days'   => 90,
			'fluentcrm_sync'       => false,
			'default_webhook_url'  => '',
		);
		if ( false === get_option( 'ct_settings' ) ) {
			add_option( 'ct_settings', $defaults );
		}

		CT\Install\PrebuiltWorkflows::install();

		add_option( 'customer_tagger_version', CUSTOMER_TAGGER_VERSION );

		add_option( 'ct_onboarding_complete', false );

		set_transient( 'ct_activation_redirect', true, 30 );
	}
}
