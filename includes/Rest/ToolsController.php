<?php
/**
 * Tools REST API (backfill, export).
 *
 * @package Customer_Tagger
 */

declare( strict_types=1 );

namespace CT\Rest;

use CT\License\Limits;
use CT\Scheduler\ActionSchedulerBridge;
use CT\Storage\TagRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin tools endpoints.
 */
class ToolsController {

	public static function register(): void {
		register_rest_route(
			'ct/v1',
			'/backfill',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'start_backfill' ),
				'permission_callback' => array( WorkflowsController::class, 'can_manage' ),
			)
		);

		register_rest_route(
			'ct/v1',
			'/export',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'export_by_tag' ),
				'permission_callback' => array( WorkflowsController::class, 'can_manage' ),
			)
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 */
	public static function start_backfill( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! Limits::can_backfill() ) {
			return new \WP_REST_Response(
				array( 'message' => __( 'Backfill requires Pro (or enable ct_allow_free_backfill filter).', 'customer-tagger' ) ),
				403
			);
		}

		$params = $request->get_json_params();
		$state  = array(
			'page'   => 1,
			'after'  => isset( $params['after'] ) ? sanitize_text_field( (string) $params['after'] ) : '',
			'before' => isset( $params['before'] ) ? sanitize_text_field( (string) $params['before'] ) : '',
		);

		ActionSchedulerBridge::queue_backfill( $state );
		return new \WP_REST_Response( array( 'queued' => true ) );
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 */
	public static function export_by_tag( \WP_REST_Request $request ): \WP_REST_Response {
		$slug = TagRepository::sanitize_slug( (string) $request->get_param( 'tag' ) );
		if ( '' === $slug ) {
			return new \WP_REST_Response( array( 'message' => 'Tag required' ), 400 );
		}

		$user_ids = TagRepository::get_user_ids_by_tag( $slug );
		$rows     = array();

		foreach ( $user_ids as $uid ) {
			$user = get_userdata( $uid );
			if ( ! $user ) {
				continue;
			}
			$rows[] = array(
				'user_id' => $uid,
				'email'   => $user->user_email,
				'name'    => $user->display_name,
				'tags'    => implode( ',', TagRepository::get_user_tag_slugs( $uid ) ),
			);
		}

		return new \WP_REST_Response( array( 'customers' => $rows, 'count' => count( $rows ) ) );
	}
}
