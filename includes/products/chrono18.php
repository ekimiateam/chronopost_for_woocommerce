<?php
/**
 *
 * Chronopost 18 offer
 *
 * @since      1.0.0
 * @package    Chronopost
 * @subpackage Chronopost/includes/products
 * @author     Adexos <contact@adexos.fr>
 */
function chrono18_init()
{
    if (! class_exists('WC_Chrono18')) {
        class WC_Chrono18 extends WC_Chronopost_Product
        {
            private $chrono_settings;

            public function shipping_method_settings()
            {
				$this->id = 'chrono18'; // Id for your shipping method. Should be uunique.
				$this->pretty_title = __('Chronopost - Express delivery at home before 6pm', 'chronopost');  // Title shown in admin
				$this->title = __('Chronopost - Express delivery at home before 6pm', 'chronopost');  // Title shown in admin
				$this->method_title = __('Chronopost - Express delivery at home before 6pm', 'chronopost');  // Title shown in admin
				$this->method_description = __('Parcels delivered the next day before 6pm at your home. The day before delivery, You\'ll be notified by e-mail and SMS.',
					'chronopost'); // Description shown in admin
				$this->product_code = '16';
				$this->product_code_bal = '2M';
				$this->product_code_str = '18H';
				$this->product_code_bal_str = '18H BAL';
				$this->product_code_return = '4U';
				$this->product_code_return_service = '835';
            }
        }
    }
}

add_action('woocommerce_shipping_init', 'chrono18_init');

function add_chrono18($methods)
{
	$methods['chrono18'] = 'WC_Chrono18';

	return $methods;
}

add_filter('woocommerce_shipping_methods', 'add_chrono18');
