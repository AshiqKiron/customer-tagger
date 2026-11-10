<?php
/**
 * Executes workflows for a trigger.
 *
 * @package Customer_Tagger
 */

declare( strict_types=1 );

namespace CT\Engine;

use CT\License\Limits;
use CT\Storage\WorkflowRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads workflows and runs evaluator + actions.
 */
class WorkflowRunner {

	/**
	 * Run workflows immediately (after optional delay handled by scheduler).
	 *
	 * @param array<string, mixed> $payload Context payload.
	 */
	public static function dispatch( string $entity, string $trigger, array $payload ): void {
		$workflows = WorkflowRepository::get_active_workflows( $entity, $trigger );
		if ( empty( $workflows ) ) {
			return;
		}

		$ctx = self::build_context( $entity, $payload );
		if ( ! $ctx ) {
			return;
		}

		foreach ( $workflows as $workflow ) {
			if ( ! Limits::workflow_may_run( (int) $workflow['id'] ) ) {
				continue;
			}

			$config = $workflow['config'] ?? array();
			if ( ! is_array( $config ) ) {
				continue;
			}

			$conditions = $config['conditions'] ?? array();
			if ( ! Evaluator::matches( is_array( $conditions ) ? $conditions : array(), $ctx ) ) {
				if ( ! empty( $config['lifecycle']['revert_when_conditions_false'] ) ) {
					self::revert_actions( $workflow, $ctx );
				}
				continue;
			}

			ActionRunner::run( $workflow, $ctx );
		}
	}

	/**
	 * Schedule with global + per-workflow delay.
	 *
	 * @param array<string, mixed> $payload Payload.
	 */
	public static function schedule( string $entity, string $trigger, array $payload, int $workflow_delay = 0 ): void {
		$settings = get_option( 'ct_settings', array() );
		$global   = isset( $settings['global_delay_seconds'] ) ? (int) $settings['global_delay_seconds'] : 0;
		$delay    = max( $global, $workflow_delay );

		if ( $delay > 0 ) {
			\CT\Scheduler\ActionSchedulerBridge::schedule_delayed_dispatch( $entity, $trigger, $payload, $delay );
			return;
		}

		self::dispatch( $entity, $trigger, $payload );
	}

	/**
	 * @param array<string, mixed> $payload Payload.
	 */
	private static function build_context( string $entity, array $payload ): ?Context {
		if ( 'customer' === $entity || 'order' === $entity ) {
			if ( ! empty( $payload['order_id'] ) ) {
				$ctx = Context::from_order( (int) $payload['order_id'] );
				if ( isset( $payload['check_product_id'] ) ) {
					$ctx->meta['check_product_id'] = (int) $payload['check_product_id'];
				}
				if ( isset( $payload['check_category_id'] ) ) {
					$ctx->meta['check_category_id'] = (int) $payload['check_category_id'];
				}
				return $ctx;
			}
			if ( ! empty( $payload['user_id'] ) ) {
				return Context::from_user( (int) $payload['user_id'] );
			}
		}

		if ( 'product' === $entity && ! empty( $payload['product_id'] ) ) {
			return Context::from_product( (int) $payload['product_id'] );
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $workflow Workflow row.
	 */
	private static function revert_actions( array $workflow, Context $ctx ): void {
		$config  = $workflow['config'] ?? array();
		$actions = $config['actions'] ?? array();
		if ( ! is_array( $actions ) ) {
			return;
		}

		$to_remove = array();
		foreach ( $actions as $action ) {
			if ( ! is_array( $action ) ) {
				continue;
			}
			if ( 'add_tags' === ( $action['type'] ?? '' ) ) {
				$to_remove = array_merge( $to_remove, (array) ( $action['tags'] ?? array() ) );
			}
		}

		if ( $to_remove && $ctx->user_id > 0 ) {
			\CT\Storage\TagRepository::remove_tags_from_user( $ctx->user_id, $to_remove );
		}
	}

	/**
	 * Preview a single workflow against an order.
	 *
	 * @param array<string, mixed> $workflow Workflow row.
	 */
	public static function preview( array $workflow, int $order_id ): bool {
		$ctx = Context::from_order( $order_id );
		$config = $workflow['config'] ?? array();
		$conditions = is_array( $config['conditions'] ?? null ) ? $config['conditions'] : array();
		return Evaluator::matches( $conditions, $ctx );
	}
}
