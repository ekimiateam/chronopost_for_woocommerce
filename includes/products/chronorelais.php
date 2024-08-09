<?php
/**
 *
 * Chronopost Relai offer
 *
 * @since      1.0.0
 * @package    Chronopost
 * @subpackage Chronopost/includes/products
 * @author     Adexos <contact@adexos.fr>
 */

function chronorelais_init()
{
    if (! class_exists('WC_Chronorelais')) {
        class WC_Chronorelais extends WC_Chronopost_Product
        {
            public $_chronorelais;
            public $_chronorelay_id;

            public function shipping_method_settings()
            {
                $this->id = 'chronorelais'; // Id for your shipping method. Should be unique.
				$this->pretty_title = __('Chronopost – Express delivery in Pickup relay', 'chronopost');  // Title shown in admin
				$this->title = __('Chronopost – Express delivery in Pickup relay', 'chronopost');  // Title shown in admin
				$this->method_title = __('Chronopost – Express delivery in Pickup relay', 'chronopost');  // Title shown in admin
				$this->method_description = __('Parcels delivered the next day before 13h in the Pickup of your choice. You\'ll be notified by e-mail and SMS.',
					'chronopost'); // Description shown in admin
				$this->product_code = '86';
				$this->product_code_str = 'PR';
				$this->max_product_weight = 20;
            }

            public function load_frontend_hooks()
            {
                if ($this->instance_settings['enabled']) {
                    add_action('woocommerce_review_order_before_payment', array($this, 'relay_fancybox'));
                }
            }

            public function custom_actions()
            {
                add_action('wp_ajax_load_chronorelais_picker', array($this, 'ajax_load_chronorelais_picker'));
                add_action('wp_ajax_nopriv_load_chronorelais_picker', array($this, 'ajax_load_chronorelais_picker'));
            }

            public function ajax_load_chronorelais_picker()
            {
                $this->relay_fancybox();
            }

            public function extra_form_fields()
            {
                unset($this->instance_form_fields['deliver_on_saturday']);
                $this->instance_form_fields['max_distance_search'] = array(
                    'title' 		=> __('Max distance search', 'chronopost'),
                    'type' 			=> 'number',
                    'default' 		=> '15'
                );
                $this->instance_form_fields['max_pickup_relay_number'] = array(
                    'title' 		=> __('Max pickup relay number', 'chronopost'),
                    'type' 			=> 'number',
                    'default' 		=> '5'
                );
            }

            public function getChronoRelaisMethod()
            {
                $packages = WC()->shipping->get_packages();
                if (!empty($packages[0]['rates']) && array_key_exists($this->id, $packages[0]['rates'])) {
                    return $this->id;
                }
            }

            public function relay_fancybox()
            {

                // Only available on checkout page
                if (!is_checkout()) {
                    return false;
                }

                if ($this->id == $this->getChronoRelaisMethod()) {

                    if (apply_filters('chrono_relais_template', $overridden_template = locate_template('chronopost/chronorelais.php'))) {
                        load_template($overridden_template);
                    } else {
                        load_template(CHRONO_PLUGIN_PATH . '/templates/chronorelais.php');
                    }
                }
            }

            public function getChronorelais()
            {
                $ws = new Chronopost_Webservice();

                $this->id = get_query_var('shipping_method_id');
                if (isset($_GET['mappostalcode'])) {
                    $webservbt =  $ws->getPointsRelaisByCp($_GET['mappostalcode']);
                } else {

                    if (isset($_POST['method_id'])) {
                        $method_id = sanitize_key($_POST['method_id']);
                    } else {
                        $method_id = get_query_var('shipping_method_id');
                    }

                    $webservbt = $ws->getPointRelaisByAddress($method_id);
                }
                $this->_chronorelais = $webservbt;

                return $this->_chronorelais;
            }
        }
    }
}

add_action('woocommerce_shipping_init', 'chronorelais_init');

function add_chronorelais($methods)
{
    $methods['chronorelais'] = 'WC_Chronorelais';
    return $methods;
}

add_filter('woocommerce_shipping_methods', 'add_chronorelais');


// ajax reload force this method to be placed outside the WC_Chronorelais class

