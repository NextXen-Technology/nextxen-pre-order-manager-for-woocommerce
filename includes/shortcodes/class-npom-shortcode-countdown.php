<?php
/**
 * Countdown Pre-Orders
 *
 * @package     NPOM/Shortcodes
 * @author      WooThemes
 * @copyright   Copyright (c) 2013, WooThemes
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Countdown Shortcode
 *
 * Displays a JavaScript-enabled countdown timer
 *
 * @since 1.0
 */
class NPOM_Shortcode_Countdown {

	/**
	 * Get the shortcode content.
	 *
	 * @param array $atts associative array of shortcode parameters
	 * @return string shortcode content
	 */
	public static function get( $atts ) {
		global $woocommerce;
		return $woocommerce->shortcode_wrapper( array( __CLASS__, 'output' ), $atts, array( 'class' => 'nextxen-pre-order-manager' ) );
	}

	/**
	 * Sanitize the layout content.
	 *
	 * @param string $content Layout content
	 * @return string
	 */
	private static function sanitize_layout( $content ) {
		$content = wp_kses_no_null( $content, array( 'slash_zero' => 'keep' ) );
		$content = wp_kses_normalize_entities( $content );
		$content = preg_replace_callback( '%(<!--.*?(-->|$))|(<(?!})[^>]*(>|$))%', array( __CLASS__, 'sanitize_layout_callback' ), $content );

		return $content;
	}

	/**
	 * Callback for `sanitize_layout()`.
	 *
	 * @param array $matches preg_replace regexp matches
	 * @return string
	 */
	public static function sanitize_layout_callback( $matches ) {
		$allowed_html      = wp_kses_allowed_html( 'post' );
		$allowed_protocols = wp_allowed_protocols();

		return wp_kses_split2( $matches[0], $allowed_html, $allowed_protocols );
	}

