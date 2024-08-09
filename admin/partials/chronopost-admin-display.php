<?php
/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://www.adexos.fr
 * @since      1.0.0
 *
 * @package    Chronopost
 * @subpackage Chronopost/admin/partials
 */
class Chronopost_Admin_Display {

	function __construct() {
		//add_action( 'admin_init', array( $this, 'chronopost_add_thickbox' ) );
		add_action( 'admin_menu', array( $this, 'chronopost_add_admin_menu' ) );
		add_action( 'admin_menu', array( $this, 'chronopost_settings_init' ) );
		add_action( 'admin_menu', array( $this, 'chronopost_imports_init' ) );
		add_action( 'admin_notices', array( $this, 'chronopost_admin_config_notice' ) );
		add_action( 'admin_notices', array( $this, 'chronopost_admin_delayed_errors' ) );
		add_filter( 'pre_update_option_chronopost_settings', array( $this, 'check_settings' ), 10, 2 );
		add_action( 'update_option_chronopost_settings', array( $this, 'delete_contract_transients' ), 10, 2 );
		add_filter( 'set-screen-option', [ $this, 'daily_dockets_set_screen_option' ], 10, 3 );

	}

	public function delete_contract_transients( $old_value, $value ) {
		foreach ( $old_value['general']['accounts'] as $account ) {
			delete_transient( 'contract_infos_' . $account['number'] );
		}
		foreach ( $value['general']['accounts'] as $account ) {
			delete_transient( 'contract_infos_' . $account['number'] );
		}
	}

	public function check_settings( $chrono_settings ) {
		$accounts       = $chrono_settings['general']['accounts'];
		$valid_accounts = array();
		foreach ( $accounts as $key => $account ) {
			$account_status = chrono_check_login( $account['number'], $account['password'] );
			$chrono_settings['general']['accounts'][ $key ]['status'] = $account_status['status'];
		}

		if ( $chrono_settings['insurance']['enable'] == 'yes' && $chrono_settings['insurance']['min_amount'] == '' ) {
			$_SESSION['alert_msg']                  = __( 'You must define a minimun amount in order to enabled the insurance.', 'chronopost' );
			$chrono_settings['insurance']['enable'] = 'no';
		}

		return $chrono_settings;
	}

	static function get_default_values() {
		return array(
			'contract' => array(
				1 => array(
					'number'     => '19869502',
					'label'      => __( 'TEST Contract', 'chronopost' ),
					'subaccount' => null,
					'password'   => '255562',
				),
			),
			'address'  => array(),
		);
	}

	function chronopost_admin_config_notice() {
		if ( isset($_SESSION) && array_key_exists( 'alert_msg', $_SESSION ) ) {
				add_thickbox();
				$msg = $_SESSION['alert_msg'];
				echo chrono_notice(
					$msg, 'error', true, array(
						'width'  => 320,
						'height' => 200,
						'title'  => __( 'Chronopost notification about weight', 'chronopost' ),
					)
				);
			if ( ! array_key_exists( 'chronoaction', $_GET ) ) {
					unset( $_SESSION['alert_msg'] );
			}
		}

		if ( ! ( array_key_exists( 'page', $_GET ) && $_GET['page'] == 'chronopost' ) && ( ! chronopost_is_configured() || ! chronopost_methods_is_configured() ) ) {
			?>
					 <div class="updated notice is-dismissible chronopost-notice">
							<h2 class="full-width"><?php _e( 'Thank you for using the Chronopost plugin for WooCommerce!', 'chronopost' ); ?></h2>
							<ol>
								<li><?php echo sprintf( __( 'Don\'t forget to <a href="%s">configure your Chronopost Settings.</a>', 'chronopost' ), admin_url( 'admin.php?page=chronopost' ) ); ?></li>
								<li><?php echo sprintf( __( 'Start <a href="%s">configuring your Chronopost delivery methods</a>', 'chronopost' ), admin_url( 'admin.php?page=wc-settings&tab=shipping&section=chrono10' ) ); ?></li>
								<li><?php _e( 'Enjoy our plugin ;)', 'chronopost' ); ?></li>
							 </ol>
							 <p><em><?php _e( 'The Chronopost Team', 'chronopost' ); ?></em></p>
					 </div>
					 <?php
						chronopost_methods_is_configured();

						/* Delete transient, only display this notice once. */
						delete_transient( 'fx-admin-notice-example' );
		}
		if ( isset( $_SESSION['label_error'] ) && ! isset( $_GET['chronoaction'] ) ) {
			echo chrono_notice( $_SESSION['label_error'], 'error', false );
			unset( $_SESSION['label_error'] );
		}
	}

	function chronopost_admin_delayed_errors() {
		if ( $error_message = get_transient( 'chronopost_admin_error' ) ) {
			echo chrono_notice( $error_message, 'error' );
			delete_transient( 'chronopost_admin_error' );
		}
	}

	function chronopost_add_admin_menu() {
		add_menu_page(
			'chronopost',
			'Chronopost',
			'manage_woocommerce',
			'chronopost',
			array( $this, 'chronopost_options_page' ),
			plugin_dir_url( dirname( __FILE__ ) ) . 'img/icon.png'
		);

		add_submenu_page(
			'chronopost',
			__( 'Shipments', 'chronopost' ),
			__( 'Shipments', 'chronopost' ),
			'manage_woocommerce',
			'chronopost-shipping',
			array( $this, 'chronopost_shipping' )
		);

		add_submenu_page(
			'chronopost',
			__( 'Import trackings', 'chronopost' ),
			__( 'Import trackings', 'chronopost' ),
			'manage_woocommerce',
			'chronopost-imports',
			array( $this, 'chronopost_imports' )
		);

		$daily_docket_hook = add_submenu_page(
			'chronopost',
			__( 'Daily docket', 'chronopost' ),
			__( 'Daily docket', 'chronopost' ),
			'manage_woocommerce',
			'chronopost-daily-docket',
			array( $this, 'chronopost_daily_docket' )
		);
		add_action( "load-$daily_docket_hook", [ $this, 'daily_dockets_screen_option' ] );

		add_submenu_page(
			'chronopost',
			__( 'Export CSS', 'chronopost' ),
			__( 'Export CSS', 'chronopost' ),
			'manage_woocommerce',
			'chronopost-exports',
			array( $this, 'chronopost_exports' )
		);
	}

	function daily_dockets_set_screen_option( $status, $option, $value ) {
		if ( 'chrono_order_per_page' == $option ) {
			update_user_option(wp_get_current_user()->ID, 'chrono_order_per_page', $value);
			return $value;
		}
	}

