<?php
/**
 * Customer tag storage (junction table).
 *
 * @package Customer_Tagger
 */

declare( strict_types=1 );

namespace CT\Storage;

use CT\Database\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tag dictionary + user assignments.
 */
class TagRepository {

	/**
	 * @param string $slug Raw slug.
	 */
	public static function sanitize_slug( string $slug ): string {
		$slug = sanitize_title( $slug );
		return substr( $slug, 0, 191 );
	}

	/**
	 * Get or create tag by slug.
	 *
	 * @param string $slug  Tag slug.
	 * @param string $label Optional label.
	 */
	public static function get_or_create_tag( string $slug, string $label = '' ): int {
		global $wpdb;

		$slug  = self::sanitize_slug( $slug );
		$table = Schema::table_names()['tags'];

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE slug = %s",
				$slug
			)
		);

		if ( $id > 0 ) {
			return $id;
		}

		if ( '' === $label ) {
			$label = ucwords( str_replace( array( '-', '_' ), ' ', $slug ) );
		}

		$wpdb->insert(
			$table,
			array(
				'slug'  => $slug,
				'label' => $label,
			),
			array( '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * @return string[]
	 */
	public static function get_user_tag_slugs( int $user_id ): array {
		global $wpdb;

		$tags_table = Schema::table_names()['tags'];
		$ut_table   = Schema::table_names()['user_tags'];

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT t.slug FROM {$tags_table} t
				INNER JOIN {$ut_table} ut ON ut.tag_id = t.id
				WHERE ut.user_id = %d
				AND (ut.expires_at IS NULL OR ut.expires_at > %s)",
				$user_id,
				gmdate( 'Y-m-d H:i:s' )
			)
		);

		return array_map( 'strval', $rows ?: array() );
	}

	public static function user_has_tag( int $user_id, string $slug ): bool {
		return in_array( self::sanitize_slug( $slug ), self::get_user_tag_slugs( $user_id ), true );
	}

	/**
	 * @param string[] $slugs Tag slugs.
	 */
	public static function add_tags_to_user(
		int $user_id,
		array $slugs,
		int $workflow_id = 0,
		?int $expires_after_days = null
	): array {
		global $wpdb;

		$added   = array();
		$ut      = Schema::table_names()['user_tags'];
		$expires = null;

		if ( null !== $expires_after_days && $expires_after_days > 0 ) {
			$expires = gmdate( 'Y-m-d H:i:s', time() + ( $expires_after_days * DAY_IN_SECONDS ) );
		}

		foreach ( $slugs as $raw ) {
			$slug = self::sanitize_slug( (string) $raw );
			if ( '' === $slug ) {
				continue;
			}

			$tag_id = self::get_or_create_tag( $slug );

			$exists = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$ut} WHERE user_id = %d AND tag_id = %d",
					$user_id,
					$tag_id
				)
			);

			if ( $exists > 0 ) {
				$wpdb->update(
					$ut,
					array(
						'workflow_id' => $workflow_id,
						'expires_at'    => $expires,
					),
					array( 'id' => $exists ),
					array( '%d', '%s' ),
					array( '%d' )
				);
				continue;
			}

			$wpdb->insert(
				$ut,
				array(
					'user_id'     => $user_id,
					'tag_id'      => $tag_id,
					'workflow_id' => $workflow_id,
					'expires_at'  => $expires,
				),
				array( '%d', '%d', '%d', '%s' )
			);

			$added[] = $slug;
		}

		return $added;
	}

	/**
	 * @param string[] $slugs Tag slugs.
	 */
	public static function remove_tags_from_user( int $user_id, array $slugs ): array {
		global $wpdb;

		$removed  = array();
		$tags_tbl = Schema::table_names()['tags'];
		$ut       = Schema::table_names()['user_tags'];

		foreach ( $slugs as $raw ) {
			$slug = self::sanitize_slug( (string) $raw );
			if ( '' === $slug ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$tag_id = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT id FROM {$tags_tbl} WHERE slug = %s", $slug )
			);

			if ( $tag_id < 1 ) {
				continue;
			}

			$deleted = $wpdb->delete(
				$ut,
				array(
					'user_id' => $user_id,
					'tag_id'  => $tag_id,
				),
				array( '%d', '%d' )
			);

			if ( $deleted ) {
				$removed[] = $slug;
			}
		}

		return $removed;
	}

	/**
	 * @return array<int, array{slug:string,label:string,color:string,description:string}>
	 */
	public static function get_all_tags(): array {
		global $wpdb;

		$table = Schema::table_names()['tags'];
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT slug, label, color, description FROM {$table} ORDER BY label ASC", ARRAY_A );

		return $rows ?: array();
	}

	/**
	 * @return int[]
	 */
	public static function get_user_ids_by_tag( string $slug ): array {
		global $wpdb;

		$tags_tbl = Schema::table_names()['tags'];
		$ut       = Schema::table_names()['user_tags'];

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array_map(
			'intval',
			$wpdb->get_col(
				$wpdb->prepare(
					"SELECT ut.user_id FROM {$ut} ut
					INNER JOIN {$tags_tbl} t ON t.id = ut.tag_id
					WHERE t.slug = %s
					AND (ut.expires_at IS NULL OR ut.expires_at > %s)",
					self::sanitize_slug( $slug ),
					gmdate( 'Y-m-d H:i:s' )
				)
			) ?: array()
		);
	}

	public static function count_tags(): int {
		global $wpdb;

		$table = Schema::table_names()['tags'];
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * Remove expired user tag rows.
	 */
	public static function purge_expired(): int {
		global $wpdb;

		$ut = Schema::table_names()['user_tags'];
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$ut} WHERE expires_at IS NOT NULL AND expires_at <= %s",
				gmdate( 'Y-m-d H:i:s' )
			)
		);
	}
}
