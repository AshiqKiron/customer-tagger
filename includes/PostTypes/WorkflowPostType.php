<?php
/**
 * Workflow custom post type.
 *
 * @package Customer_Tagger
 */

declare( strict_types=1 );

namespace CT\PostTypes;

use CT\Storage\WorkflowRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers ct_workflow CPT (admin UI hidden; managed via plugin screens).
 */
class WorkflowPostType {

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register(): void {
		register_post_type(
			WorkflowRepository::POST_TYPE,
			array(
				'labels'              => array(
					'name' => __( 'Tag Workflows', 'customer-tagger' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => true,
				'capability_type'     => 'shop_order',
				'map_meta_cap'        => true,
				'supports'            => array( 'title', 'editor', 'custom-fields' ),
				'delete_with_user'    => false,
			)
		);
	}
}
