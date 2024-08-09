<?php
/**
 * Customer note email
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/customer-note.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see 	    https://docs.woocommerce.com/document/template-structure/
 * @author 		WooThemes
 * @package 	WooCommerce/Templates/Emails
 * @version     2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p><?php _e( "Hello,", 'chronopost' ); ?></p>

<p><?php echo sprintf(__( "You will soon be making a Chronopost shipment for your backorder #%s. The person who sent you this mail has already prepared the waybill you will use. After printing, affix the shipping letter in an adhesive plastic pouch and affix it to your shipment. Note that the bar code must be clearly visible.", 'chronopost' ), $order->get_id()); ?></p>

<p><?php _e( "Regards.", 'chronopost' ); ?></p>

<?php

/**
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );
