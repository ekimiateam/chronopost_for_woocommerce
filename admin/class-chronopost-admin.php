<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://www.adexos.fr
 * @since      1.0.0
 *
 * @package    Chronopost
 * @subpackage Chronopost/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Chronopost
 * @subpackage Chronopost/admin
 * @author     Adexos <contact@adexos.fr>
 */
class Chronopost_Admin
{
	/**
	 * @var array
	 */
	private static $shipping_zones;

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
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct($plugin_name, $version)
	{
		$this->plugin_name = $plugin_name;
		$this->version = $version;

		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/chronopost-admin-display.php';
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles()
	{

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


		wp_enqueue_style('wickedpicker_css', plugin_dir_url(__FILE__) . 'vendor/wickedpicker/dist/wickedpicker.min.css', array(), $this->version, 'all');
		wp_enqueue_style('woocommerce_admin', plugins_url().'/woocommerce/assets/css/admin.css', $this->version, 'all');
		wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/chronopost-admin.css', array(), $this->version, 'all');
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts()
	{

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
		$wp_locale = new WP_Locale;
		wp_enqueue_style('woocommerce_admin_styles');
		wp_enqueue_script('wickedpicker_js', plugin_dir_url(__FILE__) . 'vendor/wickedpicker/dist/wickedpicker.min.js', array( 'jquery' ), $this->version, false);
		wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/chronopost-admin.js', array( 'jquery', 'wickedpicker_js' ), $this->version, false);
		wp_localize_script(
			'chronopost',
			'Chronopost',
			array(
				'ajaxurl' => admin_url('admin-ajax.php'),
				'chrono_nonce' => wp_create_nonce('chronopost_ajax'),
				'select_time' => __('Select a time', 'chronopost'),
				'weekday' => ($wp_locale->weekday),
				'to' => __('To', 'chronopost'),
				'from' => __('From', 'chronopost'),
				'min_weight' => __('Min weight', 'chronopost'),
				'max_weight' => __('Max weight', 'chronopost'),
				'shipping_rate' => __('Shipping Rate', 'chronopost'),
				'delete_rate' => __('Delete selected rates', 'chronopost'),
				'add_rate' => __('Add new rate', 'chronopost'),
				'alert_method_not_allowed' => __('You can\'t enable this product with this contract. Please contact us for more informations.', 'chronopost'),
			)
		);
	}

	/**
	 * Run all upgrade scripts
	 */
	public function update_db_check() {
		$installed_ver = get_option( "chrono_db_version", '1.0.0' );

		if (version_compare($installed_ver, '1.1.0', '<')) {
			Chronopost_Admin_Upgrades::upgrade_1_1_0();
		}

		if (isset($_GET['chronopost_do_upgrade']) && $_GET['chronopost_do_upgrade'] === '2.0.0'
				&& version_compare($installed_ver, '2.0.0', '<')) {
			Chronopost_Admin_Upgrades::upgrade_2_0_0();
		}

		if (isset($_GET['chronopost_skip_upgrade'])) {
			$this->skip_upgrade((string)$_GET['chronopost_skip_upgrade']);
		}

	}

	protected function skip_upgrade($version)
	{
		update_option( "chrono_db_version", $version );
	}

	/**
	 *  Add a custom email to the list of emails WooCommerce should load
	 *
	 * @since 0.1
	 * @param array $email_classes available email classes
	 * @return array filtered available email classes
	 */
	public function add_return_label_woocommerce_email($email_classes)
	{
		require_once CHRONO_PLUGIN_PATH. 'includes/class-chronopost-return-email.php';

		// add the email class to the list of email classes that WooCommerce loads
		$email_classes['WC_Return_Label_Email'] = new WC_Return_Label_Email();

		return $email_classes;
	}

	public function chrono_order_meta_box($order_id)
	{
		$screen = get_current_screen();
		if ($screen->action != 'add') {
			add_meta_box(
				'chrono_meta_box',
				__('Chronopost', 'chronopost'),
				array($this, 'chrono_order_meta_box_callback'),
				'shop_order',
				'side'
			);
		}
	}

	public function chrono_order_meta_box_callback($order)
	{
		global $wpdb;
		$track_list = array();

		$is_chronopost_method = false;

		$_order = new WC_Order($order->ID);

		$order_shipping_method = $_order->get_shipping_methods();
		// Find the shipping instance
		$order_shipping_instance = null;
		foreach ($_order->get_shipping_methods() as $item_shipping_method) {
			$order_shipping_instance = $item_shipping_method->get_instance_id();
			break;
		}

		if ($order_shipping_method) {
			$shipping_method = reset($order_shipping_method);
			$shipping_method_id = $shipping_method->get_method_id();
			$shippingMethodAllow = array_keys(get_option('chronopost_shipping_methods'));
			$is_chronopost_method = in_array($shipping_method_id, $shippingMethodAllow);
		}

		if ($is_chronopost_method) {
			// Display the right box
			wp_nonce_field('actions_mr_meta_box', 'shipment_list_nonce');
			$shipment_datas = chrono_get_shipment_datas($order->ID); ?>
			<div id="order-<?php echo $order->ID; ?>">
				<span class="spinner"></span>
				<small class="meta"><em><?php echo $shipping_method->get_name(); ?></em></small>
			</div>
			<table style="margin-top: 10px" class="wp-list-table widefat fixed chrono-order-settings">
				<tbody>
				<tr>
					<td colspan="2">
						<h4 style="text-align:center"><?php _e('Shipment', 'chronopost') ?></h4>
						<div>
							<?php if ($shipment_datas):  ?>
								<?php foreach ($shipment_datas as $shipment): ?>
									<?php foreach($shipment['_parcels'] as $parcel): ?>
										<?php
										$track_list[] = '<a target="_blank" href="' . chrono_get_tracking_url($parcel['_skybill_number'], $shipping_method_id, $order_shipping_instance) .'">'. $parcel['_skybill_number'] .'</a>';
										?>
									<?php endforeach; ?>
								<?php endforeach; ?>
								<div class="chrono-tracklist">
									<?php echo implode(', ', $track_list); ?>
								</div>
								<a class="button button-small chrono-print" target="_blank" href="<?php echo admin_url('admin.php?post='.$_order->get_id().'&action=edit&chronoaction=view-label&order='.$_order->get_id().'&shipment_nonce='.wp_create_nonce('shipment_list_nonce')); ?>"><?php echo _n('Download label', 'Download labels', count($shipment_datas), 'chronopost'); ?></a>
							<?php endif; ?>
						</div>
					</td>
				</tr>
				<tr>
					<td class="manage-colum"><?php _e("Use contract", 'chronopost') ?></td>
					<td class="column-contract">
						<?php
						$shipping_method_contract = '';
						$shipping_method_instance = chrono_get_shipping_method_by_instance_id($order_shipping_instance);
						if (isset($shipping_method_instance->instance_settings['contract'])) {
							$shipping_method_contract = $shipping_method_instance->instance_settings['contract'];
						}
						$contracts = chrono_get_all_contracts();
						// Pourrait être surchargé par l'utilisateur
						$order_contract = get_post_meta( $_order->get_id(), '_use_contract', true);
						if ($order_contract) {
							$shipping_method_contract = $order_contract;
						}
						?>
						<select name="use-contract" data-order-id="<?php echo $_order->get_id(); ?>" <?php echo $shipment_datas ? 'disabled="disabled"' : '' ?>>
							<?php foreach ($contracts as $contract): ?>
								<option value="<?php echo $contract['number'] ?>"
									<?php echo ($shipping_method_contract == $contract['number']) ? 'selected="selected"' : ''?>>
									<?php echo $contract['label'] ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<?php if (chrono_get_option('enable', 'insurance') == 'yes'): ?>
					<?php
					$forceDisableInsurance = false;
					$insurance_enable = get_post_meta($_order->get_id(), '_insurance_enable', true) == '' ? 'yes' : get_post_meta($_order->get_id(), '_insurance_enable', true);

					$adValoremAmount = (float)chrono_get_option('min_amount', 'insurance');
					if ($_order->get_subtotal() < $adValoremAmount) {
						$insurance_enable = 'no';
						$forceDisableInsurance = true;
					}

                    // 2S can't be insured
                    if ($shipping_method_id === 'chronotoshopdirect' || $shipping_method_id === 'chronotoshopeurope') {
                        $insurance_enable = 'no';
                        $forceDisableInsurance = true;
                    }

					$totalAdValorem = chrono_get_advalorem_amount( $_order );
					?>
					<tr>
						<td class="manage-colum column-order_date"><?php _e("Advalorem insurance", 'chronopost') ?></td>
						<td class="insurance-enable">
							<select name="insurance[enable]"
									data-order-id="<?php echo $_order->get_id(); ?>"
									data-action="update_insurance_enable"
									<?php echo $forceDisableInsurance ? 'disabled="disabled"' : '' ?>
							>
								<option value="no"<?php echo $insurance_enable == 'no' ? ' selected="selected"' : ''; ?>><?php _e('No', 'chronopost'); ?></option>
								<option value="yes"<?php echo $insurance_enable == 'yes' ? ' selected="selected"' : ''; ?>><?php _e('Yes', 'chronopost'); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<td class="manage-colum column-order_date"><?php _e('Insurance amount', 'chronopost'); ?></td>
						<td class="insurance-amount">
							<input type="number" value="<?php echo ($totalAdValorem / 100); ?>" step="0.01"
								   id="insurance_amount" name="insurance[amount]"
									<?php echo $forceDisableInsurance ? 'disabled="disabled"' : '' ?>
								   data-order-id="<?php echo $_order->get_id(); ?>">
						</td>
					</tr>
				<?php endif; ?>
				<tr>
					<td class="manage-colum column-order_date"><?php _e("Ship On Saturday", 'chronopost') ?></td>
					<td class="ship-on-saturday">
						<?php
						$saturday_active = chrono_get_method_settings($shipping_method_id, $order_shipping_instance, 'deliver_on_saturday') == 'yes' ? true : false;
						$ship_saturday = get_post_meta( $_order->get_id(), '_ship_on_saturday', true);
						$user_defined = get_post_meta( $_order->get_id(), '_ship_on_saturday_user_defined', true);

						if ($ship_saturday == '' && $user_defined !== 'yes') {
							$ship_saturday = chrono_is_sending_day() ? 'yes' : 'no';
						}
						?>
						<?php if ($saturday_active && !chrono_is_shipping_methods_without_saturday($shipping_method_id)): ?>
							<select name="ship-saturday" data-order-id="<?php echo $_order->get_id(); ?>" data-action="update_saturday_shipping"<?php echo $user_defined === 'yes' ? ' disabled="disabled"' : ''; ?>>
								<option value="no"<?php echo $ship_saturday == 'no' ? ' selected="selected"' : ''; ?>><?php _e('No', 'chronopost'); ?></option>
								<option value="yes"<?php echo $ship_saturday == 'yes' ? ' selected="selected"' : ''; ?>><?php _e('Yes', 'chronopost'); ?></option>
							</select>
						<?php else: ?>
							<?php _e('Not active', 'chronopost'); ?>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td class="manage-colum column-order_date"><?php _e("Parcels number", 'chronopost') ?></td>
					<td class="parcels-number">
						<input name="parcels" type="number" value="<?php echo chrono_get_parcels_number($_order->get_id()) ?>"
                               <?php if (chrono_is_toshop($shipping_method)):  ?>
                               disabled="disabled" max="1"
                               <?php endif; ?>
							   min="1" data-action="update_parcels"
							   data-order-id="<?php echo $_order->get_id(); ?>" />
					</td>
				</tr>
				<tr>
					<td class="parcels-dimensions column-dimensions parcels-settings" colspan="2">
						<div class="title"><?php _e("Parcels dimensions", 'chronopost') ?></div>
						<?php
						$dimensions = array('weight', 'height', 'length', 'width');
						$weight_unit = chrono_get_weight_unit();
						$defaultWeight = Chronopost_Package::getTotalWeight($_order->get_items(), false);
						$parcels_dimensions = chrono_get_parcels_dimensions($_order->get_id());
						if (!$parcels_dimensions) {
							$parcels_dimensions = array( 1 => array(
								'weight' => $defaultWeight,
								'height' => 1,
								'length' => 1,
								'width' => 1
							));
						}
						?>
						<?php foreach ($parcels_dimensions as $i => $parcel_dimensions): ?>
							<div class="package-dimensions <?php echo $i == 1 ? 'default' : '' ?>">
								<?php foreach ($parcel_dimensions as $dimension => $value) : ?>
									<?php
									if ($dimension === 'weight') {
										$max_weight = $shipping_method->get_method_id() == 'chronorelais' || $shipping_method->get_method_id() == 'chronorelaiseurope' || $shipping_method->get_method_id() == 'chronotoshopeurope' || $shipping_method->get_method_id() == 'chronorelaisdom' ? 20 : 30;
										if ($weight_unit === 'g') {
											$max_weight = $max_weight * 1000;
											$value = $value * 1000;
										}
									}
									?>
									<label>
                                        <span>
                                            <?php echo ucfirst(__($dimension, 'chronopost')) ?>
											<?php if ($dimension === 'weight'): ?>
												(<?php echo $weight_unit; ?>)
											<?php endif; ?>
                                        </span>
										<div class="sub-field">
											<input name="parcels_dimensions[1][<?php echo $dimension ?>]" type="number" class="default"
												   placeholder="<?php echo ucfirst(__($dimension, 'chronopost')) ?>"
												   value="<?php echo $value ?>"
												<?php echo $dimension == 'weight' ? 'step=".1" max="' . $max_weight . '"' : ''; ?>
												   data-order-id="<?php echo $_order->get_id(); ?>" />
										</div>
									</label>
								<?php endforeach; ?>
							</div>
						<?php endforeach; ?>
					</td>
				</tr>
				<tr>
					<td style="text-align:center" colspan="2">
						<a class="button button-primary chrono-generate-label" data-order-id="<?php echo $_order->get_id(); ?>" href="<?php echo admin_url('admin.php?post='.$_order->get_id().'&action=edit&chronoaction=print-label&order='.$_order->get_id().'&shipment_nonce='.wp_create_nonce('shipment_list_nonce')); ?>"><?php echo __('Generate label', 'chronopost'); ?></a>
					</td>
				</tr>
				</tbody>
			</table>
			<?php if ($shipment_datas): ?>
				<table style="margin-top: 10px" class="wp-list-table widefat fixed chrono-order-settings">
					<tbody>
					<tr>
						<td colspan="2" style="text-align:center">
							<h4><?php _e('Return label', 'chronopost') ?></h4>
							<?php foreach($shipment_datas as $shipment): ?>
								<?php foreach($shipment['_parcels'] as $parcel): ?>
									<?php
									$pdf_path = chrono_get_media_path().'chronopost-etiquette-retour-' . $parcel['_skybill_number'] . '.pdf';
									$return_label = chrono_get_return_label($_order->get_id(), $parcel['_skybill_number']);
									?>
									<?php if (file_exists($pdf_path)): ?>
										<?php if ($return_label): ?>
											<div class="return-action-wrapper">
												<a style="margin-bottom: 10px;" class="button button-small" target="_blank" href="<?php echo str_replace(chrono_get_media_path(), chrono_get_media_url(), $pdf_path); ?>">
													<?php _e('Return label', 'chronopost'); ?> <?php echo $parcel['_skybill_number']; ?>
												</a>
												<div class="return-action">
													<span class="chrono-icon-plus"></span>
													<ul>
														<li><a href="<?php echo str_replace(chrono_get_media_path(), chrono_get_media_url(), $pdf_path); ?>"><?php _e('View return label', 'chronopost'); ?></a></li>
														<li><a href="<?php echo admin_url('post.php?post='.$_order->get_id().'&action=edit&chronoaction=return-label&order='.$_order->get_id().'&skybill_id=' . $parcel['_skybill_number'] . '&shipment_nonce='.wp_create_nonce( 'shipment_list_nonce' )); ?>"><?php _e('Generate return label again', 'chronopost'); ?></a></li>
														<li><a href="<?php echo admin_url('post.php?post='.$_order->get_id().'&action=edit&chronoaction=return-label&method=delete&order='.$_order->get_id().'&return_skybill_id=' . $return_label . '&skybill_id=' . $parcel['_skybill_number'] . '&shipment_nonce='.wp_create_nonce( 'shipment_list_nonce' ) ); ?>"><?php _e('Delete return label', 'chronopost'); ?></a></li>
													</ul>
												</div>
											</div>
										<?php else: ?>
											<a style="margin-bottom: 10px;" class="button button-small" target="_blank" href="<?php echo str_replace(chrono_get_media_path(), chrono_get_media_url(), $pdf_path); ?>">
												<?php _e('Return label', 'chronopost'); ?> <?php echo $parcel['_skybill_number']; ?>
											</a>
										<?php endif; ?>
									<?php else: ?>
										<a class="button button-small button-primary" href="<?php echo admin_url('post.php?post='.$_order->get_id().'&action=edit&chronoaction=return-label&order='.$_order->get_id().'&skybill_id=' . $parcel['_skybill_number'] . '&shipment_nonce='.wp_create_nonce( 'shipment_list_nonce' ) ); ?>"><?php _e('Generate return label', 'chronopost'); ?> <?php echo $parcel['_skybill_number']; ?></a>
									<?php endif; ?>
								<?php endforeach; ?>
							<?php endforeach; ?>
						</td>
						</td>
					</tr>
					</tbody>
				</table>
			<?php endif; ?>
			<?php
		}
	}

    public function shipping_zone_method_added($instance_id, $type, $zone_id)
    {
        /**
         * @global wpdb $wpdb WordPress database abstraction object.
         */
        global $wpdb;

        $instance = chrono_get_zone_method_by_id($zone_id, $type);
        if (!$instance->isAvailableForContract()) {
            $contracts = chrono_get_all_contracts();
            // Loop through contracts to find a suitable one
            foreach ($contracts as $contract) {
                if ($instance->isAvailableForContract($contract)) {
                    $instance->instance_settings['contract'] = $contract['number'];
                    $instance->enabled = 'yes';

                    // Update settings
                    update_option( $instance->get_instance_option_key(), apply_filters( 'woocommerce_shipping_' . $instance->id . '_instance_settings_values', $instance->instance_settings, $instance ), 'yes' );

                    // Set as enabled
                    $is_enabled = absint( 'yes' === $instance->enabled );
                    if ( $wpdb->update( "{$wpdb->prefix}woocommerce_shipping_zone_methods", array( 'is_enabled' => $is_enabled ), array( 'instance_id' => absint( $instance_id ) ) ) ) {
                        do_action( 'woocommerce_shipping_zone_method_status_toggled', $instance_id, $instance->id, $zone_id, $is_enabled );
                    }
                }
            }
        }
    }
}

function ajax_update_insurance()
{
	$order_ID = wc_sanitize_order_id($_POST['order_id']);
	$value = (int) sanitize_text_field($_POST['new_value']);

	if (update_post_meta($order_ID, '_insurance_amount', $value)) {
		$response = array(
			'status' => 'success'
		);
	} else {
		$response = array(
			'status' => 'error'
		);
	}
	echo wp_send_json($response);
}

add_action('wp_ajax_nopriv_update_insurance', 'ajax_update_insurance');
add_action('wp_ajax_update_insurance', 'ajax_update_insurance');



function ajax_update_saturday_shipping()
{
	$order_ID = wc_sanitize_order_id($_POST['order_id']);
	$value = sanitize_text_field($_POST['new_value']);
	if (update_post_meta($order_ID, '_ship_on_saturday', $value)) {
		$response = array(
			'status' => 'success'
		);
	} else {
		$response = array(
			'status' => 'error'
		);
	}
	echo wp_send_json($response);
}

add_action('wp_ajax_nopriv_update_saturday_shipping', 'ajax_update_saturday_shipping');
add_action('wp_ajax_update_saturday_shipping', 'ajax_update_saturday_shipping');

function ajax_update_parcels()
{
	$order_ID = wc_sanitize_order_id($_POST['order_id']);
	$currentValue = get_post_meta($order_ID,'_parcels_number', true);
	$new_value = (int)sanitize_text_field($_POST['new_value']);

	if($new_value > 1){
		$insurance_enable="no";
		update_post_meta($order_ID, '_insurance_enable', $insurance_enable);
		update_post_meta($order_ID, '_insurance_amount', 0);
	}

	if ($new_value == '') {
		delete_post_meta($order_ID, '_parcels_number');
		$response = array(
			'status' => 'success'
		);
	} elseif (is_int($new_value) && ($currentValue == $new_value || (update_post_meta($order_ID, '_parcels_number', $new_value)))) {
		$response = array(
			'status' => 'success'
		);
	} else {
		$response = array(
			'status' => 'error'
		);
	}
	echo wp_send_json($response);
}

add_action('wp_ajax_nopriv_update_returning', 'ajax_update_returning');
add_action('wp_ajax_update_returning', 'ajax_update_returning');

function ajax_update_returning()
{
	$return_method = $_POST['return_method_value'];
	$return_label = $_POST['return_label_value'];
	$return_contract = $_POST['return_contract_value'];

	$order_ID = wc_sanitize_order_id($_POST['order_id']);

	$check_metas = [
		'_last_return_method' => $return_method,
		'_last_return_label' => $return_label,
		'_last_return_contract' => $return_contract,
	];

	foreach ($check_metas as $check_meta => $value) {
		if (get_post_meta($order_ID, $check_meta)[0] != $value) {
			update_post_meta($order_ID, $check_meta, $value);
		}
	}

	echo wp_send_json(['status' => 'success']);
}

add_action('wp_ajax_nopriv_update_dimensions', 'ajax_update_dimensions');
add_action('wp_ajax_update_dimensions', 'ajax_update_dimensions');

function ajax_update_dimensions()
{
	$params = array();
	parse_str($_POST['new_value'], $params);

	foreach ($params['parcels_dimensions'] as &$dimensions) {
		array_walk($dimensions, function(&$value, &$key) {
			if (chrono_get_weight_unit() === 'g' && $key === 'weight') {
				$value = $value / 1000;
			}

			$value = str_replace(',', '.', $value);
		});
	}
	$params = $params['parcels_dimensions'];


	// Vérification des données
	// 1. Le poids maximal autorisé est 20 Kg pour le chrono relais et 30 Kg pour tous les autres produits.
	// 2. Les dimensions maximales autorisées sont 100 cm pour chacunes d’entres elles. Et l'ensemble du colis (L+2H+2l)
	//    ne doit pas dépasser 250 cm pour les offres Relais. Pour toutes les autres offres, les dimensions maximales
	//    autorisées sont 150 cm pour chacunes d’entres elles. Et l'ensemble du colis (L+2H+2l) de doit pas dépasser 300 cm.
	$order_ID = wc_sanitize_order_id($_POST['order_id']);
	$_order = new WC_Order($order_ID);
	$order_shipping_method = $_order->get_shipping_methods();
	$shipping_method_id = '';
	if ($order_shipping_method) {
		$shipping_method = reset($order_shipping_method);
		$shipping_method_id = $shipping_method->get_method_id();
	}

	$check = chrono_check_packages_dimensions($shipping_method_id, $params);
	if ($check !== true) {
		wp_send_json(array(
			'status' => 'error',
			'message' => $check
		));
	}

	$currentValue = json_encode(chrono_get_parcels_dimensions($order_ID));
	$newValue = json_encode($params);

	if (empty($params)) {
		delete_post_meta($order_ID, '_parcels_dimensions');
		$response = array(
			'status' => 'success'
		);
	} elseif ($currentValue == $newValue || update_post_meta($order_ID, '_parcels_dimensions', $newValue)) {
		$response = array(
			'status' => 'success'
		);
	} else {
		$response = array(
			'status' => 'error'
		);
	}
	echo wp_send_json($response);
}

add_action('wp_ajax_nopriv_update_parcels', 'ajax_update_parcels');
add_action('wp_ajax_update_parcels', 'ajax_update_parcels');

function ajax_update_insurance_amount()
{
	$order_ID = wc_sanitize_order_id($_POST['order_id']);
	if (isset($_POST['insurance_amount'])) {
		$new_value = sanitize_text_field($_POST['insurance_amount']);
	} else {
		$new_value = sanitize_text_field($_POST['new_value']);
	}

	if ($new_value == '') {
		delete_post_meta($order_ID, '_insurance_amount');
		$response = array(
			'status' => 'success'
		);
	} elseif (is_numeric($new_value) && $updated = update_post_meta($order_ID, '_insurance_amount', (float) $new_value)) {
		$response = array(
			'status' => 'success'
		);
	} else {
		$response = array(
			'status' => 'error'
		);
	}
	echo wp_send_json($response);
}

add_action('wp_ajax_nopriv_update_insurance_amount', 'ajax_update_insurance_amount');
add_action('wp_ajax_update_insurance_amount', 'ajax_update_insurance_amount');

add_action('wp_ajax_nopriv_test_login', 'ajax_chrono_test_login');
add_action('wp_ajax_test_login', 'ajax_chrono_test_login');

function ajax_chrono_test_login()
{
	if (!isset($_POST['chrono_nonce'])) {
		return false;
	}
	$nonce = sanitize_key($_POST['chrono_nonce']);

	// check to see if the submitted nonce matches with the
	// generated nonce we created earlier
	if (! wp_verify_nonce($nonce, 'chronopost_ajax')) {
		die('Busted!');
	}

	$account = sanitize_text_field($_POST['account']);
	$password = sanitize_text_field($_POST['password']);
	$response = chrono_check_login($account, $password);

	echo wp_send_json($response);
}

function chrono_check_login($account, $password) {
	$ws = new Chronopost_Webservice();
	$params = array(
		'accountNumber' => $account,
		'password' => $password,
		'depCode' => '92500',
		'arrCode' => '75001',
		'weight' => '1',
		'productCode' => '1',
		'type' => 'D'
	);
	$res = $ws->getQuickcost($params);

	if ($res->errorCode === 0) {
		$response = array(
			'status' => 'success',
			'message' => __('Valid username or password', 'chronopost')
		);
	} elseif ($res->errorCode === 3) {
		$response = array(
			'status' => 'error',
		);
	} else {
		$response = array(
			'status' => 'error',
			'message' => __('A system error occured. Please contact the Chronopost support if the problem persists.', 'chronopost')
		);
	}
	return $response;
}

function ajax_update_insurance_enable()
{
	$order_ID = wc_sanitize_order_id($_POST['order_id']);
	$new_value = sanitize_text_field($_POST['new_value']);
	if (update_post_meta($order_ID, '_insurance_enable', $new_value)) {
		$response = array(
			'status' => 'success'
		);
	} else {
		$response = array(
			'status' => 'error'
		);
	}
	echo wp_send_json($response);
}

add_action('wp_ajax_nopriv_update_insurance_enable', 'ajax_update_insurance_enable');
add_action('wp_ajax_update_insurance_enable', 'ajax_update_insurance_enable');

function ajax_update_order_contract()
{
	$order_ID = wc_sanitize_order_id($_POST['order_id']);
	$new_value = (int) sanitize_text_field($_POST['use_contract']);
	if (update_post_meta($order_ID, '_use_contract', $new_value)) {
		$response = array(
			'status' => 'success'
		);
	} else {
		$response = array(
			'status' => 'error'
		);
	}
	echo wp_send_json($response);
}

add_action('wp_ajax_nopriv_update_order_contract', 'ajax_update_order_contract');
add_action('wp_ajax_update_order_contract', 'ajax_update_order_contract');

function chronopost_shipping_zone_methods_check_availability()
{
	if ( ! wp_verify_nonce( wp_unslash( $_POST['wc_shipping_zones_nonce'] ), 'wc_shipping_zones_nonce' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		wp_send_json_error( 'bad_nonce' );
		wp_die();
	}

	$method_ID = sanitize_key($_POST['method']);
	$zone_ID = sanitize_key($_POST['zone_id']);
	$zone = new WC_Shipping_Zone($zone_ID);
	$zone_methods = $zone->get_shipping_methods();
	if (isset($zone_methods[$method_ID])) {
		$instance = $zone_methods[$method_ID];
		$shippingMethodAllow = array_keys(get_option('chronopost_shipping_methods'));
		$is_chronopost_method = in_array($instance->id, $shippingMethodAllow, true);
		$allowed = true;
		if ($is_chronopost_method) {
			$allowed = $instance->isAvailableForContract();
		}

		wp_send_json_success([
			'allowed' => $allowed,
		]);
	}

	wp_send_json_error();
}

add_action('wp_ajax_nopriv_chronopost_shipping_zone_methods_check_availability', 'chronopost_shipping_zone_methods_check_availability');
add_action('wp_ajax_chronopost_shipping_zone_methods_check_availability', 'chronopost_shipping_zone_methods_check_availability');

function chronopost_shipping_zone_shipping_methods($method_ID, $type, $zone_ID) {
	global $wpdb;

	$zone = new WC_Shipping_Zone($zone_ID);
	$zone_methods = $zone->get_shipping_methods();
	if (isset($zone_methods[$method_ID])) {
		$instance = $zone_methods[$method_ID];
		$instance->refresh_methods();
		$shippingMethodAllow = array_keys(get_option('chronopost_shipping_methods'));
		$is_chronopost_method = in_array($instance->id, $shippingMethodAllow, true);
		if ($is_chronopost_method) {
			$allowed = $instance->isAvailableForContract();
			if (!$allowed) {
				$wpdb->update( "{$wpdb->prefix}woocommerce_shipping_zone_methods", array( 'is_enabled' => $allowed ), array( 'instance_id' => absint( $method_ID ) ) );
			}
		}
	}
}
add_action('woocommerce_shipping_zone_method_added', 'chronopost_shipping_zone_shipping_methods', 10, 3);
