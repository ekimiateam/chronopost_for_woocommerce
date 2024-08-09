<?php
/**
 *
 * Chronopost Sameday offer
 *
 * @since      1.0.0
 * @package    Chronopost
 * @subpackage Chronopost/includes/products
 * @author     Adexos <contact@adexos.fr>
 */
function chronosameday_init()
{
    if (! class_exists('WC_Chronosameday')) {
        class WC_Chronosameday extends WC_Chronopost_Product
        {
            public function shipping_method_settings()
			{
				$this->id = 'chronosameday'; // Id for your shipping method. Should be uunique.
				$this->pretty_title = __('Chronopost - Same-day delivery at home', 'chronopost');  // Title shown in admin
				$this->title = __('Chronopost - Same-day delivery at home', 'chronopost');  // Title shown in admin
				$this->method_title = __('Chronopost - Same-day delivery at home', 'chronopost');  // Title shown in admin
				$this->method_description = __('Parcels delivered to Europe in 1 to 3 days',
					'chronopost'); // Description shown in admin
				$this->product_code = '4I';
				$this->product_code_str = 'SMD';
            }

            public function extra_form_fields()
            {
				parent::extra_form_fields();
                $this->instance_form_fields['delivery_time_limit'] = array(
                    'title' 		=> __('Disable after', 'chronopost'),
                    'type' 			=> 'select',
                    'default' 		=> '15:00',
                    'options' => array(
                      '07:00' => '7:00',
                      '07:30' => '7:30',
                      '08:00' => '8:00',
                      '08:30' => '8:30',
                      '09:00' => '9:00',
                      '09:30' => '9:30',
                      '10:00' => '10:00',
                      '10:30' => '10:30',
                      '11:00' => '11:00',
                      '11:30' => '11:30',
                      '12:00' => '12:00',
                      '12:30' => '12:30',
                      '13:00' => '13:00',
                      '13:30' => '13:30',
                      '14:00' => '14:00',
                      '14:30' => '14:30',
                      '15:00' => '15:00',
                    )
                );
            }

            public function calculate_shipping($package = array())
            {
                $timezone = chrono_get_timezone();

                $delivery_time_limit = new DateTime(date('Y-m-d') . chrono_get_method_settings($this->id, 'delivery_time_limit'), new DateTimeZone($timezone));
                $now = new DateTime('NOW', new DateTimeZone($timezone));

                if ($now >= $delivery_time_limit) {
                    return false;
                }

                $rate = $this->get_shipping_rate($package);
                $this->add_rate($rate);
            }
        }
    }
}

add_action('woocommerce_shipping_init', 'chronosameday_init');

function add_chronosameday($methods)
{
	$methods['chronosameday'] = 'WC_Chronosameday';

	return $methods;
}

add_filter('woocommerce_shipping_methods', 'add_chronosameday');
