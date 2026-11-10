<?php
/**
 * Product save/stock triggers.
 *
 * @package Customer_Tagger
 */

declare( strict_types=1 );

namespace CT\Triggers;

use CT\Engine\WorkflowRunner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product entity workflows.
 */
class ProductTriggers {

	public static function init(): void {
		add_action( 'woocommerce_update_product', array( __CLASS__, 'on_product_saved' ), 20, 1 );
		add_action( 'woocommerce_product_set_stock', array( __CLASS__, 'on_stock_change' ), 20, 1 );
	}

	public static function on_product_saved( $product_id ): void {
		WorkflowRunner::dispatch( 'product', 'product_saved', array( 'product_id' => (int) $product_id ) );
	}

	/**
	 * @param \WC_Product $product Product object.
	 */
	public static function on_stock_change( $product ): void {
		if ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
			WorkflowRunner::dispatch( 'product', 'product_stock_changed', array( 'product_id' => (int) $product->get_id() ) );
		}
	}
}
