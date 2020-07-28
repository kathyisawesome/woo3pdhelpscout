<?php
/**
 * Handles responses to our Webhook.
 *
 * @package woo3pd_helpscout/Woo3pdHelpscout/api
 */
namespace Woo3pdHelpscout\Endpoints;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use HelpScout\Api\Webhooks\IncomingWebhook;
use Woo3pdHelpscout\App;
use Woo3pdHelpscout\AbstractApp;

/**
 * Webhook class.
 */
class Webhook extends AbstractApp {

	/**
	 * constructor.
	 */
	public function setup_hooks() {
		foreach ( App::instance()->get_apis() as $api ) {
			add_action( 'woo3pd_api_' . $api, array( $this, 'process_webhook' ) );
		}
		add_action( 'woo3pd_helpscout_handle_webhook', array( $this, 'handle' ) );
	}

	/**
	 * Check for Webhook Response.
	 *
	 * @param  string $api The source of this API webhook.
	 *
	 * @throws  \Exception
	 */
	public function process_webhook( $api ) {

		try {

			// Give HS itself some time to process customers first.
			sleep( 7 );

			global $wp;

			$api = $wp->query_vars[ App::ENDPOINT ];

			do_action( 'woo3pd_helpscout_handle_webhook', $api );

		} catch ( \Exception $e ) {

			App::instance()->log( $e->getMessage(), 'error' );

			// Email notification of failure.
			$to       = get_bloginfo( 'admin_email' );
			$subject  = sprintf( esc_html__( 'Webhook failure notification for %s', 'woo3pd-helpscout' ), bloginfo( 'name' ) );
			$message  = sprintf( esc_html__( 'Webhook failured with error code: %s', 'woo3pd-helpscout' ), $e->getMessage() );
			$message .= '<pre> ' . json_encode( $_POST ) . '</pre>';

			// wp_mail( $to, $subject, $message );

		} finally {

			http_response_code( 200 );

		}

	}

	/**
	 * Send to appropriate vendor for proper parsing.
	 *
	 * @param  string $api The source of this API webhook.
	 */
	public function handle( $api ) {
		$api = App::instance()->get_api_instance( $api );
		$api->handle_webhook();
	}

}
