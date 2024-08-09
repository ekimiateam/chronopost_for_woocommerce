<?php
/**
 *
 * Chronopost Express offer
 *
 * @since      1.0.0
 * @package    Chronopost
 * @subpackage Chronopost/includes/products
 * @author     Adexos <contact@adexos.fr>
 */
function chronoexpress_init()
{
    if (! class_exists('WC_Chronoexpress')) {
        class WC_Chronoexpress extends WC_Chronopost_Product
        {
            public function shipping_method_settings()
            {
				$this->id = 'chronoexpress'; // Id for your shipping method. Should be uunique.
				$this->pretty_title = __('Chronopost - Express delivery at home', 'chronopost');  // Title shown in admin
				$this->title = __('Chronopost - Express delivery at home', 'chronopost');  // Title shown in admin
				$this->method_title = __('Chronopost - Express delivery at home', 'chronopost');  // Title shown in admin
				$this->method_description = __('Parcels delivered to Europe in 1 to 3 days, 48 hours to the DOM and 2 to 5 days to the rest of the world.',
					'chronopost'); // Description shown in admin
				$this->product_code = '17';
				$this->product_code_str = 'EI';
            }

            public function extra_form_fields()
            {
                parent::extra_form_fields();
                unset($this->instance_form_fields['deliver_on_saturday']);
            }
        }
    }
}

add_action('woocommerce_shipping_init', 'chronoexpress_init');

function add_chronoexpress($methods)
{
	$methods['chronoexpress'] = 'WC_Chronoexpress';

	return $methods;
}

add_filter('woocommerce_shipping_methods', 'add_chronoexpress');
