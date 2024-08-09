<?php
/**
 * Default Chronopost product object
 *
 * Maintain a list of all hooks that are registered throughout
 * the plugin, and register them with the WordPress API. Call the
 * run function to execute the list of actions and filters.
 *
 * @package    Chronopost
 * @subpackage Chronopost/includes
 * @author     Adexos <contact@adexos.fr>
 */

function chronopost_product_init()
{
	if (!class_exists('WC_Chronopost_Product')) {
		class WC_Chronopost_Product extends WC_Shipping_Method
		{
			protected $admin_notice;
			private $chrono_settings;
			public $id;
			public $method_description;
			public $title;
			public $loader;
			public $pretty_title;
			public $max_product_weight = 30;
			public $product_code;
			public $product_code_bal = false;
			public $product_code_str = false;
			public $product_code_bal_str = false;
			public $product_code_return = '01';
			public $product_code_return_service = '226';
			public $tracking_url = 'https://www.chronopost.fr/fr/chrono_suivi_search?listeNumerosLT={tracking_number}';

			/**
			 * Constructor for your shipping class
			 *
			 * @access public
			 * @return void
			 */
			public function __construct($instance_id = 0)
			{
				parent::__construct($instance_id);
				$this->supports = array(
						'shipping-zones',
						'instance-settings',
				);
				$this->init();
				$this->loader = new Chronopost_Loader();
				$this->used_product_code = chrono_get_option('enable',
						'bal_option') == 'yes' && $this->product_code_bal !== false ? $this->product_code_bal : $this->product_code;
				$this->rates_option_key = $this->id . '_'. $instance_id .'_table_rates';
				$this->options = $this->get_options();
				$this->chrono_settings = get_option('chronopost_settings');
			}

			public function print_admin_notice()
			{
				echo chrono_notice($this->admin_notice, 'error');
			}

			public function print_admin_success()
			{
				echo chrono_notice($this->admin_notice, 'success');
			}

			public function shipping_method_settings()
			{
				$this->id = 'chronopost_product'; // Id for your shipping method. Should be uunique.
				$this->pretty_title = __('Chronopost Product', 'chronopost');  // Title shown in admin
				$this->method_title = __('Chronopost', 'woocommerce');
				$this->method_description = ''; // Description shown in admin
			}

			private function get_options()
			{
				$options = [];
				$option = get_option($this->rates_option_key);
				if ($option === false || !isset($option['rates']) || empty($option['rates'])) {
					$csv = plugin_dir_path(dirname(__FILE__)) . 'csv/' . $this->id . '.csv';
					if (file_exists($csv)) {
						$csv = fopen($csv, 'r');

						while (($row = fgetcsv($csv, 0, ';')) !== false) {
							// Extract the parameters from the current row.
							list($countries, $min, $max, $shipping) = $row;

							$options['min'][] = $min;
							$options['max'][] = $max;
							$options['shipping'][] = $shipping;
							$options['rates'][] = array(
									'min'      => $min,
									'max'      => $max,
									'shipping' => $shipping
							);
						}
					}
				} else {
					$options = array_filter((array)get_option($this->rates_option_key));
				}

				return $options;
			}

			/**
			 * Init your settings
			 *
			 * @access public
			 * @return void
			 */
			public function init()
			{
				// Load the settings API
				$this->shipping_method_settings();
				$this->init_form_fields();
				$this->init_instance_settings();
				$this->extra_form_fields();
				$this->enabled = $this->get_option('enabled');

				$this->weight_unit = chrono_get_weight_unit();

				$this->create_select_arrays();

				add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
				$this->custom_actions();
			}

			public function process_admin_options()
			{
				$this->process_custom_settings();
				return parent::process_admin_options();
			}

			public function custom_actions()
			{
				// Silence is golden
			}

			public function extra_form_fields()
			{
				// Silence is golden
			}

			/**
			 * @param null|WC_Order $order
			 *
			 * @return array
			 */
			public function getContractInfos($order = null)
			{
				$selected_contract = false;
				if ($order !== null && $order instanceof WC_Order) {
					$selected_contract = get_post_meta($order->get_id(), '_use_contract', true);
				}

				if (!$selected_contract && isset($this->instance_settings['contract'])) {
					$selected_contract = $this->instance_settings['contract'];
				}

				if (!$selected_contract) {
					$selected_contract = $this->instance_form_fields["contract"]["default"];
				}

				return chrono_get_contract_infos($selected_contract);
			}

			public function addMargeToQuickcost($quickcost_val, $carrierCode = '', $firstPassage = true)
			{
				if ($carrierCode) {
					$quickcostMarge = $this->instance_settings['quickcost_marge'];
					$quickcostMargeType = $this->instance_settings['quickcost_marge_type'];

					if ($quickcostMarge) {
						if ($quickcostMargeType == 'amount') {
							$quickcost_val += $quickcostMarge;
						} elseif ($quickcostMargeType == 'prcent') {
							$quickcost_val += $quickcost_val * $quickcostMarge / 100;
						}
					}
				}

				return $quickcost_val;
			}

			public function calculate_shipping($package = array())
			{
				$rate = $this->get_shipping_rate($package);
				$this->add_rate($rate);
			}

			/**
			 * calculate_shipping function.
			 *
			 * @access public
			 *
			 * @param mixed $package
			 *
			 * @return array|false|void
			 */
			public function get_shipping_rate($package = array())
			{
				if (!$this->is_enabled()) {
					return false;
				}

				if ($package['destination']['postcode'] == '') {
					return false;
				}

				$cost = false;
				$ws = new Chronopost_Webservice();
				$this->isAllowed = $ws->getMethodIsAllowed($this, $package);
				if (!$this->isAllowed) {
					return false;
				}

				//what is the tax status
				$taxes = $this->instance_settings['tax_status'] == 'none' ? false : '';

				$cartWeight = WC()->cart->cart_contents_weight;

				//to get arrival code
				if (chrono_get_weight_unit() == 'g') {
					$cartWeight = $cartWeight / 1000; /* conversion g => kg */
				}

				$items = WC()->cart->get_cart();

				// if one of an item exceed 30 kg (by default), skip chrono methods
				foreach ($items as $item => $values) {
					$product_weight = (float)$values['data']->get_weight();
					if (chrono_get_weight_unit() == 'g') {
						$product_weight = $product_weight / 1000;
					}

					if ($product_weight > $this->max_product_weight) {
						return false;
					}
				}

				$dest_country = $package['destination']['country'];
				if ($this->instance_settings['quickcost_enable'] == 'yes') {
					$supplementCorse = 0; // Supplement pour la Corse
					$arrCode = $package['destination']['postcode'];

					if ($this->id == 'chronoexpress' || $this->id == 'chronoclassic' || $this->id == 'chronorelaiseurope' || $this->id == 'chronotoshopeurope') {
						$arrCode = $dest_country;
					}

					// Mapping ISO code to Chronopost code
					if ($dest_country == 'YT' || $dest_country == 'MC' || $dest_country == 'MF') {
						$arrCode = $ws->getChronoCode()[$dest_country];
					}

					$contract = $this->getContractInfos();
					if (!$contract) {
						return false;
					}

					$quickCost = array(
							'accountNumber' => $contract['number'],
							'password'      => $contract['password'],
							'depCode'       => chrono_get_option('zipcode', 'shipper'),
							'arrCode'       => $arrCode,
							'weight'        => ($cartWeight != 0) ? str_replace(',', '.', $cartWeight) : 1,
							'productCode'   => $this->used_product_code,
							'type'          => 'M'
					);

					if ($quickCostValues = $ws->getQuickcost($quickCost, $this->instance_settings['quickcost_url'])) {
						if ($quickCostValues->errorCode == 0) {
							$quickcost_val = (float)$quickCostValues->amountTTC;

							/* Ajout marge au quickcost */
							if ($quickcost_val !== false) {
								$cost = $this->addMargeToQuickcost($quickcost_val, $this->id);
							}
						} else {
							//wc_add_notice( $quickCostValues->errorMessage, 'error' );
							// @TODO situation si quickcost ne retourne aucune valeur
							return;
						}
					}
				} else {
					$supplementCorse = (float) chrono_get_option('amount', 'corsica_supplement');
				}

				// no quickcost value or no activated quickcost
				if ($cost === false) {

					if (!isset($this->options['rates'])) {
						return;
					}

					// get the associated rate for the country
					$rates = $this->options['rates'];

					if ($rates == null) {
						//no rate available for the dest country
						return;
					}

					// get the associated rate by weight
					$cost = $this->find_matching_rate($cartWeight, $rates);
				}

				if ($cost === null || $cost === false) {
					return;
				}

				if (is_numeric($this->instance_settings['handling_fee'])) {
					$cost += $this->instance_settings['handling_fee'];
				}

				if (is_numeric($this->instance_settings['application_fee'])) {
					$cost += $this->instance_settings['application_fee'];
				}

				// Add corsica supplement
				if ($package['destination']['country'] === 'FR' && (int)$package['destination']['postcode'] >= 20000 && (int)$package['destination']['postcode'] < 21000) {
					$cost += $supplementCorse;
				}

				// Free shipping feature
				if (isset($this->instance_settings['free_shipping_enable']) && $this->instance_settings['free_shipping_enable'] === 'yes' && (float)$package['cart_subtotal'] >= (float)$this->instance_settings['free_shipping_minimum_amount']) {
					$cost = false;
				}

				return array(
						'id'       => $this->id,
						'label'    => $this->instance_settings['title'] . (!$cost ? ': ' . __('Free', 'chronopost') : ''),
						'cost'     => $cost,
						'taxes'    => $taxes,
						'calc_tax' => 'per_order'
				);
			}

			public function get_rates_for_country($country)
			{
				//Find matching rate through options
				$ret = array();
				foreach ($this->options as $rate) {
					if (in_array($country, $rate['countries'])) {
						$ret[] = $rate;
					}
				}

				//if something found, return it, otherwise return null.
				return count($ret) > 0 ? $ret : null;
			}

			//Find the matching rate
			public function find_matching_rate($value, $rates)
			{
				$value = $value <= 0 ? 0.1 : $value;
				foreach ($rates as $rate) {
					// infinity case
					if ($rate['max'] == '*') {
						if ($value >= $rate['min']) {
							return $rate['shipping'];
						}
					} else {
						if ($value >= $rate['min'] && $value <= $rate['max']) {
							return $rate['shipping'];
						}
					}
				}
				return null;
			}

			/**
			 ** This initialises the form field
			 */
			public function init_form_fields()
			{
				$this->instance_form_fields = array(
						'contract' => array(
								'title'   => __('Contract', 'chronopost'),
								'type'    => 'select',
								'default' => $this->get_default_contrat(),
								'options' => $this->get_contracts_list(),
						),
						'title'   => array(
								'title'       => __('Checkout Title', 'chronopost'),
								'type'        => 'text',
								'default'     => $this->title,
						),
						'quickcost_enable'                  => array(
								'title'       => __('Activate Quickcost', 'chronopost'),
								'type'        => 'checkbox',
								'label'       => __('Automatically calculate the shipping cost with Quickcost',
										'chronopost'),
								'default'     => 'no',
								'description' => __('Quickcost will calculate the cost of an item, depending on the rates negociated with Chronopost. This option replaces the use of the fee schedule.',
										'chronopost'),
								'desc_tip'    => true
						),
						'quickcost_url'                     => array(
								'title'   => __('Quickcost URL', 'chronopost'),
								'type'    => 'text',
								'default' => 'https://www.chronopost.fr/quickcost-cxf/QuickcostServiceWS?wsdl'
						),
						'quickcost_marge'                   => array(
								'title'   => __('Value to add to Quickcost', 'chronopost'),
								'type'    => 'number',
								'default' => '0',
								'class'   => 'small-text'
						),
						'quickcost_marge_type'              => array(
								'title'             => __('Type of marge', 'chronopost'),
								'type'              => 'select',
								'default'           => 'amount',
								'options'           => array(
										'amount' => __('Amount (€)', 'chronopost'),
										'prcent' => __('Percentage (%)', 'chronopost'),
								),
								'custom_attributes' => array(
										'step' => 'any'
								)
						),
						'application_fee'                   => array(
								'title'             => __('Application fee', 'chronopost'),
								'type'              => 'number',
								'default'           => 0,
								'class'             => 'small-text',
								'custom_attributes' => array(
										'step' => 'any'
								)
						),
						'handling_fee'                      => array(
								'title'             => __('Handling fee', 'chronopost'),
								'type'              => 'number',
								'default'           => 0,
								'class'             => 'small-text',
								'custom_attributes' => array(
										'step' => 'any'
								)
						),
						'tracking_url'                      => array(
								'title'   => __('Tracking URL', 'chronopost'),
								'type'    => 'text',
								'default' => $this->tracking_url,
						),
						'deliver_on_saturday'               => array(
								'title'   => __('Deliver on Saturday?', 'chronopost'),
								'type'    => 'select',
								'default' => 'no',
								'options' => array(
										'no'  => __('No', 'chronopost'),
										'yes' => __('Yes', 'chronopost'),
								)
						),
						'enable_frontend_saturday_shipping' => array(
								'title'       => __('Enable on frontend?', 'chronopost'),
								'label'       => __('Enable Saturday Shipping on frontend', 'chronopost'),
								'type'        => 'checkbox',
								'default'     => 'no',
								'description' => __('Allows the user to define if he wishes to activate the Saturday delivery option',
										'chronopost'),
								'desc_tip'    => true
						),
						'deliver_on_saturday_amount'        => array(
								'title'             => __('Saturday option amount', 'chronopost'),
								'label'             => __('Amount of the option allowing delivery on Saturday',
										'chronopost'),
								'type'              => 'number',
								'default'           => 0,
								'class'             => 'small-text',
								'custom_attributes' => array(
										'step' => 'any'
								)
						),
						'tax_status'                        => array(
								'title'   => __('Tax status', 'chronopost'),
								'type'    => 'select',
								'default' => 'none',
								'options' => array(
										'taxable' => __('Taxable', 'chronopost'),
										'none'    => __('None', 'chronopost'),
								)
						),
						'free_shipping_enable'              => array(
								'title'       => __('Enable Free Shipping', 'chronopost'),
								'type'        => 'checkbox',
								'label'       => __('Enable "Free Shipping" feature depending on below minimum order amount',
										'chronopost'),
								'default'     => 'no',
								'description' => __('This option will enable the "Free shipping" feature. Depending on the customer cart amount the shipping cost will be free or not.',
										'chronopost'),
								'desc_tip'    => true
						),
						'free_shipping_minimum_amount'      => array(
								'title'             => __('Free Shipping minimum order amount required', 'chronopost'),
								'type'              => 'number',
								'default'           => 0,
								'class'             => 'small-text',
								'custom_attributes' => array(
										'step' => 'any'
								)
						),
						'table_rates_table'                 => array(
								'type' => 'table_rates_table'
						)
				);
			}

			// generate table rate manager
			public function generate_table_rates_table_html()
			{
				ob_start(); ?>
				<tr>
					<th scope="row" class="titledesc"><?php _e('Shipping rates', 'chronopost'); ?></th>
					<td id="<?php echo $this->id; ?>_settings" class="shipping-rate-table"
						data-rate-lines='<?php echo esc_attr(json_encode($this->options)); ?>'>
					</td>
				</tr>
				<?php
				return ob_get_clean();
			}

			public function get_default_contrat()
			{
				$accounts = chrono_get_option('accounts');
				if (!is_array($accounts)) {
					return '';
				}

				return isset($accounts[1]['number']) ? $accounts[1]['number'] : '';
			}

			public function get_contracts_list()
			{
				$accounts = chrono_get_all_contracts();
				if (!is_array($accounts)) {
					return array();
				}

				$options = array();
				foreach ($accounts as $account) {
					$options[esc_attr($account['number'])] = esc_js($account['label']);
				}

				return $options;
			}

			/**
			 * Get the shipping countries
			 */
			public function get_shipping_country_list()
			{
				$options = array();
				foreach (WC()->countries->get_shipping_countries() as $country_code => $country_name) {
					$options['country'][esc_attr($country_code)] = esc_js($country_name);
				}

				return $options;
			}

			/**
			 * Make country array
			 */
			public function create_select_arrays()
			{
				$this->country_array = array();
				foreach (WC()->countries->get_shipping_countries() as $id => $value) {
					$this->country_array[esc_attr($id)] = esc_js($value);
				}
			}

			/**
			 * Check if the product is available for the contract
			 *
			 * @return bool
			 */
			public function isAvailableForContract($contract = null)
			{
				$ws = new Chronopost_Webservice();
				$default_package = array(
						'contents'    => array(),
						'destination' => array(
								"country"   => "",
								"state"     => "",
								"postcode"  => "",
								"city"      => "",
								"address"   => "",
								"address_2" => ""
						)
				);

				// Produits disponibles pour l'addresse configurée
				$default_package['destination']['country'] = trim($this->chrono_settings['shipper']['country']);
				$default_package['destination']['postcode'] = trim($this->chrono_settings['shipper']['zipcode']);
				$default_package['destination']['city'] = trim($this->chrono_settings['shipper']['city']);
				$allowed = $ws->getMethodIsAllowed($this, $default_package, $contract);

				// Produits disponibles en France Metropolitaine
				if (!$allowed) {
					$default_package['destination']['country'] = "FR";
					$default_package['destination']['postcode'] = "75001";
					$default_package['destination']['city'] = "Paris";
					$allowed = $ws->getMethodIsAllowed($this, $default_package, $contract);
				}

				// Produits disponibles pour les DOM
				if (!$allowed) {
					$default_package['destination']['country'] = "RE";
					$default_package['destination']['postcode'] = "97400";
					$default_package['destination']['city'] = "Saint-Denis";
					$allowed = $ws->getMethodIsAllowed($this, $default_package, $contract);
				}

				// Produits disponibles pour l'Europe
				if (!$allowed) {
					$default_package['destination']['country'] = "DE";
					$default_package['destination']['postcode'] = "101127";
					$default_package['destination']['city'] = "Berlin";
					$allowed = $ws->getMethodIsAllowed($this, $default_package, $contract);
				}

				return $allowed;
			}

			/**
			 * This saves all of our custom table settings
			 */
			public function process_custom_settings()
			{
				// register chronopost method and code in the database if not exists
				$this->refresh_methods();

				$allowed = $this->isAvailableForContract();
				if ($this->is_enabled() && !$allowed) {
					if (!defined('CHRONO_RUN_ONCE')) {
						WC_Admin_Settings::add_error(
								__("You can't enable this product with this contract. Please contact us for more informations.",
										'chronopost')
						);
						define('CHRONO_RUN_ONCE', true);
					}

					// Event is fired twice
					$method_settings['enabled'] = '';
				}

				//Arrays to hold the clean POST vars
				$keys = array();
				$min = array();
				$max = array();
				$shipping = array();

				// Get the post data from shipping zone configuration
				if (isset($_POST['key'])) {
					$keys = array_map('wc_clean', $_POST['key']);
				}

				if (isset($_POST['min'])) {
					$min = array_map('wc_clean', $_POST['min']);
				}

				if (isset($_POST['max'])) {
					$max = array_map('wc_clean', $_POST['max']);
				}

				if (isset($_POST['shipping'])) {
					$shipping = array_map('wc_clean', $_POST['shipping']);
				}

				$options = $this->format_shipping_rate_options($min, $max, $shipping, $keys);

				// Save shipping rates options
				update_option($this->rates_option_key, $options);
				$this->options = $options;
			}

			public function refresh_methods() {
				// register chronopost method and code in the database if not exists
				$chronopost_methods = get_option('chronopost_shipping_methods', array(), true);
				$allowed = $this->isAvailableForContract();

				if (array_key_exists($this->id,
								$chronopost_methods) && (!isset($chronopost_methods['product_allowed'])
								|| $chronopost_methods['product_allowed'] !== $allowed)) {
					$chronopost_methods[$this->id]['product_allowed'] = $allowed;
				}

				if (!array_key_exists($this->id, $chronopost_methods)) {
					$chronopost_methods = array_merge(
							array(
									$this->id => array(
											'product_allowed'      => $allowed,
											'product_code'         => $this->product_code,
											'product_code_bal'     => $this->product_code_bal,
											'product_code_str'     => $this->product_code_str,
											'product_code_bal_str' => $this->product_code_bal_str
									)
							),
							$chronopost_methods
					);
					update_option('chronopost_shipping_methods', $chronopost_methods);
				}
			}

			public function format_shipping_rate_options($min, $max, $shipping, $keys)
			{
				$key = $this->id . '_settings';

				// Adding the shipping rates
				$obj = array();
				foreach ($min[$key] as $k => $val) {
					if (
							(!is_numeric($min[$key][$k]) || empty($min[$key][$k])) &&
							(!is_numeric($max[$key][$k]) || empty($max[$key][$k])) &&
							(!is_numeric($shipping[$key][$k]) || empty($shipping[$key][$k]))
					) {
						unset($min[$key][$k]);
						unset($max[$key][$k]);
						unset($shipping[$key][$k]);
					} else {
						//add it to the object array
						$obj[] = array(
								"min"      => $min[$key][$k],
								"max"      => $max[$key][$k],
								"shipping" => $shipping[$key][$k]
						);
					}
				}

				usort($obj, 'self::cmp'); // sort the rate datas

				//create the array to hold the data
				$options = [];
				$options['min'] = $min[$key];
				$options['max'] = $max[$key];
				$options['shipping'] = $shipping[$key];
				$options['rates'] = $obj;

				return (array)$options;
			}

			// Comparision function for sorting shipping rates
			public function cmp($a, $b)
			{
				return $a['min'] - $b['min'];
			}
		}
	}
}

