<?php

class ChronopostCron
{

    var $namespace = 'update_status_chronopost';
    var $tracking_url = 'https://www.chronopost.fr/tracking-cxf/TrackingServiceWS/trackSkybill?language=fr_FR&skybillNumber=%s';

    /**
     * Initialize class.
     *
     * @since 3.3.0
     *
     * @return void
     */
    public function __construct()
    {
        add_action('init', array(&$this, 'scheduleCron'));
        add_action($this->namespace . '_execute_cron', [&$this, 'updateStatusOrder']);
    }

    public function init()
    {
        // Hook our cron.
        add_action($this->namespace . '_execute_cron', [&$this, 'scheduleCron']);
    }

    public function scheduleCron()
    {
        if (!wp_next_scheduled($this->namespace . '_execute_cron')) {
            wp_schedule_event(time(), 'hourly', $this->namespace . '_execute_cron');
        }
    }

    public function updateStatusOrder()
    {
        global $wpdb;

        $limitDate = new DateTime();
        $limitDate->modify('-1 month');

        $orders = $wpdb->get_results("
            SELECT p.ID FROM {$wpdb->prefix}posts as p
            INNER JOIN {$wpdb->prefix}woocommerce_order_items as woi ON woi.order_id = p.ID
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta as woim ON woim.order_item_id = woi.order_item_id
            WHERE p.post_type = 'shop_order'
            AND woim.meta_value LIKE 'chrono%'
            AND p.post_status = 'wc-processing'
            AND p.post_date > '".$limitDate->format('Y-m-d')." 00:00:00'
            GROUP BY p.ID
            ORDER BY p.ID DESC
        ");

        foreach ($orders as $order) {
            $shipment_datas = chrono_get_shipment_datas($order->ID);
            if ($shipment_datas) {
                $this->checkUpdateOrder($order->ID, $shipment_datas);
            }
        }

    }

    private function checkUpdateOrder($order_id, $shipment_datas)
    {
        foreach ($shipment_datas as $shipment) {
            foreach ($shipment['_parcels'] as $parcel) {
                $fp = fopen(sprintf($this->tracking_url, $parcel['_skybill_number']), 'r');
                $xml = stream_get_contents($fp);
                fclose($fp);
                $xml = new SimpleXMLElement($xml);
                $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
                $xml->registerXPathNamespace('ns1', 'http://cxf.tracking.soap.chronopost.fr/');
                foreach ($xml->xpath('//soap:Body/ns1:trackSkybillResponse/return/listEvents/events/code') as $event) {
                    if(in_array(trim((string)$event), array("D","D1","D2","D3","D4","D5","R"))){
                        wp_update_post(array(
                            'ID'          => $order_id,
                            'post_status' => 'wc-completed'
                        ));
                        $_order = new WC_Order($order_id);
                        $note = __('Your order has been delivered to the customer', 'chronopost');
                        $_order->add_order_note($note, 1);
                    }
                }
            }
        }
    }


}

new ChronopostCron();
