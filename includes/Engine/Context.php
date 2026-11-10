<?php
/**
 * Evaluation context DTO.
 *
 * @package Customer_Tagger
 */

declare( strict_types=1 );

namespace CT\Engine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Immutable context for rule evaluation.
 */
class Context {

	public int $user_id = 0;

	public int $order_id = 0;

	public int $product_id = 0;

	/** @var \WC_Order|null */
	public $order = null;

	/** @var \WC_Product|null */
	public $product = null;

	/** @var array<string, mixed> */
	public array $customer_stats = array();

	/** @var array<string, mixed> */
	public array $meta = array();

	/**
	 * Build from order.
	 */
	public static function from_order( int $order_id ): self {
		$ctx           = new self();
		$ctx->order_id = $order_id;

		if ( ! function_exists( 'wc_get_order' ) ) {
			return $ctx;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return $ctx;
		}

		$ctx->order   = $order;
		$ctx->user_id = (int) $order->get_customer_id();
		if ( $ctx->user_id > 0 ) {
			$ctx->customer_stats = CustomerStats::for_user( $ctx->user_id );
		}

		return $ctx;
	}

	/**
	 * Build from user.
	 */
	public static function from_user( int $user_id ): self {
		$ctx            = new self();
		$ctx->user_id   = $user_id;
		$ctx->customer_stats = CustomerStats::for_user( $user_id );
		return $ctx;
	}

	/**
	 * Build from product.
	 */
	public static function from_product( int $product_id ): self {
		$ctx              = new self();
		$ctx->product_id  = $product_id;
		$ctx->product     = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
		return $ctx;
	}
}
