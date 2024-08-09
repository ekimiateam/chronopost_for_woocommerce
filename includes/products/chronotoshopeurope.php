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

function chronotoshopeurope_init()
{
    if (! class_exists('WC_ToShopEurope')) {
        class WC_ToShopEurope extends WC_ChronoRelaisEurope
        {
			private $chrono_settings;

            public function shipping_method_settings()
            {
                $this->id = 'chronotoshopeurope'; // Id for your shipping method. Should be unique.
				$this->pretty_title = __('Chronopost - Europe delivery in Pickup relay', 'chronopost');  // Title shown in admin
				$this->title = __('Chronopost - Europe delivery in Pickup relay', 'chronopost');  // Title shown in admin
				$this->method_title = __('Chronopost - Europe delivery in Pickup relay', 'chronopost');  // Title shown in admin
				$this->method_description = __('Parcels delivered in 3 to 7 days to Europe in the Pickup point of your choice.',
					'chronopost'); // Description shown in admin
				$this->product_code = '6B';
				$this->product_code_str = '6B';
				$this->max_product_weight = 20;
            }
		}
    }
}

add_action('woocommerce_shipping_init', 'chronotoshopeurope_init');

function add_toshopeurope($methods)
{
    $methods['chronotoshopeurope'] = 'WC_ToShopEurope';
    return $methods;
}

add_filter('woocommerce_shipping_methods', 'add_toshopeurope');
