<?php
/**
 *
 * Chronopost 10 offer
 *
 * @since      1.0.0
 * @package    Chronopost
 * @subpackage Chronopost/includes/products
 * @author     Adexos <contact@adexos.fr>
 */
function chrono10_init()
{
    if (! class_exists('WC_Chrono10')) {
        class WC_Chrono10 extends WC_Chronopost_Product
        {
            public function shipping_method_settings()
            {
				$this->id = 'chrono10'; // Id for your shipping method. Should be uunique.
				$this->pretty_title = __('Chronopost - Express delivery at home before 10am', 'chronopost');  // Title shown in admin
				$this->title = __('Chronopost - Express delivery at home before 10am', 'chronopost');  // Title shown in admin
				$this->method_title = __('Chronopost - Express delivery at home before 10am', 'chronopost');  // Title shown in admin
				$this->method_description = __('Parcels delivered the next day before 10am at your home. The day before delivery, You\'ll be notified by e-mail and SMS.',
					'chronopost'); // Description shown in admin
				$this->product_code = '02';
				$this->product_code_str = '10H';
				$this->product_code_return = '4S';
				$this->product_code_return_service = '180';
            }
        }
    }
}

add_action('woocommerce_shipping_init', 'chrono10_init');

function add_chrono10($methods)
{
	$methods['chrono10'] = 'WC_Chrono10';

	return $methods;
}

add_filter('woocommerce_shipping_methods', 'add_chrono10');
