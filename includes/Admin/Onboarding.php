<?php
/**
 * First-run onboarding wizard.
 *
 * @package Customer_Tagger
 */

declare( strict_types=1 );

namespace CT\Admin;

use CT\Storage\TagRepository;
use CT\Storage\WorkflowRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Simple 3-step admin onboarding.
 */
class Onboarding {

	public const OPTION_COMPLETE = 'ct_onboarding_complete';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect' ), 5 );
		add_action( 'admin_init', array( __CLASS__, 'handle_post' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_relaunch_request' ) );
	}

	public static function register_page(): void {
		add_submenu_page(
			'',
			__( 'Welcome to Customer Tagger', 'customer-tagger' ),
			__( 'Welcome', 'customer-tagger' ),
			'manage_woocommerce',
			'customer-tagger-onboarding',
			array( __CLASS__, 'render' )
		);
	}

	public static function is_complete(): bool {
		$stored = get_option( self::OPTION_COMPLETE, null );
		if ( null === $stored ) {
			return self::should_skip_for_existing_site();
		}
		return (bool) $stored;
	}

	private static function should_skip_for_existing_site(): bool {
		$workflows = get_posts(
			array(
				'post_type'      => WorkflowRepository::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		if ( ! empty( $workflows ) ) {
			update_option( self::OPTION_COMPLETE, true );
			return true;
		}
		return false;
	}

	public static function maybe_redirect(): void {
		if ( self::is_complete() ) {
			return;
		}
		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( get_transient( 'ct_activation_redirect' ) ) {
			delete_transient( 'ct_activation_redirect' );
			if ( current_user_can( 'manage_woocommerce' ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=customer-tagger-onboarding' ) );
				exit;
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['page'] ) ) : '';
		if ( '' === $page || 0 !== strpos( $page, 'customer-tagger' ) ) {
			return;
		}
		if ( 'customer-tagger-onboarding' === $page ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=customer-tagger-onboarding' ) );
		exit;
	}

	public static function handle_post(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['page'] ) || 'customer-tagger-onboarding' !== $_GET['page'] ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( ! isset( $_POST['ct_onboarding_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ct_onboarding_nonce'] ) ), 'ct_onboarding' ) ) {
			return;
		}

		if ( isset( $_POST['ct_onboarding_skip'] ) ) {
			self::mark_complete();
			wp_safe_redirect( admin_url( 'admin.php?page=customer-tagger' ) );
			exit;
		}

		if ( isset( $_POST['ct_onboarding_back'] ) ) {
			$step = isset( $_POST['ct_onboarding_step'] ) ? (int) $_POST['ct_onboarding_step'] : 1;
			$prev = max( 1, $step - 1 );
			wp_safe_redirect( admin_url( 'admin.php?page=customer-tagger-onboarding&step=' . $prev ) );
			exit;
		}

		if ( isset( $_POST['ct_onboarding_next'] ) ) {
			$step = isset( $_POST['ct_onboarding_step'] ) ? (int) $_POST['ct_onboarding_step'] : 1;

			if ( 2 === $step ) {
				$slug  = TagRepository::sanitize_slug( (string) ( $_POST['tag_slug'] ?? '' ) );
				$label = sanitize_text_field( (string) ( $_POST['tag_label'] ?? $slug ) );
				if ( $slug ) {
					TagRepository::get_or_create_tag( $slug, $label ?: $slug );
				}
			}

			if ( 3 === $step ) {
				self::mark_complete();
				wp_safe_redirect( admin_url( 'admin.php?page=customer-tagger' ) );
				exit;
			}

			$next = min( 3, $step + 1 );
			wp_safe_redirect( admin_url( 'admin.php?page=customer-tagger-onboarding&step=' . $next ) );
			exit;
		}
	}

	public static function mark_complete(): void {
		update_option( self::OPTION_COMPLETE, true );
	}

	public static function reset(): void {
		update_option( self::OPTION_COMPLETE, false );
	}

	public static function handle_relaunch_request(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified below.
		if ( empty( $_POST['ct_relaunch_onboarding'] ) ) {
			return;
		}
		if ( ! isset( $_POST['ct_relaunch_onboarding_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ct_relaunch_onboarding_nonce'] ) ), 'ct_relaunch_onboarding' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['page'] ) ) : '';
		if ( 'customer-tagger-settings' !== $page ) {
			return;
		}

		self::reset();
		wp_safe_redirect( admin_url( 'admin.php?page=customer-tagger-onboarding&step=1' ) );
		exit;
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'customer-tagger' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$step = isset( $_GET['step'] ) ? max( 1, min( 3, (int) $_GET['step'] ) ) : 1;
		?>
		<div class="wrap ct-wrap ct-onboarding">
			<div class="ct-onboarding__card">
				<div class="ct-onboarding__progress" aria-hidden="true">
					<?php for ( $i = 1; $i <= 3; $i++ ) : ?>
						<span class="ct-onboarding__dot<?php echo $i <= $step ? ' is-active' : ''; ?><?php echo $i < $step ? ' is-done' : ''; ?>"></span>
					<?php endfor; ?>
				</div>

				<form method="post">
				<?php wp_nonce_field( 'ct_onboarding', 'ct_onboarding_nonce' ); ?>
				<input type="hidden" name="ct_onboarding_step" value="<?php echo esc_attr( (string) $step ); ?>" />

				<?php if ( 1 === $step ) : ?>
					<h1><?php esc_html_e( 'Welcome to Customer Tagger', 'customer-tagger' ); ?></h1>
					<p class="ct-onboarding__lead">
						<?php esc_html_e( 'Tag WooCommerce customers automatically using workflows—when orders are placed, customers register, and more.', 'customer-tagger' ); ?>
					</p>
					<ul class="ct-onboarding__list">
						<li><?php esc_html_e( 'Define tags once (e.g. vip, first-time-buyer).', 'customer-tagger' ); ?></li>
						<li><?php esc_html_e( 'Build workflows with conditions and triggers.', 'customer-tagger' ); ?></li>
						<li><?php esc_html_e( 'Review tagging in the activity log and on user profiles.', 'customer-tagger' ); ?></li>
					</ul>
				<?php elseif ( 2 === $step ) : ?>
					<h1><?php esc_html_e( 'Create your first tag', 'customer-tagger' ); ?></h1>
					<p class="ct-onboarding__lead">
						<?php esc_html_e( 'Tags are labels attached to customers. Workflows add them when rules match. Add one now or skip and create tags later.', 'customer-tagger' ); ?>
					</p>
					<table class="form-table ct-onboarding__form" role="presentation">
						<tr>
							<th scope="row"><label for="ct-onboarding-tag-slug"><?php esc_html_e( 'Slug', 'customer-tagger' ); ?></label></th>
							<td>
								<input type="text" id="ct-onboarding-tag-slug" name="tag_slug" class="regular-text" placeholder="first-time-buyer" />
								<p class="description"><?php esc_html_e( 'Lowercase, no spaces (used in exports and APIs).', 'customer-tagger' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="ct-onboarding-tag-label"><?php esc_html_e( 'Label', 'customer-tagger' ); ?></label></th>
							<td>
								<input type="text" id="ct-onboarding-tag-label" name="tag_label" class="regular-text" placeholder="<?php esc_attr_e( 'First-time buyer', 'customer-tagger' ); ?>" />
							</td>
						</tr>
					</table>
				<?php else : ?>
					<h1><?php esc_html_e( 'You are all set', 'customer-tagger' ); ?></h1>
					<p class="ct-onboarding__lead">
						<?php esc_html_e( 'Starter workflows were added when you activated the plugin. Turn them on, edit them, or create your own from scratch.', 'customer-tagger' ); ?>
					</p>
					<p>
						<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=customer-tagger' ) ); ?>">
							<?php esc_html_e( 'View workflows', 'customer-tagger' ); ?>
						</a>
						<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=customer-tagger-workflow' ) ); ?>">
							<?php esc_html_e( 'Create a workflow', 'customer-tagger' ); ?>
						</a>
					</p>
				<?php endif; ?>

				<div class="ct-onboarding__actions">
					<div class="ct-onboarding__actions-start">
						<?php if ( $step > 1 ) : ?>
							<button type="submit" name="ct_onboarding_back" class="button">
								<?php esc_html_e( 'Back', 'customer-tagger' ); ?>
							</button>
						<?php endif; ?>
					</div>
					<div class="ct-onboarding__actions-end">
						<?php if ( $step < 3 ) : ?>
							<button type="submit" name="ct_onboarding_next" class="button button-primary">
								<?php esc_html_e( 'Continue', 'customer-tagger' ); ?>
							</button>
						<?php else : ?>
							<button type="submit" name="ct_onboarding_next" class="button button-primary">
								<?php esc_html_e( 'Finish', 'customer-tagger' ); ?>
							</button>
						<?php endif; ?>
						<button type="submit" name="ct_onboarding_skip" class="button button-link">
							<?php esc_html_e( 'Skip setup', 'customer-tagger' ); ?>
						</button>
					</div>
				</div>
				</form>
			</div>
		</div>
		<?php
	}
}
