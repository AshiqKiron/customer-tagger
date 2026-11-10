<?php
/**
 * Aggregated customer metrics.
 *
 * @package Customer_Tagger
 */

declare( strict_types=1 );

namespace CT\Engine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cached WooCommerce customer aggregates.
 */
class CustomerStats {

	/**
	 * @return array<string, mixed>
	 */
	public static function for_user( int $user_id ): array {
		if ( $user_id < 1 ) {
			return self::empty_stats();
		}

		$key  = 'ct_customer_stats_' . $user_id;
		$data = get_transient( $key );
		if ( is_array( $data ) ) {
			return $data;
		}

		$data = self::compute( $user_id );
		set_transient( $key, $data, HOUR_IN_SECONDS );
		return $data;
	}

	public static function invalidate( int $user_id ): void {
		delete_transient( 'ct_customer_stats_' . $user_id );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function empty_stats(): array {
		return array(
			'order_count'       => 0,
			'lifetime_spend'    => 0.0,
			'average_order'     => 0.0,
			'first_order'       => false,
			'last_order_days'     => null,
			'refund_count'      => 0,
			'billing_country'   => '',
			'registered_days'   => null,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function compute( int $user_id ): array {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return self::empty_stats();
		}

		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'status'      => array( 'wc-pending', 'wc-completed', 'wc-processing', 'wc-on-hold' ),
				'limit'       => -1,
				'return'      => 'ids',
			)
		);

		$count  = count( $orders );
		$total  = 0.0;
		$refunds = 0;
		$last_ts = 0;

		foreach ( $orders as $oid ) {
			$order = wc_get_order( $oid );
			if ( ! $order ) {
				continue;
			}
			$total += (float) $order->get_total();
			$refunds += count( $order->get_refunds() );
			$created = $order->get_date_created();
			if ( $created ) {
				$ts = $created->getTimestamp();
				if ( $ts > $last_ts ) {
					$last_ts = $ts;
				}
			}
		}

		$user = get_userdata( $user_id );
		$billing_country = '';
		if ( $user ) {
			$billing_country = get_user_meta( $user_id, 'billing_country', true ) ?: '';
		}

		$registered_days = null;
		if ( $user && $user->user_registered ) {
			$registered_days = (int) floor( ( time() - strtotime( $user->user_registered ) ) / DAY_IN_SECONDS );
		}

		$last_order_days = null;
		if ( $last_ts > 0 ) {
			$last_order_days = (int) floor( ( time() - $last_ts ) / DAY_IN_SECONDS );
		}

		return array(
			'order_count'     => $count,
			'lifetime_spend'  => $total,
			'average_order'   => $count > 0 ? $total / $count : 0.0,
			'first_order'     => $count === 1,
			'last_order_days' => $last_order_days,
			'refund_count'    => $refunds,
			'billing_country' => (string) $billing_country,
			'registered_days' => $registered_days,
		);
	}
}
