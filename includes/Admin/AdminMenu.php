<?php
/**
 * Admin menus and screens.
 *
 * @package Customer_Tagger
 */

declare( strict_types=1 );

namespace CT\Admin;

use CT\License\Limits;
use CT\Storage\LogRepository;
use CT\Storage\TagRepository;
use CT\Storage\WorkflowRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce admin UI.
 */
class AdminMenu {

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'save_settings' ) );
	}

	public static function register_menus(): void {
		add_menu_page(
			__( 'Customer Tagger', 'customer-tagger' ),
			__( 'Customer Tagger', 'customer-tagger' ),
			'manage_woocommerce',
			'customer-tagger',
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-tag',
			56
		);

		add_submenu_page( 'customer-tagger', __( 'Workflows', 'customer-tagger' ), __( 'Workflows', 'customer-tagger' ), 'manage_woocommerce', 'customer-tagger', array( __CLASS__, 'render_dashboard' ) );
		add_submenu_page( 'customer-tagger', __( 'Edit Workflow', 'customer-tagger' ), __( 'Add Workflow', 'customer-tagger' ), 'manage_woocommerce', 'customer-tagger-workflow', array( __CLASS__, 'render_workflow_editor' ) );
		add_submenu_page( 'customer-tagger', __( 'Tags', 'customer-tagger' ), __( 'Tags', 'customer-tagger' ), 'manage_woocommerce', 'customer-tagger-tags', array( __CLASS__, 'render_tags' ) );
		add_submenu_page( 'customer-tagger', __( 'Activity Log', 'customer-tagger' ), __( 'Activity Log', 'customer-tagger' ), 'manage_woocommerce', 'customer-tagger-logs', array( __CLASS__, 'render_logs' ) );
		add_submenu_page( 'customer-tagger', __( 'Tools', 'customer-tagger' ), __( 'Tools', 'customer-tagger' ), 'manage_woocommerce', 'customer-tagger-tools', array( __CLASS__, 'render_tools' ) );
		add_submenu_page( 'customer-tagger', __( 'Settings', 'customer-tagger' ), __( 'Settings', 'customer-tagger' ), 'manage_woocommerce', 'customer-tagger-settings', array( __CLASS__, 'render_settings' ) );
	}

	/**
	 * @param string $hook_suffix Hook suffix.
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		if ( false === strpos( $hook_suffix, 'customer-tagger' ) ) {
			return;
		}

		wp_enqueue_style(
			'customer-tagger-admin',
			CUSTOMER_TAGGER_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			CUSTOMER_TAGGER_VERSION
		);

		wp_enqueue_script( 'wp-api-fetch' );

		wp_enqueue_script(
			'customer-tagger-admin',
			CUSTOMER_TAGGER_PLUGIN_URL . 'assets/js/admin.js',
			array( 'wp-api-fetch', 'wp-i18n' ),
			CUSTOMER_TAGGER_VERSION,
			true
		);

		wp_localize_script(
			'customer-tagger-admin',
			'ctAdmin',
			array(
				'root'  => esc_url_raw( rest_url( 'ct/v1' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	public static function save_settings(): void {
		if ( ! isset( $_POST['ct_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ct_settings_nonce'] ) ), 'ct_save_settings' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$settings = array(
			'global_delay_seconds' => isset( $_POST['global_delay_seconds'] ) ? (int) $_POST['global_delay_seconds'] : 0,
			'log_retention_days'   => isset( $_POST['log_retention_days'] ) ? (int) $_POST['log_retention_days'] : 90,
			'fluentcrm_sync'       => ! empty( $_POST['fluentcrm_sync'] ),
			'default_webhook_url'  => isset( $_POST['default_webhook_url'] ) ? esc_url_raw( wp_unslash( $_POST['default_webhook_url'] ) ) : '',
		);

		update_option( 'ct_settings', $settings );

		if ( isset( $_POST['ct_pro_license_active'] ) ) {
			update_option( 'ct_pro_license_active', ! empty( $_POST['ct_pro_license_active'] ) );
		}
	}

	public static function render_dashboard(): void {
		self::guard();
		$workflows = get_posts(
			array(
				'post_type'      => WorkflowRepository::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 100,
			)
		);
		?>
		<div class="wrap ct-wrap">
			<h1><?php esc_html_e( 'Tag Workflows', 'customer-tagger' ); ?></h1>
			<p class="ct-muted">
				<?php
				echo esc_html(
					Limits::is_pro()
						? __( 'Pro: unlimited workflows and backfill.', 'customer-tagger' )
						: sprintf(
							/* translators: %d workflow limit */
							__( 'Free plan: up to %d active workflows.', 'customer-tagger' ),
							Limits::FREE_WORKFLOW_LIMIT
						)
				);
				?>
			</p>
			<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=customer-tagger-workflow' ) ); ?>"><?php esc_html_e( 'Add workflow', 'customer-tagger' ); ?></a></p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'customer-tagger' ); ?></th>
						<th><?php esc_html_e( 'Entity', 'customer-tagger' ); ?></th>
						<th><?php esc_html_e( 'Trigger', 'customer-tagger' ); ?></th>
						<th><?php esc_html_e( 'Status', 'customer-tagger' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $workflows ) ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No workflows yet.', 'customer-tagger' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $workflows as $post ) : ?>
						<tr>
							<td><?php echo esc_html( $post->post_title ); ?></td>
							<td><?php echo esc_html( (string) get_post_meta( $post->ID, '_ct_entity', true ) ); ?></td>
							<td><?php echo esc_html( (string) get_post_meta( $post->ID, '_ct_trigger', true ) ); ?></td>
							<td><?php echo 'publish' === $post->post_status ? esc_html__( 'Active', 'customer-tagger' ) : esc_html__( 'Draft', 'customer-tagger' ); ?></td>
							<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=customer-tagger-workflow&workflow_id=' . $post->ID ) ); ?>"><?php esc_html_e( 'Edit', 'customer-tagger' ); ?></a></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
			<div id="ct-workflow-app" class="ct-hidden"></div>
		</div>
		<?php
	}

	public static function render_workflow_editor(): void {
		self::guard();
		$workflow_id = isset( $_GET['workflow_id'] ) ? (int) $_GET['workflow_id'] : 0;
		?>
		<div class="wrap ct-wrap">
			<h1><?php echo $workflow_id ? esc_html__( 'Edit workflow', 'customer-tagger' ) : esc_html__( 'New workflow', 'customer-tagger' ); ?></h1>
			<div id="ct-workflow-editor" data-workflow-id="<?php echo esc_attr( (string) $workflow_id ); ?>"></div>
		</div>
		<?php
	}

	public static function render_tags(): void {
		self::guard();
		$tags = TagRepository::get_all_tags();

		if ( isset( $_POST['ct_new_tag_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ct_new_tag_nonce'] ) ), 'ct_new_tag' ) ) {
			$slug = TagRepository::sanitize_slug( (string) ( $_POST['tag_slug'] ?? '' ) );
			$label = sanitize_text_field( (string) ( $_POST['tag_label'] ?? $slug ) );
			if ( $slug ) {
				TagRepository::get_or_create_tag( $slug, $label );
				$tags = TagRepository::get_all_tags();
			}
		}
		?>
		<div class="wrap ct-wrap">
			<h1><?php esc_html_e( 'Customer tags', 'customer-tagger' ); ?></h1>
			<form method="post" class="ct-inline-form">
				<?php wp_nonce_field( 'ct_new_tag', 'ct_new_tag_nonce' ); ?>
				<input type="text" name="tag_slug" placeholder="<?php esc_attr_e( 'slug', 'customer-tagger' ); ?>" required />
				<input type="text" name="tag_label" placeholder="<?php esc_attr_e( 'Label', 'customer-tagger' ); ?>" />
				<button type="submit" class="button"><?php esc_html_e( 'Add tag', 'customer-tagger' ); ?></button>
			</form>
			<ul class="ct-tag-list">
				<?php foreach ( $tags as $tag ) : ?>
					<li><span class="ct-chip"><?php echo esc_html( $tag['label'] ); ?></span> <code><?php echo esc_html( $tag['slug'] ); ?></code></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	public static function render_logs(): void {
		self::guard();
		$logs = LogRepository::get_recent( 100 );
		?>
		<div class="wrap ct-wrap">
			<h1><?php esc_html_e( 'Activity log', 'customer-tagger' ); ?></h1>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'When', 'customer-tagger' ); ?></th>
						<th><?php esc_html_e( 'Workflow', 'customer-tagger' ); ?></th>
						<th><?php esc_html_e( 'Entity', 'customer-tagger' ); ?></th>
						<th><?php esc_html_e( 'Action', 'customer-tagger' ); ?></th>
						<th><?php esc_html_e( 'Tags', 'customer-tagger' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $logs as $log ) : ?>
					<tr>
						<td><?php echo esc_html( (string) $log['created_at'] ); ?></td>
						<td>#<?php echo esc_html( (string) $log['workflow_id'] ); ?></td>
						<td><?php echo esc_html( $log['entity_type'] . ' #' . $log['entity_id'] ); ?></td>
						<td><?php echo esc_html( (string) $log['action'] ); ?></td>
						<td><code><?php echo esc_html( (string) $log['tags'] ); ?></code></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public static function render_tools(): void {
		self::guard();
		?>
		<div class="wrap ct-wrap">
			<h1><?php esc_html_e( 'Tools', 'customer-tagger' ); ?></h1>
			<div id="ct-tools-app"></div>
			<h2><?php esc_html_e( 'Test workflow', 'customer-tagger' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Use the workflow editor preview or REST POST /ct/v1/workflows/{id}/preview with an order ID.', 'customer-tagger' ); ?></p>
		</div>
		<?php
	}

	public static function render_settings(): void {
		self::guard();
		$settings = get_option( 'ct_settings', array() );
		$pro      = (bool) get_option( 'ct_pro_license_active', false );
		?>
		<div class="wrap ct-wrap">
			<h1><?php esc_html_e( 'Settings', 'customer-tagger' ); ?></h1>
			<form method="post">
				<?php wp_nonce_field( 'ct_save_settings', 'ct_settings_nonce' ); ?>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Global tagging delay (seconds)', 'customer-tagger' ); ?></th>
						<td><input type="number" name="global_delay_seconds" value="<?php echo esc_attr( (string) ( $settings['global_delay_seconds'] ?? 0 ) ); ?>" min="0" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Log retention (days)', 'customer-tagger' ); ?></th>
						<td><input type="number" name="log_retention_days" value="<?php echo esc_attr( (string) ( $settings['log_retention_days'] ?? 90 ) ); ?>" min="1" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Sync tags to FluentCRM', 'customer-tagger' ); ?></th>
						<td><label><input type="checkbox" name="fluentcrm_sync" value="1" <?php checked( ! empty( $settings['fluentcrm_sync'] ) ); ?> /> <?php esc_html_e( 'Enabled', 'customer-tagger' ); ?></label></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Default webhook URL', 'customer-tagger' ); ?></th>
						<td><input type="url" class="large-text" name="default_webhook_url" value="<?php echo esc_attr( (string) ( $settings['default_webhook_url'] ?? '' ) ); ?>" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Pro license (dev toggle)', 'customer-tagger' ); ?></th>
						<td><label><input type="checkbox" name="ct_pro_license_active" value="1" <?php checked( $pro ); ?> /> <?php esc_html_e( 'Unlock Pro limits', 'customer-tagger' ); ?></label></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<hr class="ct-settings-divider" />

			<h2><?php esc_html_e( 'Setup wizard', 'customer-tagger' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Run the 3-step onboarding again to review how tags and workflows work.', 'customer-tagger' ); ?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=customer-tagger-settings' ) ); ?>">
				<?php wp_nonce_field( 'ct_relaunch_onboarding', 'ct_relaunch_onboarding_nonce' ); ?>
				<?php
				submit_button(
					__( 'Relaunch onboarding', 'customer-tagger' ),
					'secondary',
					'ct_relaunch_onboarding',
					false
				);
				?>
			</form>
		</div>
		<?php
	}

	private static function guard(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'customer-tagger' ) );
		}
	}
}
