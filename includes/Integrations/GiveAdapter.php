<?php
/**
 * GiveWP integration.
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
 * Runs customer workflows after donation complete.
 */
class GiveAdapter {

	public static function init(): void {
		add_action( 'give_complete_donation', array( __CLASS__, 'on_donation' ), 20, 1 );
	}

	/**
	 * @param int $payment_id Donation payment ID.
	 */
	public static function on_donation( $payment_id ): void {
		$user_id = function_exists( 'give_get_payment_user_id' ) ? (int) give_get_payment_user_id( $payment_id ) : 0;
		if ( $user_id < 1 && function_exists( 'give_get_payment_meta' ) ) {
			$email = give_get_payment_meta( $payment_id, '_give_payment_donor_email', true );
			if ( $email ) {
				$user = get_user_by( 'email', $email );
				if ( $user ) {
					$user_id = (int) $user->ID;
				}
			}
		}

		if ( $user_id < 1 ) {
			return;
		}

		WorkflowRunner::dispatch(
			'customer',
			'order_placed',
			array(
				'user_id' => $user_id,
				'meta'    => array( 'give_payment_id' => (int) $payment_id ),
			)
		);
	}
}
