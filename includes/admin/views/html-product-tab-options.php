<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $post;

// Availability date
$availability_timestamp = NPOM_Product::get_localized_availability_datetime_timestamp( $post->ID );
$availability_date      = ( 0 === $availability_timestamp ) ? '' : date_i18n( 'Y-m-d H:i', $availability_timestamp );

// Exception conditions that prevent pre-order settings changes
$has_active_pre_orders = NPOM_Product::product_has_active_pre_orders( $post->ID );
$has_synced_subs       = NPOM_Compat_Subscriptions::product_has_synced_subs();
$has_trial_period      = NPOM_Compat_Subscriptions::product_has_trial_period();

// Linter is getting confused with HTML mixed with PHP, so ignoring this rule
// phpcs:disable Generic.WhiteSpace.ScopeIndent.IncorrectExactIndent,Generic.WhiteSpace.ScopeIndent.Incorrect,Generic.WhiteSpace.DisallowSpaceIndent,Generic.WhiteSpace.ScopeIndent.IncorrectExact
?>

<div id="npom_data" class="panel woocommerce_options_panel">

	<div class="options_group">

		<?php if ( $has_active_pre_orders || $has_synced_subs || $has_trial_period ) : ?>

			<div class="notice notice-warning inline">

				<?php if ( $has_active_pre_orders ) : ?>
					<p>
						<?php esc_html_e( "This product has active pre-orders, so settings can't be changed while they're in progress. To make changes, cancel or complete the active pre-orders.", 'nextxen-pre-order-manager' ); ?>
					</p>
					<p>
						<a href="
						<?php
							echo esc_url(
								add_query_arg(
									array( '_product_id' => $post->ID ),
									admin_url( 'admin.php?page=npom_manager' )
								)
							);
						?>
						" class="button">
						<?php
							esc_html_e( 'View Pre-Orders', 'nextxen-pre-order-manager' );
						?>
						</a>&nbsp;&nbsp;
						<a href="
							<?php
							echo esc_url(
								add_query_arg(
									array(
										'tab'     => 'actions',
										'section' => 'complete',
										'action_default_product' => $post->ID,
									),
									admin_url( 'admin.php?page=npom_manager' )
								)
							);
							?>
						" class="button"><?php esc_html_e( 'Complete Pre-Orders', 'nextxen-pre-order-manager' ); ?></a>&nbsp;&nbsp;
						<a href="
							<?php
							echo esc_url(
								add_query_arg(
									array(
										'tab'     => 'actions',
										'section' => 'cancel',
										'action_default_product' => $post->ID,
									),
									admin_url( 'admin.php?page=npom_manager' )
								)
							);
							?>
						" class="button"><?php esc_html_e( 'Cancel Pre-Orders', 'nextxen-pre-order-manager' ); ?></a>
					</p>
				<?php endif; ?>

				<?php if ( $has_synced_subs ) : ?>
					<p>
						<?php esc_html_e( "This product has synced subscriptions, so settings can't be changed while they're in progress. To make changes, cancel or complete the synced subscriptions.", 'nextxen-pre-order-manager' ); ?>
					</p>
				<?php endif; ?>

				<?php if ( $has_trial_period ) : ?>
					<p>
						<?php esc_html_e( "This product has a trial period, so settings can't be changed while the trial is in progress. To make changes, cancel or complete the trial.", 'nextxen-pre-order-manager' ); ?>
					</p>
				<?php endif; ?>

			</div>

		<?php
			// Don't render code instead of just hiding/disabling fields to prevent client-side tampering with pre-order settings
			else :
		?>

			<?php
				/**
				 * Action hook to add custom content to the start of the pre-order product options
				 *
				 * @since 1.0.0
				 *
				 * @param int $post_id The ID of the post being edited
				 */
				do_action( 'npom_product_options_start', $post->ID );

				// Enable pre-orders checkbox.
				woocommerce_wp_checkbox(
					array(
						'id'          => '_npom_enabled',
						'label'       => __( 'Enable pre-orders', 'nextxen-pre-order-manager' ),
						'description' => __( 'Allow customers to place pre-orders for this product', 'nextxen-pre-order-manager' ),
						'desc_tip'    => false,
					)
				);
			?>

			<div class="npom-product-tab-fields-container">

				<p class="form-field _npom_availability_datetime_field">
					<label for="_npom_availability_datetime"><?php esc_html_e( 'Release date (optional)', 'nextxen-pre-order-manager' ); ?></label>
					<input
						type="text"
						class="short"
						name="_npom_availability_datetime"
						id="_npom_availability_datetime"
						value="<?php echo esc_attr( $availability_date ); ?>"
						placeholder="YYYY-MM-DD HH:MM"
					/>
					<span class="woocommerce-help-tip" tabindex="0" data-tip="<?php esc_attr_e( '(Optional) Specify when the product will be available. If set, customers will see this release date at checkout.', 'nextxen-pre-order-manager' ); ?>" aria-label="<?php esc_attr_e( '(Optional) Specify when the product will be available. If set, customers will see this release date at checkout.', 'nextxen-pre-order-manager' ); ?>"></span>
				</p>

				<?php

					// Pre-order fee
					woocommerce_wp_text_input(
						array(
							'id'          => '_npom_fee',
							'class'       => 'short wc_input_price',
							/* translators: %s: currency symbol */
							'label'       => sprintf( __( 'Pre-order fee (%s - optional)', 'nextxen-pre-order-manager' ), get_woocommerce_currency_symbol() ),
							'description' => __( '(Optional) Add an extra charge for pre-orders. Leave blank (or zero) if no additional fee is required.', 'nextxen-pre-order-manager' ),
							'desc_tip'    => true,
							'value'       => wc_format_localized_decimal( get_post_meta( $post->ID, '_npom_fee', true ) ),
							'placeholder' => '0' . wc_get_price_decimal_separator() . '00',
						)
					);

					// Pre-Order Payment Timing section

					woocommerce_wp_radio(
						array(
							'id'          => '_npom_when_to_charge',
							'label'       => __( 'Customers will be charged', 'nextxen-pre-order-manager' ),
							'description' => '',
							'options'     => array(
								'upfront'      => __( 'Upfront (pay now)', 'nextxen-pre-order-manager' ),
								'upon_release' => __( 'Upon release (pay later)', 'nextxen-pre-order-manager' ),
							),
							'default'     => 'upon_release',
						)
					);

					/**
					 * Action hook to add custom content to the end of the pre-order product options
					 *
					 * @since 1.0.0
					 *
					 * @param int $post_id The ID of the post being edited
					 */
					do_action( 'npom_product_options_end' );

					// Show upgrade prompts for premium features when no license is active.
					if ( ! NPOM_Premium::is_active() ) :
						?>
						<div class="wc-pom-premium-features-notice" style="margin-top:16px;padding:14px 16px;background:#f9f0ff;border:1px solid #c9a0dc;border-radius:5px;">
							<p style="font-weight:700;margin:0 0 8px;color:#3c1f5e;font-size:13px;">
								🔒 <?php esc_html_e( 'Unlock Premium Features', 'nextxen-pre-order-manager' ); ?>
							</p>
							<ul style="margin:0 0 12px;padding-left:18px;color:#50575e;font-size:13px;">
								<li><?php esc_html_e( 'Deposit / Partial Payments — collect a deposit now, charge the rest on release', 'nextxen-pre-order-manager' ); ?></li>
								<li><?php esc_html_e( 'Quantity Limit — cap the number of pre-orders accepted per product', 'nextxen-pre-order-manager' ); ?></li>
								<li><?php esc_html_e( 'Dashboard Widget — live pre-order stats on your WP dashboard', 'nextxen-pre-order-manager' ); ?></li>
								<li><?php esc_html_e( 'CSV Export — one-click export of all pre-order data', 'nextxen-pre-order-manager' ); ?></li>
								<li><?php esc_html_e( 'WooCommerce Subscriptions compatibility', 'nextxen-pre-order-manager' ); ?></li>
							</ul>
							<?php if ( function_exists( 'npom_fs' ) ) : ?>
								<a href="<?php echo esc_url( npom_fs()->get_upgrade_url() ); ?>" class="button button-primary" target="_blank">
									<?php esc_html_e( 'Upgrade to Premium', 'nextxen-pre-order-manager' ); ?>
								</a>
								<a href="<?php echo esc_url( npom_fs()->get_trial_url() ); ?>" style="margin-left:8px;font-size:12px;" target="_blank">
									<?php esc_html_e( 'Start free 14-day trial', 'nextxen-pre-order-manager' ); ?>
								</a>
							<?php endif; ?>
						</div>
					<?php endif; ?>

				<p class="npom-payment-timing-help">
					<strong><?php esc_html_e( 'Upfront (pay now):', 'nextxen-pre-order-manager' ); ?></strong>
					<?php esc_html_e( 'Charge customers during checkout. Once payment is confirmed, the order stays in "Pre-ordered" until release, then switches to "Completed" for virtual/downloadable items or "Processing" for physical items.', 'nextxen-pre-order-manager' ); ?>
					<br>
					<strong><?php esc_html_e( 'Upon release (pay later):', 'nextxen-pre-order-manager' ); ?></strong>
					<?php esc_html_e( 'No charge is taken at checkout. The order remains "Pre-ordered" until release, when it moves to "Pending", then auto-charges if there is a saved payment method, or emails the customer a payment link, switching to "Completed" (virtual/downloadable) or "Processing" (physical) when the payment is confirmed.', 'nextxen-pre-order-manager' ); ?>
				</p>

			</div>

		<?php endif; ?>
	</div>
</div>

<?php // phpcs:enable Generic.WhiteSpace.ScopeIndent.IncorrectExactIndent,Generic.WhiteSpace.ScopeIndent.Incorrect,Generic.WhiteSpace.DisallowSpaceIndent,Generic.WhiteSpace.ScopeIndent.IncorrectExact ?>
