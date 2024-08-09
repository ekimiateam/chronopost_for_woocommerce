<?php
/**
 * The class which aims to give informations about shipped products.
 *
 * @since      1.0.0
 * @package    Chronopost
 * @subpackage Chronopost/includes
 * @author     Adexos <contact@adexos.fr>
 */

class Chronopost_Package
{
	public static $admin_notice = '';

	public static function print_admin_notice()
	{
		echo chrono_notice(self::$admin_notice, 'error');
	}

	public static function getTotalWeight($order_items, $round = false)
	{
		$weight = 0;
		foreach ($order_items as $values) {
			$product_id = is_array($values) && array_key_exists('product_id', $values) ? $values['product_id'] : $values->get_product_id();

			$product = new WC_Product($product_id);
			if (!$product->needs_shipping()) {
				continue;
			}

			if (!$product->get_weight()) {
				if (is_admin()) {
					self::$admin_notice = sprintf(__('Warning, missing weight for Product %s. You should fill this information to make right label estimates.', 'chronopost'), $product_id);
					add_action('admin_notices', array('Chronopost_Package', 'print_admin_notice'));
				}
				return;
			}

			$itemsWeight = wc_get_weight($product->get_weight(), chrono_get_weight_unit());
			if ($round) {
				$itemsWeight = round($itemsWeight);
			}

			$weight += $values['quantity'] * $itemsWeight;
		}

		if (chrono_get_weight_unit() === 'g') {
			$weight = $weight / 1000;
		}

		return $weight;
	}
}
