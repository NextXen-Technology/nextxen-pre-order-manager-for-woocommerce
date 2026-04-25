<?php
/**
 * WooCommerce Pre-Order Manager
 *
 * @package   NPOM/My_Pre_Orders
 * @author    NextXen Technology
 * @copyright Copyright (c) 2015, WooThemes
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * My Pre-Orders class
 *
 * @since 1.4.4
 */
class NPOM_My_Pre_Orders {

	/**
	 * Adds needed hooks / filters
	 */
	public function __construct() {
		// New endpoint for pre-orders.
		add_action( 'init', array( $this, 'add_endpoint' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );
		add_filter( 'woocommerce_get_query_vars', array( $this, 'add_pre_orders_woocommerce_query_vars' ) );

		// Change page title.
		add_filter( 'the_title', array( $this, 'endpoint_title' ) );
		add_filter( 'woocommerce_endpoint_pre-orders_title', array( $this, 'change_endpoint_title' ) );

		// Insert Pre-Orders menu in My Account menus.
		add_filter( 'woocommerce_account_menu_items', array( $this, 'menu_items' ) );
		add_action( 'woocommerce_account_pre-orders_endpoint', array( $this, 'my_pre_orders' ) );

		add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'add_pre_order_actions' ), 10, 2 );
	}

	/**
	 * Add cancel action to pre-orders within my account.
	 *
	 * Runs on the `woocommerce_my_account_my_orders_actions` filter.
	 *
	 * Modifies the order actions as displayed on the order page within my account to display
	 * a cancel button for pre-orders that can be cancelled.
	 *
	 * @since 2.2.3
	 *
	 * @param array     $actions The order actions.
	 * @param \WC_Order $order   The order object.
	 * @return array The modified order actions.
	 */
	public function add_pre_order_actions( $actions, $order ) {
		// Only apply to the view order page.
		if ( ! is_view_order_page() ) {
			return $actions;
		}

		// If the order can already be cancelled, don't add the action.
		if ( isset( $actions['cancel'] ) ) {
			return $actions;
		}

		// If this does not contain pre-order items, don't add the action.
		if ( ! NPOM_Order::order_contains_pre_order( $order ) ) {
			return $actions;
		}

		if ( NPOM_Manager::can_pre_order_be_changed_to( 'cancelled', $order ) ) {
			$actions['cancel'] = array(
				'url'        => NPOM_Manager::get_users_change_status_link( 'cancelled', $order ),
				'name'       => __( 'Cancel', 'nextxen-pre-order-manager' ),
				/* translators: %s: order number */
				'aria-label' => sprintf( __( 'Cancel order %s', 'nextxen-pre-order-manager' ), $order->get_order_number() ),
			);
		}

		return $actions;
	}

	/**
	 * Register new endpoint to use inside My Account page.
	 *
	 * @since 1.4.7
	 *
	 * @see https://developer.wordpress.org/reference/functions/add_rewrite_endpoint/
	 */
	public function add_endpoint() {
		add_rewrite_endpoint( 'pre-orders', EP_ROOT | EP_PAGES );
	}

	/**
	 * Add pre-orders query var.
	 *
	 * @since 1.4.7
	 *
	 * @param array $vars Query vars
	 *
	 * @return array altered query vars
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'pre-orders';
		return $vars;
	}

	/**
	 * Add pre-orders query var.
	 *
	 * @since 2.1.4
	 *
	 * @param array $vars Query vars.
	 *
	 * @return array altered query vars
	 */
	public function add_pre_orders_woocommerce_query_vars( $vars ) {
		$vars['pre-orders'] = 'pre-orders';

		return $vars;
	}

	/**
	 * Change title for pre-orders endpoint.
	 *
	 * @since 1.4.7
	 *
	 * @param string $title Page title
	 *
	 * @return string Page title
	 */
	public function endpoint_title( $title ) {
		if ( $this->is_pre_orders_endpoint() ) {
			$title = __( 'Pre-orders', 'nextxen-pre-order-manager' );
			remove_filter( 'the_title', array( $this, 'endpoint_title' ) );
		}

		return $title;
	}

	/**
	 * Update the title for the pre-orders endpoint.
	 *
	 * @since 2.1.4
	 *
	 * @param string $title Title.
	 *
	 * @return string Altered title
	 */
	public function change_endpoint_title( $title ) {
		$title = __( 'Pre-orders', 'nextxen-pre-order-manager' );

		return $title;
	}

	/**
	 * Checks if current page is pre-orders endpoint.
	 *
	 * @since 1.4.7
	 *
	 * @return bool Returns true if current page is pre-orders endpoint
	 */
	public function is_pre_orders_endpoint() {
		global $wp_query;

		return ( isset( $wp_query->query_vars['pre-orders'] )
			&& ! is_admin()
			&& is_main_query()
			&& in_the_loop()
			&& is_account_page()
		);
	}

	/**
	 * Insert Pre-Ordres menu into My Account menus.
	 *
	 * @since 1.4.7
	 *
	 * @param array $items Menu items
	 *
	 * @return array Menu items
	 */
	public function menu_items( $items ) {
		// Insert Pre-Orders menu.
		$new_items               = array();
		$new_items['pre-orders'] = __( 'Pre-orders', 'nextxen-pre-order-manager' );

		return $this->_insert_new_items_after( $items, $new_items, 'dashboard' );
	}

	/**
	 * Helper to add new items into an array after a selected item.
	 *
	 * @since 1.4.7
	 *
	 * @param array  $items     Menu items
	 * @param array  $new_items New menu items
	 * @param string $after     Key in items
	 *
	 * @return array Menu items
	 */
	protected function _insert_new_items_after( $items, $new_items, $after ) {
		// Search for the item position and +1 since is after the selected item key.
		$position = array_search( $after, array_keys( $items ) ) + 1;

		// Insert the new item.
		$array  = array_slice( $items, 0, $position, true );
		$array += $new_items;
		$array += array_slice( $items, $position, count( $items ) - $position, true );

		return $array;
	}

	/**
	 * Output "My Pre-Orders" table in the user's My Account page
	 */
	public function my_pre_orders() {
		global $npom_manager;

		$page_number = get_query_var( 'pre-orders', 1 );
		if ( ! is_numeric( $page_number ) || $page_number < 1 ) {
			$page_number = 1;
		}

		$query      = null; // Passed by reference to get_users_pre_orders() to get the raw query object.
		$pre_orders = NPOM_Manager::get_users_pre_orders( null, $page_number, $query );
		$items      = array();
		$actions    = array();

		foreach ( $pre_orders as $order ) {
			$_actions   = array();
			$order_item = NPOM_Order::get_pre_order_item( $order );

			// Stop if the pre-order is complete
			if ( is_null( $order_item ) ) {
				continue;
			}

			// Set the items for the table
			$items[] = array(
				'order' => $order,
				'data'  => $order_item,
			);

			// Determine the available actions (Cancel)
			if ( NPOM_Manager::can_pre_order_be_changed_to( 'cancelled', $order ) ) {
				$_actions['cancel'] = array(
					'url'        => NPOM_Manager::get_users_change_status_link( 'cancelled', $order ),
					'name'       => __( 'Cancel', 'nextxen-pre-order-manager' ),
					/* translators: %s: order number */
					'aria-label' => sprintf( __( 'Cancel order %s', 'nextxen-pre-order-manager' ), $order->get_order_number() ),
				);
			}

			$actions[ $order->get_id() ] = $_actions;
		}

		// Load the template
		wc_get_template(
			'myaccount/my-pre-orders.php',
			array(
				'pre_orders'      => $pre_orders,
				'items'           => $items,
				'actions'         => $actions,
				'current_page'    => (int) $page_number,
				'total_pages'     => (int) $query->max_num_pages,
				'wp_button_class' => wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '',
			),
			'',
			$npom_manager->get_plugin_path() . '/templates/'
		);
	}
}

new NPOM_My_Pre_Orders();
