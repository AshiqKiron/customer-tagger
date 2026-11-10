<?php
/**
 * FluentCRM tag sync.
 *
 * @package Customer_Tagger
 */

declare( strict_types=1 );

namespace CT\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps customer tags into FluentCRM when enabled.
 */
class FluentCRM {

	public static function sync_tags( int $user_id, array $tags ): void {
		$settings = get_option( 'ct_settings', array() );
		if ( empty( $settings['fluentcrm_sync'] ) ) {
			return;
		}

		if ( ! function_exists( 'FluentCrmApi' ) ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user || ! $user->user_email ) {
			return;
		}

		try {
			$api      = FluentCrmApi( 'contacts' );
			$contact  = $api->getContactByUserRef( $user_id );
			if ( ! $contact ) {
				$contact = $api->createOrUpdate(
					array(
						'email'   => $user->user_email,
						'user_id' => $user_id,
					)
				);
			}

			if ( $contact && method_exists( $contact, 'attachTags' ) ) {
				$contact->attachTags( $tags );
			}
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			unset( $e );
		}
	}
}
