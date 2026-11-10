<?php
/**
 * Execution log storage.
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
 * Activity log for workflows.
 */
class LogRepository {

	/**
	 * @param string[]             $tags    Tag slugs.
	 * @param array<string, mixed> $context Optional context.
	 */
	public static function write(
		int $workflow_id,
		string $entity_type,
		int $entity_id,
		string $action,
		array $tags,
		array $context = array()
	): void {
		global $wpdb;

		$table = Schema::table_names()['log'];

		$wpdb->insert(
			$table,
			array(
				'workflow_id' => $workflow_id,
				'entity_type' => $entity_type,
				'entity_id'   => $entity_id,
				'action'      => $action,
				'tags'        => wp_json_encode( array_values( $tags ) ),
				'context'     => wp_json_encode( $context ),
			),
			array( '%d', '%s', '%d', '%s', '%s', '%s' )
		);

		self::maybe_prune();
	}

	/**
	 * Prune old log rows per settings.
	 */
	private static function maybe_prune(): void {
		$settings = get_option( 'ct_settings', array() );
		$days     = isset( $settings['log_retention_days'] ) ? (int) $settings['log_retention_days'] : 90;
		if ( $days < 1 ) {
			return;
		}

		global $wpdb;
		$table = Schema::table_names()['log'];
		$cut   = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cut )
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_recent( int $limit = 50, int $offset = 0 ): array {
		global $wpdb;

		$table = Schema::table_names()['log'];
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset ),
			ARRAY_A
		);

		return $rows ?: array();
	}
}
