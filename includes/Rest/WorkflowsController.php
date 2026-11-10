<?php
/**
 * Workflows REST API.
 *
 * @package Customer_Tagger
 */

declare( strict_types=1 );

namespace CT\Rest;

use CT\Engine\FieldRegistry;
use CT\Engine\WorkflowRunner;
use CT\License\Limits;
use CT\Storage\LogRepository;
use CT\Storage\WorkflowRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD + preview for workflows.
 */
class WorkflowsController {

	public static function register(): void {
		register_rest_route(
			'ct/v1',
			'/workflows',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'list_workflows' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'create_workflow' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			'ct/v1',
			'/workflows/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'get_workflow' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
				array(
					'methods'             => 'PUT,PATCH',
					'callback'            => array( __CLASS__, 'update_workflow' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( __CLASS__, 'delete_workflow' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			'ct/v1',
			'/workflows/(?P<id>\d+)/preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'preview' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			'ct/v1',
			'/fields',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'list_fields' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			'ct/v1',
			'/logs',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'list_logs' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);
	}

	public static function can_manage(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	public static function list_workflows(): \WP_REST_Response {
		$all = get_posts(
			array(
				'post_type'      => WorkflowRepository::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 100,
			)
		);

		$data = array();
		foreach ( $all as $post ) {
			$config = json_decode( $post->post_content, true );
			$data[] = array(
				'id'          => $post->ID,
				'title'       => $post->post_title,
				'active'      => 'publish' === $post->post_status,
				'entity'      => get_post_meta( $post->ID, '_ct_entity', true ),
				'trigger'     => get_post_meta( $post->ID, '_ct_trigger', true ),
				'priority'    => (int) get_post_meta( $post->ID, '_ct_priority', true ),
				'delay_seconds' => (int) get_post_meta( $post->ID, '_ct_delay_seconds', true ),
				'run_mode'    => get_post_meta( $post->ID, '_ct_run_mode', true ),
				'config'      => is_array( $config ) ? $config : array(),
			);
		}

		return new \WP_REST_Response( $data );
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 */
	public static function get_workflow( \WP_REST_Request $request ): \WP_REST_Response {
		$id   = (int) $request['id'];
		$post = get_post( $id );
		if ( ! $post || WorkflowRepository::POST_TYPE !== $post->post_type ) {
			return new \WP_REST_Response( array( 'message' => 'Not found' ), 404 );
		}

		$config = json_decode( $post->post_content, true );
		return new \WP_REST_Response(
			array(
				'id'     => $id,
				'title'  => $post->post_title,
				'active' => 'publish' === $post->post_status,
				'entity' => get_post_meta( $id, '_ct_entity', true ),
				'trigger' => get_post_meta( $id, '_ct_trigger', true ),
				'config' => is_array( $config ) ? $config : array(),
				'meta'   => array(
					'priority'        => (int) get_post_meta( $id, '_ct_priority', true ),
					'delay_seconds'   => (int) get_post_meta( $id, '_ct_delay_seconds', true ),
					'run_mode'        => get_post_meta( $id, '_ct_run_mode', true ),
				),
			)
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 */
	public static function create_workflow( \WP_REST_Request $request ): \WP_REST_Response {
		return self::save_from_request( 0, $request );
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 */
	public static function update_workflow( \WP_REST_Request $request ): \WP_REST_Response {
		return self::save_from_request( (int) $request['id'], $request );
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 */
	private static function save_from_request( int $id, \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}

		$active = ! empty( $params['active'] );
		if ( $active && ! Limits::can_activate_workflow() && $id < 1 ) {
			return new \WP_REST_Response(
				array( 'message' => __( 'Free plan workflow limit reached.', 'customer-tagger' ) ),
				403
			);
		}

		$title  = sanitize_text_field( (string) ( $params['title'] ?? __( 'New workflow', 'customer-tagger' ) ) );
		$config = is_array( $params['config'] ?? null ) ? $params['config'] : array();
		$meta   = is_array( $params['meta'] ?? null ) ? $params['meta'] : array();

		$meta['active']         = $active;
		$meta['entity']         = sanitize_key( (string) ( $params['entity'] ?? 'customer' ) );
		$meta['trigger']        = sanitize_key( (string) ( $params['trigger'] ?? 'order_placed' ) );
		$meta['priority']       = (int) ( $meta['priority'] ?? 10 );
		$meta['delay_seconds']  = (int) ( $meta['delay_seconds'] ?? $params['delay_seconds'] ?? 0 );
		$meta['run_mode']       = sanitize_key( (string) ( $meta['run_mode'] ?? 'realtime' ) );

		$saved = WorkflowRepository::save_workflow( $id, $title, $config, $meta );
		if ( $saved < 1 ) {
			return new \WP_REST_Response( array( 'message' => 'Save failed' ), 500 );
		}

		return new \WP_REST_Response( array( 'id' => $saved ), 200 );
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 */
	public static function delete_workflow( \WP_REST_Request $request ): \WP_REST_Response {
		$id = (int) $request['id'];
		wp_delete_post( $id, true );
		return new \WP_REST_Response( array( 'deleted' => true ) );
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 */
	public static function preview( \WP_REST_Request $request ): \WP_REST_Response {
		$id       = (int) $request['id'];
		$params   = $request->get_json_params();
		$order_id = is_array( $params ) ? (int) ( $params['order_id'] ?? 0 ) : 0;
		$post     = get_post( $id );
		if ( ! $post ) {
			return new \WP_REST_Response( array( 'message' => 'Not found' ), 404 );
		}

		$config = json_decode( $post->post_content, true );
		$workflow = array(
			'id'     => $id,
			'config' => is_array( $config ) ? $config : array(),
		);

		$match = WorkflowRunner::preview( $workflow, $order_id );
		return new \WP_REST_Response( array( 'matches' => $match ) );
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 */
	public static function list_fields( \WP_REST_Request $request ): \WP_REST_Response {
		$entity = sanitize_key( (string) $request->get_param( 'entity' ) );
		if ( '' === $entity ) {
			$entity = 'customer';
		}
		return new \WP_REST_Response( FieldRegistry::get_fields( $entity ) );
	}

	public static function list_logs(): \WP_REST_Response {
		return new \WP_REST_Response( LogRepository::get_recent( 100 ) );
	}
}
