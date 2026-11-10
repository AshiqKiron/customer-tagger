<?php
/**
 * Tags REST API.
 *
 * @package Customer_Tagger
 */

declare( strict_types=1 );

namespace CT\Rest;

use CT\Storage\TagRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tag dictionary endpoints.
 */
class TagsController {

	public static function register(): void {
		register_rest_route(
			'ct/v1',
			'/tags',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'list_tags' ),
					'permission_callback' => array( WorkflowsController::class, 'can_manage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'create_tag' ),
					'permission_callback' => array( WorkflowsController::class, 'can_manage' ),
				),
			)
		);
	}

	public static function list_tags(): \WP_REST_Response {
		return new \WP_REST_Response( TagRepository::get_all_tags() );
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 */
	public static function create_tag( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params();
		$slug   = TagRepository::sanitize_slug( (string) ( $params['slug'] ?? '' ) );
		$label  = sanitize_text_field( (string) ( $params['label'] ?? $slug ) );
		if ( '' === $slug ) {
			return new \WP_REST_Response( array( 'message' => 'Invalid slug' ), 400 );
		}

		$id = TagRepository::get_or_create_tag( $slug, $label );
		return new \WP_REST_Response( array( 'id' => $id, 'slug' => $slug ) );
	}
}
