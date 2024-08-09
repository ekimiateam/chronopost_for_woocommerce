<?php
/**
 * Chronopost Shipment Management
 * (Deprecated)
 *
 *
 * @since      1.0.0
 * @package    Chronopost
 * @subpackage Chronopost/includes
 * @author     Adexos <contact@adexos.fr>
 */

function chronopost_ajax_init()
{
    class Chronopost_Ajax
    {
        public function __construct()
        {
            add_action('wp_ajax_create_shipment_label', array($this, 'ajax_saveAndCreateShipmentLabel'), 10);
        }
        
        public function ajax_saveAndCreateShipmentLabel()
        {
            $shipment = new Chronopost_Shipment();
            if (!isset($_POST['order_id']) || !isset($_POST['chrono_nonce'])) {
                return false;
            }
            $order_id =  wc_sanitize_order_id($_POST['order_id']);
            $nonce = sanitize_text_field($_POST['chrono_nonce']);
            
            // check to see if the submitted nonce matches with the
            // generated nonce we created earlier
            if (! wp_verify_nonce($nonce, 'chronopost_ajax')) {
                die('Busted!');
            }
            
            $response = array();

            try {
                $shipping_labels = $shipment->saveAndCreateShipmentLabel($order_id);
                $response['status'] = 'success';
    
                foreach ($shipping_labels as $key => $shipping_label) {
                    $shipping_labels[$key]['_tracking_url'] = chrono_get_tracking_url($shipping_label['_skybill_number'], $shipping_label['_shipping_method_id']);
                }
                $response['shipping_labels'] = $shipping_labels;
                echo wp_send_json($response);
            } catch (Exception $e) {
                $response['status'] = 'error';
                $response['message'] = chrono_notice($e->getMessage(), 'error');
                echo wp_send_json($response);
            }
        }
    }
    
    new Chronopost_Ajax();
}

add_action('admin_init', 'chronopost_ajax_init');
