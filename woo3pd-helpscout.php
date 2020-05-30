<?php
/*
Plugin Name: Woo3pd Helpscout Webhook
Plugin URI: http://www.kathyisawesome.com/4
Description: Parse WooCommerce.com support emails into HelpScout conversations
Version: 0.0.1
Author: Kathy Darling
Author URI: http://www.kathyisawesome.com
License: GPL3
Text Domain: woo3pd-api

Copyright 2020  Kathy Darling  (email: kathy@kathyisawesome.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


// Don't load directly.
if ( ! function_exists( 'is_admin' ) ) {
    header( 'Status: 403 Forbidden' );
    header( 'HTTP/1.1 403 Forbidden' );
    exit();
}


class Woo3pd_Helpscout {

	/**
	* @constant string donate url
	*/
	CONST DONATE_URL = 'https://www.paypal.com/fundraiser/charity/1451316';

	/**
	* @constant string minimum version of PHP
	* @since 1.5
	*/
	CONST MIN_PHP = '7.1';

	/**
	 * Plugin settings.
	 *
	 * @var array the DB settings for plugin.
	 */
	protected static $settings = array();

	/**
	 * Plugin default settings.
	 *
	 * @var array the DB settings for plugin.
	 */
	protected static $defaultSettings = array( 
				'appId'         => '',
				'appSecret'     => '',
				'appSecretKey'  => '',
				'accessToken'   => '',
				'refreshToken'  => '',
			);
	/**
	 * The Helpscout Webhook app ID.
	 *
	 * @var string app ID.
	 */
	protected static $appId;

	/**
	 * The Helpscout Webhook app secret.
	 *
	 * @var string app secret.
	 */
	protected static $appSecret;

	/**
	 * The Helpscout Webhook app secret key.
	 *
	 * @var string app secret key.
	 */
	protected static $appSecretKey;

	/**
	 * Woo3pd_Helpscout pseudo constructor.
	 */
	public static function init() {

		// PHP version check.
		if ( ! function_exists( 'phpversion' ) || version_compare( phpversion(), self::MIN_PHP, '<' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'php_failure_notice' ) );
			return false;
		}

		require_once 'vendor/autoload.php';
		require_once 'includes/class-woo3pd-helpscout-webhook-handler.php';

		// Load the textdomain.
		add_action( 'init', array( __CLASS__, 'load_textdomain' ) );

		// Register settings.
		add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );

		// Add plugin options page.
		add_action( 'admin_menu', array( __CLASS__, 'add_options_page' ) );

		// Add settings link to plugins page.
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( __CLASS__, 'add_action_links' ), 10, 2 );

		// Add Donate link to plugin.
		add_filter( 'plugin_row_meta', array( __CLASS__, 'add_meta_links' ), 10, 2 );

		// API Route.
		add_action( 'init', array( __CLASS__, 'add_endpoint' ), 0 );
		add_filter( 'query_vars', array( __CLASS__, 'add_query_vars' ), 0 );
		add_action( 'parse_request', array( __CLASS__, 'handle_api_requests' ), 0 );

	}


	/**
	 * PHP version failure notice
	 */
	public static function php_failure_notice() {

		$notice = sprintf(
			// Translators: %1$s link to documentation. %2$s minimum required PHP version number.
			__( 'Woo3pd Helpscout requires at least PHP <strong>%1$s</strong>. Learn <a href="%2$s">how to update PHP</a>.', 'woo3pd-helpscout' ),
			self::MIN_PHP,
			'https://docs.woocommerce.com/document/how-to-update-your-php-version/'
		);
    	?>
	    <div class="notice notice-warning">
	        <p><?php echo wp_kses_post( $notice ); ?></p>
	    </div>
    <?php
		}

	/*-----------------------------------------------------------------------------------*/
	/* Localization Functions */
	/*-----------------------------------------------------------------------------------*/

	/**
	 * Make Plugin Translation-ready
	 */
	public static function load_textdomain() {
		load_plugin_textdomain( 'woo3pd-helpscout', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}


	/*-----------------------------------------------------------------------------------*/
	/* Install/Uninstall */
	/*-----------------------------------------------------------------------------------*/

	/**
	 * Flush rewrite routes.
	 */
	public static function install() {
		self::add_endpoint();
		flush_rewrite_rules();	
	}


	/**
	 * Delete options table entries
	 */
	public static function uninstall() {
		$settings  = get_option( 'woo3pd_helpscout' ); 
		if( isset( $settings['delete'] ) && 'yes' === isset( $settings['delete'] ) ) {
			delete_option( 'woo3pd_helpscout' );
		}
		flush_rewrite_rules();
	}


	/*-----------------------------------------------------------------------------------*/
	/* Admin Settings */
	/*-----------------------------------------------------------------------------------*/

	/**
	 * Register admin settings
	 */
	public static function admin_init() {
		register_setting( 'woo3pd_helpscout', 'woo3pd_helpscout', array( __CLASS__, 'validate_options' ) );
	  }


	/**
	 * Add options page
	 */
	public static function add_options_page() {
		add_options_page(__( 'Woo3pd HelpScout Options Page', 'woo3pd-helpscout' ), __( 'Woo3pd HelpScout', 'woo3pd-helpscout' ), 'manage_options', 'woo3pd_helpscout', array( __CLASS__, 'render_form' ) );
	}

	/**
	 * Display a Settings link on the main Plugins page
	 * @param  array $links
	 * @param  string $file
	 * @return array
	 * @since  1.6.4
	 */
	public static function add_action_links( $links, $file ) {

		$plugin_link = '<a href="'. add_query_arg( 'page', 'woo3pd_helpscout', admin_url( 'options-general.php' ) ) . '">' . __( 'Settings', 'woo3pd-helpscout' ) . '</a>';
	  	
	  	// Make the 'Settings' link appear first
	  	array_unshift( $links, $plugin_link );

		return $links;
		
	  }

	/**
	 * Add donation link
	 * @param array $plugin_meta
	 * @param string $plugin_file
	 */
	public static function add_meta_links( $plugin_meta, $plugin_file ) {
		if( $plugin_file === plugin_basename(__FILE__) ) {
			$plugin_meta[] = '<a class="dashicons-before dashicons-awards" href="' . self::DONATE_URL . '" target="_blank">' . __( 'Donate', 'woo3pd-helpscout' ) . '</a>';
		}
		return $plugin_meta;
	}

	/**
	 * Render the Plugin options form
	 */
	public static function render_form() {
		include( 'includes/views/plugin-options.php' );
	}

	/**
	 * Sanitize and validate input.
	 * Accepts an array, return a sanitized array.
	 *
	 * @param array $input all posted data
	 * @return array $clean data that is allowed to be save
	 */
	public static function validate_options( $input ) {

		$clean = array();

		$clean['appId']     =  isset( $input['appId'] ) ? sanitize_text_field( $input['appId'] ) : '';
		$clean['appSecret'] =  isset( $input['appSecret'] ) ? sanitize_text_field( $input['appSecret'] ) : '';
		$clean['appSecretKey'] =  isset( $input['appSecretKey'] ) ? sanitize_text_field( $input['appSecretKey'] ) : '';
		$clean['delete']    =  isset( $input['delete'] ) && $input['delete'] ? 'yes' : 'no' ;

		return $clean;
	
	}

	/*-----------------------------------------------------------------------------------*/
	/* API */
	/*-----------------------------------------------------------------------------------*/


	/**
	 * Add new query vars.
	 *
	 * @param array $vars Query vars.
	 * @return string[]
	 */
	public static function add_query_vars( $vars ) {
		$vars[] = 'woo3pd-api';
		return $vars;
	}

	/**
	 * API for HelpScout webhooks.
	 */
	public static function add_endpoint() {
		add_rewrite_endpoint( 'woo3pd-api', EP_ALL );
	}

	/**
	 * API request - Trigger any API requests.
	 *
	 * @since   2.0
	 * @version 2.4
	 */
	public static function handle_api_requests() {
		global $wp;

		if ( ! empty( $_GET['woo3pd-api'] ) ) { // WPCS: input var okay, CSRF ok.
			$wp->query_vars['woo3pd-api'] = sanitize_key( wp_unslash( $_GET['woo3pd-api'] ) ); // WPCS: input var okay, CSRF ok.
		}

		// woo3pd-api endpoint requests.
		if ( ! empty( $wp->query_vars['woo3pd-api'] ) ) {

			// Buffer, we won't want any output here.
			ob_start();

			// No cache headers.
			wc_nocache_headers();

			// Clean the API request.
			$api_request = strtolower( sanitize_text_field( $wp->query_vars['woo3pd-api'] ) );

			// Trigger generic action before request hook.
			do_action( 'woo3pd_api_request', $api_request );

			// Is there actually something hooked into this API request? If not trigger 400 - Bad request.
			status_header( has_action( 'woo3pd_api_' . $api_request ) ? 200 : 400 );

			// Trigger an action which plugins can hook into to fulfill the request.
			do_action( 'woo3pd_api_' . $api_request );

			// Done, clear buffer and exit.
			ob_end_clean();
			die( '-1' );
		}
	}


	/*-----------------------------------------------------------------------------------*/
	/* Helper Functions */
	/*-----------------------------------------------------------------------------------*/

	/**
	 * Get a setting from the DB.
	 *
	 * @return  string
	 */
	public static function get_setting( $setting = '' ) {

		if( empty( self::$settings ) ) {
			$settings = get_option( 'woo3pd_helpscout' );
			self::$settings = wp_parse_args( $settings, self::$defaultSettings );
		}

		return isset( self::$settings[$setting] ) ? self::$settings[$setting] : false;

	}



	/**
	 * Plugin Path.
	 * 
	 * @since 3.1
	 *
	 * @return  string
	 */
	public static function get_plugin_path() {
		return untrailingslashit( plugin_dir_path( __FILE__ ) );
	}

} // End class.

add_action( 'plugins_loaded', array( 'Woo3pd_Helpscout', 'init' ) );

// Set-up install action.
register_activation_hook( __FILE__, array( 'Woo3pd_Helpscout', 'install' ) );

// Set-up uninstall action.
register_uninstall_hook( __FILE__, array( 'Woo3pd_Helpscout', 'uninstall' ) );
