<?php
/**
 * This file is part of the AmphiBee package.
 * (c) AmphiBee <contact@amhibee.fr>
 */

class Chronopost_Admin_Upgrades
{
	private static $shipping_zones = [];

	/**
	 * We need to transform shipments data for new multishipping feature
	 */
	public static function upgrade_1_1_0()
	{
		// Loop chronopost orders
		$_orders = WC_Chronopost_Order::get_orders();
		while ($_orders->have_posts()) {
			$should_update = false;
			$_orders->the_post();
			$_order = new WC_Order(get_the_ID());
			$shipment_datas = chrono_get_shipment_datas($_order->get_id());
			$new_shipment_datas = array();
			foreach ((array)$shipment_datas as $shipment_data) {
				if (isset($shipment_data['_pdf_buffer'])) {
					$new_shipment_datas[] = array(
						'_reservation_number' => null,
						'_shipping_method_id' => $shipment_data['_shipping_method_id'],
						'_parcels'            => array($shipment_data)
					);
					$should_update = true;
				}
			}
			if ($should_update) {
				update_post_meta($_order->get_id(), '_shipment_datas', $new_shipment_datas);
			}
		}

		update_option("chrono_db_version", '1.1.0');
	}

	/**
	 * Upgrade settings to new format
	 *
	 * @return void
	 */
	public static function upgrade_2_0_0()
	{
		$existingZones = self::get_all_shipping_zones();
		$foundCountries = [];

		// Find all countries that already are in a zone
		foreach ($existingZones as $zone) {
			$locations = $zone->get_zone_locations();
			foreach ($locations as $location) {
				if ($location->type === 'country' && !isset($foundCountries[$location->code])) {
					$foundCountries[$location->code] = $zone;
				}
			}
		}

		// Loop over Chronopost shipping methods and try to match a shipping zone
		$zonesToCreate = [];
		$carrierToZoneMap = [];
		foreach (WC()->shipping()->get_shipping_methods() as $shipping_method) {
			if ($shipping_method instanceof WC_Chronopost_Product) {
				$table_rates = get_option($shipping_method->id . '_table_rates');
				// Skip if zone is already migrated or not available
				if ($table_rates === false || isset($table_rates['rates'])) {
					continue;
				}
				foreach ($table_rates as $zoneName => $settings) {
					// Existing zone for the country, assign carrier
					$shippingZones = self::findCorrespondingZones($settings['countries'], $foundCountries);
					if (!empty($shippingZones)) {
						$carrierToZoneMap[] = [
							'carrier'  => $shipping_method,
							'zones'    => $shippingZones,
							'settings' => $settings
						];
						continue;
					}

					// No zone found, create a new one
					if (!isset($zonesToCreate[md5($zoneName)])) {
						$zonesToCreate[md5($zoneName)]['name'] = $zoneName;
						$zonesToCreate[md5($zoneName)]['countries'] = $settings['countries'];
						$zonesToCreate[md5($zoneName)]['products'] = [];
					}
					$zonesToCreate[md5($zoneName)]['products'][] = [
						'settings' => $settings,
						'carrier'  => $shipping_method
					];
				}
			}
		}

		if ($carrierToZoneMap) {
			foreach ($carrierToZoneMap as $carrierToZone) {
				foreach ($carrierToZone['zones'] as $zone) {
					$instance = $zone->add_shipping_method($carrierToZone['carrier']->id);
					// Migrate settings
					self::migrate_settings($carrierToZone, $instance);
				}
			}
		}

		if ($zonesToCreate) {
			foreach ($zonesToCreate as $zoneToCreate) {
				$zone = new WC_Shipping_Zone();
				// We limit the size due to database structure limitations
				$zone->set_zone_name('[CHR] ' . substr($zoneToCreate['name'], 0, 196));
				$countries = [];
				foreach ($zoneToCreate['products'] as $product) {
					if (!isset($product['carrier'], $product['carrier']->id)) {
						continue;
					}
					$instance = $zone->add_shipping_method($product['carrier']->id);
					$countries += $product['settings']['countries'];
					// Update product settings to new format
					// Migrate settings
					self::migrate_settings($product, $instance);
				}
				if (!empty($countries)) {
					foreach ($countries as $country) {
						$zone->add_location($country, 'country');
					}
				}
				$zone->save();
			}
		}

		update_option("chrono_db_version", '2.0.0');
	}

	protected static function migrate_settings($carrier, $instance)
	{
		// Migrate settings
		$settings = get_option('woocommerce_' . $carrier['carrier']->id . '_settings');
		update_option('woocommerce_' . $carrier['carrier']->id . '_' . $instance . '_settings',
			$settings);
		update_option($carrier['carrier']->id . '_' . $instance . '_table_rates',
			$carrier['settings']);

		if ($carrier['carrier'] instanceof WC_ChronoPrecise) {
			$slot_options = get_option("chronoprecise_table_slots");
			$cost_levels = get_option("chronoprecise_cost_levels");
			update_option('chronoprecise_'. $instance .'_table_slots', $slot_options);
			update_option('chronoprecise_'. $instance .'_cost_levels', $cost_levels);
		}
	}

	/**
	 * @function      Get WooCommerce Shipping Zones
	 * @return        WC_Shipping_Zone[]
	 * @author        Rodolfo Melogli
	 * @compatible    WooCommerce 5
	 */

	private static function get_all_shipping_zones()
	{
		if (empty(self::$shipping_zones)) {
			$data_store = WC_Data_Store::load('shipping-zone');
			$raw_zones = $data_store->get_zones();
			foreach ($raw_zones as $raw_zone) {
				$zones[] = new WC_Shipping_Zone($raw_zone);
			}
			$zones[] = new WC_Shipping_Zone(0);
			self::$shipping_zones = $zones;
		}

		return self::$shipping_zones;
	}

	/**
	 * This method with loop over all existing shipping zones and find which ones are available for those countries
	 *
	 * @param array $countries
	 * @param array $zones
	 *
	 * @return array
	 */
	private static function findCorrespondingZones($countries, $zones)
	{
		$correspondingZones = array();
		foreach ($countries as $country) {
			if (isset($zones[$country]) && is_a($zones[$country], WC_Shipping_Zone::class)) {
				$correspondingZones[$zones[$country]->get_id()] = $zones[$country];
			}
		}

		return $correspondingZones;
	}
}