add_action('woocommerce_shipping_init', 'chronopost_product_init');

function chrono_get_saturday_shipping_id($shipping_method_id = 0)
{

	if (chrono_is_sending_day()) {
		$shippingMethodAllow = array_keys(get_option('chronopost_shipping_methods'));
		$sat_shipMethodAllow = array_diff(
				$shippingMethodAllow,
				array('chronorelaiseurope', 'chronotoshopeurope', 'chronoexpress', 'chronoclassic', 'chronosameday')
		);

		if (in_array($shipping_method_id, $sat_shipMethodAllow)) {
			return true;
		}
	}

	return false;
}

// Ajout de la mise en place du champs dans le tunnel de commande
add_action('woocommerce_after_shipping_rate', 'chrono_saturday_additional_shipping_field', 30);

function chrono_saturday_additional_shipping_field($method)
{
	if (isset($_GET['wc-ajax']) && $_GET['wc-ajax'] === 'update_shipping_method') {
		return;
	}

	if (isset($_POST['shipping_method']) && $method->get_id() === $_POST['shipping_method'][0] && chrono_get_saturday_shipping_id($method->get_id())) {

		$enable_frontend_saturday_shipping = (chrono_get_method_settings(
						$method->get_id(),
						'enable_frontend_saturday_shipping') === 'yes'
		);

		$_deliver_on_saturday = (bool)(chrono_get_method_settings(
				$method->get_id(),
				'deliver_on_saturday') == 'yes' ? 1 : 0
		);

		if ($enable_frontend_saturday_shipping && $_deliver_on_saturday) {
			$fee = (float)chrono_get_method_settings($method->get_id(), 'deliver_on_saturday_amount');
			$checked = chrono_get_saturday_shipping_post_data();
			echo '<div class="chrono-saturday-shipping">';
			echo '<input type="checkbox" name="ship_on_saturday" id="saturday_shipping" value="yes" class="shipping_method ship_on_saturday"' . ($checked === 'yes' ? ' checked="checked"' : '') . '>';
			echo '<label for="saturday_shipping">';
			echo '<span class="has-tooltip">';
			echo __('I want to be delivered on Saturday', 'chronopost');
			echo '&nbsp;<i class="fa fa-info-circle" aria-hidden="true"></i>';
			echo '<span class="tooltip-content">';
			echo __("If my order is too late to be delivered on Friday and I don't want to wait until Monday",
					'chronopost');
			echo '</span>';
			echo '</span>';

			if ($fee > 0) {
				echo ' <small class="shipping-extra-fee">(+' . wc_price($fee) . ')</small>';
			}
			echo '</label>';
			echo '</div>';
		}
	}
}

// Sauvegarde de la donnée si la livraison le samedi est sélectionnée par le client
add_action('woocommerce_checkout_update_order_meta', 'chrono_save_shipping_method_saturday', 10, 1);
function chrono_save_shipping_method_saturday($order_id)
{
	if (!isset($_POST['ship_on_saturday']) || !$_POST['ship_on_saturday']) {
		return;
	}

	if ($_POST['ship_on_saturday'] === 'yes') {
		update_post_meta($order_id, '_ship_on_saturday', 'yes');
		update_post_meta($order_id, '_ship_on_saturday_user_defined', 'yes');
	}
}
