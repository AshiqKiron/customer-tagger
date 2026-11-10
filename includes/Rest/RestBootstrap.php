<?php
/**
 * REST API routes.
 *
 * @package Customer_Tagger
 */

declare( strict_types=1 );

namespace CT\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers ct/v1 routes.
 */
class RestBootstrap {

	public static function register_routes(): void {
		WorkflowsController::register();
		TagsController::register();
		ToolsController::register();
	}
}
