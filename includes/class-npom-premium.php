<?php
/**
 * WooCommerce Pre-Order Manager – Premium Gate Helper
 *
 * Single place to check whether the current install has an active
 * Freemius premium license.  Every premium feature in the plugin
 * should call NPOM_Premium::is_active() instead of
 * calling npom_fs()->can_use_premium_code() directly, so that:
 *
 *  - The check is mockable in tests.
 *  - The fallback (when Freemius SDK is absent) is handled centrally.
 *  - We can add trial / grace-period logic here later without touching
 *    every feature file.
 *
 * @package NPOM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NPOM_Premium
 */
class NPOM_Premium {

	/**
	 * Returns true when the site has a valid paid (or trial) Freemius license.
	 *
	 * @return bool
	 */
	public static function is_active() {
		if ( ! function_exists( 'npom_fs' ) ) {
			return false;
		}
		return npom_fs()->can_use_premium_code();
	}

	/**
	 * Output an upgrade notice inside the admin (for use in settings fields,
	 * product tabs, etc.).
	 *
	 * @param string $feature  Human-readable feature name shown in the notice.
	 * @return void
	 */
	public static function upgrade_notice( $feature = '' ) {
		if ( ! is_admin() || ! function_exists( 'npom_fs' ) ) {
			return;
		}

		$upgrade_url = npom_fs()->get_upgrade_url();
		$label       = $feature
			? sprintf(
				/* translators: %s: feature name */
				__( '%s is a premium feature.', 'nextxen-pre-order-manager' ),
				'<strong>' . esc_html( $feature ) . '</strong>'
			)
			: __( 'This is a premium feature.', 'nextxen-pre-order-manager' );

		printf(
			'<p class="wc-pom-upgrade-notice" style="background:#fff8e5;border-left:4px solid #dba617;padding:8px 12px;margin:8px 0;font-size:13px;">
				%s
				<a href="%s" class="button button-primary button-small" style="margin-left:10px;" target="_blank">%s</a>
			</p>',
			wp_kses( $label, array( 'strong' => array() ) ),
			esc_url( $upgrade_url ),
			esc_html__( 'Upgrade Now', 'nextxen-pre-order-manager' )
		);
	}

	/**
	 * Output an inline "lock" badge (small, for table rows / list items).
	 *
	 * @return void
	 */
	public static function lock_badge() {
		if ( ! is_admin() || ! function_exists( 'npom_fs' ) ) {
			return;
		}
		printf(
			'<span class="wc-pom-lock-badge" title="%s" style="display:inline-block;background:#7f54b3;color:#fff;font-size:10px;font-weight:700;padding:1px 7px;border-radius:10px;vertical-align:middle;margin-left:6px;">%s</span>',
			esc_attr__( 'Premium feature — upgrade to unlock', 'nextxen-pre-order-manager' ),
			esc_html__( 'PRO', 'nextxen-pre-order-manager' )
		);
	}
}
