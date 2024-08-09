<?php

class Chronopost_Admin_Notices
{
	/**
	 * @var string
	 */
	public $plugin_name;
	/**
	 * @var string
	 */
	public $version;
	/**
	 * @var string
	 */
	public $max_db_version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param string $plugin_name The name of this plugin.
	 * @param string $version     The version of this plugin.
	 *
	 * @since    1.0.0
	 */
	public function __construct($plugin_name, $version)
	{
		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->max_db_version = '2.0.0';
	}

	public function admin_notices()
	{
		if ($this->need_update()) {
			$this->display_update_notice();
		}
	}

	private function need_update()
	{
		$installed_ver = get_option( "chrono_db_version", '1.0.0' );
		return version_compare($installed_ver, '2.0.0', '<');
	}

	private function display_update_notice()
	{
		include plugin_dir_path(__FILE__) . 'partials/chronopost-admin-notice-update.php';
	}

}
