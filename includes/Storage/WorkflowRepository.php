<?php
/**
 * Workflow CPT access.
 *
 * @package Customer_Tagger
 */

declare( strict_types=1 );

namespace CT\Storage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads and saves workflow definitions.
 */
class WorkflowRepository {

	public const POST_TYPE = 'ct_workflow';

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_active_workflows( ?string $entity = null, ?string $trigger = null ): array {
		$args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'orderby'        => 'meta_value_num',
			'meta_key'       => '_ct_priority',
			'order'          => 'ASC',
		);

		$meta_query = array();
		if ( $entity ) {
			$meta_query[] = array(
				'key'   => '_ct_entity',
				'value' => $entity,
			);
		}
		if ( $trigger ) {
			$meta_query[] = array(
				'key'   => '_ct_trigger',
				'value' => $trigger,
			);
		}
		if ( $meta_query ) {
			$args['meta_query'] = $meta_query;
		}

		$posts = get_posts( $args );
		$out   = array();

		foreach ( $posts as $post ) {
			$config = json_decode( $post->post_content, true );
			if ( ! is_array( $config ) ) {
				$config = array();
			}

			$out[] = array(
				'id'          => $post->ID,
				'title'       => $post->post_title,
				'entity'      => get_post_meta( $post->ID, '_ct_entity', true ) ?: 'customer',
				'trigger'     => get_post_meta( $post->ID, '_ct_trigger', true ) ?: 'order_placed',
				'priority'    => (int) get_post_meta( $post->ID, '_ct_priority', true ),
				'delay'       => (int) get_post_meta( $post->ID, '_ct_delay_seconds', true ),
				'run_mode'    => get_post_meta( $post->ID, '_ct_run_mode', true ) ?: 'realtime',
				'config'      => $config,
				'post_status' => $post->post_status,
			);
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $config Workflow JSON.
	 * @param array<string, mixed> $meta   Workflow meta.
	 */
	public static function save_workflow( int $id, string $title, array $config, array $meta ): int {
		$post_id = wp_insert_post(
			array(
				'ID'           => $id > 0 ? $id : 0,
				'post_type'    => self::POST_TYPE,
				'post_title'   => $title,
				'post_content' => wp_json_encode( $config ),
				'post_status'  => ! empty( $meta['active'] ) ? 'publish' : 'draft',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return 0;
		}

		update_post_meta( $post_id, '_ct_entity', sanitize_key( (string) ( $meta['entity'] ?? 'customer' ) ) );
		update_post_meta( $post_id, '_ct_trigger', sanitize_key( (string) ( $meta['trigger'] ?? 'order_placed' ) ) );
		update_post_meta( $post_id, '_ct_priority', (int) ( $meta['priority'] ?? 10 ) );
		update_post_meta( $post_id, '_ct_delay_seconds', (int) ( $meta['delay_seconds'] ?? 0 ) );
		update_post_meta( $post_id, '_ct_run_mode', sanitize_key( (string) ( $meta['run_mode'] ?? 'realtime' ) ) );

		return (int) $post_id;
	}

	public static function count_active(): int {
		$q = new \WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		return (int) $q->found_posts;
	}
}
