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
use HelpScout\Api\Mailboxes\MailboxRequest;
use HelpScout\Api\Conversations\CustomField;
use HelpScout\Api\Conversations\Threads\NoteThread;
use HelpScout\Api\Conversations\Threads\CustomerThread;
use HelpScout\Api\Customers\Customer;


/**
 * Woo3pd_Helpscout_Webhook_Handler class.
 */
class Woo3pd_Helpscout_Webhook_Handler {

	/**
	 * The data parsed fom the Woo email.
	 * The translated values need to match the custom fields set up in HelpScout.
	 * 
	 * @var array
	 */
	public static $customFields = array (
		'customer_name' => '',
		'website' => '',
		'subscription_started' => '',
		'subscription_ends' => '',
		'wc_version' => '',
		'plugin_version' => '',
		'php_version' => '',
	);

	/**
	 * @var obj HelpScout\Api\ApiClient
	 */
	private static $client;

	/**
	 * Pseudo constructor.
	 */
	public static function init() {
		add_action( 'woo3pd_api_helpscout', array( __CLASS__, 'validate_webhook' ) );
		add_action( 'woo3pd_helpscout_valid_webhook_convo.created', array( __CLASS__, 'new_conversation' ) );
	}

	/**
	 * Check for Webhook Response.
	 */
	public static function validate_webhook() {

		$appSecretKey = Woo3pd_Helpscout::get_setting( 'appSecretKey' );
		$eventType    = '';
	
		try {

			// Read JSON file
			$json = file_get_contents( __DIR__ . '/webhook-payload.json');

			//Decode JSON
			$webhook = json_decode( $json );
			$eventType = 'convo.created';
		
			//$webhook   = IncomingWebhook::makeFromGlobals( $appSecretKey );	
			//$eventType = $webhook->getEventType();
			//$obj       = $webhook->getDataObject();
			//
		} catch (\HelpScout\Api\Exception\InvalidSignatureException $e) {
			wp_die( 'Helpscout Webhook Failure', 'Helpscout Webhook', array( 'response' => 500 ) );
			// Add log here
		} finally {
			
			if( $eventType ) {
				do_action( 'woo3pd_helpscout_valid_webhook_' . $eventType, $webhook );
			}
			exit;
		
		} 
		
	}


	/**
	 * Authenticate with the Helpscout API.
	 */
	public static function getClient( $refresh = false ) {

		$appId        = Woo3pd_Helpscout::get_setting( 'appId' );
		$appSecret    = Woo3pd_Helpscout::get_setting( 'appSecret' );
		$accessToken  = Woo3pd_Helpscout::get_setting( 'accessToken' );
		$refreshToken = Woo3pd_Helpscout::get_setting( 'refreshToken' );

		// Initialize API Client 
	    self::$client = ApiClientFactory::createClient();
		self::$client = self::$client->useClientCredentials($appId, $appSecret);
	
		if ( $accessToken && ! $refresh ) {	
			self::$client->setAccessToken( $accessToken );
		} else {
			self::$client->getAuthenticator()->fetchAccessAndRefreshToken();
			$accessToken = self::$client->getAuthenticator()->fetchAccessAndRefreshToken()->accessToken();
			Woo3pd_Helpscout::set_settings( array( 'accessToken' => $accessToken ) );
		}

		return self::$client;

	}

