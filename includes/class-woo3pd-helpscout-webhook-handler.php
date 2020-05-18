<?php
/**
 * Handles responses from Helpsout.
 *
 * @package woo3pd_helpscout/api
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use HelpScout\Api\ApiClientFactory;
use HelpScout\Api\Webhooks\IncomingWebhook;

/**
 * Woo3pd_Helpscout_Webhook_Handler class.
 */
class Woo3pd_Helpscout_Webhook_Handler {

	/**
	 * Pseudo constructor.
	 */
	public static function init( $sandbox = false, $receiver_email = '' ) {
		add_action( 'woo3pd_api_helpscout', array( __CLASS__, 'validate_webhook' ) );
		add_action( 'woo3pd_helpscout_valid_webhook_convo.created', array( __CLASS__, 'new_conversation' ) );
	}

	/**
	 * Check for Webhook Response.
	 */
	public static function validate_webhook() {

		$appSecretKey = Woo3pd_Helpscout::get_setting( 'appSecretKey' );
	
		try {

			// Read JSON file
			$json = file_get_contents( __DIR__ . '/webhook-payload.json');

			//Decode JSON
			$obj = json_decode( $json );
			$eventType = 'convo.created';
		
			//$webhook   = IncomingWebhook::makeFromGlobals( $appSecretKey );	
			//$eventType = $webhook->getEventType();
		    //$obj       = $webhook->getDataObject();
			
			do_action( 'woo3pd_helpscout_valid_webhook_' . $eventType, $obj );
			exit;
		
		} catch (\Exception $e) {
			wp_die( 'Helpscout Webhook Failure', 'Helpscout Webhook', array( 'response' => 500 ) );
		}
		
	}


	/**
	 * Manpiulate the new conversation.
	 *
	 * @param  obj $obj Data object from webhook.
	 */
	public static function authenticate( $obj ) {

		$appId        = Woo3pd_Helpscout::get_setting( 'appId' );
		$appSecret    = Woo3pd_Helpscout::get_setting( 'appSecret' );
		$authToken    = Woo3pd_Helpscout::get_setting( 'authToken' );
		$refreshToken = Woo3pd_Helpscout::get_setting( 'refreshToken' );


	}


	/**
	 * Manpiulate the new conversation.
	 *
	 * @param  obj $obj Data object from webhook.
	 */
	public static function new_conversation( $obj ) {

		try {

			// todo: pass the webhook object
			// $webhook->getConversation();

			$convo_id = $obj->id;
			$folder_id = $obj->folderId;

			$threads = $obj->_embedded->threads;
			$first_thread = $threads[0];
			$thread_id = $first_thread->id;

			$html = $first_thread->body;

			if ( ! $html ) {
				//$error = new HelpScoutControllerApiV2Error( 'rest_invalid_message', 'Could not parse message.' );
				//Log::error( $error->get_error_data() );
				//$this->handle_error( $error, $request );
				//return new Response( $error->get_error_data(), 400 );
			}

			$ticket_data = self::parse_woo_email( $html );

			echo '<pre>';
			print_r($ticket_data);
			echo '</pre>';

		} catch (\Exception $e) {
			print_r($e->getMessage());
		}

	}


	/**
	 * Parse email into data.
	 *
	 * @param  string $html The html from the first conversation's thread, which is the email from Woo.
	 */
	public static function parse_woo_email( $html ) {

		libxml_use_internal_errors( true );
		$html_document = new DOMDocument();
		$html_loaded   = $html_document->loadHTML( $html );
		libxml_clear_errors();

		if ( ! $html_loaded ) {
			throw new Exception( 'Could not parse message content' );
			//$error = new HelpScoutControllerApiV2Error( 'rest_invalid_message_content', 'Could not parse message content.' );
			//Log::error( $error->get_error_data() );
			//$this->handle_error( $error, $request );
			//return new Response( $error->get_error_data(), 400 );
		}

		$product_name_node         = $html_document->getElementById( 'product-name' );
		$customer_html_node        = $html_document->getElementById( 'customer-email' );
		$customer_name_node        = $html_document->getElementById( 'customer-name' );
		$website_node              = $html_document->getElementById( 'ticket-web-site' );
		$subject_node              = $html_document->getElementById( 'ticket-subject' );
		$description_node          = $html_document->getElementById( 'ticket-description' );
		$subscription_started_node = $html_document->getElementById( 'support-subscription-started' );
		$subscription_ends_node    = $html_document->getElementById( 'support-subscription-ends' );
		$site_status_node          = $html_document->getElementById( 'site-status-report' );

		$ticket_data = array(
			'product_name'         => $product_name_node ? $product_name_node->textContent : '',
			'customer_email'       => $customer_html_node ? $customer_html_node->textContent : '',
			'customer_name'        => $customer_name_node ? $customer_name_node->textContent : '',
			'subscription_started' => $subscription_started_node ? $subscription_started_node->textContent : '',
			'subscription_ends'    => $subscription_ends_node ? $subscription_ends_node->textContent : '',
			'website'              => $website_node ? $website_node->textContent : '',
			'subject'              => $subject_node ? $subject_node->textContent : '',
			'description'          => $description_node ? str_replace( array( '<dd id="ticket-description" style="padding-bottom: 1em;">', '</dd>' ), '', $html_document->saveHTML( $description_node ) ) : 'Failed to parse ticket Description!',
			'status'               => $site_status_node ? trim( str_replace( array( '<pre class="">`', '<pre>`', '`</pre>', '<pre>', '</pre>' ), '', $html_document->saveHTML( $site_status_node ) ) ) : '',
		);

		if ( empty( $ticket_data[ 'customer_name' ] ) ) {
			$ticket_data[ 'customer_name' ] = 'Undefined';
		}

		//$ticket_data_validation_result = self::validate_parsed_data( $ticket_data );
		
		//if ( $this->is_error( $ticket_data_validation_result ) ) {
		//	Log::error( $ticket_data_validation_result->get_error_data() );
		//	$this->handle_error( $ticket_data_validation_result, $request );
		//	return new Response( $ticket_data_validation_result->get_error_data(), 400 );
		//}

		return $ticket_data;

	}


	/**
	 * Validate successful parsing of ticket data.
	 *
	 * @param  array  $parsed_data
	 * @return boolean|HelpScoutControllerApiV2Error
	 */
	private static function validate_parsed_data( $parsed_data ) {
		$errors = array();
		if ( empty( $parsed_data[ 'customer_email' ] ) ) {
			$errors[] = 'Failed to parse Email field.';
		}
		if ( empty( $parsed_data[ 'website' ] ) ) {
			$errors[] = 'Failed to parse URL field.';
		}
		if ( empty( $parsed_data[ 'subject' ] ) ) {
			$errors[] = 'Failed to parse Subject field.';
		}
		if ( ! empty( $errors ) ) {
			$error_content = new HelpScoutControllerApiV2Error( 'rest_ticket_data_parse_failure', 'Failed to parse ticket data.', $errors );
			return $error_content;
		}
		return true;
	}

}
Woo3pd_Helpscout_Webhook_Handler::init();