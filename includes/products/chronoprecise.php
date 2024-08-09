<?php
/**
 *
 * Chronopost Precise offer
 *
 * @since      1.0.0
 * @package    Chronopost
 * @subpackage Chronopost/includes/products
 * @author     Adexos <contact@adexos.fr>
 */
function chronoprecise_init()
{
	if (! class_exists('WC_ChronoPrecise')) {
		class WC_ChronoPrecise extends WC_Chronopost_Product
		{
			public $slot_option_key;
			public $cost_level_option_key;

			public function shipping_method_settings()
			{
				$this->id = 'chronoprecise'; // Id for your shipping method. Should be uunique.
				$this->pretty_title = __('Chronopost - Express delivery on appointment', 'chronopost');  // Title shown in admin
				$this->title = __('Chronopost - Express delivery on appointment', 'chronopost');  // Title shown in admin
				$this->method_title = __('Chronopost - Express delivery on appointment', 'chronopost');  // Title shown in admin
				$this->method_description = __('By appointment at your home! Order delivered on the day of your choice in a 2-hour time slot. You can reprogram your delivery in case of absence.',
						'chronopost'); // Description shown in admin
				$this->title = __('Chronopost - Express delivery on appointment', 'chronopost'); // This can be added as an setting but for this example its forced.
				$this->product_code = '2O';
				$this->product_code_str = 'SRDV';

				$this->slot_option_key = $this->id . '_' . $this->get_instance_id() . '_table_slots';
				$this->cost_level_option_key = $this->id . '_' . $this->get_instance_id() . '_cost_levels';
			}

			public function get_instance() {
				return $this;
			}

			public function custom_actions()
			{
				add_action('woocommerce_settings_save_shipping', array( $this, 'process_slot_settings' ));
				add_action('woocommerce_settings_save_shipping', array( $this, 'process_cost_level_settings' ));
			}


			public function calculate_shipping($package = array())
			{
				$rate = $this->get_shipping_rate($package);

				if ($rate === null || $rate === false) {
					return false;
				}

				$cost = $rate['cost'];

				//what is the tax status
				if ($this->instance_settings['tax_status'] == 'none') {
					$taxes = false;
				} else {
					$taxes = '';
				}

                $post_datas = [];
				if (array_key_exists('post_data', $_POST) && $_POST['post_data'] != '') {
					$post_datas = chrono_get_post_datas($_POST['post_data']);
				} else if (array_key_exists('chronopostprecise_creneaux_info', $_POST)) {
                    //
                    $post_datas = $_POST;
                    if (array_key_exists('chronopostprecise_creneaux_info', $post_datas)) {
                        $post_datas['chronopostprecise_creneaux_info'] = json_decode(stripslashes($post_datas['chronopostprecise_creneaux_info']));
                    }

                }

                if (array_key_exists('chronopostprecise_creneaux_info', $post_datas) && !empty($post_datas['chronopostprecise_creneaux_info']->tariffLevel)) {
                    $cost_levels = $this->get_cost_levels();
                    $cost += $cost_levels[$post_datas['chronopostprecise_creneaux_info']->tariffLevel]['price'];
                }

				$rate = array(
					'id' => $this->id,
					'label' => $this->pretty_title . (!$cost ? ': ' . __('Free', 'chronopost') : ''),
					'cost' => $cost,
					'taxes' => $taxes,
					'calc_tax' => 'per_order'
				);
				$this->add_rate($rate);

				return $this->rates;
			}

			public function process_custom_settings()
			{
				parent::process_custom_settings();
				$this->process_slot_settings();
				$this->process_cost_level_settings();
			}

			public function process_slot_settings()
			{
				if (!isset($_POST['slot']) || !is_array($_POST['slot'])) {
					$_POST['slot'] = array();
				}

				// Input data check
				foreach( $_POST['slot'] as $key => $slot) {
					if (!is_numeric($slot['startday']) || !is_numeric($slot['endday'])
						|| !preg_match('/[0-9]{2}:[0-9]{2}/', $slot['starthour'])
						|| !preg_match('/[0-9]{2}:[0-9]{2}/', $slot['endhour'])
					) {
						unset($_POST['slot'][$key]);
					}
				}

				update_option($this->slot_option_key, $_POST['slot']);
			}

			public function process_cost_level_settings()
			{
				$appointment_costs = array();
				if (is_array($_POST['appointment_costs'])
					&& array_key_exists('N1', $_POST['appointment_costs'])
					&& array_key_exists('N2', $_POST['appointment_costs'])
					&& array_key_exists('N3', $_POST['appointment_costs'])
					&& array_key_exists('N4', $_POST['appointment_costs'])
				) {
					$appointment_costs = $_POST['appointment_costs'];
				}
				update_option($this->cost_level_option_key, $appointment_costs);
			}

			public function get_slot_options()
			{
				return get_option($this->slot_option_key, array());
			}

			public function get_cost_levels()
			{
				return get_option($this->cost_level_option_key, array());
			}

			public function extra_form_fields()
			{
				$wp_locale = new WP_Locale;

				unset($this->instance_form_fields['deliver_on_saturday']);

				$this->instance_form_fields['delivery_date_day_nbr'] = array(
					'title' 		=> __('Date of delivery', 'chronopost'),
					'type' 			=> 'number',
					'description' => __('Date from which the weeks list of the RDV option will be calculated', 'chronopost'),
					'desc_tip'     => true,
					'default' 		=> '2',
					'class'  => 'small-text',
					'custom_attributes' => array(
						'min' => 0,
						'max' => 31,
						'data-text-before' => __('Day of delivery +', 'chronopost'),
						'data-text-after' => __('day(s)', 'chronopost')
					)
				);
				$this->instance_form_fields['delivery_date_day'] = array(
					'title' 		=> __('or day and hour of delivery', 'chronopost'),
					'type' 			=> 'select',
					'default' 		=> '1',
					'options' => $wp_locale->weekday
				);
				$this->instance_form_fields['delivery_date_hour'] = array(
					'title' 		=> __('Hour of delivery', 'chronopost'),
					'type' 			=> 'text',
					'default' 		=> '10:00',
					'class'  => 'small-text timepicker'
				);
				$this->instance_form_fields['closing_slot'] = array(
					'type' 			=> 'date_range_selector',
				);
				$this->instance_form_fields['cost_levels'] = array(
					'type' 			=> 'cost_level',
				);
				$this->instance_form_fields['cost_levels_show'] = array(
					'title' 		=> __('Show cost levels', 'chronopost'),
					'type' 			=> 'select',
					'default' 		=> 1,
					'options' => array(
						1 => __('Yes', 'chronopost'),
						0 => __('No', 'chronopost'),
					)
				);
			}

			public function generate_cost_level_html()
			{
				ob_start();

				$cost_levels = $this->get_cost_levels(); ?>
				<tr>
					<th scope="row" class="titledesc"><?php _e('Cost levels', 'chronopost'); ?></th>
					<td id="<?php echo $this->id; ?>_slot_settings">
						<table class="appointmentrows widefat">
							<?php for ($i = 1; $i <= 4; $i++): ?>
								<?php
								$appointment_status = false;
								$appointment_price = false;
								if (is_array($cost_levels) && array_key_exists('N'.$i, $cost_levels)) {
									$appointment_status = array_key_exists('status', $cost_levels['N'.$i]) ? $cost_levels['N'.$i]['status'] : '1';
									$appointment_price = array_key_exists('price', $cost_levels['N'.$i]) ? $cost_levels['N'.$i]['price'] : 0;
								} ?>
								<tr>
									<td>
										<b><?php printf(__('Appointment %s', 'chronopost'), $i); ?></b>
									</td>
									<td>
										<?php echo __('Status', 'chronopost'); ?>
										<select name="appointment_costs[N<?php echo $i; ?>][status]">
											<option value="1"<?php echo $appointment_status == '1' ? ' selected="selected"' : ''; ?>>Ouvert</option>
											<option value="0"<?php echo $appointment_status == '0' ? ' selected="selected"' : ''; ?>>Fermé</option>
										</select>
									</td>
									<td>
										<label for=""><?php echo __('Price', 'chronopost'); ?></label>
										<input value="<?php echo $appointment_price; ?>" type="number" step="any" name="appointment_costs[N<?php echo $i; ?>][price]" value="2" class="required-entry validate-number">
									</td>
								</tr>
							<?php endfor; ?>
						</table>
					</td>
				</tr>
				<?php
				return ob_get_clean();
			}

			public function generate_date_range_selector_html()
			{
				ob_start();

				$slot_datas = $this->get_slot_options();
				if (empty($slot_datas)) {
					$slot_datas = array();
				}
				?>
				<tr>
					<th scope="row" class="titledesc"><?php _e('Slots to be closed', 'chronopost'); ?></th>
					<td id="<?php echo $this->id; ?>_slot_settings" data-slot-lines=<?php echo esc_attr(json_encode($slot_datas)); ?>>
						<table class="closingslotsrows widefat">
							<col style="width:2%">
							<col style="width:4%">
							<col style="width:45%">
							<col style="width:4%;">
							<col style="width:45%;">
							<tbody>
							<?php $wp_locale = new WP_Locale; ?>
							<tr>
								<td colspan="5" class="add-slot-buttons">
									<a href="#" class="add button"><?php _e('Add New Slot', 'chronopost'); ?></a>
									<a href="#" class="delete button"><?php _e('Delete Selected Slot', 'chronopost'); ?></a>
								</td>
							</tr>
							</tbody>
						</table>
					</td>
				</tr>
				<?php
				return ob_get_clean();
			}

			public function load_frontend_hooks()
			{
				if ($this->instance_settings['enabled']) {
					add_action('woocommerce_review_order_before_payment', array($this, 'rdv_fancybox'));
				}
			}

			public function getSearchDeliverySlot()
			{
				/* appel WS SearchDeliverySlot pour récupérer les créneaux de livraison permettant de construire le semainier */
				$ws = new Chronopost_Webservice();
				return $ws->getSearchDeliverySlot($this->id);
			}

			public function rdv_fancybox()
			{

				// Only available on checkout page
				if (!is_checkout()) {
					return false;
				}

				$delivery_slots = $this->getSearchDeliverySlot();

				set_query_var('shipping_method_id', $this->id);
				set_query_var('_creneaux', $delivery_slots ? $delivery_slots->return->slotList : false);
				set_query_var('meshCode', $delivery_slots ? $delivery_slots->return->meshCode : false);
				set_query_var('transactionID', $delivery_slots ? $delivery_slots->return->transactionID : false);
				set_query_var('table_slots', get_option($this->id . '_' . $this->get_instance_id() . '_table_slots', false));
				set_query_var('cost_levels', get_option($this->id . '_' . $this->get_instance_id() . '_cost_levels', false));

				if ($overridden_template = apply_filters('chrono_precise_template', locate_template('chronopost/chronoprecise.php'))) {
					load_template($overridden_template);
				} else {
					load_template(CHRONO_PLUGIN_PATH . '/templates/chronoprecise.php');
				}
			}
		}
	}
}