	/**
	 * Manpiulate the new conversation.
	 *
	 * @param  obj $webhook \Helpscout\API\Webhook
	 */
	public static function new_conversation( $webhook, $retry = false ) {

		try {

			// Authenticate with API.
			$client = self::getClient( $retry );

			// todo: pass the webhook object
			// $conversation = $webhook->getConversation();
			// $conversationId = $conversation->getId(); /* ?? */
			// $mailboxId = $conversation->getMailboxId();	
			//	$threads = $client->threads()->list($conversationId);
	      	//  $firstThread   = $threads[0];
	      	//	$threadId      = $firstThread->getId();
	      	//	$html = $firstThread->getText();
      				
			$mailboxId      = $webhook->mailboxId;
			$conversationId = $webhook->id;
			$folder_id      = $webhook->folderId;

      		// Thread info from the webhook.
      		$threads     = $webhook->_embedded->threads;
			$firstThread = $threads[0];
			$threadId    = $firstThread->id;
			$html        = $firstThread->body;
	
			if ( ! $html ) {
				//$error = new HelpScoutControllerApiV2Error( 'rest_invalid_message', 'Could not parse message.' );
				//Log::error( $error->get_error_data() );
				//$this->handle_error( $error, $request );
				//return new Response( $error->get_error_data(), 400 );
			}
			
			$ticket_data = self::parse_woo_email( $html );

            /**
             * Create Customer
             */
            $customer = new Customer();
            $customer->setFirstName($ticket_data['customer']['first_name']);
            $customer->setLastName($ticket_data['customer']['last_name']);
            $customer->addEmail($ticket_data['customer']['email'], 'work');

			/**
			 * Threads
			 */	
			// System status as note.
	        $noteThread = new NoteThread();
            $noteThread->setText( '<pre>' . $ticket_data[ 'status' ] . '</pre>' );

            $client->threads()->create($conversationId, $noteThread);

            // Clients question to separate thread.            
            $customerThread = new CustomerThread();
            $customerThread->setCustomer($customer);
            $customerThread->setText( $ticket_data[ 'description' ] );

            $client->threads()->create($conversationId, $noteThread);

        	/**
			 * Tags
			 */
			if ( ! empty( $ticket_data['product_tag'] ) ) {
				$client->conversations()->updateTags( $conversationId, array( $ticket_data['product_tag'] ) );
			}

			/**
			 * Custom Fields
			 */	
			$mailboxRequest = new MailboxRequest( array( 'fields' ) );
			$mailbox = self::$client->mailboxes()->get( $mailboxId, $mailboxRequest );
			$mailboxFields = $mailbox->getFields();

			if ( ! empty( $mailboxFields ) && ! empty( self::get_translated_custom_fields() ) ) { 

				$newCustomFields = array();

				foreach( $mailboxFields as $field ) {

					$key = array_search( $field->getName(), self::get_translated_custom_fields() );

					if ( false !== $key && isset( $ticket_data[$key] ) ) {
						$newCustomField = new CustomField();
						$newCustomField->setId( $field->getId() );
						$newCustomField->setValue( $ticket_data[$key] );
						$newCustomFields[] = $newCustomField;
					}

				}
			}

			if ( ! empty( $newCustomFields ) ) {
				$client->conversations()->updateCustomFields( $conversationId, $newCustomFields );
			}

			/**
			 * Since we can't remove a thread, reduce orginal thread to a time notice of when it was parsed.
			 */
			$updatedText = sprintf( esc_html__( 'Processed by webhook on %s', 'woo3pd' ), current_time( get_option( 'date_format' ) ) );
			$client->threads()->updateText( $conversationId, $threadId, $updatedText );

		} catch ( \HelpScout\Api\Exception\AuthenticationException $e) {

			// Retry one time.
			if ( ! $retry ) {
				self::new_conversation( $webhook, true );
			} else {
			// We've retried this and it's still not working... @todo log an error
			}

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
		}
		
		$status = '';
		if ( preg_match( '/(.*)<dd id="site-status-report"(.*?)>(.*?)<\/dd>(.*)/s', $html, $html_matches ) ) {
			$html   = $html_matches[ 1 ] . '<dd id="site-status-report"></dd>' . $html_matches[ 4 ];
			$status = $html_matches[ 3 ];
		}

		$product_name_node         = $html_document->getElementById( 'product-name' );
		$customer_email_node       = $html_document->getElementById( 'customer-email' );
		$customer_name_node        = $html_document->getElementById( 'customer-name' );
		$website_node              = $html_document->getElementById( 'ticket-web-site' );
		$subject_node              = $html_document->getElementById( 'ticket-subject' );
		$description_node          = $html_document->getElementById( 'ticket-description' );
		$subscription_started_node = $html_document->getElementById( 'support-subscription-started' );
		$subscription_ends_node    = $html_document->getElementById( 'support-subscription-ends' );
		$site_status_node          = $html_document->getElementById( 'site-status-report' );

		$ticket_data = array(
			'product_name'         => $product_name_node ? $product_name_node->textContent : '',
			'product_tag'          => $product_name_node ? self::sanitize_product_name( $product_name_node->textContent ) : '',
			'customer'             => array( 
										'full_name'  => $customer_name_node ? $customer_name_node->textContent : '',
										'first_name' => '',
										'last_name'  => '',
										'email'      => $customer_email_node ? $customer_email_node->textContent : '',
										),
			'subscription_started' => $subscription_started_node ? $subscription_started_node->textContent : '',
			'subscription_ends'    => $subscription_ends_node ? $subscription_ends_node->textContent : '',
			'website'              => $website_node ? esc_url_raw( $website_node->textContent ) : '',
			'subject'              => $subject_node ? $subject_node->textContent : '',
			'description'          => $description_node ? str_replace( array( '<dd id="ticket-description" style="padding-bottom: 1em;">', '</dd>' ), '', $html_document->saveHTML( $description_node ) ) : 'Failed to parse ticket Description!',
			'status'               => $site_status_node ? trim( str_replace( '`', '', strip_tags( $html_document->saveHTML( $site_status_node ), '<br>' ) ) ) : '',
			'wc_version'           => '',
			'php_version'          => '',
			'version'              => '',
		);

		// Stash full name as it's own key for custom fields.
		$ticket_data[ 'customer_name' ] = $ticket_data[ 'customer' ][ 'full_name' ];

		if ( empty( $ticket_data[ 'customer'][ 'full_name' ] ) ) {
			$ticket_data[ 'customer' ][ 'full_name' ] = 'Undefined';
		}
	
		$name = explode( ' ', $ticket_data[ 'customer' ][ 'full_name' ] );
		if ( 2 === sizeof( $name ) ) {
			$ticket_data['customer'][ 'first_name' ] = $name[ 0 ];
			$ticket_data['customer'][ 'last_name' ]  = $name[ 1 ];
		}

		// WooCommerce plugin version.
	    if ( preg_match( '/WC Version: ([A-Za-z0-9\.\-]+)/i', $ticket_data[ 'status' ], $wc_version_matches ) ) {
            $ticket_data['wc_version'] = strip_tags($wc_version_matches[ 1 ]);
        }

        // PHP version.
        if ( preg_match( '/PHP Version: ([A-Za-z0-9\.\-]+)/i', $ticket_data[ 'status' ], $php_version_matches ) ) {
            $ticket_data['php_version'] = strip_tags($php_version_matches[ 1 ]);
        }

        // Plugin version.
        if ( preg_match( '/' . $ticket_data[ 'product_name' ] . ': (.+?) - ([A-Za-z0-9\.\-]+)/i', $ticket_data[ 'status' ], $plugin_version_matches ) ) {
			$ticket_data['version'] = $plugin_version_matches[ 2 ];
		}

		self::validate_parsed_data( $ticket_data );

		return $ticket_data;

	}

