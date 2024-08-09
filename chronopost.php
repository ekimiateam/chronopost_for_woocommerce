<?php

/**
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://www.adexos.fr
 * @since             1.0.0
 * @package           Chronopost
 *
 * @wordpress-plugin
 * Plugin Name:       Chronopost
 * Plugin URI:        https://www.chronopost.fr/
 * Description:       Chronopost shipping methods for WooCommerce
 * Version:           2.0.0
 * Author:            Adexos
 * Author URI:        https://www.adexos.fr
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       chronopost
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define('CHRONO_PLUGIN_PATH', plugin_dir_path( __FILE__ ));
define('CHRONO_PLUGIN_URL', plugin_dir_url( __FILE__ ));

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-chronopost-activator.php
 */
function activate_chronopost() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-chronopost-activator.php';
	Chronopost_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-chronopost-deactivator.php
 */
function deactivate_chronopost() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-chronopost-deactivator.php';
	Chronopost_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_chronopost' );
register_deactivation_hook( __FILE__, 'deactivate_chronopost' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-chronopost-core.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_chronopost() {

	require_once plugin_dir_path( __FILE__ ) . 'includes/deprecated.php';
	require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

	$chronopost = new Chronopost();
	$chronopost->run();

}

/**
 * Check if WooCommerce is active
 */

 if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	run_chronopost();
 }
