<?php
/**
 * Public-facing functionality.
 *
 * @package Customer_Tagger
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Front-end hooks.
 */
class Customer_Tagger_Public {

	/**
	 * Register public hooks.
	 */
	public function init(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue front-end assets when needed.
	 */
	public function enqueue_assets(): void {
		// Enqueue when a feature requires front-end assets.
	}
}
