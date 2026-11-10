<?php
/**
 * WordPress user registration trigger.
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
 * Customer registered workflows.
 */
class UserTriggers {

	public static function init(): void {
		add_action( 'user_register', array( __CLASS__, 'on_register' ), 20, 1 );
	}

	public static function on_register( int $user_id ): void {
		WorkflowRunner::schedule( 'customer', 'customer_registered', array( 'user_id' => $user_id ), 0 );
	}
}