	/**
	 * Output the countdown timer.  This defaults to the following format, where
	 * elments in [ ] are not shown if zero:
	 *
	 * [y Years] [o Months] [d Days] h Hours m Minutes s Seconds
	 *
	 * The following shortcode arguments are optional:
	 *
	 * * product_id/product_sku - id or sku of pre-order product to countdown to.
	 *     Defaults to current product, if any
	 * * until - date/time to count down to, overrides product release date
	 *     if set.  Example values: "15 March 2015", "+1 month".
	 *     More examples: http://php.net/manual/en/function.strtotime.php
	 * * before - text to show before the countdown.  Only available if 'layout' is not ''
	 * * after - text to show after the countdown.  Only available if 'layout' is not ''
	 * * layout - The countdown layout, defaults to y Years o Months d Days h Hours m Minutes s Seconds
	 *     See http://keith-wood.name/countdownRef.html#layout for all possible options
	 * * format - The format for the countdown display.  Example: 'yodhms'
	 *     to display the year, month, day and time.  See http://keith-wood.name/countdownRef.html#format for all options
	 * * compact - If 'true' displays the date/time labels in compact form, ie
	 *     'd' rather than 'days'.  Defaults to 'false'
	 *
	 * When the countdown date/time is reached the page will refresh.
	 *
	 * To test different time periods you can create shortcodes like the following samples:
	 *
	 * [npom_countdown until="+10 year"]
	 * [npom_countdown until="+10 month"]
	 * [npom_countdown until="+10 day"]
	 * [npom_countdown until="+10 second"]
	 *
	 * @param array $atts associative array of shortcode parameters
	 */
	public static function get_pre_order_countdown_shortcode_content( $atts ) {
		global $woocommerce, $product, $wpdb;

		$shortcode_atts = shortcode_atts(
			array(
				'product_id'  => '',
				'product_sku' => '',
				'until'       => '',
				'before'      => '',
				'after'       => '',
				'layout'      => '{y<}{yn} {yl}{y>} {o<}{on} {ol}{o>} {d<}{dn} {dl}{d>} {h<}{hn} {hl}{h>} {m<}{mn} {ml}{m>} {s<}{sn} {sl}{s>}',
				'format'      => 'yodHMS',
				'compact'     => 'false',
			),
			$atts
		);

		$product_id = $shortcode_atts['product_id'];

		// product by sku?
		if ( $shortcode_atts['product_sku'] ) {
			$product_id = wc_get_product_id_by_sku( $shortcode_atts['product_sku'] );
		}

		// product by id?
		if ( $product_id ) {
			$product = wc_get_product( $product_id );
		}

		// no product, or product is in the trash? Bail.
		if ( ! $product instanceof WC_Product || 'trash' === $product->get_status() ) {
			return;
		}

		// date override (convert from string unless someone was savvy enough to provide a timestamp)
		$until = $shortcode_atts['until'];
		if ( $until && ! is_numeric( $until ) ) {
			$until = strtotime( $until );
		}

		// no date override, get the datetime from the product.
		if ( ! $until ) {
			$until = get_post_meta( $product->get_id(), '_npom_availability_datetime', true );
		}

		// can't do anything without an 'until' date
		if ( ! $until ) {
			return;
		}

		// if a layout is being used, prepend/append the before/after text
		$layout = $shortcode_atts['layout'];
		if ( $layout ) {
			$layout  = esc_js( $shortcode_atts['before'] );
			$layout .= self::sanitize_layout( $shortcode_atts['layout'] );
			$layout .= sanitize_text_field( $shortcode_atts['after'] );
		}

		// enqueue the required javascripts
		self::enqueue_scripts();

		// countdown javascript
		ob_start();
		?>
		( function( $ ) {
			$( function() {
				$('#woocommerce-pre-order-manager-countdown-<?php echo esc_attr( $until ); ?>').countdown({
					until: new Date(<?php echo (int) $until * 1000; ?>),
					layout: '<?php echo esc_js( $layout ); ?>',
					format: '<?php echo esc_js( $shortcode_atts['format'] ); // nosemgrep -- already sanitized by esc_js. ?>',
					compact: <?php echo filter_var( $shortcode_atts['compact'], FILTER_VALIDATE_BOOLEAN ) ? 'true' : 'false'; // nosemgrep -- already sanitized by filter_var. ?>,
					expiryUrl: location.href,
				});
			} );
		} )( jQuery );
		<?php
		$javascript = ob_get_clean();

		$handle = 'npom-countdown-inline';
		wp_register_script( $handle, '', array( 'jquery', 'jquery-countdown' ), NPOM_VERSION, true );
		wp_enqueue_script( $handle );
		wp_add_inline_script( $handle, $javascript );

		ob_start();
		?>
		<div class="nextxen-pre-order-manager">
			<?php // the countdown element with a unique identifier to allow multiple countdowns on the same page, and common class for ease of styling ?>
			<div class="woocommerce-pre-order-manager-countdown" id="woocommerce-pre-order-manager-countdown-<?php echo esc_attr( $until ); ?>"></div>
		</div>
		<?php
		return ob_get_clean();
	}


	/**
	 * Enqueue required JavaScripts:
	 * * jquery.countdown.js - Main countdown script
	 * * jquery.countdown-{language}.js - Localized countdown script based on WPLANG, and if available
	 */
	private static function enqueue_scripts() {
		global $npom_manager;

		// enqueue the main countdown script
		wp_enqueue_script( 'jquery-countdown', $npom_manager->get_plugin_url() . '/build/jquery-countdown/jquery.countdown.js', array( 'jquery' ), '1.6.1', true );

		if ( defined( 'WPLANG' ) && WPLANG ) {
			// countdown includes some localization files, in the form: jquery.countdown-es.js and jquery.countdown-pt-BR.js
			//  convert our WPLANG constant to that format and see whether we have a localization file to include
			@list( $lang, $dialect ) = explode( '_', WPLANG );
			if ( 0 === strcasecmp( $lang, $dialect ) ) {
				$dialect = null;
			}
			$localization = $lang;
			if ( $dialect ) {
				$localization .= '-' . $dialect;
			}

			if ( ! is_readable( $npom_manager->get_plugin_path() . '/build/jquery-countdown/jquery.countdown-' . $localization . '.js' ) ) {
				$localization = $lang;
				if ( ! is_readable( $npom_manager->get_plugin_path() . '/build/jquery-countdown/jquery.countdown-' . $localization . '.js' ) ) {  // try falling back to base language if dialect is not found
					$localization = null;
				}
			}

			if ( $localization ) {
				wp_enqueue_script( 'jquery-countdown-' . $localization, $npom_manager->get_plugin_url() . '/build/jquery-countdown/jquery.countdown-' . $localization . '.js', array( 'jquery-countdown' ), '1.6.1', true );
			}
		}
	}
}
