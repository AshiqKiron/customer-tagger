<?php
/**
 * Database schema.
 *
 * @package Customer_Tagger
 */

declare( strict_types=1 );

namespace CT\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates and drops plugin tables (Option B).
 */
class Schema {

	/**
	 * @return string[]
	 */
	public static function table_names(): array {
		global $wpdb;

		return array(
			'tags'      => $wpdb->prefix . 'ct_tags',
			'user_tags' => $wpdb->prefix . 'ct_user_tags',
			'log'       => $wpdb->prefix . 'ct_log',
		);
	}

	/**
	 * Create tables.
	 */
	public static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$tables  = self::table_names();

		$sql_tags = "CREATE TABLE {$tables['tags']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			slug varchar(191) NOT NULL,
			label varchar(191) NOT NULL,
			description text NULL,
			color varchar(7) NOT NULL DEFAULT '#2271b1',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
		) $charset;";

		$sql_user_tags = "CREATE TABLE {$tables['user_tags']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			tag_id bigint(20) unsigned NOT NULL,
			workflow_id bigint(20) unsigned NOT NULL DEFAULT 0,
			added_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			expires_at datetime NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_tag (user_id, tag_id),
			KEY tag_id (tag_id),
			KEY expires_at (expires_at)
		) $charset;";

		$sql_log = "CREATE TABLE {$tables['log']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			workflow_id bigint(20) unsigned NOT NULL DEFAULT 0,
			entity_type varchar(32) NOT NULL,
			entity_id bigint(20) unsigned NOT NULL,
			action varchar(32) NOT NULL,
			tags text NOT NULL,
			context longtext NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY workflow_id (workflow_id),
			KEY entity (entity_type, entity_id),
			KEY created_at (created_at)
		) $charset;";

		dbDelta( $sql_tags );
		dbDelta( $sql_user_tags );
		dbDelta( $sql_log );

		update_option( 'ct_db_version', CUSTOMER_TAGGER_VERSION );
	}

	/**
	 * Drop tables on uninstall.
	 */
	public static function drop_tables(): void {
		global $wpdb;

		$tables = self::table_names();
		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}

		delete_option( 'ct_db_version' );
	}
}
