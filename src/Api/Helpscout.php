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

use HelpScout\Api\ApiClientFactory;
use HelpScout\Api\Webhooks\IncomingWebhook;
use HelpScout\Api\Mailboxes\MailboxRequest;
use HelpScout\Api\Conversations\Conversation;
use HelpScout\Api\Conversations\Threads\NoteThread;
use HelpScout\Api\Conversations\Threads\CustomerThread;
use HelpScout\Api\Conversations\CustomField;
use HelpScout\Api\Customers\Customer;
use HelpScout\Api\Customers\CustomerFilters;
use HelpScout\Api\Tags\Tag;
use HelpScout\Api\Entity\Collection;
use HelpScout\Api\Entity\PagedCollection;

/**
 * HelpscoutApi class.
 */
class Helpscout extends AbstractAPI {

	/**
	 * @var string
	 */
	protected $id = 'helpscout';

	/**
	 * @var obj HelpScout\Api\ApiClient
	 */
	private $client;

	/**
	 * Additional settings.
	 *
	 * @var array additional DB settings for this API.
	 */
	protected $extra_settings = array(
		'secret_key' => '',
		'mailbox_id' => '',
	);

	/**
	 * The data parsed fom the Woo email.
	 * The translated values need to match the custom fields set up in HelpScout.
	 *
	 * @var array
	 */
	public $customFields = array(
		'customerName'        => '',
		'website'             => '',
		'subscriptionStarted' => '',
		'subscriptionEnds'    => '',
		'wcVersion'           => '',
		'plugin_version'      => '',
		'phpVersion'          => '',
	);

	/**
	 * Handle the webhook.
	 */
	public function handle_webhook() {
		$webhook = $this->get_payload();
		$this->auto_refresh_token( 'update_conversation', $webhook );
	}

	/**
	 * Get the body of the Email from the Webhook.
	 *
	 * @return obj $webhook \Helpscout\API\Webhook
	 */
	public function get_payload() {

		$webhook = '';

		$secretKey = $this->get_setting( 'secret_key' );

		try {

			$webhook = IncomingWebhook::makeFromGlobals( $secretKey );

			// $html = file_get_contents( __DIR__ . '/webhook-payload.json');

		} catch ( \HelpScout\Api\Exception\InvalidSignatureException $e ) {

			$message  = 'Invalid Helpscout Webhook Signature' . '</br>';
			$message .= 'server vars: ' . json_encode( $_SERVER ) . '</br>';
			$message .= 'payload : ' . json_encode( @file_get_contents('php://input') );

			throw new \Exception( $message );
		}

		return $webhook;

	}

	/**
	 * Authenticate with the Helpscout API.
	 */
	public function get_client() {

		// Initialize API Client
		if ( empty( $this->client ) ) {

			$appId       = $this->get_setting( 'client_id' );
			$appSecret   = $this->get_setting( 'client_secret' );
			$accessToken = $this->get_setting( 'access_token' );

			$accessToken = '';

			if ( $appId && $appSecret ) {
				$this->client = ApiClientFactory::createClient();
				$this->client = $this->client->useClientCredentials( $appId, $appSecret );
			} else {
				throw new \Exception( 'No tokens saved in settings.' );
			}

			if ( $this->client && $accessToken ) {
				$this->client->setAccessToken( $accessToken );
			}
		}

		return $this->client;

	}

	/**
	 * Refresh the token.
	 *
	 * @param  string $client_id - ignored here since we're using their API wrappers.
	 * @param  string $client_secret - ignored here since we're using their API wrappers.
	 * @param  string $refresh_token - ignored here since we're using their API wrappers.
	 * @return string
	 *
	 * @param  string $html - The original email message.
	 */
	public function refresh_token( $client_id = '', $client_secret = '', $refresh_token = '' ) {
		$this->get_client()->getAuthenticator()->fetchAccessAndrefresh_token();
		$accessToken = $this->get_client()->getAuthenticator()->fetchAccessAndrefresh_token()->accessToken();
		App::instance()->set_settings( array( 'access_token' => $accessToken ) );
	}


