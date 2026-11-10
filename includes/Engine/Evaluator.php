<?php
/**
 * Workflow condition evaluator.
 *
 * @package Customer_Tagger
 */

declare( strict_types=1 );

namespace CT\Engine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AND/OR rule evaluation.
 */
class Evaluator {

	/**
	 * @param array<string, mixed> $node Conditions node.
	 */
	public static function matches( array $node, Context $ctx ): bool {
		if ( empty( $node ) ) {
			return true;
		}

		if ( isset( $node['rules'] ) && is_array( $node['rules'] ) ) {
			$op    = strtoupper( (string) ( $node['op'] ?? 'AND' ) );
			$rules = $node['rules'];
			if ( 'OR' === $op ) {
				foreach ( $rules as $rule ) {
					if ( self::matches_rule( $rule, $ctx ) ) {
						return true;
					}
				}
				return false;
			}
			foreach ( $rules as $rule ) {
				if ( ! self::matches_rule( $rule, $ctx ) ) {
					return false;
				}
			}
			return true;
		}

		return self::matches_rule( $node, $ctx );
	}

	/**
	 * @param array<string, mixed> $rule Single rule or nested group.
	 */
	private static function matches_rule( array $rule, Context $ctx ): bool {
		if ( isset( $rule['rules'] ) ) {
			return self::matches( $rule, $ctx );
		}

		$field    = (string) ( $rule['field'] ?? '' );
		$operator = (string) ( $rule['operator'] ?? 'eq' );
		$expected = $rule['value'] ?? null;
		$list_op  = (string) ( $rule['list_op'] ?? '' );

		if ( $list_op && $ctx->order ) {
			return self::evaluate_line_items( $list_op, $field, $operator, $expected, $ctx );
		}

		if ( 'order.contains_product' === $field ) {
			$ctx->meta['rule_product_id'] = (int) $expected;
			$actual                       = self::resolve_field_value( $field, $ctx );
		} elseif ( 'order.contains_category' === $field ) {
			$ctx->meta['rule_category_id'] = (int) $expected;
			$actual                        = self::resolve_field_value( $field, $ctx );
		} else {
			$actual = self::resolve_field_value( $field, $ctx );
		}

		$actual = apply_filters( 'ct_evaluate_field', $actual, $field, $ctx );

		if ( in_array( $field, array( 'order.contains_product', 'order.contains_category' ), true ) ) {
			return (bool) $actual;
		}

		return self::compare( $actual, $operator, $expected );
	}

	/**
	 * @param mixed $actual   Actual value.
	 * @param mixed $expected Expected value.
	 */
	private static function compare( $actual, string $operator, $expected ): bool {
		switch ( $operator ) {
			case 'eq':
				return (string) $actual === (string) $expected;
			case 'neq':
				return (string) $actual !== (string) $expected;
			case 'gt':
				return (float) $actual > (float) $expected;
			case 'gte':
				return (float) $actual >= (float) $expected;
			case 'lt':
				return (float) $actual < (float) $expected;
			case 'lte':
				return (float) $actual <= (float) $expected;
			case 'in':
				$list = is_array( $expected ) ? $expected : array_map( 'trim', explode( ',', (string) $expected ) );
				return in_array( (string) $actual, array_map( 'strval', $list ), true );
			case 'not_in':
				$list = is_array( $expected ) ? $expected : array_map( 'trim', explode( ',', (string) $expected ) );
				return ! in_array( (string) $actual, array_map( 'strval', $list ), true );
			case 'contains':
				return false !== strpos( strtolower( (string) $actual ), strtolower( (string) $expected ) );
			case 'empty':
				return '' === $actual || null === $actual || ( is_array( $actual ) && empty( $actual ) );
			case 'regex':
				return 1 === preg_match( (string) $expected, (string) $actual );
			default:
				return false;
		}
	}

	/**
	 * @param mixed $expected Expected value.
	 */
	private static function evaluate_line_items( string $list_op, string $field, string $operator, $expected, Context $ctx ): bool {
		if ( ! $ctx->order ) {
			return false;
		}

		$results = array();
		foreach ( $ctx->order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}
			$tmp           = clone $ctx;
			$tmp->product    = $product;
			$tmp->product_id = $product->get_id();
			$results[]       = self::compare( self::resolve_field_value( $field, $tmp ), $operator, $expected );
		}

		if ( empty( $results ) ) {
			return 'none_line_items' === $list_op;
		}

		switch ( $list_op ) {
			case 'any_line_item':
				return in_array( true, $results, true );
			case 'all_line_items':
				return ! in_array( false, $results, true );
			case 'none_line_items':
				return ! in_array( true, $results, true );
			default:
				return false;
		}
	}

	/**
	 * @return mixed
	 */
	public static function resolve_field_value( string $field, Context $ctx ) {
		if ( 0 === strpos( $field, 'customer.' ) ) {
			$key = substr( $field, 9 );
			return $ctx->customer_stats[ $key ] ?? null;
		}

		if ( 0 === strpos( $field, 'order.' ) && $ctx->order ) {
			$key = substr( $field, 6 );
			switch ( $key ) {
				case 'total':
					return (float) $ctx->order->get_total();
				case 'subtotal':
					return (float) $ctx->order->get_subtotal();
				case 'item_count':
					return $ctx->order->get_item_count();
				case 'coupon_used':
					return count( $ctx->order->get_coupon_codes() ) > 0;
				case 'coupon_code':
					$codes = $ctx->order->get_coupon_codes();
					return $codes[0] ?? '';
				case 'coupon_codes':
					return $ctx->order->get_coupon_codes();
				case 'payment_method':
					return $ctx->order->get_payment_method();
				case 'shipping_country':
					return $ctx->order->get_shipping_country();
				case 'status':
					return $ctx->order->get_status();
				case 'contains_product':
					return null;
				case 'contains_category':
					return null;
				case 'utm_source':
					return $ctx->order->get_meta( '_wc_order_attribution_utm_source' )
						?: $ctx->order->get_meta( 'utm_source' );
				default:
					return null;
			}
		}

		if ( 'order.contains_product' === $field && $ctx->order ) {
			$product_id = (int) ( $ctx->meta['rule_product_id'] ?? 0 );
			foreach ( $ctx->order->get_items() as $item ) {
				if ( (int) $item->get_product_id() === $product_id ) {
					return true;
				}
			}
			return false;
		}

		if ( 'order.contains_category' === $field && $ctx->order ) {
			$cat_id = (int) ( $ctx->meta['rule_category_id'] ?? 0 );
			foreach ( $ctx->order->get_items() as $item ) {
				$terms = wp_get_post_terms( (int) $item->get_product_id(), 'product_cat', array( 'fields' => 'ids' ) );
				if ( in_array( $cat_id, $terms, true ) ) {
					return true;
				}
			}
			return false;
		}

		if ( 0 === strpos( $field, 'product.' ) && $ctx->product ) {
			$key = substr( $field, 8 );
			switch ( $key ) {
				case 'stock_status':
					return $ctx->product->get_stock_status();
				case 'stock_quantity':
					return $ctx->product->get_stock_quantity();
				case 'on_sale':
					return $ctx->product->is_on_sale();
			}
		}

		return null;
	}
}
