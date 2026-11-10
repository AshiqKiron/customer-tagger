<?php
/**
 * WooCommerce order triggers.
 *
 * @package Customer_Tagger
 */

declare( strict_types=1 );

namespace CT\Triggers;

use CT\Engine\CustomerStats;
use CT\Engine\WorkflowRunner;
use CT\Storage\WorkflowRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hooks order events to workflow runner.
 */
class OrderTriggers {

	public static function init(): void {
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'on_order_placed' ), 20, 1 );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'on_status_changed' ), 20, 4 );
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'on_payment_complete' ), 20, 1 );
	}

	public static function on_order_placed( $order_id ): void {
		$order_id = (int) $order_id;
		if ( $order_id < 1 ) {
			return;
		}

		if ( function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$customer_id = (int) $order->get_customer_id();
				if ( $customer_id > 0 ) {
					CustomerStats::invalidate( $customer_id );
				}
			}
		}

		$payload = array( 'order_id' => $order_id );
		self::run_for_workflows( 'customer', 'order_placed', $payload );
	}

	public static function on_payment_complete( $order_id ): void {
		$order_id = (int) $order_id;
		$payload  = array( 'order_id' => $order_id );
		self::run_for_workflows( 'customer', 'order_paid', $payload );
	}

	public static function on_status_changed( $order_id, $from, $to, $order ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$order_id = (int) $order_id;
		$payload  = array( 'order_id' => $order_id, 'status' => $to );

		self::run_for_workflows( 'customer', 'order_status_changed', $payload );
		self::run_for_workflows( 'order', 'order_status_changed', $payload );

		$customer_id = (int) $order->get_customer_id();
		if ( $customer_id > 0 ) {
			CustomerStats::invalidate( $customer_id );
		}
	}

	/**
	 * @param array<string, mixed> $payload Payload.
	 */
	private static function run_for_workflows( string $entity, string $trigger, array $payload ): void {
		$workflows = WorkflowRepository::get_active_workflows( $entity, $trigger );
		if ( empty( $workflows ) ) {
			return;
		}

		$max_delay = 0;
		foreach ( $workflows as $wf ) {
			$max_delay = max( $max_delay, (int) ( $wf['delay'] ?? 0 ) );
		}

		WorkflowRunner::schedule( $entity, $trigger, $payload, $max_delay );
	}
}
