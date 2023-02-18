<?php
/**
 * Handles responses to our Webhook.
 * 
 * @since 1.0.0
 * @version 1.0.0
 *
 * @package woo3pdHelpscout/Woo3pdHelpscout/api
 */
namespace Woo3pdHelpscout\Endpoints;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use HelpScout\Api\Webhooks\IncomingWebhook;
use Woo3pdHelpscout\App;
use Woo3pdHelpscout\AbstractApp;
use Woo3pdHelpscout\Exceptions\QuietException;

/**
 * Webhook class.
 */
class Webhook extends AbstractApp {

	/**
	 * constructor.
	 */
	public function setup_hooks() {
		$provider = App::instance()->get_api();
		add_action( 'woo3pd_api_' . $provider, array( $this, 'process_webhook' ) );	
	}

	/**
	 * Send to appropriate vendor for proper parsing.
	 *
	 * @throws  \Exception
	 */
	public function process_webhook() {

		try {

			// Give HS itself some time to process customers first.
			sleep( 10 );

			$provider = App::instance()->get_api();

			$api = App::instance()->get_api_instance( $provider );
			$api->handle_webhook();

		} catch ( QuietException $e ) {

			// Nothing to log.// @todo- remove.
			App::instance()->log( $e->getMessage(), 'info' );

		} catch ( \Exception $e ) {

			App::instance()->log( $e->getMessage(), 'error' );

			// Email notification of failure.
			$to       = get_bloginfo( 'admin_email' );
			$subject  = sprintf( esc_html__( 'Webhook failure notification for %s', 'woo3pdhelpscout' ), bloginfo( 'name' ) );
			$message  = sprintf( esc_html__( 'Webhook failured with error code: %s', 'woo3pdhelpscout' ), $e->getMessage() );
			$message .= '<pre> ' . json_encode( $_POST ) . '</pre>';

			// wp_mail( $to, $subject, $message );

		} finally {

			http_response_code( 200 );

		}

	}

}
