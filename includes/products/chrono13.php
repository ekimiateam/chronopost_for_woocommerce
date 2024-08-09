<?php
/**
 *
 * Chronopost 13 offer
 *
 * @since      1.0.0
 * @package    Chronopost
 * @subpackage Chronopost/includes/products
 * @author     Adexos <contact@adexos.fr>
 */
function chrono13_init()
{
    if (! class_exists('WC_Chrono13')) {
        class WC_Chrono13 extends WC_Chronopost_Product
        {
            private $chrono_settings;

            public function shipping_method_settings()
            {
                $this->id = 'chrono13'; // Id for your shipping method. Should be unique.
				$this->pretty_title = __('Chronopost - Express delivery at home before 1pm', 'chronopost');  // Title shown in admin
				$this->title = __('Chronopost - Express delivery at home before 1pm', 'chronopost');  // Title shown in admin
				$this->method_title = __('Chronopost - Express delivery at home before 1pm', 'chronopost');  // Title shown in admin
				$this->method_description = __('Parcels delivered the next day before 1pm at your home. The day before delivery, You\'ll be notified by e-mail and SMS.',
					'chronopost'); // Description shown in admin
				$this->product_code = '01';
				$this->product_code_bal = '01';
				$this->product_code_return = '4T';
				$this->product_code_return_service = '898';
            }
        }
    }
}

add_action('woocommerce_shipping_init', 'chrono13_init');

function add_chrono13($methods)
{
	$methods['chrono13'] = 'WC_Chrono13';

	return $methods;
}

add_filter('woocommerce_shipping_methods', 'add_chrono13');
