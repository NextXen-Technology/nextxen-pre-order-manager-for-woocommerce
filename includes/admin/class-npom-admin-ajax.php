<?php
/**
 * Class responsible for handling ajax calls on the admin for Pre-Orders
 *
 * WooCommerce Pre-Order Manager
 *
 * @package   NPOM/Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Pre-Orders Admin class.
 */
class NPOM_Admin_Ajax {

	public function __construct() {
		//Adds validation to make sure only one pre-order product is added to order
		add_action( 'woocommerce_ajax_add_order_item_validation', array( $this, 'can_add_product_to_order' ), 10, 4 );

		//Adds fees to pre-order items when creating order from admin
		add_action( 'woocommerce_ajax_order_items_added', array( $this, 'maybe_add_pre_order_fee_admin' ), 10, 2 );

		//Remove fees from order when removing a pre-order item from admin
		add_action( 'woocommerce_before_delete_order_item', array( $this, 'maybe_remove_pre_order_fee_admin' ), 10, 1 );

		//Remove fees from order when removing a pre-order item from admin
		add_action( 'woocommerce_order_before_calculate_totals', array( $this, 'maybe_adjust_pre_order_fee_admin' ), 10, 2 );

		// search products for pre-order products
		add_action( 'wp_ajax_npom_json_search_products', array( $this, 'search_pre_order_enabled_products' ) );
	}

	/**
	 * Adds validation to make sure only one pre-order product is added to an order
	 * @param WP_Error $validation_error
	 * @param WC_Product $product
	 * @param WC_Order $order
	 * @param int $qty
	 *
	 * @return WP_Error
	 */
	public function can_add_product_to_order( $validation_error, $product, $order, $qty ) {
		$items                      = $order->get_items();
		$is_added_product_pre_order = NPOM_Product::product_can_be_pre_ordered( $product );

		foreach ( $items as $item ) {
			if ( ! NPOM_Product::product_can_be_pre_ordered( $item->get_product() ) && ! $is_added_product_pre_order ) {
				continue;
			}

			if ( $item->get_product()->get_id() === $product->get_id() ) {
				$validation_error->add( 'multiple-pre-order-products', __( "You can't add multiple products on a pre-order. Change the quantity of the item instead of adding more items.", 'nextxen-pre-order-manager' ) );
				break;
			}

			if ( $item->get_product()->get_id() !== $product->get_id() ) {
				$validation_error->add( 'multiple-pre-order-products', __( "You can't add multiple products on a pre-order", 'nextxen-pre-order-manager' ) );
				break;
			}
		}

		return $validation_error;
	}

	/**
	 * Add pre-order fee when a pre-order product is added
	 *
	 * @param WC_Order_Item[] $added_items
	 * @param WC_Order $order
	 *
	 * @since 1.6.0
	 */
	public function maybe_add_pre_order_fee_admin( $added_items, $order ) {
		$wc_pre_order_cart = new NPOM_Cart();

		foreach ( $added_items as $item_id => $item ) {
			$fee = $wc_pre_order_cart->generate_fee( $item->get_product() );

			if ( ! $fee ) {
				continue;
			}

			$item_fee = new WC_Order_Item_Fee();
			$item_fee->set_name( $fee['label'] );
			$item_fee->set_tax_status( $fee['tax_status'] );
			$item_fee->set_total( $fee['amount'] * $item->get_quantity() );
			$item_fee->add_meta_data( 'pre_order_parent_item_id', $item_id, true );
			$item_fee->save();

			$order->add_item( $item_fee );
		}

		$order->save();
	}

	/**
	 * Removes pre-order fees from the order when the pre-order product is removed
	 *
	 * @param int $item_id
	 *
	 * @since 1.6.0
	 */
	public function maybe_remove_pre_order_fee_admin( $item_id ) {

		$item = WC_Order_Factory::get_order_item( absint( $item_id ) );

		if ( ! $item || 'line_item' !== $item->get_type() || ! NPOM_Product::product_can_be_pre_ordered( $item->get_product() ) ) {
			return;
		}

		$order = $item->get_order();
		$fees  = $order->get_fees();

		foreach ( $fees as $fee_id => $fee ) {
			if ( $item_id === (int) $fee->get_meta( 'pre_order_parent_item_id', true ) ) {
				$order->remove_item( $fee_id );
				$order->save();

				return;
			}
		}
	}

	/**
	 * Adjusts pre-order fees when product quantity changes
	 *
	 * @param bool $and_taxes
	 * @param WC_Order $order
	 */
	public function maybe_adjust_pre_order_fee_admin( $and_taxes, $order ) {

		$items = $order->get_items();

		foreach ( $items as $item ) {
			if ( NPOM_Product::product_can_be_pre_ordered( $item->get_product() ) ) {
				foreach ( $order->get_fees() as $item_fee ) {
					if ( $item->get_id() === (int) $item_fee->get_meta( 'pre_order_parent_item_id' ) ) {
						$wc_pre_order_cart = new NPOM_Cart();
						$fee               = $wc_pre_order_cart->generate_fee( $item->get_product() );

						$item_fee->set_total( $fee['amount'] * $item->get_quantity() );
						break;
					}
				}
			}
		}
	}

	/**
	 * Search for products and echo json.
	 *
	 * @param string $term (default: '') Term to search for.
	 */
	public function search_pre_order_enabled_products( $term = '' ) {
		check_ajax_referer( 'search-products', 'security' );

		if ( empty( $term ) && isset( $_GET['term'] ) ) {
			$term = (string) wc_clean( wp_unslash( $_GET['term'] ) );
		}

		if ( empty( $term ) ) {
			wp_die();
		}

		$ids      = $this->search_pre_order_enabled_product_ids( $term );
		$products = array();

		foreach ( $ids as $id ) {
			$product_object = wc_get_product( $id );

			if ( ! wc_products_array_filter_readable( $product_object ) ) {
				continue;
			}

			$formatted_name                        = $product_object->get_formatted_name();
			$products[ $product_object->get_id() ] = rawurldecode( wp_strip_all_tags( $formatted_name ) );
		}

		wp_send_json( $products );
	}

