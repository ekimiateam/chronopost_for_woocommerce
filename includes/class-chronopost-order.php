<?php
/**
 * Get orders using Chronopost Product Shipping.
 *
 * Maintain a list of all hooks that are registered throughout
 * the plugin, and register them with the WordPress API. Call the
 * run function to execute the list of actions and filters.
 *
 * @package    Chronopost
 * @subpackage Chronopost/includes
 * @author     Adexos <contact@adexos.fr>
 */

if (! class_exists('WC_Chronopost_Order')) {
	class WC_Chronopost_Order
	{
		public static function get_post_count($only_label = false)
		{
			$args = self::get_wp_query_args(-1, 1, $only_label);
			$query = new WP_Query($args);
			return $query->post_count;
		}

		public static function get_wp_query_args($limit = -1, $pagenum = 1, $only_label = false)
		{
			global $wpdb;

			$results = $wpdb->get_results( "
                SELECT SQL_CALC_FOUND_ROWS p.ID FROM {$wpdb->prefix}posts as p
                INNER JOIN {$wpdb->prefix}woocommerce_order_items as woi ON woi.order_id = p.ID
                INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta as woim ON woim.order_item_id = woi.order_item_id
                WHERE p.post_type = 'shop_order'
                AND woim.meta_value LIKE 'chrono%'
                AND ((p.post_status <> 'trash' AND p.post_status <> 'auto-draft'))
                GROUP BY p.ID
                ORDER BY p.ID DESC
            " );

			if (count($results) == 0) {
				return [];
			}

			$order_ids = array();

			foreach ($results as $p) {
				$order_ids[] = $p->ID;
			}

			$meta_query = [];

			if ($only_label) {
				$meta_query[] = array(
					'key'		=> '_shipment_datas',
					'value'		=> '_skybill_number',
					'compare'	=> 'LIKE'
				);
			}

			$args = array(
				'posts_per_page'	=> $limit,
				'post_type'		=> 'shop_order',
				'post__in'	    => $order_ids,
				'post_status'   => 'any',
				'orderby'       => 'ID',
				'order'         => 'DESC',
				'meta_query'	=> $meta_query,
				'paged' => $pagenum
			);

			return $args;
		}

		public static function get_orders($limit = -1, $pagenum = 1, $only_label= false)
		{
			$args = self::get_wp_query_args($limit, $pagenum, $only_label);
			return new WP_Query($args);
		}

		/**
		 * @param        $_order
		 * @param        $parcels
		 * @param string $reservation_number
		 *
		 * @return array|bool|mixed
		 */
		public static function add_tracking_numbers($_order, $parcels, $reservation_number = '')
		{
			$order_shipping_method = $_order->get_shipping_methods();
			$shipping_method = reset($order_shipping_method);
			$shipping_method_id = $shipping_method->get_method_id();

			$shipment_datas = chrono_get_shipment_datas( $_order->get_id() );
			if ( ! $shipment_datas ) {
				$shipment_datas = array();
			}
			$new_shipment_datas = array(
				'_reservation_number'     => $reservation_number,
				'_shipping_method_id' => $shipping_method_id,
				'_parcels' => array()
			);

			$tracking_infos = array();
			foreach ($parcels as $parcel) {
				$tracking_number = $parcel->skybillNumber;
				$tracking_infos[] = array(
					'number' => $tracking_number,
					'url' => chrono_get_tracking_url( $tracking_number, $shipping_method_id )
				);
				$new_parcel = array(
					'_skybill_number' => $tracking_number,
					'_parcel'         => json_decode( json_encode( $parcel ), true )
				);
				array_push($new_shipment_datas['_parcels'], $new_parcel);
			}

			$shipment_datas[] = $new_shipment_datas;
			update_post_meta($_order->get_id(), '_shipment_datas', $shipment_datas);

			if (isset($tracking_infos) && is_array($tracking_infos)) {
				foreach ($tracking_infos as $tracking_info) {
					$note = sprintf(__('Your order has just been shipped. The tracking number is %s, you can track your shipment by <a href="%s">clicking here</a>', 'chronopost'), $tracking_info['number'], $tracking_info['url']);
					$_order->add_order_note($note, 1);
				}
			}
			return $shipment_datas;
		}

		public static function add_parcels_dimensions($shipment_datas, $parcels_dimensions) {
			end($shipment_datas);
			$last_shipment_key = key($shipment_datas);
			foreach ($parcels_dimensions as $i => $dimensions) {
				if (isset($shipment_datas[$last_shipment_key]['_parcels'][$i - 1])) {
					$shipment_datas[$last_shipment_key]['_parcels'][$i - 1]['dimensions'] = $dimensions;
				}
			}
			return $shipment_datas;
		}

		public static function add_parcels_skybill_params($shipment_datas, $expeditionArray) {
			end($shipment_datas);
			$last_shipment_key = key($shipment_datas);
			foreach ($expeditionArray['skybillValue'] as $i => $params) {
				if (isset($shipment_datas[$last_shipment_key]['_parcels'][$i])) {
					$shipment_datas[$last_shipment_key]['_parcels'][$i]['skybillValue'] = $params;
				}
			}
			return $shipment_datas;
		}
	}
}