add_action('woocommerce_shipping_init', 'chronoprecise_init');

function add_chronoprecise($methods)
{
	$methods['chronoprecise'] = 'WC_ChronoPrecise';
	return $methods;
}

add_filter('woocommerce_shipping_methods', 'add_chronoprecise');

add_action('woocommerce_after_shipping_rate', 'chrono_add_select_rdv_link', 10, 2);

function chrono_add_select_rdv_link($method, $index)
{
	if ($method->id == 'chronoprecise' && is_checkout()) {
		echo '<div class="appointment-link" id="container-method-chronoprecise-link">';
		if (array_key_exists('post_data', $_POST) && $_POST['post_data'] != '') {
			$post_datas = chrono_get_post_datas($_POST['post_data']);
			if (array_key_exists('chronopostprecise_creneaux_info', $post_datas) && $post_datas['chronopostprecise_creneaux_info'] != '') {
				$appointment_date = chronoprecise_format_appointment_date((array)$post_datas['chronopostprecise_creneaux_info']);
				echo" <small class=\"appointment-selected\">";
				printf(__('Appointment for delivery on %s between %s and %s', 'chronopost'), $appointment_date['date'], $appointment_date['startHour'], $appointment_date['endHour']);
				echo "</small>";
				echo ' <a href="javascript:;">' . __('Edit', 'chronopost') . '</a>';
			} else {
				echo '<a href="javascript:;">' . __('Select an appointment', 'chronopost') . '</a>';
			}
		} else {
			echo '<a href="javascript:;">' . __('Select an appointment', 'chronopost') . '</a>';
		}
		echo '</div>';
	}
}

