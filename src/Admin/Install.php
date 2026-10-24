<?php
/**
 * Install/Uninstall
 *
 * @since 1.0.0
 * @package Woo3pdHelpscout/Admin
 */
namespace Woo3pdHelpscout\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Install {

	/**
	 * Flush rewrite routes.
	 */
	public static function install() {
		\Woo3pdHelpscout\Endpoints\Controller::instance()->add_endpoint();
		flush_rewrite_rules();
	}


	/**
	 * Delete options table entries
	 */
	public static function uninstall() {
		$settings = get_option( 'woo3pd_helpscout' );
		if ( isset( $settings['delete'] ) && 'yes' === $settings['delete'] ) {
			delete_option( 'woo3pd_helpscout' );
		}
		flush_rewrite_rules();
		wp_clear_scheduled_hook( 'woo3pdhelpscout_gmail_api_watch' );
	}
} // End class.
