<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://www.adexos.fr
 * @since      1.0.0
 *
 * @package    Chronopost
 * @subpackage Chronopost/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Chronopost
 * @subpackage Chronopost/public
 * @author     Adexos <contact@adexos.fr>
 */
class Chronopost_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Chronopost_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Chronopost_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/chronopost-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Chronopost_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Chronopost_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_register_script('chronopost-leaflet', plugin_dir_url( __FILE__ ) . 'js/leaflet.js', array(), '2.0');
		wp_enqueue_script('chronopost-leaflet');
        wp_enqueue_style('chronopost-leaflet', plugin_dir_url( __FILE__ ) . 'css/leaflet/leaflet.css', array(), $this->version, 'all' );

		wp_register_script('chronopost-fancybox', CHRONO_PLUGIN_URL . 'public/vendor/fancybox/jquery.fancybox.min.js', array( 'jquery', 'woocommerce' ), '3.1.20');
		wp_enqueue_script('chronopost-fancybox');

		wp_enqueue_style('chronopost-fancybox', CHRONO_PLUGIN_URL . 'public/vendor/fancybox/jquery.fancybox.min.css');

		wp_enqueue_script( 'chronomap', plugin_dir_url( __FILE__ ) . 'js/chronomap.plugin.js', array( 'jquery', 'woocommerce', 'chronopost-fancybox', 'chronopost-leaflet' ), $this->version, false );

		wp_localize_script(
			'chronomap',
			'Chronomap',
			$this->get_localized_script('chronomap')
		);

		wp_enqueue_script( 'chronoprecise', plugin_dir_url( __FILE__ ) . 'js/chronoprecise.plugin.js', array( 'jquery', 'woocommerce', 'chronopost-fancybox' ), $this->version, false );

		wp_localize_script(
			'chronoprecise',
			'Chronoprecise',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'chrono_nonce' => wp_create_nonce( 'chronopost_ajax' ),
				'prev_week_txt' => __('Prev week', 'chronopost'),
				'next_week_txt' => __('Next week', 'chronopost'),
                'error_cant_reach_server' => __('An error occurred when fetching time slots. Please try again and contact us if the problem persists.', 'chronopost')
			)
		);

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/chronopost-public.js', array( 'jquery', 'woocommerce', 'chronopost-leaflet', 'chronomap', 'chronoprecise' ), $this->version, false );

	}

	public function get_localized_script($handle)
	{
		if ($handle === 'chronomap') {
			$localized_strings = array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'chrono_nonce' => wp_create_nonce( 'chronopost_ajax' ),
				'no_pickup_relay' => __('Select a pickup relay', 'chronopost'),
				'pickup_relay_edit_text' => __('Edit', 'chronopost'),
				'loading_txt' => __('Loading, please wait&hellip;', 'chronopost'),
				'day_mon' => __('Mon', 'chronopost'),
				'day_tue' => __('Tue', 'chronopost'),
				'day_wed' => __('Wed', 'chronopost'),
				'day_thu' => __('Thu', 'chronopost'),
				'day_fri' => __('Fri', 'chronopost'),
				'day_sat' => __('Sat', 'chronopost'),
				'day_sun' => __('Sun', 'chronopost'),
				'infos' => __('Infos', 'chronopost'),
				'opening_hours' => __('Opening hours', 'chronopost'),
				'closed' => __('Closed', 'chronopost'),
			);
		}

		return $localized_strings;
	}

}