function action_woocommerce_checkout_update_order_review($order)
{
	if (array_key_exists('shipping_method', $_POST) && $_POST['shipping_method'][0] == 'chronoprecise') {
		$chronoprecise_post_datas = chrono_get_post_datas($order);
		$cronoprecise_slot_datas = isset($chronoprecise_post_datas['chronopostprecise_creneaux_info']) ? $chronoprecise_post_datas['chronopostprecise_creneaux_info'] : 'empty';
		$chronoprecise_hash = 'wc_chronoprecise_' . md5(json_encode($cronoprecise_slot_datas) . WC_Cache_Helper::get_transient_version('shipping'));
		$stored_appointement = WC()->session->get('chronoprecise_appointment_datas');

		if (! is_array($stored_appointement) || $chronoprecise_hash !== $stored_appointement['appointment_hash'] || 'yes' === get_option('woocommerce_shipping_debug_mode', 'no')) {
			$packages = WC()->cart->get_shipping_packages();

			// Calculate costs for passed packages
			foreach ($packages as $package_key => $package_to_hash) {
				// Check if we need to recalculate shipping for this package

				// Remove data objects so hashes are consistent
				foreach ($package_to_hash['contents'] as $item_id => $item) {
					unset($package_to_hash['contents'][ $item_id ]['data']);
				}

				$package_hash = 'wc_ship_' . md5(json_encode($package_to_hash) . WC_Cache_Helper::get_transient_version('shipping'));
				$session_key  = 'shipping_for_package_' . $package_key;
				unset(WC()->session->$session_key);

				// Store in session to avoid recalculation
				WC()->session->set('chronoprecise_appointment_datas', array(
					'appointment_hash' => $chronoprecise_hash
				));
			}
		}
	}
}
add_action('woocommerce_checkout_update_order_review', 'action_woocommerce_checkout_update_order_review', 10, 1);

