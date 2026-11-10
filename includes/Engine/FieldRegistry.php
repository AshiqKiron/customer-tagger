<?php
/**
 * Condition field definitions.
 *
 * @package Customer_Tagger
 */

declare( strict_types=1 );

namespace CT\Engine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers evaluable fields for workflow builder UI.
 */
class FieldRegistry {

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_fields( string $entity ): array {
		$fields = array(
			array( 'field' => 'order.total', 'label' => __( 'Order total', 'customer-tagger' ), 'type' => 'number', 'entity' => 'customer' ),
			array( 'field' => 'order.subtotal', 'label' => __( 'Order subtotal', 'customer-tagger' ), 'type' => 'number', 'entity' => 'customer' ),
			array( 'field' => 'order.item_count', 'label' => __( 'Order item count', 'customer-tagger' ), 'type' => 'number', 'entity' => 'customer' ),
			array( 'field' => 'order.coupon_used', 'label' => __( 'Coupon used', 'customer-tagger' ), 'type' => 'boolean', 'entity' => 'customer' ),
			array( 'field' => 'order.coupon_code', 'label' => __( 'Coupon code', 'customer-tagger' ), 'type' => 'string', 'entity' => 'customer' ),
			array( 'field' => 'order.payment_method', 'label' => __( 'Payment method', 'customer-tagger' ), 'type' => 'string', 'entity' => 'customer' ),
			array( 'field' => 'order.shipping_country', 'label' => __( 'Shipping country', 'customer-tagger' ), 'type' => 'string', 'entity' => 'customer' ),
			array( 'field' => 'order.contains_product', 'label' => __( 'Contains product ID', 'customer-tagger' ), 'type' => 'number', 'entity' => 'customer' ),
			array( 'field' => 'order.contains_category', 'label' => __( 'Contains category ID', 'customer-tagger' ), 'type' => 'number', 'entity' => 'customer' ),
			array( 'field' => 'order.utm_source', 'label' => __( 'UTM source', 'customer-tagger' ), 'type' => 'string', 'entity' => 'customer' ),
			array( 'field' => 'customer.order_count', 'label' => __( 'Lifetime order count', 'customer-tagger' ), 'type' => 'number', 'entity' => 'customer' ),
			array( 'field' => 'customer.lifetime_spend', 'label' => __( 'Lifetime spend', 'customer-tagger' ), 'type' => 'number', 'entity' => 'customer' ),
			array( 'field' => 'customer.average_order', 'label' => __( 'Average order value', 'customer-tagger' ), 'type' => 'number', 'entity' => 'customer' ),
			array( 'field' => 'customer.first_order', 'label' => __( 'Is first order', 'customer-tagger' ), 'type' => 'boolean', 'entity' => 'customer' ),
			array( 'field' => 'customer.last_order_days', 'label' => __( 'Days since last order', 'customer-tagger' ), 'type' => 'number', 'entity' => 'customer' ),
			array( 'field' => 'customer.refund_count', 'label' => __( 'Refund count', 'customer-tagger' ), 'type' => 'number', 'entity' => 'customer' ),
			array( 'field' => 'customer.billing_country', 'label' => __( 'Billing country', 'customer-tagger' ), 'type' => 'string', 'entity' => 'customer' ),
			array( 'field' => 'customer.registered_days', 'label' => __( 'Days since registration', 'customer-tagger' ), 'type' => 'number', 'entity' => 'customer' ),
			array( 'field' => 'order.status', 'label' => __( 'Order status', 'customer-tagger' ), 'type' => 'string', 'entity' => 'order' ),
			array( 'field' => 'product.stock_status', 'label' => __( 'Stock status', 'customer-tagger' ), 'type' => 'string', 'entity' => 'product' ),
			array( 'field' => 'product.stock_quantity', 'label' => __( 'Stock quantity', 'customer-tagger' ), 'type' => 'number', 'entity' => 'product' ),
			array( 'field' => 'product.on_sale', 'label' => __( 'On sale', 'customer-tagger' ), 'type' => 'boolean', 'entity' => 'product' ),
		);

		$filtered = array_values(
			array_filter(
				$fields,
				static function ( array $f ) use ( $entity ): bool {
					return $f['entity'] === $entity || ( 'customer' === $entity && in_array( $f['field'], array( 'order.total', 'customer.lifetime_spend' ), true ) );
				}
			)
		);

		if ( 'customer' === $entity ) {
			$filtered = array_values(
				array_filter(
					$fields,
					static function ( array $f ): bool {
						return 0 === strpos( $f['field'], 'order.' ) || 0 === strpos( $f['field'], 'customer.' );
					}
				)
			);
		} elseif ( 'order' === $entity ) {
			$filtered = array_values(
				array_filter(
					$fields,
					static function ( array $f ): bool {
						return 0 === strpos( $f['field'], 'order.' );
					}
				)
			);
		} elseif ( 'product' === $entity ) {
			$filtered = array_values(
				array_filter(
					$fields,
					static function ( array $f ): bool {
						return 0 === strpos( $f['field'], 'product.' );
					}
				)
			);
		}

		return apply_filters( 'ct_condition_fields', $filtered, $entity );
	}
}
