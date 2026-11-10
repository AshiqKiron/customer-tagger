<?php
/**
 * Plugin bootstrap.
 *
 * @package Customer_Tagger
 */

declare( strict_types=1 );

namespace CT;

use CT\Admin\AdminMenu;
use CT\Admin\Onboarding;
use CT\Admin\UserProfileTags;
use CT\Admin\UsersListColumn;
use CT\Integrations\EDDAdapter;
use CT\Integrations\GiveAdapter;
use CT\PostTypes\OrderTagTaxonomy;
use CT\PostTypes\WorkflowPostType;
use CT\Rest\RestBootstrap;
use CT\Scheduler\ActionSchedulerBridge;
use CT\Triggers\OrderTriggers;
use CT\Triggers\ProductTriggers;
use CT\Triggers\UserTriggers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin wiring.
 */
class Plugin {

	private static ?Plugin $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init(): void {
		if ( ! $this->woocommerce_active() ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		WorkflowPostType::init();
		OrderTagTaxonomy::init();
		ActionSchedulerBridge::init();
		OrderTriggers::init();
		UserTriggers::init();
		ProductTriggers::init();
		EDDAdapter::init();
		GiveAdapter::init();

		if ( is_admin() ) {
			Onboarding::init();
			AdminMenu::init();
			UserProfileTags::init();
			UsersListColumn::init();
		}

		add_action( 'rest_api_init', array( RestBootstrap::class, 'register_routes' ) );
		add_action( 'init', array( $this, 'register_cli' ) );
	}

	private function woocommerce_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	public function woocommerce_missing_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>';
		esc_html_e( 'Customer Tagger requires WooCommerce to be installed and active.', 'customer-tagger' );
		echo '</p></div>';
	}

	public function register_cli(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'ct', CLI\Commands::class );
		}
	}
}