	/**
	 * Search pre-order enabled products for a term and return ids.
	 *
	 * Taken from WC_Product_Data_Store_CPT::search_products and modified to only search for pre-order enabled products
	 *
	 * @param  string     $term Search term.
	 * @return array of ids
	 */
	public function search_pre_order_enabled_product_ids( $term ) {
		global $wpdb;

		// See if search term contains OR keywords.
		if ( stristr( $term, ' or ' ) ) {
			$term_groups = preg_split( '/\s+or\s+/i', $term );
		} else {
			$term_groups = array( $term );
		}

		$search_where   = '';
		$search_queries = array();

		foreach ( $term_groups as $term_group ) {
			// Parse search terms.
			if ( preg_match_all( '/".*?("|$)|((?<=[\t ",+])|^)[^\t ",+]+/', $term_group, $matches ) ) {
				$search_terms = $this->get_valid_search_terms( $matches[0] );
				$count        = count( $search_terms );

				// if the search string has only short terms or stopwords, or is 10+ terms long, match it as sentence.
				if ( 9 < $count || 0 === $count ) {
					$search_terms = array( $term_group );
				}
			} else {
				$search_terms = array( $term_group );
			}

			$term_group_query = array();

			foreach ( $search_terms as $search_term ) {
				$like = '%' . $wpdb->esc_like( $search_term ) . '%';

				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- an array of placeholders is a valid arg.
				$term_query = $wpdb->prepare(
					'( posts.post_title LIKE %s ) OR ( posts.post_excerpt LIKE %s ) OR ( posts.post_content LIKE %s ) OR ( wc_product_meta_lookup.sku LIKE %s )',
					array_fill( 0, 4, $like )
				);

				$term_group_query[] = "( {$term_query} )";
			}

			if ( $term_group_query ) {
				$search_queries[] = implode( ' AND ', $term_group_query );
			}
		}

		if ( ! empty( $search_queries ) ) {
			$search_where = ' AND (' . implode( ') OR (', $search_queries ) . ') ';
		}

		// phpcs:ignore WordPress.VIP.DirectDatabaseQuery.DirectQuery
		$search_results = $wpdb->get_results(
			// phpcs:disable
			"SELECT DISTINCT posts.ID as product_id FROM {$wpdb->posts} posts
			 LEFT JOIN {$wpdb->wc_product_meta_lookup} wc_product_meta_lookup ON posts.ID = wc_product_meta_lookup.product_id
			 INNER JOIN {$wpdb->postmeta} postmeta ON ( posts.ID = postmeta.post_id )
			WHERE posts.post_type = 'product'
			AND ( postmeta.meta_key = '_npom_enabled' AND postmeta.meta_value = 'yes' )
			$search_where
			AND posts.post_status IN ('private', 'publish')
			ORDER BY posts.ID ASC
			LIMIT 30
			"
			// phpcs:enable
		);

		$product_ids = wp_parse_id_list( wp_list_pluck( $search_results, 'product_id' ) );
		_prime_post_caches( array_map( 'absint', $product_ids ) );

		if ( is_numeric( $term ) ) {
			$post_id   = absint( $term );
			$post_type = get_post_type( $post_id );

			if ( 'product' === $post_type ) {
				$product_ids[] = $post_id;
			}
		}

		return wp_parse_id_list( $product_ids );
	}

	/**
	 * Check if the terms are suitable for searching.
	 *
	 * Derived from WC_Product_Data_Store_CPT::get_valid_search_terms
	 */
	protected function get_valid_search_terms( $terms ) {
		$valid_terms = array();
		$stopwords   = $this->get_search_stopwords();

		foreach ( $terms as $term ) {
			// keep before/after spaces when term is for exact match, otherwise trim quotes and spaces.
			if ( preg_match( '/^".+"$/', $term ) ) {
				$term = trim( $term, "\"'" );
			} else {
				$term = trim( $term, "\"' " );
			}

			// Avoid single A-Z and single dashes.
			if ( empty( $term ) || ( 1 === strlen( $term ) && preg_match( '/^[a-z\-]$/i', $term ) ) ) {
				continue;
			}

			if ( in_array( wc_strtolower( $term ), $stopwords, true ) ) {
				continue;
			}

			$valid_terms[] = $term;
		}

		return $valid_terms;
	}

	/**
	 * Retrieve stopwords used when parsing search terms.
	 *
	 * Derived from WC_Product_Data_Store_CPT::get_search_stopwords
	 */
	protected function get_search_stopwords() {
		// Translators: This is a comma-separated list of very common words that should be excluded from a search, like a, an, and the. These are usually called "stopwords". You should not simply translate these individual words into your language. Instead, look for and provide commonly accepted stopwords in your language.
		$stopwords = array_map(
			'wc_strtolower',
			array_map(
				'trim',
				explode(
					',',
					_x(
						'about,an,are,as,at,be,by,com,for,from,how,in,is,it,of,on,or,that,the,this,to,was,what,when,where,who,will,with,www',
						'Comma-separated list of search stopwords in your language',
						'nextxen-pre-order-manager'
					)
				)
			)
		);

		return apply_filters( 'wp_search_stopwords', $stopwords ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment -- WP Core filter.
	}
}


new NPOM_Admin_Ajax();