add_action('woocommerce_after_shipping_rate', 'add_picking_relay_map_link', 10, 2);

function add_picking_relay_map_link($method, $index)
{
	$methods = WC()->shipping->get_shipping_methods();
	if (isset($methods[$method->id])) {
		$instance = $methods[$method->id];
		if ($instance instanceof WC_Chronorelais && is_checkout()) {
			echo '<div class="pickup-relay-link"><a href="javascript:;">' . __('Select a pickup relay', 'chronopost') . '</a></div>';
		}
	}
}

add_filter('woocommerce_order_shipping_to_display', 'add_chronorelais_extra_shipping_info', 10, 2);

function add_chronorelais_extra_shipping_info($shipping, $_order)
{
	$available_methods = WC()->shipping->get_shipping_methods();
    $shipping_methods = $_order->get_shipping_methods();
    if (is_array($shipping_methods)) {
        $shipping_method = array_shift($shipping_methods);
        $shipping_method_id = $shipping_method['method_id'];
    }
	$current_method = $available_methods[$shipping_method_id];
    if ($current_method instanceof WC_Chronorelais) {
        $pickup_relay_details = get_post_meta($_order->get_id(), '_shipping_method_chronorelais');
        if (is_array($pickup_relay_details)) {
            $pickup_relay_details = array_shift($pickup_relay_details);
        }
        $shipping .= " <small>";
        $shipping .= "({$pickup_relay_details['name']}, {$pickup_relay_details['address']} {$pickup_relay_details['postcode']} {$pickup_relay_details['city']}";
        if (!is_checkout() && $pickup_relay_details['google_map_url'] != "") {
            $shipping .= " - <a href=\"{$pickup_relay_details['google_map_url']}\">" . __('View on Google Map', 'chronopost') . "</a>";
        }
        $shipping .= ")</spall>";
    }
    return $shipping;
}

add_action('wp_ajax_nopriv_chronopost_pickup_relays', 'ajax_get_chronopost_pickup_relays');
add_action('wp_ajax_chronopost_pickup_relays', 'ajax_get_chronopost_pickup_relays');

function ajax_get_chronopost_pickup_relays()
{
    $nonce = $_POST['chrono_nonce'];

    // check to see if the submitted nonce matches with the
    // generated nonce we created earlier
    if (! wp_verify_nonce($nonce, 'chronopost_ajax')) {
        die('Busted!');
    }

    $ws = new Chronopost_Webservice();

    $method_id = sanitize_key($_POST['method_id']);
    $shippingMethodCode = explode("_", $method_id);
    $shippingMethodCode = $shippingMethodCode[0];
    $webservbt = $ws->getPointRelaisByAddress($shippingMethodCode);

    $response['data'] = $webservbt;
    $response['status'] = 'success';

    echo wp_send_json($response);
}

add_action('wp_ajax_load_chronorelais_picker', 'ajax_load_chronorelais_picker');
add_action('wp_ajax_nopriv_load_chronorelais_picker', 'ajax_load_chronorelais_picker');


