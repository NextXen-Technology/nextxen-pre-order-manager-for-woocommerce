<?php
/**
 * WooCommerce Pre-Order Manager – Dashboard Widget (Premium Feature)
 *
 * Registers a WordPress admin dashboard widget that gives store owners a
 * real-time snapshot of their pre-order activity:
 *
 *   • Total active pre-orders (count + revenue)
 *   • Pre-orders completed this month
 *   • Pre-orders cancelled this month
 *   • Upcoming releases (products releasing within the next 30 days)
 *   • Quick links to the Pre-Orders management screen and settings
 *
 * Stats are cached for 5 minutes via a transient to avoid hammering the DB
 * on every dashboard load.
 *
 * @package NPOM/Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NPOM_Dashboard_Widget
 */
class NPOM_Dashboard_Widget {

	/**
	 * Transient key / cache TTL.
	 */
	const TRANSIENT_KEY = 'npom_dashboard_stats';
	const CACHE_TTL     = 5 * MINUTE_IN_SECONDS;

	/**
	 * Boot hooks.
	 */
	public function __construct() {
		add_action( 'wp_dashboard_setup', array( $this, 'register_widget' ) );

		// Flush cached stats whenever a pre-order status changes.
		add_action( 'npom_pre_order_completed',  array( $this, 'flush_cache' ) );
		add_action( 'wc_pre_order_status_new_to_active',         array( $this, 'flush_cache' ) );
		add_action( 'wc_pre_order_status_active_to_cancelled',   array( $this, 'flush_cache' ) );
		// Also flush when a pre-order is cancelled from the manager's cancel flow.
		add_action( 'npom_pre_order_cancelled',  array( $this, 'flush_cache' ) );
	}

	// -------------------------------------------------------------------------
	// Widget registration
	// -------------------------------------------------------------------------

