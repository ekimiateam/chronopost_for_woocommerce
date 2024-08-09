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

function chronotoshopdirect_init()
{
    if (! class_exists('WC_ToShopDirect')) {
        class WC_ToShopDirect extends WC_Chronorelais
        {
			private $chrono_settings;

            public function shipping_method_settings()
            {
                $this->id = 'chronotoshopdirect'; // Id for your shipping method. Should be unique.
				$this->pretty_title = __('Chronopost - Delivery in Pickup relay', 'chronopost');  // Title shown in admin
				$this->title = __('Chronopost - Delivery in Pickup relay', 'chronopost');  // Title shown in admin
				$this->method_title = __('Chronopost - Delivery in Pickup relay', 'chronopost');  // Title shown in admin
				$this->method_description = __('Parcels delivered in 2 to 3 days in the Pickup point of your choice. You\'ll be notified by e-mail.',
					'chronopost'); // Description shown in admin
				$this->product_code = '5X';
				$this->product_code_str = '5X';
				$this->max_product_weight = 20;
				$this->product_code_return = '5Y';
            }
		}
    }
}

add_action('woocommerce_shipping_init', 'chronotoshopdirect_init');

function add_toshopdirect($methods)
{
    $methods['chronotoshopdirect'] = 'WC_ToShopDirect';
    return $methods;
}

add_filter('woocommerce_shipping_methods', 'add_toshopdirect');