add_filter('woocommerce_order_shipping_to_display', 'add_chronoprecise_extra_shipping_info', 10, 2);

function chronoprecise_format_appointment_date($appointment_datas = array())
{
	$_date = new DateTime($appointment_datas['deliveryDate']);
	$_date->setTimeZone(new DateTimeZone(chrono_get_timezone()));
	$date = $_date->format('d/m/Y');

	$startHour = str_pad($appointment_datas['startHour'], 2, '0', STR_PAD_LEFT);
	$startMinutes = str_pad($appointment_datas['startMinutes'], 2, '0', STR_PAD_LEFT);
	$endHour = str_pad($appointment_datas['endHour'], 2, '0', STR_PAD_LEFT);
	$endMinutes = str_pad($appointment_datas['endMinutes'], 2, '0', STR_PAD_LEFT);

	return array(
		'date' => $date,
		'startHour' => $startHour.':'.$startMinutes,
		'endHour' => $endHour.':'.$endMinutes
	);
}


function add_chronoprecise_extra_shipping_info($shipping, $_order)
{
	$shipping_methods = $_order->get_shipping_methods();
	if (is_array($shipping_methods)) {
		$shipping_method = array_shift($shipping_methods);
		$shipping_method_id = $shipping_method['method_id'];
	}
	if ($shipping_method_id == 'chronoprecise') {
		$appointment_details = get_post_meta($_order->get_id(), '_shipping_method_chronoprecise');
		if (is_array($appointment_details)) {
			$appointment_details = array_shift($appointment_details);
		}

		$appointment_date = chronoprecise_format_appointment_date($appointment_details);

		$shipping .= " <small>(";
		$shipping .= sprintf(__('Appointment for delivery on %s between %s and %s', 'chronopost'), $appointment_date['date'], $appointment_date['startHour'], $appointment_date['endHour']);
		$shipping .= ")</spall>";
	}
	return $shipping;
}

