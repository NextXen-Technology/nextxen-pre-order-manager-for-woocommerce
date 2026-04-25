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
	exit;
} // Exit if accessed directly ?>

<?php do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<?php

if ( 'pending' === $order->get_status() && ! NPOM_Manager::is_zero_cost_order( $order ) && ! NPOM_Order::order_has_payment_token( $order ) ) :
	?>

	<p>
	<?php
	/* translators: %1$s: href link for checkout payment url %2$s: closing href link */
	printf( esc_html__( 'Your pre-order is now available, but requires payment. %1$sPlease pay for your pre-order now.%2$s', 'nextxen-pre-order-manager' ), '<a href="' . esc_url( $order->get_checkout_payment_url() ) . '">', '</a>' );
	?>
	</p>

<?php elseif ( 'pending' === $order->get_status() && ! NPOM_Manager::is_zero_cost_order( $order ) && NPOM_Order::order_has_payment_token( $order ) ) : ?>

	<p>
		<?php esc_html_e( 'Your pre-order is now available. Your payment is being processed automatically and you will receive a confirmation once the payment is complete.', 'nextxen-pre-order-manager' ); ?>
	</p>

<?php elseif ( 'on-hold' === $order->get_status() && ! NPOM_Manager::is_zero_cost_order( $order ) ) : ?>

	<p>
		<?php esc_html_e( "Your pre-order is now available, but is waiting for the payment to be confirmed. Please wait until it's confirmed. Optionally, make sure the related payment has been sent to avoid delays on your order.", 'nextxen-pre-order-manager' ) . "\n\n"; ?>
	</p>

<?php elseif ( 'failed' === $order->get_status() ) : ?>

	<p>
	<?php
	/* translators: %1$s: href link for checkout payment url %2$s: closing href link */
	printf( esc_html__( 'Your pre-order is now available, but automatic payment failed. %1$sPlease update your payment information now.%2$s', 'nextxen-pre-order-manager' ), '<a href="' . esc_url( $order->get_checkout_payment_url() ) . '">', '</a>' );
	?>
	</p>

<?php else : ?>

<p><?php esc_html_e( 'Your pre-order is now available. Your order details are shown below for your reference.', 'nextxen-pre-order-manager' ); ?></p>

<?php endif; ?>

<?php if ( $message ) : ?>
	<blockquote><?php echo wp_kses_post( wpautop( wptexturize( $message ) ) ); ?></blockquote>
<?php endif; ?>

<?php
/*
 * @hooked WC_Emails::order_details() Shows the order details table.
 * @hooked WC_Structured_Data::generate_order_data() Generates structured data.
 * @hooked WC_Structured_Data::output_structured_data() Outputs structured data.
 * @since 2.5.0
 */
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

/*
 * @hooked WC_Emails::order_meta() Shows order meta data.
 */
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

/*
 * @hooked WC_Emails::customer_details() Shows customer details
 * @hooked WC_Emails::email_address() Shows email address
 */
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

?>
<p>
<?php
	/**
	* Show user-defined additional content - this is set in each email's settings.
	*/
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
} else {
	esc_html_e( 'Thanks for shopping with us.', 'nextxen-pre-order-manager' );
}
?>
</p>
<?php



/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );
