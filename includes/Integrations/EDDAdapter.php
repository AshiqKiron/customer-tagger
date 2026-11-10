<?php
/**
 * Easy Digital Downloads integration.
 *
 * @package Customer_Tagger
 */

declare( strict_types=1 );

namespace CT\Integrations;

use CT\Engine\WorkflowRunner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs customer workflows after EDD purchase.
 */
class EDDAdapter {

	public static function init(): void {
		add_action( 'edd_complete_purchase', array( __CLASS__, 'on_purchase' ), 20, 1 );
	}

	/**
	 * @param int $payment_id Payment ID.
	 */
	public static function on_purchase( $payment_id ): void {
		if ( ! function_exists( 'edd_get_payment' ) ) {
			return;
		}

		$payment = edd_get_payment( $payment_id );
		if ( ! $payment ) {
			return;
		}

		$user_id = (int) $payment->user_id;
		if ( $user_id < 1 ) {
			return;
		}

		WorkflowRunner::dispatch(
			'customer',
			'order_placed',
			array(
				'user_id' => $user_id,
				'meta'    => array( 'edd_payment_id' => (int) $payment_id ),
			)
		);
	}
}