	/**
	 * Register the dashboard widget.
	 */
	public function register_widget() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'npom_dashboard_widget',
			__( 'Pre-Order Manager', 'nextxen-pre-order-manager' ),
			array( $this, 'render_widget' )
		);
	}

	// -------------------------------------------------------------------------
	// Widget render
	// -------------------------------------------------------------------------

	/**
	 * Render the dashboard widget HTML.
	 */
	public function render_widget() {
		$stats = $this->get_stats();
		?>
		<div class="npom-widget">
			<style>
				.npom-widget .pom-stat-row {
					display: flex;
					justify-content: space-between;
					align-items: center;
					padding: 8px 0;
					border-bottom: 1px solid #f0f0f1;
				}
				.npom-widget .pom-stat-row:last-child { border-bottom: none; }
				.npom-widget .pom-stat-label { color: #50575e; font-size: 13px; }
				.npom-widget .pom-stat-value { font-weight: 600; font-size: 14px; color: #1d2327; }
				.npom-widget .pom-stat-value.pom-highlight { color: #0071a1; }
				.npom-widget .pom-section-title {
					font-size: 11px;
					text-transform: uppercase;
					letter-spacing: .5px;
					color: #8c8f94;
					margin: 14px 0 4px;
				}
				.npom-widget .pom-upcoming-item {
					display: flex;
					justify-content: space-between;
					padding: 5px 0;
					border-bottom: 1px solid #f0f0f1;
					font-size: 13px;
				}
				.npom-widget .pom-upcoming-item:last-child { border-bottom: none; }
				.npom-widget .pom-upcoming-date { color: #8c8f94; }
				.npom-widget .pom-actions {
					margin-top: 12px;
					padding-top: 10px;
					border-top: 1px solid #f0f0f1;
					display: flex;
					gap: 8px;
					flex-wrap: wrap;
				}
				.npom-widget .pom-no-data {
					color: #8c8f94;
					font-style: italic;
					font-size: 13px;
					padding: 4px 0;
				}
			</style>

			<!-- Active pre-orders -->
			<p class="pom-section-title"><?php esc_html_e( 'Active Pre-Orders', 'nextxen-pre-order-manager' ); ?></p>

			<div class="pom-stat-row">
				<span class="pom-stat-label"><?php esc_html_e( 'Total active orders', 'nextxen-pre-order-manager' ); ?></span>
				<span class="pom-stat-value pom-highlight"><?php echo esc_html( number_format_i18n( $stats['active_count'] ) ); ?></span>
			</div>

			<div class="pom-stat-row">
				<span class="pom-stat-label"><?php esc_html_e( 'Active revenue (deposit + upfront)', 'nextxen-pre-order-manager' ); ?></span>
				<span class="pom-stat-value"><?php echo wp_kses_post( wc_price( $stats['active_revenue'] ) ); ?></span>
			</div>

			<!-- This month -->
			<p class="pom-section-title"><?php esc_html_e( 'This Month', 'nextxen-pre-order-manager' ); ?></p>

			<div class="pom-stat-row">
				<span class="pom-stat-label"><?php esc_html_e( 'New pre-orders', 'nextxen-pre-order-manager' ); ?></span>
				<span class="pom-stat-value"><?php echo esc_html( number_format_i18n( $stats['month_new'] ) ); ?></span>
			</div>

			<div class="pom-stat-row">
				<span class="pom-stat-label"><?php esc_html_e( 'Completed / released', 'nextxen-pre-order-manager' ); ?></span>
				<span class="pom-stat-value"><?php echo esc_html( number_format_i18n( $stats['month_completed'] ) ); ?></span>
			</div>

			<div class="pom-stat-row">
				<span class="pom-stat-label"><?php esc_html_e( 'Cancelled', 'nextxen-pre-order-manager' ); ?></span>
				<span class="pom-stat-value"><?php echo esc_html( number_format_i18n( $stats['month_cancelled'] ) ); ?></span>
			</div>

			<!-- Upcoming releases -->
			<p class="pom-section-title"><?php esc_html_e( 'Upcoming Releases (next 30 days)', 'nextxen-pre-order-manager' ); ?></p>

			<?php if ( ! empty( $stats['upcoming'] ) ) : ?>
				<?php foreach ( $stats['upcoming'] as $item ) : ?>
					<div class="pom-upcoming-item">
						<a href="<?php echo esc_url( get_edit_post_link( $item['product_id'] ) ); ?>">
							<?php echo esc_html( $item['name'] ); ?>
						</a>
						<span class="pom-upcoming-date"><?php echo esc_html( $item['release_date'] ); ?></span>
					</div>
				<?php endforeach; ?>
			<?php else : ?>
				<p class="pom-no-data"><?php esc_html_e( 'No products releasing in the next 30 days.', 'nextxen-pre-order-manager' ); ?></p>
			<?php endif; ?>

			<!-- Quick actions -->
			<div class="pom-actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=npom_manager' ) ); ?>" class="button button-secondary button-small">
					<?php esc_html_e( 'Manage Pre-Orders', 'nextxen-pre-order-manager' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=pre_orders' ) ); ?>" class="button button-secondary button-small">
					<?php esc_html_e( 'Settings', 'nextxen-pre-order-manager' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=npom_manager&action=export_csv' ) ); ?>" class="button button-secondary button-small">
					<?php esc_html_e( 'Export CSV', 'nextxen-pre-order-manager' ); ?>
				</a>
			</div>

			<p style="font-size:11px;color:#8c8f94;margin:8px 0 0;">
				<?php
				printf(
					/* translators: %s: cache duration in minutes */
					esc_html__( 'Stats cached for %d minutes.', 'nextxen-pre-order-manager' ),
					absint( self::CACHE_TTL / MINUTE_IN_SECONDS )
				);
				?>
			</p>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Stats
	// -------------------------------------------------------------------------

	/**
	 * Return aggregated pre-order statistics, using a transient cache.
	 *
	 * @return array {
	 *   @type int    $active_count     Number of currently active pre-orders.
	 *   @type float  $active_revenue   Revenue from active pre-orders.
	 *   @type int    $month_new        New pre-orders placed this calendar month.
	 *   @type int    $month_completed  Pre-orders completed this calendar month.
	 *   @type int    $month_cancelled  Pre-orders cancelled this calendar month.
	 *   @type array  $upcoming         Products releasing within 30 days.
	 * }
	 */
	public function get_stats() {
		$cached = get_transient( self::TRANSIENT_KEY );
		if ( false !== $cached ) {
			return $cached;
		}

		$stats = array(
			'active_count'    => 0,
			'active_revenue'  => 0.0,
			'month_new'       => 0,
			'month_completed' => 0,
			'month_cancelled' => 0,
			'upcoming'        => array(),
		);

		// Active pre-orders.
		$active_orders = wc_get_orders(
			array(
				'status'     => array( 'wc-pre-ordered' ),
				'meta_query' => array(
					array(
						'key'     => '_npom_is_pre_order',
						'value'   => 1,
						'compare' => '=',
					),
				),
				'return'     => 'objects',
				'limit'      => -1,
			)
		);

		foreach ( $active_orders as $order ) {
			$stats['active_count']++;
			$stats['active_revenue'] += (float) $order->get_total();
		}

		// This month's stats — use WordPress timezone so dates reflect store config.
		try {
			$tz          = new DateTimeZone( wp_timezone_string() );
			$month_start = ( new DateTime( 'first day of this month midnight', $tz ) )->getTimestamp();
			$month_end   = ( new DateTime( 'last day of this month 23:59:59', $tz ) )->getTimestamp();
		} catch ( Exception $e ) {
			$month_start = strtotime( 'first day of this month midnight' );
			$month_end   = strtotime( 'last day of this month 23:59:59' );
		}

		$all_pre_orders = wc_get_orders(
			array(
				'meta_query'   => array(
					array(
						'key'     => '_npom_is_pre_order',
						'value'   => 1,
						'compare' => '=',
					),
				),
				'date_created' => $month_start . '...' . $month_end,
				'return'       => 'objects',
				'limit'        => -1,
			)
		);

		foreach ( $all_pre_orders as $order ) {
			$status = $order->get_meta( '_npom_status', true );
			$stats['month_new']++;
			if ( 'completed' === $status ) {
				$stats['month_completed']++;
			} elseif ( 'cancelled' === $status ) {
				$stats['month_cancelled']++;
			}
		}

		// Upcoming releases.
		$now          = time();
		$thirty_days  = $now + ( 30 * DAY_IN_SECONDS );

		$upcoming_products = get_posts(
			array(
				'post_type'   => 'product',
				'post_status' => 'publish',
				'fields'      => 'ids',
				'nopaging'    => true,
				'meta_query'  => array(
					'relation' => 'AND',
					array(
						'key'   => '_npom_enabled',
						'value' => 'yes',
					),
					array(
						'key'     => '_npom_availability_datetime',
						'value'   => array( $now, $thirty_days ),
						'compare' => 'BETWEEN',
						'type'    => 'NUMERIC',
					),
				),
				'orderby'     => 'meta_value_num',
				'meta_key'    => '_npom_availability_datetime',
				'order'       => 'ASC',
				'numberposts' => 5,
			)
		);

		foreach ( $upcoming_products as $product_id ) {
			$timestamp = (int) get_post_meta( $product_id, '_npom_availability_datetime', true );
			$stats['upcoming'][] = array(
				'product_id'   => $product_id,
				'name'         => get_the_title( $product_id ),
				'release_date' => date_i18n( get_option( 'date_format' ), $timestamp ),
			);
		}

		set_transient( self::TRANSIENT_KEY, $stats, self::CACHE_TTL );

		return $stats;
	}

	// -------------------------------------------------------------------------
	// Cache helpers
	// -------------------------------------------------------------------------

	/**
	 * Delete the cached stats transient.
	 */
	public function flush_cache() {
		delete_transient( self::TRANSIENT_KEY );
	}
}
