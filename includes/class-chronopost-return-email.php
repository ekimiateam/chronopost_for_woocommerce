<?php
/**
* A custom Expedited Order WooCommerce Email class
*
* @since 0.1
* @extends \WC_Email
*/

if (! defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

class WC_Return_Label_Email extends WC_Email
{
    /**
    * Set email defaults
    *
    * @since 0.1
    */
    public function __construct()
    {
  
     // set ID, this simply needs to be a unique name
        $this->id = 'wc_return_label';
        $this->customer_email = true;
  
        // this is the title in WooCommerce Email settings
        $this->title = __('Return Label Email', 'chronopost');
  
        // this is the description in WooCommerce email settings
        $this->description = __('Email sent to customers when return labels are generated', 'chronopost');
  
        // these define the locations of the templates that this email should use, we'll just use the new order template since this email is similar
        $this->template_html  = '../../chronopost/emails/customer-return-label.php';
        $this->template_plain = '../../chronopost/emails/plain/customer-return-label.php';
  
        // Trigger on new paid orders
        add_action('chronopost_send_return_label_order', array( $this, 'trigger' ));
  
        // Call parent constructor to load any other defaults not explicity defined here
        parent::__construct();
    }
  
    /**
      * Get email subject.
      *
      * @since  3.1.0
      * @return string
      */
    public function get_default_subject()
    {
        return sprintf(__('%s: your Chronopost return label', 'chronopost'), get_bloginfo('name'));
    }

    /**
      * Get email heading.
      *
      * @since  3.1.0
      * @return string
      */
    public function get_default_heading()
    {
        return __('Your Chronopost return label', 'chronopost');
    }
  
    /**
      * Trigger.
      *
      * @param array $args
      */
    public function trigger($args)
    {
        if (! empty($args)) {
            $defaults = array(
        'order_id'      => '',
        'return_label'  => ''
      );

            $args = wp_parse_args($args, $defaults);

            extract($args);

            if ($order_id && ($this->object = wc_get_order($order_id))) {
                $this->recipient               = $this->object->get_billing_email();

                $this->find['order-date']      = '{order_date}';
                $this->find['order-number']    = '{order_number}';

                $this->replace['order-date']   = wc_format_datetime($this->object->get_date_created());
                $this->replace['order-number'] = $this->object->get_order_number();
            } else {
                return;
            }
        }

        if (! $this->is_enabled() || ! $this->get_recipient()) {
            return;
        }

        $this->setup_locale();
        $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), (array)$return_label);
        $this->restore_locale();
    }

    /**
    * get_content_html function.
    *
    * @since 0.1
    * @return string
    */
    public function get_content_html()
    {
        return wc_get_template_html($this->template_html, array(
            'order'         => $this->object,
            'email_heading' => $this->get_heading(),
            'sent_to_admin' => true,
            'plain_text'    => false,
            'email'			=> $this,
        ));
    }


    /**
    * get_content_plain function.
    *
    * @since 0.1
    * @return string
    */
    public function get_content_plain()
    {
        return wc_get_template_html($this->template_plain, array(
            'order'         => $this->object,
            'email_heading' => $this->get_heading(),
            'sent_to_admin' => true,
            'plain_text'    => false,
            'email'			=> $this,
        ));
    }
} // end \WC_Expedited_Order_Email class

return new WC_Return_Label_Email();