	function daily_dockets_screen_option() {
		$option = 'per_page';
		$args   = [
			'label'   => __( 'Orders per page', 'chronopost' ),
			'default' => 10,
			'max'     => 100,
			'option'  => 'chrono_order_per_page',
		];

		add_screen_option( $option, $args );
	}

	function chronopost_settings_init() {

		register_setting( 'chronopost_optionpage', 'chronopost_settings' );

		add_settings_section(
			'chronopost_main_settings_section',
			false,
			array( $this, 'chronopost_main_settings_intro' ),
			'chronopost_optionpage'
		);

		add_settings_section(
			'chronopost_main_account_section',
			__( 'Accounts configuration', 'chronopost' ),
			array( $this, 'chronopost_account_render' ),
			'chronopost_optionpage'
		);

		/*add_settings_field(
			'chronopost_account_settings',
			__( 'Accounts settings', 'chronopost' ),
			array($this, 'chronopost_account_render'),
			'chronopost_optionpage',
			'chronopost_main_account_section'
		);*/

		add_settings_section(
			'chronopost_shipper_section',
			__( 'Shipper address', 'chronopost' ),
			array( $this, 'start_section' ),
			'chronopost_optionpage'
		);

		add_settings_field(
			'chronopost_shipper_civility',
			__( 'Civility', 'chronopost' ),
			array( $this, 'chronopost_shipper_civility_render' ),
			'chronopost_optionpage',
			'chronopost_shipper_section'
		);

		add_settings_field(
			'chronopost_shipper_name',
			__( 'Name', 'chronopost' ),
			array( $this, 'chronopost_shipper_name_render' ),
			'chronopost_optionpage',
			'chronopost_shipper_section'
		);

		add_settings_field(
			'chronopost_shipper_name2',
			__( 'Name 2', 'chronopost' ),
			array( $this, 'chronopost_shipper_name2_render' ),
			'chronopost_optionpage',
			'chronopost_shipper_section'
		);

		add_settings_field(
			'chronopost_shipper_address',
			__( 'Address', 'chronopost' ),
			array( $this, 'chronopost_shipper_address_render' ),
			'chronopost_optionpage',
			'chronopost_shipper_section'
		);

		add_settings_field(
			'chronopost_shipper_address2',
			__( 'Address 2', 'chronopost' ),
			array( $this, 'chronopost_shipper_address2_render' ),
			'chronopost_optionpage',
			'chronopost_shipper_section'
		);

		add_settings_field(
			'chronopost_shipper_zipcode',
			__( 'Zipcode', 'chronopost' ),
			array( $this, 'chronopost_shipper_zipcode_render' ),
			'chronopost_optionpage',
			'chronopost_shipper_section'
		);

		add_settings_field(
			'chronopost_shipper_city',
			__( 'City', 'chronopost' ),
			array( $this, 'chronopost_shipper_city_render' ),
			'chronopost_optionpage',
			'chronopost_shipper_section'
		);

		add_settings_field(
			'chronopost_shipper_country',
			__( 'Country', 'chronopost' ),
			array( $this, 'chronopost_shipper_country_render' ),
			'chronopost_optionpage',
			'chronopost_shipper_section'
		);

		add_settings_field(
			'chronopost_shipper_contactname',
			__( 'Contact name', 'chronopost' ),
			array( $this, 'chronopost_shipper_contactname_render' ),
			'chronopost_optionpage',
			'chronopost_shipper_section'
		);

		add_settings_field(
			'chronopost_shipper_email',
			__( 'Email', 'chronopost' ),
			array( $this, 'chronopost_shipper_email_render' ),
			'chronopost_optionpage',
			'chronopost_shipper_section'
		);

		add_settings_field(
			'chronopost_shipper_phone',
			__( 'Phone', 'chronopost' ),
			array( $this, 'chronopost_shipper_phone_render' ),
			'chronopost_optionpage',
			'chronopost_shipper_section'
		);

		add_settings_field(
			'chronopost_shipper_mobile',
			__( 'Mobile', 'chronopost' ),
			array( $this, 'chronopost_shipper_mobile_render' ),
			'chronopost_optionpage',
			'chronopost_shipper_section'
		);

		add_settings_section(
			'chronopost_customer_section',
			__( 'Billing address', 'chronopost' ),
			array( $this, 'start_section' ),
			'chronopost_optionpage'
		);

		add_settings_field(
			'chronopost_customer_civility',
			__( 'Civility', 'chronopost' ),
			array( $this, 'chronopost_customer_civility_render' ),
			'chronopost_optionpage',
			'chronopost_customer_section'
		);

		add_settings_field(
			'chronopost_customer_name',
			__( 'Name', 'chronopost' ),
			array( $this, 'chronopost_customer_name_render' ),
			'chronopost_optionpage',
			'chronopost_customer_section'
		);

		add_settings_field(
			'chronopost_customer_name2',
			__( 'Name 2', 'chronopost' ),
			array( $this, 'chronopost_customer_name2_render' ),
			'chronopost_optionpage',
			'chronopost_customer_section'
		);

		add_settings_field(
			'chronopost_customer_address',
			__( 'Address', 'chronopost' ),
			array( $this, 'chronopost_customer_address_render' ),
			'chronopost_optionpage',
			'chronopost_customer_section'
		);

		add_settings_field(
			'chronopost_customer_address2',
			__( 'Address 2', 'chronopost' ),
			array( $this, 'chronopost_customer_address2_render' ),
			'chronopost_optionpage',
			'chronopost_customer_section'
		);

		add_settings_field(
			'chronopost_customer_zipcode',
			__( 'Zipcode', 'chronopost' ),
			array( $this, 'chronopost_customer_zipcode_render' ),
			'chronopost_optionpage',
			'chronopost_customer_section'
		);

		add_settings_field(
			'chronopost_customer_city',
			__( 'City', 'chronopost' ),
			array( $this, 'chronopost_customer_city_render' ),
			'chronopost_optionpage',
			'chronopost_customer_section'
		);

		add_settings_field(
			'chronopost_customer_country',
			__( 'Country', 'chronopost' ),
			array( $this, 'chronopost_customer_country_render' ),
			'chronopost_optionpage',
			'chronopost_customer_section'
		);

		add_settings_field(
			'chronopost_customer_contactname',
			__( 'Contact name', 'chronopost' ),
			array( $this, 'chronopost_customer_contactname_render' ),
			'chronopost_optionpage',
			'chronopost_customer_section'
		);

		add_settings_field(
			'chronopost_customer_email',
			__( 'Email', 'chronopost' ),
			array( $this, 'chronopost_customer_email_render' ),
			'chronopost_optionpage',
			'chronopost_customer_section'
		);

		add_settings_field(
			'chronopost_customer_phone',
			__( 'Phone', 'chronopost' ),
			array( $this, 'chronopost_customer_phone_render' ),
			'chronopost_optionpage',
			'chronopost_customer_section'
		);

		add_settings_field(
			'chronopost_customer_mobile',
			__( 'Mobile', 'chronopost' ),
			array( $this, 'chronopost_customer_mobile_render' ),
			'chronopost_optionpage',
			'chronopost_customer_section'
		);

		add_settings_section(
			'chronopost_return_section',
			__( 'Return address', 'chronopost' ),
			array( $this, 'start_section' ),
			'chronopost_optionpage'
		);

		add_settings_field(
			'chronopost_return_civility',
			__( 'Civility', 'chronopost' ),
			array( $this, 'chronopost_return_civility_render' ),
			'chronopost_optionpage',
			'chronopost_return_section'
		);

		add_settings_field(
			'chronopost_return_name',
			__( 'Name', 'chronopost' ),
			array( $this, 'chronopost_return_name_render' ),
			'chronopost_optionpage',
			'chronopost_return_section'
		);

		add_settings_field(
			'chronopost_return_name2',
			__( 'Name 2', 'chronopost' ),
			array( $this, 'chronopost_return_name2_render' ),
			'chronopost_optionpage',
			'chronopost_return_section'
		);

		add_settings_field(
			'chronopost_return_address',
			__( 'Address', 'chronopost' ),
			array( $this, 'chronopost_return_address_render' ),
			'chronopost_optionpage',
			'chronopost_return_section'
		);

		add_settings_field(
			'chronopost_return_address2',
			__( 'Address 2', 'chronopost' ),
			array( $this, 'chronopost_return_address2_render' ),
			'chronopost_optionpage',
			'chronopost_return_section'
		);

		add_settings_field(
			'chronopost_return_zipcode',
			__( 'Zipcode', 'chronopost' ),
			array( $this, 'chronopost_return_zipcode_render' ),
			'chronopost_optionpage',
			'chronopost_return_section'
		);

		add_settings_field(
			'chronopost_return_city',
			__( 'City', 'chronopost' ),
			array( $this, 'chronopost_return_city_render' ),
			'chronopost_optionpage',
			'chronopost_return_section'
		);

		add_settings_field(
			'chronopost_return_country',
			__( 'Country', 'chronopost' ),
			array( $this, 'chronopost_return_country_render' ),
			'chronopost_optionpage',
			'chronopost_return_section'
		);

		add_settings_field(
			'chronopost_return_contactname',
			__( 'Contact name', 'chronopost' ),
			array( $this, 'chronopost_return_contactname_render' ),
			'chronopost_optionpage',
			'chronopost_return_section'
		);

		add_settings_field(
			'chronopost_return_email',
			__( 'Email', 'chronopost' ),
			array( $this, 'chronopost_return_email_render' ),
			'chronopost_optionpage',
			'chronopost_return_section'
		);

		add_settings_field(
			'chronopost_return_phone',
			__( 'Phone', 'chronopost' ),
			array( $this, 'chronopost_return_phone_render' ),
			'chronopost_optionpage',
			'chronopost_return_section'
		);

		add_settings_field(
			'chronopost_return_mobile',
			__( 'Mobile', 'chronopost' ),
			array( $this, 'chronopost_return_mobile_render' ),
			'chronopost_optionpage',
			'chronopost_return_section'
		);

		add_settings_section(
			'chronopost_skybill_section',
			__( 'Printing options', 'chronopost' ),
			false,
			'chronopost_optionpage'
		);

		add_settings_field(
			'chronopost_skybill_mode',
			__( 'Print Mode', 'chronopost' ),
			array( $this, 'chronopost_skybill_mode_render' ),
			'chronopost_optionpage',
			'chronopost_skybill_section'
		);

		add_settings_section(
			'chronopost_bal_option',
			__( 'Mailbox delivery option', 'chronopost' ),
			false,
			'chronopost_optionpage'
		);

		add_settings_field(
			'chronopost_bal_option_enable',
			__( 'Enable bal option', 'chronopost' ),
			array( $this, 'chronopost_bal_option_enable_render' ),
			'chronopost_optionpage',
			'chronopost_bal_option'
		);

		add_settings_section(
			'chronopost_saturday_shipping_option',
			__( 'Saturday shipping', 'chronopost' ),
			false,
			'chronopost_optionpage'
		);

		add_settings_field(
			'chronopost_saturday_shipping_begin_slot',
			__( 'Slot when saturday option is enabled', 'chronopost' ),
			array( $this, 'chronopost_saturday_shipping_begin_slot_render' ),
			'chronopost_optionpage',
			'chronopost_saturday_shipping_option'
		);

		add_settings_section(
			'chronopost_insurance_option',
			__( 'Ad Valorem Insurance', 'chronopost' ),
			false,
			'chronopost_optionpage'
		);

		add_settings_field(
			'chronopost_insurance_option_enable',
			__( 'Enable Ad Volorem Insurance', 'chronopost' ),
			array( $this, 'chronopost_insurance_option_enable_render' ),
			'chronopost_optionpage',
			'chronopost_insurance_option'
		);

		add_settings_field(
			'chronopost_insurance_amount',
			__( 'Minimum amount to be insured', 'chronopost' ),
			array( $this, 'chronopost_insurance_amount_render' ),
			'chronopost_optionpage',
			'chronopost_insurance_option'
		);

		/* Corsica Supplement */
		add_settings_section(
			'chronopost_corsica_supplement',
			__( 'Corsica Supplement', 'chronopost' ),
			false,
			'chronopost_optionpage'
		);

		add_settings_field(
			'chronopost_corsica_supplement_amount',
			__( 'Supplement amount', 'chronopost' ),
			array( $this, 'chronopost_corsica_supplement_amount_render' ),
			'chronopost_optionpage',
			'chronopost_corsica_supplement'
		);
		/**********************/

		add_settings_section(
			'chronopost_css_export_settings',
			__( 'CSS Export Settings', 'chronopost' ),
			false,
			'chronopost_optionpage'
		);

		add_settings_field(
			'chronopost_css_file_ext',
			__( 'File Extension', 'chronopost' ),
			array( $this, 'chronopost_css_file_ext_render' ),
			'chronopost_optionpage',
			'chronopost_css_export_settings'
		);

		add_settings_field(
			'chronopost_css_file_charset',
			__( 'File charset', 'chronopost' ),
			array( $this, 'chronopost_css_file_charset_render' ),
			'chronopost_optionpage',
			'chronopost_css_export_settings'
		);

		add_settings_field(
			'chronopost_css_eol_ext',
			__( 'End of line character', 'chronopost' ),
			array( $this, 'chronopost_css_eol_render' ),
			'chronopost_optionpage',
			'chronopost_css_export_settings'
		);

		add_settings_field(
			'chronopost_css_field_delimiter',
			__( 'Field delimiter', 'chronopost' ),
			array( $this, 'chronopost_css_field_delimiter_render' ),
			'chronopost_optionpage',
			'chronopost_css_export_settings'
		);

		add_settings_field(
			'chronopost_css_field_separator',
			__( 'Field separator', 'chronopost' ),
			array( $this, 'chronopost_css_field_separator_render' ),
			'chronopost_optionpage',
			'chronopost_css_export_settings'
		);
		add_action('pre_update_option_chronopost_imports', array($this, 'process_trackings_file'), 10, 3);

		/**********************/

		add_settings_section(
			'chronopost_dropoff_settings',
			__( 'Dropoff Settings', 'chronopost' ),
			false,
			'chronopost_optionpage'
		);

		add_settings_field(
			'chronopost_css_eol_ext',
			__( 'Display mode for drop-offs', 'chronopost' ),
			array($this, 'chronopost_ws_dropoff_type_render'),
			'chronopost_optionpage',
			'chronopost_dropoff_settings'
		);
	}

