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
		'secret_key' => '',
	);


	/**
	 * Get the body of the Email from the Webhook.
	 *
	 * @return  string - the body of the email.
	 */
	public function get_payload() {

		$html = '';

		if ( ! empty( $_POST ) && isset( $_POST['html'] ) ) {
			$html = $_POST['html'];
		}

		return $html;

	}

	/**
	 * Handle the webhook.
	 *
	 * @return  string $html
	 */
	public function handle_webhook() {
		$html = $this->get_payload();
		Helpscout::instance()->auto_refresh_token( [ $this, 'new_conversation' ], $html );
	}



	/**
	 * Create a new conversation.
	 *
	 * @param  string $html - The original email message.
	 */
	public function new_conversation( $html ) {

		$client     = $this->get_client();
		$mailbox_id = $this->get_setting( 'mailbox_id' );

		if ( ! $mailbox_id ) {
			throw new \Exception( 'Missing mailbox id' );
		}

		$ticket_data = Parse::instance()->parse_woo_email( $html );

		/**
		 * Customer
		 */
		$customer = new Customer();
		if ( '' !== $ticket_data['customer']['first_name'] ) {
			$customer->setFirstName( substr( $ticket_data['customer']['first_name'], 0, 40 ) );
		}
		
		if ( '' !== $ticket_data['customer']['first_name'] ) {
			$customer->setLastName( substr( $ticket_data['customer']['last_name'], 0, 40 ) );
		}
		
		$customer->addEmail( $ticket_data['customer']['email'], 'work' );

		/**
		 * Threads
		 */
		$threads = array();

		// System status as note.
		$noteThread = new NoteThread();
		$noteThread->setText( '<pre>' . $ticket_data['status'] . '</pre>' );
		$threads[] = $noteThread;

		// Clients question to separate thread.
		$customerThread = new CustomerThread();
		$customerThread->setCustomer( $customer );
		$customerThread->setText( $ticket_data['description'] );
		$threads[] = $customerThread;

		/**
		 * Tags
		 */
		$tag = new Tag();
		$tag->setName( $ticket_data['product_tag'] );

		/**
		 * Custom Fields
		 */
		$customFields = $this->get_custom_fields( $mailbox_id, $ticket_data );

		/**
		 * Build the new conversation.
		 */
		$conversation = new Conversation();
		$conversation->setSubject( $ticket_data['subject'] );
		$conversation->setStatus( 'active' );
		$conversation->setType( 'email' );
		$conversation->setMailboxId( $mailbox_id );
		$conversation->setCustomer( $customer );
		$conversation->setThreads( new Collection( $threads ) );
		$conversation->addTag( $tag );
		$conversation->setCustomFields( new Collection( $customFields ) );

		// Create the new conversation.
		$new_conversation_id = $client->conversations()->create( $conversation );

	}


}
