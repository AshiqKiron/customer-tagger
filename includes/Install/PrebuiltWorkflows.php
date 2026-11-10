<?php
/**
 * Default workflow templates.
 *
 * @package Customer_Tagger
 */

declare( strict_types=1 );

namespace CT\Install;

use CT\Storage\WorkflowRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Seeds starter workflows on activation.
 */
class PrebuiltWorkflows {

	public static function install(): void {
		if ( get_option( 'ct_prebuilt_installed' ) ) {
			return;
		}

		$templates = self::templates();
		foreach ( $templates as $tpl ) {
			WorkflowRepository::save_workflow(
				0,
				$tpl['title'],
				$tpl['config'],
				$tpl['meta']
			);
		}

		update_option( 'ct_prebuilt_installed', true );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function templates(): array {
		return array(
			array(
				'title'  => __( 'First-time buyer', 'customer-tagger' ),
				'meta'   => array(
					'entity'  => 'customer',
					'trigger' => 'order_placed',
					'active'  => true,
					'priority' => 10,
				),
				'config' => array(
					'version'    => 1,
					'conditions' => array(
						'op'    => 'AND',
						'rules' => array(
							array( 'field' => 'customer.first_order', 'operator' => 'eq', 'value' => true ),
						),
					),
					'actions'    => array(
						array( 'type' => 'add_tags', 'tags' => array( 'first-time-buyer' ) ),
					),
					'lifecycle'  => array(),
				),
			),
			array(
				'title'  => __( 'Repeat customer (3+ orders)', 'customer-tagger' ),
				'meta'   => array(
					'entity'  => 'customer',
					'trigger' => 'order_placed',
					'active'  => true,
					'priority' => 20,
				),
				'config' => array(
					'version'    => 1,
					'conditions' => array(
						'op'    => 'AND',
						'rules' => array(
							array( 'field' => 'customer.order_count', 'operator' => 'gte', 'value' => 3 ),
						),
					),
					'actions'    => array(
						array( 'type' => 'add_tags', 'tags' => array( 'repeat-customer' ) ),
					),
					'lifecycle'  => array(),
				),
			),
			array(
				'title'  => __( 'VIP lifetime spend', 'customer-tagger' ),
				'meta'   => array(
					'entity'  => 'customer',
					'trigger' => 'order_placed',
					'active'  => false,
					'priority' => 30,
				),
				'config' => array(
					'version'    => 1,
					'conditions' => array(
						'op'    => 'AND',
						'rules' => array(
							array( 'field' => 'customer.lifetime_spend', 'operator' => 'gte', 'value' => 1000 ),
						),
					),
					'actions'    => array(
						array( 'type' => 'add_tags', 'tags' => array( 'vip' ) ),
						array( 'type' => 'remove_tags', 'tags' => array( 'prospect' ) ),
					),
					'lifecycle'  => array(),
				),
			),
			array(
				'title'  => __( 'Dynamic coupon tag', 'customer-tagger' ),
				'meta'   => array(
					'entity'  => 'customer',
					'trigger' => 'order_placed',
					'active'  => false,
					'priority' => 40,
				),
				'config' => array(
					'version'    => 1,
					'conditions' => array(
						'op'    => 'AND',
						'rules' => array(
							array( 'field' => 'order.coupon_used', 'operator' => 'eq', 'value' => true ),
						),
					),
					'actions'    => array(
						array( 'type' => 'add_dynamic_tag', 'template' => 'used-coupon-{order.coupon_code}' ),
					),
					'lifecycle'  => array( 'remove_tags_after_days' => 35, 'cancel_expiry_on_rematch' => true ),
				),
			),
			array(
				'title'  => __( 'Refund watch', 'customer-tagger' ),
				'meta'   => array(
					'entity'  => 'customer',
					'trigger' => 'order_placed',
					'active'  => false,
					'priority' => 50,
				),
				'config' => array(
					'version'    => 1,
					'conditions' => array(
						'op'    => 'AND',
						'rules' => array(
							array( 'field' => 'customer.refund_count', 'operator' => 'gte', 'value' => 2 ),
						),
					),
					'actions'    => array(
						array( 'type' => 'add_tags', 'tags' => array( 'refund-watch' ) ),
					),
					'lifecycle'  => array(),
				),
			),
			array(
				'title'  => __( 'Lapsed buyer (daily)', 'customer-tagger' ),
				'meta'   => array(
					'entity'   => 'customer',
					'trigger'  => 'scheduled_daily',
					'run_mode' => 'scheduled',
					'active'   => false,
					'priority' => 60,
				),
				'config' => array(
					'version'    => 1,
					'conditions' => array(
						'op'    => 'AND',
						'rules' => array(
							array( 'field' => 'customer.last_order_days', 'operator' => 'gte', 'value' => 180 ),
						),
					),
					'actions'    => array(
						array( 'type' => 'add_tags', 'tags' => array( 'lapsed' ) ),
						array( 'type' => 'remove_tags', 'tags' => array( 'active-buyer' ) ),
					),
					'lifecycle'  => array( 'revert_when_conditions_false' => true ),
				),
			),
		);
	}
}