	function start_section() {
		?>
		<button class="clean-section button button-primary"><?php echo __( 'Reset form data', 'chronopost' ); ?></button>
		<?php
	}

	function chronopost_insurance_option_enable_render() {
		$wp_locale = new WP_Locale;
		?>
		<select name='chronopost_settings[insurance][enable]'>
			<option value="no"<?php echo chrono_get_option( 'enable', 'insurance' ) == 'no' ? ' selected="selected"' : ''; ?>><?php _e( 'No', 'chronopost' ); ?></option>
			<option value="yes"<?php echo chrono_get_option( 'enable', 'insurance' ) == 'yes' ? ' selected="selected"' : ''; ?>><?php _e( 'Yes', 'chronopost' ); ?></option>
		</select>
		<?php
	}

	function chronopost_saturday_shipping_begin_slot_render() {
		$wp_locale = new WP_Locale;
		$startday  = chrono_get_option( 'startday', 'saturday_slot', 4 );
		$endday    = chrono_get_option( 'endday', 'saturday_slot', 5 );
		$starttime = chrono_get_option( 'starttime', 'saturday_slot', '15:00' );
		$endtime   = chrono_get_option( 'endtime', 'saturday_slot', '18:00' );
		?>
		<table class="saturday-slot-table">
			<colgroup>
				<col style="width:4%">
				<col style="width:45%">
				<col style="width:4%;">
				<col style="width:45%;">
			</colgroup>
			<tbody>
				<tr data-id="1">
						<td><?php _e( 'From', 'chronopost' ); ?></td>
						<td>
							<select name="chronopost_settings[saturday_slot][startday]">
									<?php foreach ( $wp_locale->weekday as $key => $day ) : ?>
										<option value="<?php echo $key; ?>"<?php echo $key == $startday ? ' selected="selected"' : ''; ?>><?php echo $day; ?></option>
									<?php endforeach; ?>
							</select>
							<input type="text" name="chronopost_settings[saturday_slot][starttime]" value="<?php echo $starttime; ?>" class="small-text timepicker" tabindex="-1">
						</td>
						<td><?php _e( 'To', 'chronopost' ); ?></td>
						<td>
							<select name="chronopost_settings[saturday_slot][endday]">
									<?php foreach ( $wp_locale->weekday as $key => $day ) : ?>
										<option value="<?php echo $key; ?>"<?php echo $key == $endday ? ' selected="selected"' : ''; ?>><?php echo $day; ?></option>
									<?php endforeach; ?>
							</select>
							<input type="text" name="chronopost_settings[saturday_slot][endtime]" value="<?php echo $endtime; ?>" class="small-text timepicker" tabindex="0">
						</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	function chronopost_saturday_shipping_end_slot_render() {

	}

	function chronopost_main_settings_intro() {
		?>
		<div class="chronopost-intro">
			<p>
				<?php _e( 'Offer to your customers the first Express delivery service with the official Chronopost module for WooCommerce. With Chronopost, your customer will have the choice of the main delivery modes within 24h : at home,  at a Pickup point or at the office !', 'chronopost' ); ?></strong>
			</p><p>
				<?php _e( 'Your customers will also have the rdv service :  They are notified by email or SMS the day before the delivery and can reschedule the delivery or ask to be delivered at a pickup point among more than 17 000 points (post offices, Pickup relay or Chronopost agencies).', 'chronopost' ); ?>
			</p><p>
				<?php _e( 'Expand your business internationally with Chronopost international delivery service which is included in this module.', 'chronopost' ); ?>
			</p><p><strong>
				<?php _e( 'Find all these services in the Chronopost e-commerce pack : MyChrono. To activate the module on your site, contact us at ', 'chronopost' ); ?><a href="mailto:demandez.a.chronopost@chronopost.fr">demandez.a.chronopost@chronopost.fr</a>
				</strong>
			</p>
		</div>
		<?php
	}

	function chronopost_insurance_amount_render() {
		?>
		<input type='number' name='chronopost_settings[insurance][min_amount]' value='<?php echo chrono_get_option( 'min_amount', 'insurance' ); ?>'>
		<?php
	}

	function chronopost_bal_option_enable_render() {
		?>
		<select name='chronopost_settings[bal_option][enable]'>
			<option value="no"<?php echo chrono_get_option( 'enable', 'bal_option' ) == 'no' ? ' selected="selected"' : ''; ?>><?php _e( 'No', 'chronopost' ); ?></option>
			<option value="yes"<?php echo chrono_get_option( 'enable', 'bal_option' ) == 'yes' ? ' selected="selected"' : ''; ?>><?php _e( 'Yes', 'chronopost' ); ?></option>
		</select>
		<?php
	}

	function chronopost_css_file_charset_render() {
		?>
		<select name='chronopost_settings[css][file_charset]'>
			<option value="ISO-8859-1"<?php echo chrono_get_option( 'file_charset', 'css' ) == 'ISO-8859-1' ? ' selected="selected"' : ''; ?>>ISO-8859-1</option>
			<option value="UTF-8"<?php echo chrono_get_option( 'file_charset', 'css' ) == 'UTF-8' ? ' selected="selected"' : ''; ?>>UTF-8</option>
			<option value="ASCII-7"<?php echo chrono_get_option( 'file_charset', 'css' ) == 'ASCII-7' ? ' selected="selected"' : ''; ?>>ASCII-7 Bits</option>
		</select>
		<?php
	}

	function chronopost_css_eol_render() {
		?>
		<select name='chronopost_settings[css][eol]'>
			<option value="lf"<?php echo chrono_get_option( 'eol', 'css' ) == 'lf' ? ' selected="selected"' : ''; ?>>LF</option>
			<option value="cr"<?php echo chrono_get_option( 'eol', 'css' ) == 'cr' ? ' selected="selected"' : ''; ?>>CR</option>eol
			<option value="crlf"<?php echo chrono_get_option( 'eol', 'css' ) == 'crlf' ? ' selected="selected"' : ''; ?>>CR+LF</option>
		</select>
		<?php
	}

	function chronopost_css_field_delimiter_render() {
		?>
		<select name='chronopost_settings[css][field_delimiter]'>
			<option value="none"<?php echo chrono_get_option( 'field_delimiter', 'css' ) == 'none' ? ' selected="selected"' : ''; ?>><?php _e( 'None', 'chronopost' ); ?></option>
			<option value="simple_quote"<?php echo chrono_get_option( 'field_delimiter', 'css' ) == 'simple_quote' ? ' selected="selected"' : ''; ?>><?php _e( 'Simple quote', 'chronopost' ); ?></option>
			<option value="double_quotes"<?php echo chrono_get_option( 'field_delimiter', 'css' ) == 'double_quotes' ? ' selected="selected"' : ''; ?>><?php _e( 'Double quotes', 'chronopost' ); ?></option>
		</select>
		<?php
	}

	function chronopost_css_field_separator_render() {
		?>
		<select name='chronopost_settings[css][field_separator]'>
			<option value=";"<?php echo chrono_get_option( 'field_separator', 'css' ) == ';' ? ' selected="selected"' : ''; ?>><?php _e( 'Semicolon', 'chronopost' ); ?></option>
			<option value=","<?php echo chrono_get_option( 'field_separator', 'css' ) == ',' ? ' selected="selected"' : ''; ?>><?php _e( 'Comma', 'chronopost' ); ?></option>
		</select>
		<?php
	}

	function chronopost_css_file_ext_render() {
		?>
		<select name='chronopost_settings[css][file_extension]'>
			<option value=".txt"<?php echo chrono_get_option( 'file_extension', 'css' ) == '.txt' ? ' selected="selected"' : ''; ?>>.txt</option>
			<option value=".csv"<?php echo chrono_get_option( 'file_extension', 'css' ) == '.csv' ? ' selected="selected"' : ''; ?>>.csv</option>
			<option value=".chr"<?php echo chrono_get_option( 'file_extension', 'css' ) == '.chr' ? ' selected="selected"' : ''; ?>>.chr</option>
		</select>
		<?php
	}

	function chronopost_return_section_render() {
		$return_methods = array(
			Chronopost_Webservice::CHRONOPOST_REVERSE_R,
			Chronopost_Webservice::CHRONOPOST_REVERSE_S,
			Chronopost_Webservice::CHRONOPOST_REVERSE_T,
			Chronopost_Webservice::CHRONOPOST_REVERSE_U,
			Chronopost_Webservice::CHRONOPOST_REVERSE_RELAIS_EUROPE,
			Chronopost_Webservice::CHRONOPOST_REVERSE_TOSHOP
		);
		$selected = Chronopost_Webservice::CHRONOPOST_REVERSE_T;
		if (chrono_get_option('mode', 'default_return')) {
			$selected = chrono_get_option('mode', 'default_return');
		}

		?>
		<select name='chronopost_settings[default_return][mode]'>
		<?php foreach ($return_methods as $return_method) : ?>
			<option value="<?php echo $return_method ?>"
				<?php echo ($return_method === $selected) ? 'selected="selected"' : '' ?>
			>
				<?php echo Chronopost_Webservice::getReturnServiceLabel($return_method) ?>
			</option>
		<?php endforeach; ?>
		</select>
		<?php
	}

	function chronopost_skybill_mode_render() {
		?>
		<select name='chronopost_settings[skybill][mode]'>
			<option value="PDF"<?php echo chrono_get_option( 'mode', 'skybill' ) == 'PDF' ? ' selected="selected"' : ''; ?>><?php echo __( 'Print PDF Laser with proof.', 'chronopost' ); ?></option>
			<option value="SPD"<?php echo chrono_get_option( 'mode', 'skybill' ) == 'SPD' ? ' selected="selected"' : ''; ?>><?php echo __( 'Print PDF laser without proof', 'chronopost' ); ?></option>
			<option value="THE"<?php echo chrono_get_option( 'mode', 'skybill' ) == 'THE' ? ' selected="selected"' : ''; ?>><?php echo __( 'Print PDF thermal', 'chronopost' ); ?></option>
		</select>
		<?php
	}

	function chronopost_shipper_civility_render() {
		?>
		<select name='chronopost_settings[shipper][civility]'>
			<option value="M"<?php echo chrono_get_option( 'civility', 'shipper' ) == 'M' ? ' selected="selected"' : ''; ?>><?php echo __( 'Mr.', 'chronopost' ); ?></option>
			<option value="E"<?php echo chrono_get_option( 'civility', 'shipper' ) == 'E' ? ' selected="selected"' : ''; ?>><?php echo __( 'Mrs', 'chronopost' ); ?></option>
			<option value="L"<?php echo chrono_get_option( 'civility', 'shipper' ) == 'L' ? ' selected="selected"' : ''; ?>><?php echo __( 'Miss', 'chronopost' ); ?></option>
		</select>
		<?php
	}

	function chronopost_shipper_name_render() {
		?>
		<input type='text' name='chronopost_settings[shipper][name]' value='<?php echo chrono_get_option( 'name', 'shipper' ); ?>'>
		<?php
	}

	function chronopost_shipper_name2_render() {
		?>
		<input type='text' name='chronopost_settings[shipper][name2]' value='<?php echo chrono_get_option( 'name2', 'shipper' ); ?>'>
		<?php
	}

	function chronopost_shipper_address_render() {
		?>
		<input type='text' name='chronopost_settings[shipper][address]' value='<?php echo chrono_get_option( 'address', 'shipper' ); ?>'>
		<?php
	}

	function chronopost_shipper_address2_render() {
		?>
		<input type='text' name='chronopost_settings[shipper][address2]' value='<?php echo chrono_get_option( 'address2', 'shipper' ); ?>'>
		<?php
	}

	function chronopost_shipper_zipcode_render() {
		?>
		<input type='text' name='chronopost_settings[shipper][zipcode]' value='<?php echo chrono_get_option( 'zipcode', 'shipper' ); ?>'>
		<?php
	}

	function chronopost_shipper_contactname_render() {
		?>
		<input type='text' name='chronopost_settings[shipper][contactname]' value='<?php echo chrono_get_option( 'contactname', 'shipper' ); ?>'>
		<?php
	}

	function chronopost_shipper_email_render() {
		?>
		<input type='text' name='chronopost_settings[shipper][email]' value='<?php echo chrono_get_option( 'email', 'shipper' ); ?>'>
		<?php
	}

	function chronopost_shipper_phone_render() {
		?>
		<input type='text' name='chronopost_settings[shipper][phone]' value='<?php echo chrono_get_option( 'phone', 'shipper' ); ?>'>
		<?php
	}

	function chronopost_shipper_mobile_render() {
		?>
		<input type='text' name='chronopost_settings[shipper][mobile]' value='<?php echo chrono_get_option( 'mobile', 'shipper' ); ?>'>
		<?php
	}

	function chronopost_shipper_city_render() {
		?>
		<input type='text' name='chronopost_settings[shipper][city]' value='<?php echo chrono_get_option( 'city', 'shipper' ); ?>'>
		<?php
	}

	function chronopost_shipper_country_render() {
		?>
		<select name='chronopost_settings[shipper][country]'>
			<option value="FR"<?php echo chrono_get_option( 'country', 'shipper' ) == 'FR' ? ' selected="selected"' : ''; ?>>France Métropolitaine</option>
			<option value="GP"<?php echo chrono_get_option( 'country', 'shipper' ) == 'GP' ? ' selected="selected"' : ''; ?>>Guadeloupe</option>
			<option value="GF"<?php echo chrono_get_option( 'country', 'shipper' ) == 'GF' ? ' selected="selected"' : ''; ?>>Guyane</option>
			<option value="MQ"<?php echo chrono_get_option( 'country', 'shipper' ) == 'MQ' ? ' selected="selected"' : ''; ?>>Martinique</option>
			<option value="YT"<?php echo chrono_get_option( 'country', 'shipper' ) == 'YT' ? ' selected="selected"' : ''; ?>>Mayotte</option>
			<option value="RE"<?php echo chrono_get_option( 'country', 'shipper' ) == 'RE' ? ' selected="selected"' : ''; ?>>Réunion</option>
			<option value="MF"<?php echo chrono_get_option( 'country', 'shipper' ) == 'MF' ? ' selected="selected"' : ''; ?>>Saint-Martin</option>
		</select>
		<?php
	}

	function chronopost_account_render() {
		$accounts_values = chrono_get_option( 'accounts' );
		if ( ! $accounts_values ) {
			$default_values  = self::get_default_values();
			$accounts_values = $default_values['contract'];
		}
		?>
		<table class="form-table show-table">
			<tr>
				<td>
					<div class="chronopost-accounts-settings">
						<p class="hint"><?php _e( 'Put here all your Chronopost contracts. You can change the default contract for each shipping method in the Carrier settings page.', 'chronopost' ); ?></p>
						<script type="text/javascript">
							var chrono_alert_remove_contract = '<?php echo str_replace( "'", "\'", __( 'This will permanently delete this contract. Are you sure you want to proceed ?', 'chronopost' ) ); ?>';
						</script>
						<input type="hidden" name="chronopost_account_index" value="<?php echo max( array_keys( $accounts_values ) ); ?>" >
						<button style="display: none" id="chrono_remove_button_template" class="removeContract button button-delete"><?php echo __( 'Remove contract', 'chronopost' ); ?></button>
					   <?php foreach ( $accounts_values as $index => $account_settings ) : ?>
						<div class="chronopost-settings-account status-<?php echo isset( $account_settings['status'] ) ? $account_settings['status'] : ''; ?> <?php echo ( $index === 1 ) ? 'default' : ''; ?>">
							<p class="account-title"><?php echo __( 'Account #', 'chronopost' ) . '<span class="index">' . $index . '</span>'; ?></p>
							<table class="form-table show-table">
								<tr>
									<th scope="row"><?php echo __( 'Account number', 'chronopost' ); ?></th>
									<td><input class="account-number" type='text' name='chronopost_settings[general][accounts][<?php echo $index; ?>][number]' value='<?php echo $account_settings['number']; ?>'></td>
								</tr>
								<tr>
									<th scope="row"><?php echo __( 'Account label', 'chronopost' ); ?></th>
									<td><input class="account-label" type='text' name='chronopost_settings[general][accounts][<?php echo $index; ?>][label]' value='<?php echo $account_settings['label']; ?>'></td>
								</tr>
								<tr>
									<th scope="row"><?php echo __( 'Sub-account number', 'chronopost' ); ?></th>
									<td><input class="account-subaccount" type='text' name='chronopost_settings[general][accounts][<?php echo $index; ?>][subaccount]' value='<?php echo $account_settings['subaccount']; ?>'></td>
								</tr>
								<tr>
									<th scope="row"><?php echo __( 'Chronopost password', 'chronopost' ); ?></th>
									<td><input class="account-password" type='text' name='chronopost_settings[general][accounts][<?php echo $index; ?>][password]' value='<?php echo $account_settings['password']; ?>'></td>
								</tr>
								<tr class="contract-functions">
									<td class="contract-test"><button class="testWSLogin button button-secondary"><?php _e( 'Test my login datas', 'chronopost' ); ?></button>
										<span class="spinner"></span></td>
									<td class="contract-delete">
										<?php if ( $index !== 1 ) : ?>
											<button class="removeContract button button-delete"><?php echo __( 'Remove contract', 'chronopost' ); ?></button>
										<?php endif; ?>
									</td>
								</tr>
								<tr><td class="testWSLoginResult" colspan="2"></td></tr>
							</table>
						</div>
						<?php endforeach; ?>
					</div>

					<button class="addNewContract button button-primary"><?php echo __( 'Add a new contract', 'chronopost' ); ?></button>
				</td>
			</tr>
		</table>
		<?php
	}

	function chronopost_customer_civility_render() {
		?>
		<select name='chronopost_settings[customer][civility]'>
			<option value="M"<?php echo chrono_get_option( 'civility', 'customer' ) == 'M' ? ' selected="selected"' : ''; ?>><?php echo __( 'Mr.', 'chronopost' ); ?></option>
			<option value="E"<?php echo chrono_get_option( 'civility', 'customer' ) == 'E' ? ' selected="selected"' : ''; ?>><?php echo __( 'Mrs', 'chronopost' ); ?></option>
			<option value="L"<?php echo chrono_get_option( 'civility', 'customer' ) == 'L' ? ' selected="selected"' : ''; ?>><?php echo __( 'Miss', 'chronopost' ); ?></option>
		</select>
		<?php
	}

	function chronopost_customer_name_render() {
		?>
		<input type='text' name='chronopost_settings[customer][name]' value='<?php echo chrono_get_option( 'name', 'customer' ); ?>'>
		<?php
	}

	function chronopost_customer_name2_render() {
		?>
		<input type='text' name='chronopost_settings[customer][name2]' value='<?php echo chrono_get_option( 'name2', 'customer' ); ?>'>
		<?php
	}

	function chronopost_customer_address_render() {
		?>
		<input type='text' name='chronopost_settings[customer][address]' value='<?php echo chrono_get_option( 'address', 'customer' ); ?>'>
		<?php
	}

	function chronopost_customer_address2_render() {
		?>
		<input type='text' name='chronopost_settings[customer][address2]' value='<?php echo chrono_get_option( 'address2', 'customer' ); ?>'>
		<?php
	}

	function chronopost_customer_zipcode_render() {
		?>
		<input type='text' name='chronopost_settings[customer][zipcode]' value='<?php echo chrono_get_option( 'zipcode', 'customer' ); ?>'>
		<?php
	}

	function chronopost_customer_contactname_render() {
		?>
		<input type='text' name='chronopost_settings[customer][contactname]' value='<?php echo chrono_get_option( 'contactname', 'customer' ); ?>'>
		<?php
	}

	function chronopost_customer_email_render() {
		?>
		<input type='text' name='chronopost_settings[customer][email]' value='<?php echo chrono_get_option( 'email', 'customer' ); ?>'>
		<?php
	}

	function chronopost_customer_phone_render() {
		?>
		<input type='text' name='chronopost_settings[customer][phone]' value='<?php echo chrono_get_option( 'phone', 'customer' ); ?>'>
		<?php
	}

	function chronopost_customer_mobile_render() {
		?>
		<input type='text' name='chronopost_settings[customer][mobile]' value='<?php echo chrono_get_option( 'mobile', 'customer' ); ?>'>
		<?php
	}

	function chronopost_customer_city_render() {
		?>
		<input type='text' name='chronopost_settings[customer][city]' value='<?php echo chrono_get_option( 'city', 'customer' ); ?>'>
		<?php
	}

	function chronopost_customer_country_render() {
		?>
		<select name='chronopost_settings[customer][country]'>
			<option value="FR"<?php echo chrono_get_option( 'country', 'customer' ) == 'FR' ? ' selected="selected"' : ''; ?>>France Métropolitaine</option>
			<option value="GP"<?php echo chrono_get_option( 'country', 'customer' ) == 'GP' ? ' selected="selected"' : ''; ?>>Guadeloupe</option>
			<option value="GF"<?php echo chrono_get_option( 'country', 'customer' ) == 'GF' ? ' selected="selected"' : ''; ?>>Guyane</option>
			<option value="MQ"<?php echo chrono_get_option( 'country', 'customer' ) == 'MQ' ? ' selected="selected"' : ''; ?>>Martinique</option>
			<option value="YT"<?php echo chrono_get_option( 'country', 'customer' ) == 'YT' ? ' selected="selected"' : ''; ?>>Mayotte</option>
			<option value="RE"<?php echo chrono_get_option( 'country', 'customer' ) == 'RE' ? ' selected="selected"' : ''; ?>>Réunion</option>
			<option value="MF"<?php echo chrono_get_option( 'country', 'customer' ) == 'MF' ? ' selected="selected"' : ''; ?>>Saint-Martin</option>
		</select>
		<?php
	}

	function chronopost_return_civility_render() {
		?>
		<select name='chronopost_settings[return][civility]'>
			<option value="M"<?php echo chrono_get_option( 'civility', 'return' ) == 'M' ? ' selected="selected"' : ''; ?>><?php echo __( 'Mr.', 'chronopost' ); ?></option>
			<option value="E"<?php echo chrono_get_option( 'civility', 'return' ) == 'E' ? ' selected="selected"' : ''; ?>><?php echo __( 'Mrs', 'chronopost' ); ?></option>
			<option value="L"<?php echo chrono_get_option( 'civility', 'return' ) == 'L' ? ' selected="selected"' : ''; ?>><?php echo __( 'Miss', 'chronopost' ); ?></option>
		</select>
		<?php
	}

	function chronopost_return_name_render() {
		?>
		<input type='text' name='chronopost_settings[return][name]' value='<?php echo chrono_get_option( 'name', 'return' ); ?>'>
		<?php
	}

	function chronopost_return_name2_render() {
		?>
		<input type='text' name='chronopost_settings[return][name2]' value='<?php echo chrono_get_option( 'name2', 'return' ); ?>'>
		<?php
	}

	function chronopost_return_address_render() {
		?>
		<input type='text' name='chronopost_settings[return][address]' value='<?php echo chrono_get_option( 'address', 'return' ); ?>'>
		<?php
	}

	function chronopost_return_address2_render() {
		?>
		<input type='text' name='chronopost_settings[return][address2]' value='<?php echo chrono_get_option( 'address2', 'return' ); ?>'>
		<?php
	}

	function chronopost_return_zipcode_render() {
		?>
		<input type='text' name='chronopost_settings[return][zipcode]' value='<?php echo chrono_get_option( 'zipcode', 'return' ); ?>'>
		<?php
	}

	function chronopost_return_contactname_render() {
		?>
		<input type='text' name='chronopost_settings[return][contactname]' value='<?php echo chrono_get_option( 'contactname', 'return' ); ?>'>
		<?php
	}

	function chronopost_return_email_render() {
		?>
		<input type='text' name='chronopost_settings[return][email]' value='<?php echo chrono_get_option( 'email', 'return' ); ?>'>
		<?php
	}

	function chronopost_return_phone_render() {
		?>
		<input type='text' name='chronopost_settings[return][phone]' value='<?php echo chrono_get_option( 'phone', 'return' ); ?>'>
		<?php
	}

	function chronopost_return_mobile_render() {
		?>
		<input type='text' name='chronopost_settings[return][mobile]' value='<?php echo chrono_get_option( 'mobile', 'return' ); ?>'>
		<?php
	}

	function chronopost_return_city_render() {
		?>
		<input type='text' name='chronopost_settings[return][city]' value='<?php echo chrono_get_option( 'city', 'return' ); ?>'>
		<?php
	}

	function chronopost_return_country_render() {
		?>
		<select name='chronopost_settings[return][country]'>
			<option value="FR"<?php echo chrono_get_option( 'country', 'return' ) == 'FR' ? ' selected="selected"' : ''; ?>>France Métropolitaine</option>
			<option value="GP"<?php echo chrono_get_option( 'country', 'return' ) == 'GP' ? ' selected="selected"' : ''; ?>>Guadeloupe</option>
			<option value="GF"<?php echo chrono_get_option( 'country', 'return' ) == 'GF' ? ' selected="selected"' : ''; ?>>Guyane</option>
			<option value="MQ"<?php echo chrono_get_option( 'country', 'return' ) == 'MQ' ? ' selected="selected"' : ''; ?>>Martinique</option>
			<option value="YT"<?php echo chrono_get_option( 'country', 'return' ) == 'YT' ? ' selected="selected"' : ''; ?>>Mayotte</option>
			<option value="RE"<?php echo chrono_get_option( 'country', 'return' ) == 'RE' ? ' selected="selected"' : ''; ?>>Réunion</option>
			<option value="MF"<?php echo chrono_get_option( 'country', 'return' ) == 'MF' ? ' selected="selected"' : ''; ?>>Saint-Martin</option>
		</select>
		<?php
	}

	function chronopost_corsica_supplement_amount_render() {
		?>
		 <input type='number' step="0.01" name='chronopost_settings[corsica_supplement][amount]' value='<?php echo chrono_get_option( 'amount', 'corsica_supplement' ); ?>'/>
		 <?php
	}

	function chronopost_options_page() {
		?>
		<form action='options.php' method='post'>
			<h1>Chronopost</h1>
			<table class="form-table">
				<tbody>
					<tr><td><?php settings_fields( 'chronopost_optionpage' ); ?></td></tr>
					<?php
					do_settings_sections( 'chronopost_optionpage' );
					submit_button();
					?>
				</tbody>
			</table>

		</form>
		<?php
	}

	function chronopost_shipping() {
		global $wpdb;
		$prefixe = $wpdb->prefix;

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'partials/chronopost-admin-shipping.php';
	}

	function chronopost_imports() {
		?>
		 <div class="wrap">
			 <form action='options.php' method='post' enctype='multipart/form-data'>
				 <h1><?php echo __( 'Import trackings', 'chronopost' ); ?></h1>
				 <table class="form-table">
					 <tbody>
					 <tr><td><?php settings_fields( 'chronopost_imports_page' ); ?></td></tr>
					<?php
					do_settings_sections( 'chronopost_imports_page' );
					submit_button( __( 'Import tracking informations', 'chronopost' ) );
					?>
					 </tbody>
				 </table>
			 </form>
		 </div>
		 <?php
	}

	function chronopost_imports_init() {
		register_setting( 'chronopost_imports_page', 'chronopost_imports' );

		add_settings_section(
			'chronopost_imports_section',
			false,
			array( $this, 'chronopost_imports_render' ),
			'chronopost_imports_page'
		);
	}

	function chronopost_imports_render() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'partials/chronopost-admin-import-tracking.php';
	}

