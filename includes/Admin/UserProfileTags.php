<?php
/**
 * User profile tag UI.
 *
 * @package Customer_Tagger
 */

declare( strict_types=1 );

namespace CT\Admin;

use CT\Storage\TagRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shows customer tags on user edit screen.
 */
class UserProfileTags {

	public static function init(): void {
		add_action( 'show_user_profile', array( __CLASS__, 'render' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'render' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'save' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save' ) );
	}

	/**
	 * @param \WP_User $user User.
	 */
	public static function render( $user ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$tags = TagRepository::get_user_tag_slugs( (int) $user->ID );
		?>
		<h2><?php esc_html_e( 'Customer tags', 'customer-tagger' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><label for="ct_user_tags"><?php esc_html_e( 'Tags', 'customer-tagger' ); ?></label></th>
				<td>
					<div class="ct-profile-chips">
						<?php foreach ( $tags as $slug ) : ?>
							<span class="ct-chip"><?php echo esc_html( $slug ); ?></span>
						<?php endforeach; ?>
					</div>
					<input type="text" class="regular-text" name="ct_user_tags" id="ct_user_tags" value="<?php echo esc_attr( implode( ', ', $tags ) ); ?>" />
					<p class="description"><?php esc_html_e( 'Comma-separated slugs. Managed automatically by workflows.', 'customer-tagger' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * @param int $user_id User ID.
	 */
	public static function save( int $user_id ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! isset( $_POST['ct_user_tags'] ) ) {
			return;
		}

		$raw   = sanitize_text_field( wp_unslash( $_POST['ct_user_tags'] ) );
		$want  = array_filter( array_map( array( TagRepository::class, 'sanitize_slug' ), array_map( 'trim', explode( ',', $raw ) ) ) );
		$have  = TagRepository::get_user_tag_slugs( $user_id );
		$add   = array_diff( $want, $have );
		$remove = array_diff( $have, $want );

		if ( $add ) {
			TagRepository::add_tags_to_user( $user_id, array_values( $add ) );
		}
		if ( $remove ) {
			TagRepository::remove_tags_from_user( $user_id, array_values( $remove ) );
		}
	}
}
