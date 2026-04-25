<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Abstract_Privacy' ) ) {
	return;
}

class NPOM_Privacy extends WC_Abstract_Privacy {
	/**
	 * Constructor
	 *
	 */
	public function __construct() {
		parent::__construct();

		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Initial registration.
	 */
	public function register() {
		$this->name = __( 'Pre-orders', 'nextxen-pre-order-manager' );
	}

	/**
	 * Gets the message of the privacy to display.
	 *
	 */
	public function get_privacy_message() {
		/* translators: %s: URL */
		return wpautop( sprintf( __( 'By using this extension, you may be storing personal data or sharing data with an external service. <a href="%s" target="_blank">Learn more about how this works, including what you may want to include in your privacy policy.</a>', 'nextxen-pre-order-manager' ), 'https://nextxentech.com/docs/nextxen-pre-order-manager-for-woocommerce/privacy/' ) );
	}
}

new NPOM_Privacy();