	function process_trackings_file( $value, $old_value, $option ) {
		$settings_page = 'chronopost_imports';

		if ( ! is_numeric( $value['general']['order_id_column'] ) || ! is_numeric( $value['general']['tracking_number_column'] ) ) {
			add_settings_error( $settings_page, 10, __( 'Please choose the order reference and tracking number columns number' ) );
			return $value;
		}

		if ( $_FILES['chronopost_tracking'] && $_FILES['chronopost_tracking']['size'] > 0 ) {
			$upload_feedback = false;
			$finfo           = new finfo( FILEINFO_MIME_TYPE );
			// If the uploaded file is the right format
			if ( false === $ext = array_search(
				$finfo->file( $_FILES['chronopost_tracking']['tmp_name'] ),
				array(
					'text' => 'text/plain',
					'csv'  => 'text/csv',
				),
				true
			) ) {
				$upload_feedback = __( 'Please upload only CSV files.', 'chronopost' );
				add_settings_error( $settings_page, 10, $upload_feedback );
			}

			if ( $upload_feedback === false && isset( $_FILES['chronopost_tracking']['tmp_name'] ) && $_FILES['chronopost_tracking']['error'] === 0 ) {
				$updated_trackings = 0;
				$handle            = fopen( $_FILES['chronopost_tracking']['tmp_name'], 'r' );
				while ( $row = fgetcsv( $handle, 0, ';' ) ) {
					$order_id = $row[ $value['general']['order_id_column'] - 1 ];
					if ( ! is_numeric( $order_id ) ) {
						continue;
					}
					$tracking_number = $row[ $value['general']['tracking_number_column'] - 1 ];
					$trackingNumbers = explode( ',', trim( $tracking_number, '[]' ) );
					if ( ! is_array( $trackingNumbers ) ) {
						$upload_feedback = __( 'Unable to find tracking numbers (wrong column ?)', 'chronopost' );
						add_settings_error( $settings_page, 10, $upload_feedback );
						continue;
					}

					$parcels = array();
					foreach ( $trackingNumbers as $tracking_number ) {
						$parcel                = new stdClass();
						$parcel->skybillNumber = $tracking_number;
						$parcel->imported      = true;
						$parcels[]             = $parcel;
						$updated_trackings ++;
					}
					$_order = new WC_Order( $order_id );
					WC_Chronopost_Order::add_tracking_numbers( $_order, $parcels );
				}
				add_settings_error( $settings_page, 100, sprintf( __( 'Tracking informations updated. Total processed : %s', 'chronopost' ), $updated_trackings ), 'updated' );
			} else {
				$upload_feedback = __( 'There was a problem with your upload.', 'chronopost' );
				add_settings_error( $settings_page, 20, $upload_feedback );
			}
		} else {
			$upload_feedback = __( 'No file was uploaded.', 'chronopost' );
			add_settings_error( $settings_page, 1, $upload_feedback );
		}
		return $value;
	}

	function chronopost_daily_docket() {
		global $wpdb;
		$prefixe = $wpdb->prefix;

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'partials/chronopost-admin-daily-docket.php';
	}

	function chronopost_exports() {
		global $wpdb;
		$prefixe = $wpdb->prefix;

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'partials/chronopost-admin-exports.php';
	}

	function chronopost_ws_dropoff_type_render() {
		$selected = chrono_get_option('type', 'dropoff');
		?>
		<select name='chronopost_settings[dropoff][type]'>
			<option value="P"<?= $selected == 'P' ? ' selected="selected"' : ''; ?>><?= __('Pick-up and automatic lockers', 'chronopost') ?></option>
			<option value="C"<?= $selected == 'C' ? ' selected="selected"' : ''; ?>><?= __('Only Pick-up', 'chronopost') ?></option>
		</select>
		<?php
	}
}

new Chronopost_Admin_Display();
