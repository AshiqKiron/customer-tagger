<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Customer_Tagger
 */

declare( strict_types=1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'customer_tagger_version' );
