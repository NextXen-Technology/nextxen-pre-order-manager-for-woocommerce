<?php
/**
 * WooCommerce Pre-Order Manager
 *
 * @package     NPOM/Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Pre-Orders Cron class
 *
 * Adds custom wp-cron schedule and handles pre-order completion checks
 *
 * @since 1.0
 */
class NPOM_Cron {


	/**
	 * Adds hooks and filters
	 *
	 * @since 1.0
	 * @return \NPOM_Cron
	 */
	public function __construct() {

		// Add custom schedule for pre-order completion check.
		add_filter( 'cron_schedules', array( $this, 'add_custom_schedules' ) );

		// Schedule a complete pre-order check event if it doesn't exist - activation hooks are unreliable, so attempt to schedule events on every page load
		add_action( 'init', array( $this, 'add_scheduled_events' ) );
	}


	/**
	 * Adds custom wp-cron schedule named 'npom_completion_check' with custom interval
	 *
	 * @since 1.0
	 * @since 1.5.6 Default frequency reduced from every five minutes to once per hour.
	 * @param array $schedules existing WP recurring schedules
	 * @return array
	 */
	public function add_custom_schedules( $schedules ) {

		/**
		 * Filter the interval for the pre-order completion check.
		 *
		 * Allows developers to change the interval for the pre-order completion
		 * check cron job.
		 *
		 * @since 1.0
		 * @since 1.5.6 Default frequency reduced from every five minutes to once per hour.
		 * @param int     $interval  The interval in seconds.
		 * @param array[] $schedules The existing WP recurring schedules.
		 */
		$interval = apply_filters( 'npom_completion_check_interval', HOUR_IN_SECONDS, $schedules );

		$schedules['npom_completion_check'] = array(
			'interval' => $interval,
			/* translators: %d: Cron job interval in minutes */
			'display'  => sprintf( __( 'Every %d minutes', 'nextxen-pre-order-manager' ), $interval / MINUTE_IN_SECONDS ),
		);

		return $schedules;
	}


	/**
	 * Add scheduled events to wp-cron if not already added
	 *
	 * @since 1.0
	 * @return array
	 */
	public function add_scheduled_events() {

		// Schedule pre-order completion check with custom interval named 'npom_completion_check'
		// note the next execution time if the plugin is deactivated then reactivated is the current time + 5 minutes
		if ( ! wp_next_scheduled( 'npom_completion_check' ) ) {
			wp_schedule_event( time() + 300, 'npom_completion_check', 'npom_completion_check' );
		}
	}
} // end \NPOM_Cron class
