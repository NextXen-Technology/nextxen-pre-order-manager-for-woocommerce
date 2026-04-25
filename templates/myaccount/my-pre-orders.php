<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * My Pre-orders
 *
 * Shows the list of pre-order items on the account page
 *
 * @package NPOM/Templates
 * @version 1.4.4
 */
?>

<?php if ( ! empty( $items ) ) : ?>
	<table class="shop_table my_account_pre_orders shop_table_responsive my_account_orders">

		<thead>
			<tr>
				<th class="pre-order-order-number" scope="col"><span class="nobr"><?php echo esc_html__( 'Order', 'nextxen-pre-order-manager' ); ?></span></th>
				<th class="pre-order-title" scope="col"><span class="nobr"><?php echo esc_html__( 'Product', 'nextxen-pre-order-manager' ); ?></span></th>
				<th class="pre-order-status" scope="col"><span class="nobr"><?php echo esc_html__( 'Status', 'nextxen-pre-order-manager' ); ?></span></th>
				<th class="pre-order-release-date" scope="col"><span class="nobr"><?php echo esc_html__( 'Release date', 'nextxen-pre-order-manager' ); ?></span></th>
				<th class="pre-order-actions" scope="col"><?php echo esc_html__( 'Actions', 'nextxen-pre-order-manager' ); ?></th>
			</tr>
		</thead>

		<tbody>
			<?php
			foreach ( $items as $item ) :
					$preorder   = $item['order'];
					$order_id   = $preorder->get_id();
					$data       = $item['data'];
					$product_id = ! empty( $data['variation_id'] ) ? absint( $data['variation_id'] ) : absint( $data['product_id'] );
				?>
				<tr class="order">
					<th class="order-number" data-title="<?php echo esc_attr__( 'Order', 'nextxen-pre-order-manager' ); ?>" scope="row">
						<?php if ( method_exists( $preorder, 'get_view_order_url' ) ) : ?>
							<a href="<?php echo esc_url( $preorder->get_view_order_url() ); ?>">
								#<?php echo esc_html( $preorder->get_order_number() ); ?>
							</a>
						<?php else : ?>
							<a href="<?php echo esc_url( add_query_arg( 'order', $order_id, get_permalink( wc_get_page_id( 'view_order' ) ) ) ); ?>">
								<?php echo esc_html( $preorder->get_order_number() ); ?>
							</a>
						<?php endif; ?>
					</th>
						<td class="pre-order-title" data-title="<?php echo esc_attr__( 'Product', 'nextxen-pre-order-manager' ); ?>">
						<a href="<?php echo esc_attr( get_post_permalink( $data['product_id'] ) ); ?>">
							<?php echo wp_kses_post( $data['name'] ); ?>
						</a>
					</td>
					<td class="pre-order-status" data-title="<?php echo esc_attr__( 'Status', 'nextxen-pre-order-manager' ); ?>">
						<?php
						echo esc_html(
							NPOM_Order::get_pre_order_status_to_display( $preorder )
						);
						?>
					</td>
					<td class="pre-order-release-date" data-title="<?php echo esc_attr__( 'Release date', 'nextxen-pre-order-manager' ); ?>">
						<?php
						echo esc_html(
							NPOM_Product::get_localized_availability_date( $product_id )
						);
						?>
					</td>
					<td class="pre-order-actions order-actions">
						<?php
						if ( ! empty( $actions[ $order_id ] ) ) :
							$wp_button_class = wc_wp_theme_get_element_class_name( 'button' ) ? wc_wp_theme_get_element_class_name( 'button' ) : '';
							?>
							<?php
							foreach ( $actions[ $order_id ] as $key => $action ) : // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
								// Parse the URL to get the hidden fields.
								list( $form_action, $query_string ) = explode( '?', $action['url'], 2 );
								// The query string is passed with &amp; in the URL, so we need to convert it back to &.
								$query_string = str_replace( '&amp;', '&', $query_string );
								parse_str( $query_string, $parsed_query_string );
								?>
								<form method="get" action="<?php echo esc_url( $form_action ); ?>">
									<?php foreach ( $parsed_query_string as $name => $value ) : ?>
										<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" />
									<?php endforeach; ?>
									<button class="button woocommerce-button <?php echo sanitize_html_class( $key ); ?> <?php echo sanitize_html_class( $wp_button_class ); ?>" aria-label="<?php echo esc_attr( $action['aria-label'] ); ?>" <?php echo ( 'cancel' === $key ? 'onclick="return confirm( \'' . esc_js( __( 'Are you sure you want to cancel this pre-order?', 'nextxen-pre-order-manager' ) ) . '\');"' : '' ); ?>>
										<?php echo esc_html( $action['name'] ); ?>
									</button>
								</form>
								<?php
							endforeach;
							?>
							<?php
						endif;
						?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>

	</table>

	<?php if ( $total_pages > 1 ) : ?>
		<div class="woocommerce-pagination woocommerce-pagination--without-numbers woocommerce-Pagination">
			<?php if ( 1 !== $current_page ) : ?>
				<a class="woocommerce-button woocommerce-button--previous woocommerce-Button woocommerce-Button--previous button<?php echo esc_attr( $wp_button_class ); ?>" href="<?php echo esc_url( wc_get_endpoint_url( 'pre-orders', 2 === $current_page ? '' : $current_page - 1 ) ); ?>"><?php esc_html_e( 'Previous', 'nextxen-pre-order-manager' ); ?></a>
			<?php endif; ?>

			<?php if ( $total_pages !== $current_page ) : ?>
				<a class="woocommerce-button woocommerce-button--next woocommerce-Button woocommerce-Button--next button<?php echo esc_attr( $wp_button_class ); ?>" href="<?php echo esc_url( wc_get_endpoint_url( 'pre-orders', $current_page + 1 ) ); ?>"><?php esc_html_e( 'Next', 'nextxen-pre-order-manager' ); ?></a>
			<?php endif; ?>
		</div>
	<?php endif; ?>

<?php else : ?>

	<p><?php esc_html_e( 'You have no pre-orders.', 'nextxen-pre-order-manager' ); ?></p>

	<?php
endif;
