<?php
/**
 * Handles responses to our Webhook.
 *
 * @package woo3pd_helpscout/Woo3pdHelpscout/endpoints
 */
namespace Woo3pdHelpscout\Endpoints;

use Woo3pdHelpscout\App;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Woo3pdHelpscout\AbstractApp;

/**
 * Endpoints main class.
 */
class Controller extends AbstractApp {

	/**
	 * Attach hooks and filters.
	 */
	public function setup_hooks() {

		// API Route.
		add_action( 'init', array( $this, 'add_endpoint' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'parse_request', array( $this, 'handle_api_requests' ) );

	}

	/*
	-----------------------------------------------------------------------------------*/
	/*
	 API */
	/*-----------------------------------------------------------------------------------*/

	/**
	 * API for HelpScout webhooks.
	 */
	public function add_endpoint() {
		add_rewrite_endpoint( App::ENDPOINT, EP_ALL );
	}

	/**
	 * Add new query vars.
	 *
	 * @param array $vars Query vars.
	 * @return string[]
	 */
	public function add_query_vars( $vars ) {
		$vars[] = App::ENDPOINT;
		return $vars;
	}

	/**
	 * API request - Trigger any API requests.
	 */
	public function handle_api_requests() {
		global $wp;

		if ( ! empty( $_GET[ App::ENDPOINT ] ) ) {
			$wp->query_vars[ App::ENDPOINT ] = sanitize_key( wp_unslash( $_GET[ App::ENDPOINT ] ) );
		}

		// woo3pd-api endpoint requests.
		if ( ! empty( $wp->query_vars[ App::ENDPOINT ] ) ) {

			// Buffer, we won't want any output here.
			ob_start();

			// No cache headers.
			wc_nocache_headers();

			// Clean the API request.
			$api_request = strtolower( sanitize_text_field( $wp->query_vars[ App::ENDPOINT ] ) );

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

} // End class.
