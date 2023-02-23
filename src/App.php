<?php
/**
 * Application.
 *
 * @since 1.0.0
 * @package Woo3pdHelpscout
 */
namespace Woo3pdHelpscout;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Woo3pdHelpscout\AbstractApp;
use Woo3pdHelpscout\Api;

class App extends AbstractApp {

	/**
	* @constant string donate url
	*/
	const DONATE_URL = 'https://www.paypal.com/paypalme/kathyisawesome';

	/**
	* @constant string the endpoint
	*/
	const ENDPOINT = 'woo3pd-api';

	/**
	* @constant string the options page.
	*/
	const OPTION = 'woo3pd_helpscout';

	/**
	 * Plugin settings.
	 *
	 * @var array the DB settings for plugin.
	 */
	protected $settings = array();

	/**
	 * Plugin default settings.
	 *
	 * @var array the DB settings for plugin.
	 */
	protected $default_settings = array(
		'api'    => 'helpscout',
		'debug'  => 'no',
		'delete' => 'no',
	);

	/**
	 * Shared API default settings.
	 *
	 * @var array the DB settings for plugin.
	 */
	protected $api_settings = array(
		'client_id'     => '',
		'client_secret' => '',
		'token'         => '',
		'refresh'       => '',
		'mailbox_id'    => '', // Currently only used by SendGrid, but in theory would be used by any vendor.
	);

	/**
	 * The supported APIs.
	 *
	 * @var array
	 */
	protected $apis = array(
		'helpscout',
		'sendgrid',
	);

	/**
	 * The current chosen api.
	 *
	 * @var string
	 */
	protected $api;

	/**
	 * Woo3pdHelpscout constructor.
	 */
	public function __construct() {
		$this->default_settings = array_merge( $this->default_settings, $this->api_settings, array_fill_keys( $this->apis, array() ) );
		$this->api              = $this->get_setting( 'api' );
	}

	/**
	 * Setup actions and filters.
	 *
	 * @return void
	 */
	public function setup_hooks() {
		/**
		 * Required by {@see \AbstractApp::load_translations()}.
		 */
		$this->textdomain = 'woo3pdhelpscout';
		$this->load_translations();

		// Set-up install action.
		\register_activation_hook( __FILE__, array( 'Admin\Install', 'install' ) );

		// Set-up uninstall action.
		\register_uninstall_hook( __FILE__, array( 'Admin\Install', 'uninstall' ) );

		// Launch the endpoint and webhook.
		Endpoints\Controller::instance()->setup_hooks();
		Endpoints\Webhook::instance()->setup_hooks();

		if ( is_admin() ) {
			Admin\Controller::instance()->setup_hooks();
		}

	}

	/**
	 * The supported APIs
	 */
	public function get_apis() {
		return $this->apis;
	}

	/**
	 * Who are we expecting to receive Webhooks from?
	 */
	public function get_api() {
		return $this->api;
	}

	/**
	 * Get the Api class instance.
	 *
	 * @param  string the API name you want the object for.
	 * @return  obj
	 */
	public function get_api_instance( $api = '' ) {
		$api        = in_array( $api, $this->get_apis() ) ? $api : $this->get_api();
		$class_name = __NAMESPACE__ . '\\Api\\' . ucfirst( $api );
		return $class_name::instance();
	}

} // End class.
