<?php
/**
 * Handles responses from Helpscout.
 *
 * @package woo3pdHelpscout/Woo3pdHelpscout/api
 */

namespace Woo3pdHelpscout\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Woo3pdHelpscout\App;
use Woo3pdHelpscout\Api\Abstracts\AbstractAPI;
use Woo3pdHelpscout\Parser\Parse;
use Woo3pdHelpscout\Exceptions\QuietException;

use HelpScout\Api\Webhooks\IncomingWebhook;
use HelpScout\Api\Conversations\Conversation;
use HelpScout\Api\Conversations\Threads\NoteThread;
use HelpScout\Api\Conversations\Threads\CustomerThread;
use HelpScout\Api\Conversations\CustomField;
use HelpScout\Api\Customers\Customer;
use HelpScout\Api\Tags\Tag;

/**
 * HelpscoutApi class.
 */
class Helpscout extends AbstractAPI {

	/**
	 * @var string
	 */
	protected $id = 'helpscout';

	/**
	 * Additional settings.
	 *
	 * @var array additional DB settings for this API.
	 */
	protected $extra_settings = array(
		'secret_key' => '',
	);

	/**
	 * Handle the webhook.
	 */
	public function handle_webhook() {
		// Give HS itself some time to process customers first.
		sleep( 10 );

		$webhook = $this->get_payload();
		$this->auto_refresh_token( [ $this, 'update_conversation' ], $webhook );
	}

	/**
	 * Get the data from the Webhook.
	 *
	 * @return obj $webhook \Helpscout\API\Webhook
	 */
	public function get_payload() {

		$webhook = '';

		$secretKey = $this->get_setting( 'secret_key' );

		try {

			$webhook = IncomingWebhook::makeFromGlobals( $secretKey );

		} catch ( \HelpScout\Api\Exception\InvalidSignatureException $e ) {

			$message  = 'Invalid Helpscout Webhook Signature' . '</br>';
			$message .= 'server vars: ' . json_encode( $_SERVER ) . '</br>';
			$message .= 'payload : ' . json_encode( @file_get_contents('php://input') );

			throw new \Exception( $message );
		}

		return $webhook;

	}

	/**
	 * Update existing conversation.
	 *
	 * @param  obj HelpScout\Api\Webhooks\IncomingWebhook $webhook
	 */
	public function update_conversation( $webhook ) {

		// Authenticate with API.
		$client = $this->get_client();

		// Get conversation info from Helpscout Webhooks.
		$conversation    = $webhook->getConversation();
		$conversation_id = $conversation->getId();
		$mailbox_id      = $conversation->getMailboxId();	
		$customer        = $conversation->getCustomer();
		$payload         = $webhook->getDataObject();
		
		// Thread info from the webhook.
		$threads      = $payload->_embedded->threads;
		$first_thread = $threads[0];
		$thread_id    = $first_thread->id;
		$html         = $first_thread->body;

		// Get the original HTML source.
		try {
			$source = $client->threads()->getSource( $conversation_id, $thread_id );
			$html = $source->getOriginal();
		} catch ( \GuzzleHttp\Exception\ClientException $e ) {
		    if ( 404 === $e->getResponse()->getStatusCode() ) {
		    	  throw new QuietException( 'This is not a *new* ticket submission.' );
		    }
		}

		if ( ! $html ) {
			throw new \Exception( 'Could not parse content from webhook.' );
		}

		$ticket_data = Parse::instance()->parse_woo_email( $html );

		/**
		 * Threads
		 */

		// System status as note.
		if ( ! empty( $ticket_data['status'] ) ) {
		
			$noteThread = new NoteThread();
			$noteThread->setText( '<pre>' . $ticket_data['status'] . '</pre>' );

			$client->threads()->create( $conversation_id, $noteThread );

		}

		// Customer question to separate thread.
		if ( ! empty( $ticket_data['description'] ) ) {

			$customerThread = new CustomerThread();

			// I think the customer is wrong here
			$customerThread->setCustomer( $customer );
			$customerThread->setText( $ticket_data['description'] );

			$client->threads()->create( $conversation_id, $customerThread );

		}

		/**
		 * Tags
		 */
		$tags = [ 'api' ]; // Always set an api tag to identify something we parsed.

		// Add product name if found.
		if ( ! empty( $ticket_data['product_tag'] ) ) {
			$tags[] = [ $ticket_data['product_tag'] ];
		}

		if ( ! empty( $tags ) ) {
			$client->conversations()->updateTags( $conversation_id, $tags );

		}

		/**
		 * Custom Fields
		 */
		$customFields = $this->get_custom_fields( $mailbox_id, $ticket_data );

		if ( ! empty( $customFields ) ) {
			$client->conversations()->updateCustomFields( $conversation_id, $customFields );
		}

		/**
		 * Since we can't remove a thread, reduce orginal thread to a time notice of when it was parsed (only IF successfully parsed).
		 */
		if ( ! empty( $ticket_data['status'] ) && ! empty( $ticket_data['description'] ) ) {
			$updatedText = sprintf(
				// Translators: %s is the date the webhook was processed.
				esc_html_x( 'Processed by webhook on %1$s at %2$s', 'Date and time', 'woo3pdhelpscout' ),
				current_time( get_option( 'date_format' ) ),
				current_time( get_option( 'time_format' ) ),
			);

			$client->threads()->updateText( $conversation_id, $thread_id, $updatedText );

		}

	}

}
