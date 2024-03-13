<?php
/**
 * Handles webhook from SendGrid API.
 *
 * @package woo3pdHelpscout/api
 */

namespace Woo3pdHelpscout\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Woo3pdHelpscout\App;
use Woo3pdHelpscout\Api\Abstracts\AbstractAPI;
use Woo3pdHelpscout\Parser\Parse;
use Woo3pdHelpscout\Exceptions\QuietException;

use HelpScout\Api\Conversations\Conversation;
use HelpScout\Api\Conversations\Threads\Attachments\AttachmentFactory;
use HelpScout\Api\Conversations\Threads\NoteThread;
use HelpScout\Api\Conversations\Threads\CustomerThread;
use HelpScout\Api\Conversations\CustomField;
use HelpScout\Api\Customers\Customer;
use HelpScout\Api\Support\Filesystem;
use HelpScout\Api\Tags\Tag;
use HelpScout\Api\Entity\Collection;

/**
 * HelpscoutApi class.
 */
class Sendgrid extends AbstractAPI {

	/**
	 * @var string
	 */
	protected $id = 'sendgrid';

	/**
	 * Additional settings.
	 *
	 * @var array additional DB settings for this API.
	 */
	protected $extra_settings = array(
		'api_key'    => '',
	);

	/**
	 * Get the webhook payload.
	 *
	 * @return  array - the $_POSTed data.
	 */
	public function get_payload() {

		$payload = array();

		$payload_json = @file_get_contents('php://input');
		
		if ( !empty($payload_json ) ) {
			$payload = json_decode($payload_json, true);
		} elseif ( ! empty( $_POST ) ) {
			$payload = $_POST;
		}

		return $payload;
	}

	/**
	 * Handle the webhook.
	 */
	public function handle_webhook() {
		$payload = $this->get_payload();
		$this->auto_refresh_token( array( $this, 'new_conversation' ), $payload );
	}

	/**
	 * Create a new conversation.
	 *
	 * @param  array $payload - The original data.
	 */
	public function new_conversation( $payload ) {

		$client     = $this->get_client();
		$mailbox_id = intval( App::instance()->get_setting( 'mailbox_id' ) );
		$api_key    = $this->get_setting( 'api_key' );
		
		if ( ! $mailbox_id ) {
			throw new \Exception( 'Missing mailbox id' );
		}

		if ( empty( $payload['html'] ) && empty( $payload['text'] ) ) {
			throw new \Exception( 'Missing email message content' );
		}

		$parsed_customer = Parse::instance()->parseEmailAddress($payload['from']);

		// Build basic ticket variables from email.
		$customer_first = $parsed_customer['first'];
		$customer_last  = $parsed_customer['last'];
		$customer_email = $parsed_customer['email'];

		$ticket_subject = isset( $payload['subject'] ) ? $payload['subject'] : '';
		$ticket_message = isset( $payload['html'] ) ? $payload['html'] : '';

		// Fallback to non-html message.
		if ( ! $ticket_message ) {
			$ticket_message = isset( $payload['text'] ) ? $payload['text'] : '';
		}

		$ticket_data = Parse::instance()->parse_woo_email( $ticket_message );

		// Update variables if we can parse anything out of the ticket.
		if ( ! empty( $ticket_data['customer']['first_name'] ) ) {
			$customer_first = substr( $ticket_data['customer']['first_name'], 0, 40 );
		}

		if ( ! empty( $ticket_data['customer']['last_name'] ) ) {
			$customer_last = substr( $ticket_data['customer']['last_name'], 0, 40 );
		}

		if ( ! empty( $ticket_data['customer']['email'] ) ) {
			$customer_email = $ticket_data['customer']['email'];
		}

		/**
		 * Customer
		 */
		$customer = new Customer();

		$customer->setFirstName( $customer_first );
		$customer->setLastName( $customer_last );
		$customer->addEmail( $customer_email, 'work' );

		/**
		 * Threads
		 */
		$threads = array();

		// System status as note.
		if ( ! empty( $ticket_data['status'] ) ) {
			$noteThread = new NoteThread();
			$noteThread->setText( '<pre>' . $ticket_data['status'] . '</pre>' );
			$threads[] = $noteThread;
		}

		// Customer question to separate thread.
		if ( ! empty( $ticket_data['description'] ) ) {
			$ticket_message = $ticket_data['description'];
		}

		$customerThread = new CustomerThread();
		$customerThread->setCustomer( $customer );
		$customerThread->setText( $ticket_message );

		if ( ! empty( $api_key ) && ! empty( $payload['attachments'] ) && ! empty( $payload['attachment-info'] ) ) {

			$attachmentInfo = json_decode($payload['attachment-info'], true);

			// Add attachments to the thread
			foreach ($attachmentInfo as $attachment) {
				$filename    = $attachment['filename'];
				$contentType = $attachment['type'];
				$contentId   = $attachment['content-id'];
				$url         = "https://api.sendgrid.com/v3/mail/attachments/$contentId/content";

				$file = file_get_contents($url, false, stream_context_create(array(
					'http' => array(
						'header' => "Authorization: Bearer {$api_key}\r\n",
					),
					)));

				if ($file === false) {
					$error = \error_get_last();

					throw new \Exception( "Error: {$error['message']} in {$error['file']} on line {$error['line']}" );

				} else {

					$attachmentObject = new Attachment();
					$attachmentObject->setFileName($filename);
					$attachmentObject->setMimeType($contentType);
					$attachmentObject->setData($file);
					$customerThread->addAttachment($attachmentObject);

				}
			}

		}
		
		$threads[] = $customerThread;

		/**
		 * Tags
		 */
		$tags = array();

		$apiTag = new Tag();
		$apiTag->setName( 'api' ); // Always set an api tag to identify something we parsed.

		$tags[] = $apiTag;

		if ( ! empty( $ticket_data['product_tag'] ) ) {
			$productTag = new Tag();
			$productTag->setName( $ticket_data['product_tag'] );
			
			$tags[] = $productTag;
		}

		/**
		 * Custom Fields
		 */
		$customFields = $this->get_custom_fields( $mailbox_id, $ticket_data );

		/**
		 * Build the new conversation.
		 */
		$conversation = new Conversation();
		$conversation->setSubject( $ticket_subject );
		$conversation->setStatus( 'active' );
		$conversation->setType( 'email' );
		$conversation->setMailboxId( $mailbox_id );
		$conversation->setCustomer( $customer );

		if ( ! empty( $threads ) ) {
			$conversation->setThreads( new Collection( $threads ) );
		}
		
		if ( ! empty( $tags ) ) {
			$conversation->setTags( new Collection( $tags ) );
		}
		
		if ( ! empty( $customFields ) ) {
			$conversation->setCustomFields( new Collection( $customFields ) );
		}

		// Create the new conversation.
		$new_conversation_id = $client->conversations()->create( $conversation );
	}
}
