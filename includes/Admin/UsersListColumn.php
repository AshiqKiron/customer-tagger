<?php
/**
 * Users list integration.
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
 * Column + filter by customer tag.
 */
class UsersListColumn {

	public static function init(): void {
		add_filter( 'manage_users_columns', array( __CLASS__, 'add_column' ) );
		add_filter( 'manage_users_custom_column', array( __CLASS__, 'render_column' ), 10, 3 );
		add_action( 'restrict_manage_users', array( __CLASS__, 'filter_dropdown' ) );
		add_action( 'pre_get_users', array( __CLASS__, 'filter_users_query' ) );
	}

	/**
	 * @param string[] $columns Columns.
	 * @return string[]
	 */
	public static function add_column( array $columns ): array {
		$columns['ct_tags'] = __( 'Customer tags', 'customer-tagger' );
		return $columns;
	}

	/**
	 * @param string $output      Cell output.
	 * @param string $column_name Column name.
	 * @param int    $user_id     User ID.
	 */
	public static function render_column( string $output, string $column_name, int $user_id ): string {
		if ( 'ct_tags' !== $column_name ) {
			return $output;
		}

		$tags = TagRepository::get_user_tag_slugs( $user_id );
		if ( empty( $tags ) ) {
			return '—';
		}

		$chips = array();
		foreach ( $tags as $slug ) {
			$chips[] = '<span class="ct-chip">' . esc_html( $slug ) . '</span>';
		}

		return implode( ' ', $chips );
	}

	public static function filter_dropdown(): void {
		$tags   = TagRepository::get_all_tags();
		$current = isset( $_GET['ct_tag'] ) ? TagRepository::sanitize_slug( wp_unslash( (string) $_GET['ct_tag'] ) ) : '';
		?>
		<label for="ct_tag" class="screen-reader-text"><?php esc_html_e( 'Filter by customer tag', 'customer-tagger' ); ?></label>
		<select name="ct_tag" id="ct_tag">
			<option value=""><?php esc_html_e( 'All customer tags', 'customer-tagger' ); ?></option>
			<?php foreach ( $tags as $tag ) : ?>
				<option value="<?php echo esc_attr( $tag['slug'] ); ?>" <?php selected( $current, $tag['slug'] ); ?>><?php echo esc_html( $tag['label'] ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * @param \WP_User_Query $query Query.
	 */
	public static function filter_users_query( $query ): void {
		if ( ! is_admin() || ! isset( $_GET['ct_tag'] ) ) {
			return;
		}

		$slug = TagRepository::sanitize_slug( wp_unslash( (string) $_GET['ct_tag'] ) );
		if ( '' === $slug ) {
			return;
		}

		$user_ids = TagRepository::get_user_ids_by_tag( $slug );
		$query->set( 'include', $user_ids ? $user_ids : array( 0 ) );
	}
}
