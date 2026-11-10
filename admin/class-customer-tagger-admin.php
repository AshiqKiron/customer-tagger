<?php
/**
 * Admin-specific functionality.
 *
 * @package Customer_Tagger
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin hooks and screens.
 */
class Customer_Tagger_Admin {

	/**
	 * Register admin hooks.
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add admin menu page.
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'Customer Tagger', 'customer-tagger' ),
			__( 'Customer Tagger', 'customer-tagger' ),
			'manage_woocommerce',
			'customer-tagger',
			array( $this, 'render_settings_page' ),
			'dashicons-tag',
			56
		);
	}

	/**
	 * Enqueue admin styles and scripts.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'toplevel_page_customer-tagger' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'customer-tagger-admin',
			CUSTOMER_TAGGER_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			CUSTOMER_TAGGER_VERSION
		);

		wp_enqueue_script(
			'customer-tagger-admin',
			CUSTOMER_TAGGER_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			CUSTOMER_TAGGER_VERSION,
			true
		);
	}

	/**
	 * Render main admin page.
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'customer-tagger' ) );
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p><?php esc_html_e( 'Customer Tagger is active. Build your tagging features here.', 'customer-tagger' ); ?></p>
		</div>
		<?php
	}
}
