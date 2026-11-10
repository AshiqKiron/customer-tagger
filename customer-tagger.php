<?php
/**
 * Customer Tagger
 *
 * @package           Customer_Tagger
 * @author            Customer Tagger
 * @copyright         2026 Customer Tagger
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Customer Tagger
 * Plugin URI:        https://example.com/customer-tagger
 * Description:       Automate WooCommerce customer, order, and product tags with rule-based workflows.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Customer Tagger
 * Author URI:        https://example.com
 * Text Domain:       customer-tagger
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CUSTOMER_TAGGER_VERSION', '1.0.0' );
define( 'CUSTOMER_TAGGER_PLUGIN_FILE', __FILE__ );
define( 'CUSTOMER_TAGGER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CUSTOMER_TAGGER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CUSTOMER_TAGGER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once CUSTOMER_TAGGER_PLUGIN_DIR . 'includes/autoload.php';
require_once CUSTOMER_TAGGER_PLUGIN_DIR . 'includes/functions-api.php';
require_once CUSTOMER_TAGGER_PLUGIN_DIR . 'includes/class-customer-tagger.php';

/**
 * Begins execution of the plugin.
 */
function customer_tagger_run(): void {
	$plugin = new Customer_Tagger();
	$plugin->run();
}

customer_tagger_run();
