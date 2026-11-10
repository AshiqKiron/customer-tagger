<?php
/**
 * WP-CLI commands.
 *
 * @package Customer_Tagger
 */

declare( strict_types=1 );

namespace CT\CLI;

use CT\Engine\WorkflowRunner;
use CT\Scheduler\ActionSchedulerBridge;
use CT\Storage\WorkflowRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * wp ct ...
 */
class Commands {

	/**
	 * Run workflows for an order.
	 *
	 * ## OPTIONS
	 *
	 * --order_id=<id>
	 * : WooCommerce order ID.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ct run-workflow --order_id=123
	 *
	 * @param array<int, string>    $args       Positional args.
	 * @param array<string, string> $assoc_args Assoc args.
	 */
	public function run_workflow( array $args, array $assoc_args ): void {
		$order_id = isset( $assoc_args['order_id'] ) ? (int) $assoc_args['order_id'] : 0;
		if ( $order_id < 1 ) {
			\WP_CLI::error( 'Provide --order_id' );
		}
		WorkflowRunner::dispatch( 'customer', 'order_placed', array( 'order_id' => $order_id ) );
		\WP_CLI::success( 'Workflows dispatched for order ' . $order_id );
	}

	/**
	 * Queue historical order backfill.
	 *
	 * ## OPTIONS
	 *
	 * [--after=<date>]
	 * [--before=<date>]
	 *
	 * @param array<int, string>    $args       Positional args.
	 * @param array<string, string> $assoc_args Assoc args.
	 */
	public function backfill( array $args, array $assoc_args ): void {
		unset( $args );
		$state = array(
			'page'   => 1,
			'after'  => $assoc_args['after'] ?? '',
			'before' => $assoc_args['before'] ?? '',
		);
		ActionSchedulerBridge::queue_backfill( $state );
		\WP_CLI::success( 'Backfill queued.' );
	}

	/**
	 * List active workflows.
	 *
	 * @param array<int, string> $args Positional.
	 */
	public function list_workflows( array $args ): void {
		unset( $args );
		$rows = WorkflowRepository::get_active_workflows();
		foreach ( $rows as $row ) {
			\WP_CLI::log( sprintf( '#%d %s [%s/%s]', $row['id'], $row['title'], $row['entity'], $row['trigger'] ) );
		}
	}
}
