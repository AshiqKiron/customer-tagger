<?php
/**
 * Workflow actions.
 *
 * @package Customer_Tagger
 */

declare( strict_types=1 );

namespace CT\Engine;

use CT\Integrations\FluentCRM;
use CT\Storage\LogRepository;
use CT\Storage\TagRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies workflow actions when conditions match.
 */
class ActionRunner {

	/**
	 * @param array<string, mixed> $workflow Workflow row from repository.
	 */
	public static function run( array $workflow, Context $ctx ): void {
		$config  = $workflow['config'] ?? array();
		$actions = $config['actions'] ?? array();
		if ( ! is_array( $actions ) ) {
			return;
		}

		$lifecycle      = is_array( $config['lifecycle'] ?? null ) ? $config['lifecycle'] : array();
		$expiry_days    = isset( $lifecycle['remove_tags_after_days'] ) ? (int) $lifecycle['remove_tags_after_days'] : 0;
		$entity         = (string) ( $workflow['entity'] ?? 'customer' );
		$workflow_id    = (int) ( $workflow['id'] ?? 0 );

		foreach ( $actions as $action ) {
			if ( ! is_array( $action ) ) {
				continue;
			}

			$type = (string) ( $action['type'] ?? '' );

			switch ( $type ) {
				case 'add_tags':
					$tags = array_map( 'strval', (array) ( $action['tags'] ?? array() ) );
					self::apply_customer_tags( $ctx, $tags, $workflow_id, $entity, 'add_tags', $expiry_days );
					break;
				case 'remove_tags':
					$tags = array_map( 'strval', (array) ( $action['tags'] ?? array() ) );
					self::remove_customer_tags( $ctx, $tags, $workflow_id, $entity );
					break;
				case 'add_dynamic_tag':
					$template = (string) ( $action['template'] ?? '' );
					$slug     = DynamicTagParser::parse( $template, $ctx );
					if ( '' !== $slug ) {
						self::apply_customer_tags( $ctx, array( $slug ), $workflow_id, $entity, 'add_dynamic_tag', $expiry_days );
					}
					break;
				case 'webhook':
					self::fire_webhook( $action, $workflow, $ctx );
					break;
				case 'add_order_tags':
					if ( $ctx->order_id > 0 ) {
						$tags = array_map( 'sanitize_title', (array) ( $action['tags'] ?? array() ) );
						wp_set_post_terms( $ctx->order_id, $tags, 'shop_order_tag', true );
						LogRepository::write( $workflow_id, 'order', $ctx->order_id, 'add_order_tags', $tags, array( 'order_id' => $ctx->order_id ) );
					}
					break;
				case 'add_product_tags':
					if ( $ctx->product_id > 0 ) {
						$tags = array_map( 'sanitize_title', (array) ( $action['tags'] ?? array() ) );
						wp_set_post_terms( $ctx->product_id, $tags, 'product_tag', true );
						LogRepository::write( $workflow_id, 'product', $ctx->product_id, 'add_product_tags', $tags, array() );
					}
					break;
			}
		}

		if ( $expiry_days > 0 && $ctx->user_id > 0 && ! empty( $lifecycle['cancel_expiry_on_rematch'] ) ) {
			\CT\Scheduler\ActionSchedulerBridge::reschedule_user_tag_expiry( $ctx->user_id, $workflow_id, $expiry_days );
		}
	}

	/**
	 * @param string[] $tags Tag slugs.
	 */
	private static function apply_customer_tags( Context $ctx, array $tags, int $workflow_id, string $entity, string $action, int $expiry_days ): void {
		if ( 'customer' !== $entity || $ctx->user_id < 1 || empty( $tags ) ) {
			return;
		}

		$added = TagRepository::add_tags_to_user(
			$ctx->user_id,
			$tags,
			$workflow_id,
			$expiry_days > 0 ? $expiry_days : null
		);

		if ( $added ) {
			LogRepository::write(
				$workflow_id,
				'customer',
				$ctx->user_id,
				$action,
				$added,
				array(
					'order_id' => $ctx->order_id,
				)
			);

			do_action( 'ct_tags_applied', $ctx->user_id, $added, $workflow_id, $ctx );
			FluentCRM::sync_tags( $ctx->user_id, $added );
		}
	}

	/**
	 * @param string[] $tags Tag slugs.
	 */
	private static function remove_customer_tags( Context $ctx, array $tags, int $workflow_id, string $entity ): void {
		if ( 'customer' !== $entity || $ctx->user_id < 1 ) {
			return;
		}

		$removed = TagRepository::remove_tags_from_user( $ctx->user_id, $tags );
		if ( $removed ) {
			LogRepository::write( $workflow_id, 'customer', $ctx->user_id, 'remove_tags', $removed, array( 'order_id' => $ctx->order_id ) );
			do_action( 'ct_tags_removed', $ctx->user_id, $removed, $workflow_id, $ctx );
		}
	}

	/**
	 * @param array<string, mixed> $action   Webhook action config.
	 * @param array<string, mixed> $workflow Workflow row.
	 */
	private static function fire_webhook( array $action, array $workflow, Context $ctx ): void {
		$url = (string) ( $action['url'] ?? '' );
		if ( '' === $url ) {
			$settings = get_option( 'ct_settings', array() );
			$url      = (string) ( $settings['default_webhook_url'] ?? '' );
		}
		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return;
		}

		wp_remote_post(
			$url,
			array(
				'timeout' => 10,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'workflow_id' => $workflow['id'] ?? 0,
						'user_id'     => $ctx->user_id,
						'order_id'    => $ctx->order_id,
						'product_id'  => $ctx->product_id,
					)
				),
			)
		);
	}
}
