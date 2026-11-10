<?php
/**
 * Order tag taxonomy for automated order tagging.
 *
 * @package Customer_Tagger
 */

declare( strict_types=1 );

namespace CT\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers shop_order_tag taxonomy when WooCommerce orders use posts.
 */
class OrderTagTaxonomy {

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ), 20 );
	}

	public static function register(): void {
		register_taxonomy(
			'shop_order_tag',
			apply_filters( 'ct_order_post_types', array( 'shop_order' ) ),
			array(
				'label'        => __( 'Order tags', 'customer-tagger' ),
				'public'       => false,
				'show_ui'      => true,
				'show_in_rest' => true,
				'hierarchical' => false,
			)
		);
	}
}