add_action('wp_ajax_load_chronoprecise_appointment', 'ajax_load_chronoprecise_appointment');
add_action('wp_ajax_nopriv_load_chronoprecise_appointment', 'ajax_load_chronoprecise_appointment');

function ajax_load_chronoprecise_appointment()
{
	$method_id = sanitize_key($_POST['method_id']);
	$response['data'] = chronoprecise_async_load_fancybox($method_id);
	$response['status'] = 'success';
	echo wp_send_json($response);
	die();
}

function chronoprecise_async_load_fancybox($method_id)
{
	// Get current shipping zone
	$shipping_packages =  WC()->cart->get_shipping_packages();
	$shipping_zone = wc_get_shipping_zone( reset( $shipping_packages ) );

	// Fetch method instance for zone
	$instance = chrono_get_zone_method_by_id($shipping_zone->get_id(), $method_id);

	// Only available on checkout page
	$ws = new Chronopost_Webservice();
	$delivery_slots = $ws->getSearchDeliverySlot($method_id);

	set_query_var('shipping_method_id', $method_id);
	if (!$delivery_slots) {
		set_query_var('_creneaux', false);
	} else {
		set_query_var('shipping_method_id', $method_id);
		set_query_var('_creneaux', $delivery_slots ? $delivery_slots->return->slotList : false);
		set_query_var('meshCode', $delivery_slots ? $delivery_slots->return->meshCode : false);
		set_query_var('transactionID', $delivery_slots ? $delivery_slots->return->transactionID : false);
		set_query_var('table_slots', get_option($method_id . '_' . $instance->get_instance_id() . '_table_slots', false));
		set_query_var('cost_levels', get_option($method_id . '_' . $instance->get_instance_id() . '_cost_levels', false));
	}

	ob_start();
	if ($overridden_template = apply_filters('chrono_precise_template', locate_template('chronopost/chronoprecise.php'))) {
		load_template($overridden_template);
	} else {
		load_template(CHRONO_PLUGIN_PATH . '/templates/chronoprecise.php');
	}
	$output = ob_get_clean();

	return $output;
}

add_action('woocommerce_checkout_update_order_meta', 'chrono_save_shipping_method_chronoprecise', 10, 2);

function chrono_save_shipping_method_chronoprecise($order_id)
{
	if ($_POST['shipping_method'][0] != 'chronoprecise') {
		return;
	}
	if ($_POST['shipping_method'][0] == 'chronoprecise' && array_key_exists('chronopostprecise_creneaux_info', $_POST) && $_POST['chronopostprecise_creneaux_info'] == '') {
		throw new Exception(sprintf(__('Please <a href="#container-method-%s">select an appointment</a>', 'chronopost'), 'chronoprecise-link'));
		die();
	}

	$rdvInfo = json_decode(stripcslashes($_POST['chronopostprecise_creneaux_info']), true);

	if (is_array($rdvInfo)) {

		$that = new WC_ChronoPrecise;

		$ws = new Chronopost_Webservice();

		$confirm = $ws->confirmDeliverySlot($that, $rdvInfo);
		if ($confirm->return->code == 0) {
			$rdvInfo = array_merge($rdvInfo, json_decode(json_encode($confirm->return->productServiceV2), true));
			update_post_meta($order_id, '_shipping_method_chronoprecise', $rdvInfo);
		} else {
			throw new Exception(__($confirm->return->message, 'chronopost'));
			die();
		}
	} else {
		throw new Exception(sprintf(__('Please <a href="#container-method-%s">select an appointment</a>', 'chronopost'), 'chronoprecise-link'));
		die();
	}
}
