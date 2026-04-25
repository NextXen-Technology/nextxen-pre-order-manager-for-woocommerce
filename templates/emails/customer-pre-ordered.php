<?php
/**
 * WooCommerce Pre-Order Manager
 *
 * @package     NPOM/Templates/Email
 */

/**
 * Customer pre-ordered order email
 *
 * @since 1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>

<?php
do_action( 'woocommerce_email_header', $email_heading, $email );

$availability_date_text = '';

/* translators: %s: availability date */
if ( ! empty( $availability_date ) ) {
	$availability_date_text = $timestamp
		/* translators: %s: availability date */
		? sprintf( esc_html__( ' on %s.', 'nextxen-pre-order-manager' ), esc_html( $availability_date ) )
		/* translators: %s: availability date */
		: sprintf( esc_html__( ' %s.', 'nextxen-pre-order-manager' ), esc_html( $availability_date ) );
} else {
	$availability_date_text = '.';
}
?>

<?php if ( NPOM_Order::order_will_be_charged_upon_release( $order ) ) : ?>

	<?php
	if ( NPOM_Order::order_has_payment_token( $order ) ) {
		/* translators: %s: availability date */
		echo '<p>' . sprintf( esc_html__( 'Your pre-order has been received. You will be automatically charged for your order via your selected payment method when your pre-order is released%s Your order details are shown below for your reference.', 'nextxen-pre-order-manager' ), $availability_date_text ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	} else {
		/* translators: %s: availability date */
		echo '<p>' . sprintf( esc_html__( 'Your pre-order has been received. You will be prompted for payment for your order when your pre-order is released%s Your order details are shown below for your reference.', 'nextxen-pre-order-manager' ), $availability_date_text ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	?>
<?php else : ?>

<p>
	<?php
	/* translators: %s: availability date */
	printf( esc_html__( 'Your pre-order has been received. You will be notified when your pre-order is released%s Your order details are shown below for your reference.', 'nextxen-pre-order-manager' ), $availability_date_text ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?>
</p>

<?php endif; ?>

<?php do_action( 'woocommerce_email_order_details', $order, false, $plain_text, $email ); ?>

<?php do_action( 'woocommerce_email_order_meta', $order, false, $plain_text, $email ); ?>

<?php do_action( 'woocommerce_email_customer_details', $order, true, $plain_text, $email ); ?>

<?php
/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}
?>

<?php do_action( 'woocommerce_email_footer', $email ); ?>