	/**
	 * Makes all letters lowercase and replaces whitespaces with dashes.
	 *
	 * @param  string  $product_name
	 * @return string  $product_name
	 */
	private static function sanitize_product_name( $product_name ) {
		$product_name = strtolower( $product_name );
		$product_name = preg_replace( "/[\s_]/", "-", $product_name );
		$product_name = str_replace( 'woocommerce-', '', $product_name );
		return $product_name;
	}
	
	/**
	 * Validate successful parsing of ticket data.
	 *
	 * @param  array  $parsed_data
	 * @return boolean
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
			throw Exception( 'Failed to parse ticket data: ' . join( ' ', $errors ) );
		}
		return true;
	}


	/**
	 * Get custom fields translated.
	 *
	 * @return array
	 */
	private static function get_translated_custom_fields() {

		$locale = function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();

		$fields = get_transient( 'woo3pd_helpscout_translated_custom_fields-' . $locale );

		if ( false === $fields ) {

			$fields = array(
				'customer_name'        => __( 'Customer Name', 'woo3pd_helpscout' ),
				'website'              => __( 'Website', 'woo3pd_helpscout' ),
				'subscription_started' => __( 'Subscription Started', 'woo3pd_helpscout' ),
				'subscription_ends'    => __( 'Subscription Ends', 'woo3pd_helpscout' ),
				'wc_version'           => __( 'WC Version', 'woo3pd_helpscout' ),
				'version'              => __( 'Version', 'woo3pd_helpscout' ),
				'php_version'          => __( 'PHP Version', 'woo3pd_helpscout' ),
			);

			set_transient( 'woo3pd_helpscout_translated_custom_fields-' . $locale, $fields, 24 * HOUR_IN_SECONDS );

		}

		return $fields;
	}

}
Woo3pd_Helpscout_Webhook_Handler::init();