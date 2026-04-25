<?php
/**
 * WooCommerce Pre-Order Manager
 *
 * @package NPOM/Templates/Email
 */

/**
 * Customer pre-order available notification email
 *
 * @since 1.0.0
 * @version 1.5.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html( wp_strip_all_tags( $email_heading ) );
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

if ( 'pending' === $order->get_status() && ! NPOM_Manager::is_zero_cost_order( $order ) && ! NPOM_Order::order_has_payment_token( $order ) ) :

	esc_html_e( 'Your pre-order is now available, but requires payment. Please pay for your pre-order now: ', 'nextxen-pre-order-manager' ) . esc_url( $order->get_checkout_payment_url() ) . "\n\n";

elseif ( 'pending' === $order->get_status() && ! NPOM_Manager::is_zero_cost_order( $order ) && NPOM_Order::order_has_payment_token( $order ) ) :

	esc_html_e( 'Your pre-order is now available. Your payment is being processed automatically and you will receive a confirmation once the payment is complete.', 'nextxen-pre-order-manager' );
	echo "\n\n";

elseif ( 'on-hold' === $order->get_status() && ! NPOM_Manager::is_zero_cost_order( $order ) ) :

	esc_html_e( "Your pre-order is now available, but is waiting for the payment to be confirmed. Please wait until it's confirmed. Optionally, make sure the related payment has been sent to avoid delays on your order.", 'nextxen-pre-order-manager' ) . "\n\n";

elseif ( 'failed' === $order->get_status() ) :

	esc_html_e( 'Your pre-order is now available, but automatic payment failed. Please update your payment information now : ', 'nextxen-pre-order-manager' ) . esc_url( $order->get_checkout_payment_url() ) . "\n\n";

else :

	esc_html_e( 'Your pre-order is now available. Your order details are shown below for your reference.', 'nextxen-pre-order-manager' ) . "\n\n";

endif;

if ( $message ) :

	echo "----------\n\n";
	echo esc_html( wp_strip_all_tags( wptexturize( $message ) ) ) . "\n\n";
	echo "----------\n\n";

endif;

/*
 * @hooked WC_Emails::order_details() Shows the order details table.
 * @hooked WC_Structured_Data::generate_order_data() Generates structured data.
 * @hooked WC_Structured_Data::output_structured_data() Outputs structured data.
 * @since 2.5.0
 */
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

echo "\n----------------------------------------\n\n";

/*
 * @hooked WC_Emails::order_meta() Shows order meta data.
 */
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

/*
 * @hooked WC_Emails::customer_details() Shows customer details
 * @hooked WC_Emails::email_address() Shows email address
 */
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

echo "\n\n----------------------------------------\n\n";

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
} else {
	echo esc_html__( 'Thanks for shopping with us.', 'nextxen-pre-order-manager' );
}

echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
