<?php
/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Chronopost
 * @subpackage Chronopost/includes
 * @author     Adexos <contact@adexos.fr>
 */

require_once plugin_dir_path(dirname(__FILE__)) . 'includes/functions-chronopost-helpers.php';

class Chronopost_Activator
{

    /**
     * Short Description. (use period)
     *
     * Long Description.
     *
     * @since    1.0.0
     */
    public static function activate()
    {
        $chronopost_media_path = chrono_get_media_path();
        if (!is_dir($chronopost_media_path)) {
            // dir doesn't exist, make it
            mkdir($chronopost_media_path, 0777, true);
        }

	    // Cleanup
	    delete_transient('chrono_contracts');

        // Default settings
        $plugin_dir = plugin_dir_path( __DIR__ );
		$default_settings = json_decode(file_get_contents($plugin_dir . 'data/default_settings.json'), true);
		add_option('chronopost_settings', $default_settings);
    }
}
