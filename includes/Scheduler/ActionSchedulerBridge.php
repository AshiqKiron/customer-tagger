<?php
/**
 * Action Scheduler integration.
 *
 * @package Customer_Tagger
 */

declare( strict_types=1 );

namespace CT\Scheduler;

use CT\Engine\WorkflowRunner;
use CT\Storage\TagRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queued jobs for delay, expiry, backfill, recompute.
 */
class ActionSchedulerBridge {

	public const HOOK_DELAYED     = 'ct_run_workflow_delayed';
	public const HOOK_REMOVE_TAGS = 'ct_remove_tags';
	public const HOOK_BACKFILL    = 'ct_batch_backfill';
	public const HOOK_RECOMPUTE   = 'ct_recompute_dynamic';

	public static function init(): void {
		add_action( self::HOOK_DELAYED, array( __CLASS__, 'handle_delayed' ), 10, 3 );
		add_action( self::HOOK_REMOVE_TAGS, array( __CLASS__, 'handle_remove_tags' ), 10, 2 );
		add_action( self::HOOK_BACKFILL, array( __CLASS__, 'handle_backfill' ), 10, 1 );
		add_action( self::HOOK_RECOMPUTE, array( __CLASS__, 'handle_recompute' ), 10, 0 );

		if ( ! wp_next_scheduled( self::HOOK_RECOMPUTE ) ) {
			wp_schedule_event( time(), 'daily', self::HOOK_RECOMPUTE );
		}
	}

	/**
	 * @param array<string, mixed> $payload Payload.
	 */
	public static function schedule_delayed_dispatch( string $entity, string $trigger, array $payload, int $delay_seconds ): void {
		$args = array( $entity, $trigger, $payload );
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time() + $delay_seconds, self::HOOK_DELAYED, $args, 'customer-tagger' );
		} else {
			wp_schedule_single_event( time() + $delay_seconds, self::HOOK_DELAYED, $args );
		}
	}

	/**
	 * @param array<string, mixed> $payload Payload.
	 */
	public static function handle_delayed( string $entity, string $trigger, array $payload ): void {
		WorkflowRunner::dispatch( $entity, $trigger, $payload );
	}

	public static function reschedule_user_tag_expiry( int $user_id, int $workflow_id, int $days ): void {
		$args = array( $user_id, $workflow_id );
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK_REMOVE_TAGS, $args, 'customer-tagger' );
		}
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time() + ( $days * DAY_IN_SECONDS ), self::HOOK_REMOVE_TAGS, $args, 'customer-tagger' );
		} else {
			wp_schedule_single_event( time() + ( $days * DAY_IN_SECONDS ), self::HOOK_REMOVE_TAGS, $args );
		}
	}

	public static function handle_remove_tags( int $user_id, int $workflow_id ): void {
		global $wpdb;

		$ut = \CT\Database\Schema::table_names()['user_tags'];
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$ut} WHERE user_id = %d AND workflow_id = %d AND expires_at IS NOT NULL AND expires_at <= %s",
				$user_id,
				$workflow_id,
				gmdate( 'Y-m-d H:i:s' )
			)
		);
		TagRepository::purge_expired();
	}

	/**
	 * @param array<string, mixed> $state Backfill state.
	 */
	public static function queue_backfill( array $state ): void {
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::HOOK_BACKFILL, array( $state ), 'customer-tagger' );
		} else {
			wp_schedule_single_event( time() + 1, self::HOOK_BACKFILL, array( $state ) );
		}
	}

	/**
	 * @param array<string, mixed> $state Backfill cursor state.
	 */
	public static function handle_backfill( array $state ): void {
		$page     = isset( $state['page'] ) ? (int) $state['page'] : 1;
		$per_page = 50;
		$after    = isset( $state['after'] ) ? (string) $state['after'] : '';
		$before   = isset( $state['before'] ) ? (string) $state['before'] : '';

		$args = array(
			'limit'   => $per_page,
			'page'    => $page,
			'orderby' => 'date',
			'order'   => 'ASC',
			'return'  => 'ids',
		);

		if ( $after ) {
			$args['date_after'] = $after;
		}
		if ( $before ) {
			$args['date_before'] = $before;
		}

		$ids = function_exists( 'wc_get_orders' ) ? wc_get_orders( $args ) : array();
		foreach ( $ids as $order_id ) {
			WorkflowRunner::dispatch( 'customer', 'order_placed', array( 'order_id' => (int) $order_id ) );
			WorkflowRunner::dispatch( 'order', 'order_status_changed', array( 'order_id' => (int) $order_id ) );
		}

		if ( count( $ids ) === $per_page ) {
			$state['page'] = $page + 1;
			self::queue_backfill( $state );
		}
	}

	public static function handle_recompute(): void {
		TagRepository::purge_expired();

		$workflows = \CT\Storage\WorkflowRepository::get_active_workflows( 'customer', 'scheduled_daily' );
		foreach ( $workflows as $workflow ) {
			$user_ids = get_users(
				array(
					'role'   => 'customer',
					'fields' => 'ID',
					'number' => 200,
				)
			);
			foreach ( $user_ids as $uid ) {
				$ctx = \CT\Engine\Context::from_user( (int) $uid );
				$config = $workflow['config'] ?? array();
				if ( \CT\Engine\Evaluator::matches( is_array( $config['conditions'] ?? null ) ? $config['conditions'] : array(), $ctx ) ) {
					\CT\Engine\ActionRunner::run( $workflow, $ctx );
				}
			}
		}
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::HOOK_RECOMPUTE );
	}
}
