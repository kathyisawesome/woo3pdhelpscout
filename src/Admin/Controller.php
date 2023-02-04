<?php
/**
 * Handles settings for plugins.
 *
 * @package woo3pd_helpscout/Woo3pdHelpscout/admin
 */
namespace Woo3pdHelpscout\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Woo3pdHelpscout\App;
use Woo3pdHelpscout\AbstractApp;

/**
 * Controller class.
 */
class Controller extends AbstractApp {

	public function setup_hooks() {

		// Handle auth actions.
		add_action( 'wp_loaded', array( $this, 'auth_handler' ) );

		// Register settings.
		add_action( 'admin_init', array( $this, 'admin_init' ) );

		// Add plugin options page.
		add_action( 'admin_menu', array( $this, 'add_options_page' ) );

		// Add settings link to plugins page.
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'add_action_links' ), 10, 2 );

		// Add Donate link to plugin.
		add_filter( 'plugin_row_meta', array( $this, 'add_meta_links' ), 10, 2 );

	}

	/*
	-----------------------------------------------------------------------------------*/
	/*
	 Handle fetching and revoking tokens */
	/*-----------------------------------------------------------------------------------*/

	/**
	 * Handle fetching and revoking tokens for APIs.
	 */
	public function auth_handler() {
		$api = App::instance()->get_api_instance();

		// The token auth flow sends back the nonce as the state parameter.
		if ( isset( $_GET['code'] ) && isset( $_GET['state'] ) && wp_verify_nonce( wp_unslash( $_GET['state'] ), 'woo3pd_helpscout_nonce' ) ) {
			App:instance()->get_api_instance()->swap_for_tokens( wp_unslash( $_GET['code'] ) );
			wp_safe_redirect( remove_query_arg( array( 'state', 'code', 'scope' ) ) );
		}

		// Revoke a token.
		if ( isset( $_GET['revoke'] ) && isset( $_GET['woo3pd_helpscout_nonce'] ) && wp_verify_nonce( wp_unslash( $_GET['woo3pd_helpscout_nonce'] ), 'woo3pd_helpscout_nonce' ) ) {
			App::instance()->get_api_instance()->revoke_tokens();
			wp_safe_redirect( remove_query_arg( array( 'woo3pd_helpscout_nonce', 'code', 'revoke' ) ) );
		}
	}

	/*
	-----------------------------------------------------------------------------------*/
	/*
	 Admin Settings */
	/*-----------------------------------------------------------------------------------*/

	/**
	 * Register admin settings
	 */
	public function admin_init() {
		register_setting( App::OPTION, App::OPTION, array( $this, 'validate_options' ) );
	}


	/**
	 * Add options page
	 */
	public function add_options_page() {
		add_options_page( __( 'Woo3pd HelpScout Options Page', 'woo3pdhelpscout' ), __( 'Woo3pd HelpScout', 'woo3pdhelpscout' ), 'manage_options', App::OPTION, array( $this, 'render_form' ) );
	}

	/**
	 * Display a Settings link on the main Plugins page
	 *
	 * @param  array $links
	 * @param  string $file
	 * @return array
	 * @since  1.6.4
	 */
	public function add_action_links( $links, $file ) {

		$plugin_link = '<a href="' . add_query_arg( 'page', App::OPTION, admin_url( 'options-general.php' ) ) . '">' . __( 'Settings', 'woo3pdhelpscout' ) . '</a>';

		// Make the 'Settings' link appear first
		array_unshift( $links, $plugin_link );

		return $links;

	}

	/**
	 * Add donation link
	 *
	 * @param array $plugin_meta
	 * @param string $plugin_file
	 */
	public function add_meta_links( $plugin_meta, $plugin_file ) {
		if ( $plugin_file === plugin_basename( __FILE__ ) ) {
			$plugin_meta[] = '<a class="dashicons-before dashicons-awards" href="' . self::DONATE_URL . '" target="_blank">' . __( 'Donate', 'woo3pdhelpscout' ) . '</a>';
		}
		return $plugin_meta;
	}

	/**
	 * Render the Plugin options form
	 */
	public function render_form() {
		require_once __DIR__ . '/views/plugin-options.php';
	}

	/**
	 * Sanitize and validate input.
	 * Accepts an array, return a sanitized array.
	 *
	 * @param array $input all posted data
	 * @return array $clean data that is allowed to be save
	 */
	public function validate_options( $input ) {

		$clean = array();

		$apis = App::instance()->get_apis();

		foreach ( App::instance()->get_default_settings() as $key => $setting ) {

			switch ( $key ) {
				case 'api':
					$value = isset( $input['api'] ) && in_array( $input['api'], $apis ) ? $input['api'] : '';
					break;
				case 'debug':
					$value = isset( $input['debug'] ) && $input['debug'] ? 'yes' : 'no';
					break;
				case 'delete':
					$value = isset( $input['delete'] ) && $input['delete'] ? 'yes' : 'no';
					break;
				default:
					if ( isset( $input[ $key ] ) && in_array( $key, $apis ) ) {

						$api = App::instance()->get_api_instance( $key );

						$value = array();

						foreach ( $api->get_default_settings() as $k => $setting ) {
							$value[ $k ] = isset( $input[ $key ][ $k ] ) ? sanitize_text_field( $input[ $key ][ $k ] ) : '';
						}
					} else {
						$value = isset( $input[ $key ] ) ? sanitize_text_field( $input[ $key ] ) : '';
					}
			}

			$clean[ $key ] = $value;

		}

		return $clean;

	}

}