	/**
	 * Auto-refresh the Token if needed.
	 *
	 * @param  string $callback - the class method to reattempt.
	 * @param  string $param - The parameter to relay to the callback method.
	 * @throws  \HelpScout\Api\Exception\AuthenticationException $e
	 */
	public function auto_refresh_token( $callback, $param ) {

		$retry = false;

		do {

			try {

				$this->$callback( $param );

			} catch ( \HelpScout\Api\Exception\AuthenticationException $e ) {

				// We've retried this and it's still not working.
				if ( $retry ) {
					$retry = false;
					throw new \Exception( $e->getMessage() );
					// Refresh and retry this one time.
				} else {
					$this->refresh_token();
					$retry = true;
				}
			} catch ( \HelpScout\Api\Exception\ValidationErrorException $e ) {

				// Something is janky with the API.
				$message  = $e->getError()->getMessage() . '</br>';
				$message .= 'logRef: ' . $e->getError()->getLogRef() . '</br>';

				$errors = $e->getError()->getErrors();
				$message .= 'errors : ';
				foreach ( $errors as $err ) {
					$message .= ' | ' . $err->getMessage();
				}
				
				throw new \Exception( $message );

			}
		} while ( $retry );

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
		$noteThread = new NoteThread();
		$noteThread->setText( '<pre>' . $ticket_data['status'] . '</pre>' );

		$client->threads()->create( $conversation_id, $noteThread );

		// Clients question to separate thread.
		$customerThread = new CustomerThread();

		// I think the customer is wrong here
		$customerThread->setCustomer( $customer );
		$customerThread->setText( $ticket_data['description'] );

		$client->threads()->create( $conversation_id, $customerThread );

		/**
		 * Tags
		 */
		if ( ! empty( $ticket_data['product_tag'] ) ) {
			$client->conversations()->updateTags( $conversation_id, array( $ticket_data['product_tag'] ) );
		}

		/**
		 * Custom Fields
		 */
		$customFields = $this->get_custom_fields( $mailbox_id, $ticket_data );

		if ( ! empty( $customFields ) ) {
			$client->conversations()->updateCustomFields( $conversation_id, $customFields );
		}

		/**
		 * Since we can't remove a thread, reduce orginal thread to a time notice of when it was parsed.
		 */
		$updatedText = sprintf(
			// Translators: %s is the date the webhook was processed.
			esc_html_x( 'Processed by webhook on %1$s at %2$s', 'Date and time', 'woo3pdhelpscout' ),
			current_time( get_option( 'date_format' ) ),
			current_time( get_option( 'time_format' ) ),
		);

		$client->threads()->updateText( $conversation_id, $thread_id, $updatedText );

	}

	/**
	 * Get custom fields translated.
	 *
	 * @return array
	 */
	private function get_translated_custom_fields() {

		$locale = function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();

		//delete_transient( 'woo3pd_helpscout_translated_custom_fields-' . $locale );

		$fields = get_transient( 'woo3pd_helpscout_translated_custom_fields-' . $locale );

		if ( false === $fields ) {

			$fields = array(
				'customer_name'        => __( 'Customer Name', 'woo3pdhelpscout' ),
				'website'              => __( 'Website', 'woo3pdhelpscout' ),
				'subscription_started' => __( 'Subscription Started', 'woo3pdhelpscout' ),
				'subscription_ends'    => __( 'Subscription Ends', 'woo3pdhelpscout' ),
				'wc_version'           => __( 'WC Version', 'woo3pdhelpscout' ),
				'version'              => __( 'Version', 'woo3pdhelpscout' ),
				'php_version'          => __( 'PHP Version', 'woo3pdhelpscout' ),
				'connected'            => __( 'Connected to WooCommerce.com', 'woo3pdhelpscout' ),
				'wpdotcom'             => __( 'Hosted at WordPress.com', 'woo3pdhelpscout' ),
			);

			set_transient( 'woo3pd_helpscout_translated_custom_fields-' . $locale, $fields, 24 * HOUR_IN_SECONDS );

		}

		return $fields;
	}

	/**
	 * Get custom fields for a particular Mailbox.
	 *
	 * @param  string $mailbox_id
	 * @param  array $ticket_data
	 * @return []CustomField
	 */
	private function get_custom_fields( $mailbox_id, $ticket_data ) {

		$custom_fields = array();

		$mailbox_request = new MailboxRequest( array( 'fields' ) );
		$mailbox         = $this->get_client()->mailboxes()->get( $mailbox_id, $mailbox_request );
		$mailbox_fields  = $mailbox->getFields();

		if ( ! empty( $mailbox_fields ) && ! empty( $this->get_translated_custom_fields() ) ) {

			foreach ( $mailbox_fields as $field ) {

				$key = array_search( $field->getName(), $this->get_translated_custom_fields() );

				if ( false !== $key && isset( $ticket_data[ $key ] ) ) {
					$custom_field = new CustomField();
					$custom_field->setId( $field->getId() );
					$custom_field->setValue( $ticket_data[ $key ] );
					$custom_fields[] = $custom_field;
				}
			}
		}

		return $custom_fields;
	}

}