function chrono_check_shipping_method_chronorelais($order, $data)
{
    $shipping_method = false;
    if (in_array($_POST['shipping_method'][0], array('chronorelais', 'chronorelaiseurope', 'chronorelaisdom', 'chronotoshopdirect', 'chronotoshopeurope'))) {
        $shipping_method = $_POST['shipping_method'][0];
    }

    if (!$shipping_method) {
        return;
    }

    if ($shipping_method && empty($_POST['shipping_method_chronorelais'])) {
        throw new Exception(sprintf(__('Please <a href="#shipping_method_0_%s">select a pickup relay</a>', 'chronopost'), $shipping_method));
    }
}
function chrono_save_shipping_method_chronorelais($order_id)
{
    $shipping_method = false;
    if (in_array($_POST['shipping_method'][0], array('chronorelais', 'chronorelaiseurope', 'chronorelaisdom', 'chronotoshopdirect', 'chronotoshopeurope'))) {
        $shipping_method = $_POST['shipping_method'][0];
    }

    if (!$shipping_method) {
        return;
    }

    if ($shipping_method && empty($_POST['shipping_method_chronorelais'])) {
        throw new Exception(sprintf(__('Please <a href="#container-method-%s">select a pickup relay</a>', 'chronopost'), $shipping_method));
        die();
    }
    // return new WP_Error( 'checkout-error', $e->getMessage() );
    if (! empty($_POST['shipping_method_chronorelais'])) {
        $relay_point_id = sanitize_text_field($_POST['shipping_method_chronorelais']);
        $ws = new Chronopost_Webservice();
        $pickup_relay_datas = $ws->getDetailRelaisPoint($relay_point_id);

        $_order = new WC_Order($order_id);
        $_order->set_shipping_company(chrono_format_relay_address($pickup_relay_datas->nom));
        $_order->set_shipping_address_1(chrono_format_relay_address($pickup_relay_datas->adresse1));
        $shipping_address_2 = $pickup_relay_datas->nom;
        if ($pickup_relay_datas->adresse3 != '') {
            $shipping_address_2 =  "\n" . $pickup_relay_datas->adresse3;
        }
        $_order->set_shipping_address_2(chrono_format_relay_address($shipping_address_2));
        $_order->set_shipping_postcode($pickup_relay_datas->codePostal);
        $_order->set_shipping_city(chrono_format_relay_address($pickup_relay_datas->localite));
        $_order->set_shipping_country($pickup_relay_datas->codePays);
        $_order->save();

        $pickup_relay_address = array();
        if (trim($pickup_relay_datas->adresse1) != '') {
            $pickup_relay_address[] = chrono_format_relay_address($pickup_relay_datas->adresse1);
        }
        if (trim($pickup_relay_datas->adresse2) != '') {
            $pickup_relay_address[] = chrono_format_relay_address($pickup_relay_datas->adresse2);
        }
        if (trim($pickup_relay_datas->adresse3) != '') {
            $pickup_relay_address[] = chrono_format_relay_address($pickup_relay_datas->adresse3);
        }

        $pickup_relay = array(
            'id' => $pickup_relay_datas->identifiant,
            'type' => $pickup_relay_datas->typeDePoint,
            'name' => chrono_format_relay_address($pickup_relay_datas->nom),
            'address' => implode(' ', $pickup_relay_address),
            'postcode' => $pickup_relay_datas->codePostal,
            'city' => chrono_format_relay_address($pickup_relay_datas->localite),
            'country' => $pickup_relay_datas->codePays,
            'google_map_url' => $pickup_relay_datas->urlGoogleMaps
        );

        update_post_meta($order_id, '_shipping_method_chronorelais', $pickup_relay);
    }
}

add_action('woocommerce_checkout_create_order', 'chrono_check_shipping_method_chronorelais', 10, 2);
add_action('woocommerce_checkout_update_order_meta', 'chrono_save_shipping_method_chronorelais', 10, 1);

function ajax_load_chronorelais_picker()
{
    $datas = chronorelais_async_load_fancybox(sanitize_key($_POST['method_id']));

    $response['data'] = !$datas ? __('No relay points found. Please check your address information.', 'chronopost') : $datas;

    $response['status'] = !$datas ? 'error' : 'success';
    echo wp_send_json($response);
    die();
}

function chronorelais_async_load_fancybox($method_id)
{
    chronopost_product_init();
    chronorelais_init();

    $ws = new Chronopost_Webservice();

    if (isset($_POST['method_id'])) {
        $method_id = sanitize_key($_POST['method_id']);
    }

    $pickup_relays = $ws->getPointRelaisByAddress($method_id);


    if (!$pickup_relays) {
        return false;
    }

    set_query_var('shipping_method_id', $method_id);
    set_query_var('pickup_relays', $pickup_relays);

    ob_start();
	if (apply_filters('chrono_relais_template', $overridden_template = locate_template('chronopost/chronorelais.php'))) {
        load_template($overridden_template);
    } else {
        load_template(CHRONO_PLUGIN_PATH . '/templates/chronorelais.php');
    }
    $output = ob_get_contents();
    ob_end_clean();

    return $output;
}

function chrono_is_method_chronorelais($method_id) {
	$methods = WC()->shipping->get_shipping_methods();
	if (isset($methods[$method_id])) {
		$instance = $methods[$method_id];
		if ($instance instanceof WC_Chronorelais) {
			return true;
		}
	}
	return false;
}

