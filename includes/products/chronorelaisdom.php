<?php
/**
 *
 * Chronopost Relai Dom offer
 *
 * @since      1.0.0
 * @package    Chronopost
 * @subpackage Chronopost/includes/products
 * @author     Adexos <contact@adexos.fr>
 */
function chronorelaisdom_init()
{
    if (! class_exists('WC_ChronoRelaisDom')) {
        class WC_ChronoRelaisDom extends WC_Chronorelais
        {
            private $chrono_settings;

            public $tracking_url = 'http://www.chronopost.fr/expedier/inputLTNumbersNoJahia.do?lang=fr_FR&listeNumeros={tracking_number}';

			public function shipping_method_settings()
			{
				$this->id = 'chronorelaisdom'; // Id for your shipping method. Should be uunique.
				$this->pretty_title = __('Chronopost - Overseas departments delivery in Pickup relay',
					'chronopost');  // Title shown in admin
				$this->title = __('Chronopost - Overseas departments delivery in Pickup relay', 'chronopost');  // Title shown in admin
				$this->method_title = __('Chronopost - Overseas departments delivery in Pickup relay', 'chronopost');  // Title shown in admin
				$this->method_description = __('Parcels delivered in 3 to 4 days to the DOM in the Pickup point of your choice.',
					'chronopost'); // Description shown in admin
				$this->product_code = '4P';
				$this->product_code_str = '4P';
				$this->max_product_weight = 20;
            }
        }
    }
}

add_action('woocommerce_shipping_init', 'chronorelaisdom_init');

function add_chronorelaisdom($methods)
{
	$methods['chronorelaisdom'] = 'WC_ChronoRelaisDom';

	return $methods;
}

add_filter('woocommerce_shipping_methods', 'add_chronorelaisdom');
