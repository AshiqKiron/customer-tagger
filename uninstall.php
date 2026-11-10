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

require_once plugin_dir_path( __FILE__ ) . 'includes/autoload.php';

CT\Database\Schema::drop_tables();

$workflows = get_posts(
	array(
		'post_type'      => 'ct_workflow',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);

foreach ( $workflows as $post_id ) {
	wp_delete_post( (int) $post_id, true );
}

delete_option( 'customer_tagger_version' );
delete_option( 'ct_db_version' );
delete_option( 'ct_settings' );
delete_option( 'ct_prebuilt_installed' );
delete_option( 'ct_pro_license_active' );
