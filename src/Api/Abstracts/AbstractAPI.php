<?php
/**
 * Handles responses from API.
 *
 * @package Woo3pdHelpscout/API/Abstracts
 */
namespace Woo3pdHelpscout\Api\Abstracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Woo3pdHelpscout\App;
use Woo3pdHelpscout\AbstractApp;
use HelpScout\Api\ApiClientFactory;

/**
 * API class.
 */
abstract class AbstractAPI extends AbstractApp {

	/**
	 * @const string
	 */
	const AUTHURL = '';

	/**
	 * @const string
	 */
	const TOKENURL = '';

	/**
	 * @const string
	 */
	const REVOKEURL = '';

	/**
	 * @const string
	 */
	const SCOPE = '';

	/**
	 * @var obj HelpScout\Api\ApiClient
	 */
	protected $client;

	 * Extra API-specific settings.
	 *
	 * @var array the DB settings for this API.
	 */
	protected $extra_settings = array();

	/**
	 * Default settings.
	 *
	 * @var array the DB settings for this API.
	 */
	protected $default_settings = array(
		'client_id'     => '',
		'client_secret' => '',
		'token'         => '',
		'refresh'       => '',
	);

	/**
	 * @var string
	 */
	protected $id = '';

	/**
	 * constructor
	 */
	public function __construct() {
		$this->default_settings = array_merge( $this->default_settings, $this->extra_settings );
	}

	/**
	 * Get the id for this API.
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get the support email's HTML.
	 *
	 * @return string
	 */
	public function get_payload() {
		return '';
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
	 * Get the auth URL
	 *
	 * @return  string $url
	 */
	public function get_redirect_url() {
		return add_query_arg(
			array(
				'page' => App::OPTION,
			),
			admin_url( 'options-general.php' ),
		);
	}

	/**
	 * Get the oAuth 2 URL
	 *
	 * @param  string $client_id
	 * @return  string $url
	 */
	public function get_auth_url( $client_id = '' ) {

		return add_query_arg(
			array(
				'response_type'   => 'code',
				'redirect_uri'    => urlencode( $this->get_redirect_url() ),
				'client_id'       => $client_id,
				'scope'           => urlencode( self::SCOPE ),
				'access_type'     => 'offline',
				'approval_prompt' => 'force',
				'state'           => \wp_create_nonce( 'woo3pd_helpscout_nonce' ),
			),
			self::AUTHURL,
		);

	}

	/**
	 * Get the auth URL
	 *
	 * @return  string $url
	 */
	public function get_revoke_url() {

		return wp_nonce_url(
			add_query_arg(
				array(
					'revoke' => 1,
					'page'   => App::OPTION,
				),
				admin_url( 'options-general.php' )
			),
			'woo3pd_helpscout_nonce',
			'woo3pd_helpscout_nonce',
		);

	}

	/**
	 * Swap an auth code for auth tokens.
	 *
	 * @param  string $code
	 * @param  string $client_id
	 * @param  string $client_secret
	 * @return array
	 */
	public function swap_for_tokens( $code, $client_id, $client_secret ) {

		$access_token  = '';
		$refresh_token = '';

		return wp_remote_post(
			self::TOKENURL,
			array(
				'body' => array(
					'code'          => $code,
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'grant_type'    => 'authorization_code',
					'redirect_uri'  => $this->get_redirect_url(),
				),
			)
		);

		if ( ! is_wp_error( $result ) ) {

			$json = json_decode( wp_remote_retrieve_body( $result ), true );

			if ( ! empty( $json['access_token'] ) && ! empty( $json['refresh_token'] ) ) {

				$access_token  = $json['access_token'];
				$refresh_token = $json['refresh_token'];

				$this->set_settings(
					array(
						$this->get_prefix() . '_token'   => $json['access_token'],
						$this->get_prefix() . '_refresh' => $json['refresh_token'],
					)
				);

			}
		}

		return array(
			'access_token'  => $access_token,
			'refresh_token' => $refresh_token,
		);
	}

	/**
	 * Remove auth tokens.
	 *
	 * @param  string $client_id
	 * @param  string $client_secret
	 * @param  string $access_token
	 * @return bool
	 */
	public function revoke_tokens( $client_id, $client_secret, $access_token ) {

		$result = wp_remote_post(
			self::revoke_url,
			array(
				'body' => array(
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'token'         => $token,
				),
			)
		);

		if ( ! is_wp_error( $result ) && ! empty( $result['response'] ) && ! empty( $result['response']['code'] ) && 200 === $result['response']['code'] ) {
			$this->set_settings(
				array(
					$this->get_prefix() . '_token'   => '',
					$this->get_prefix() . '_refresh' => '',
				)
			);
			return true;
		} else {
			return false;
		}

	}

	/*
	-----------------------------------------------------------------------------------*/
	/*
	 HelpScout Client Functions */
	/*-----------------------------------------------------------------------------------*/

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
	 * Auto-refresh the Token if needed.
	 *
	 * @param  string $callback - the function/method to reattempt.
	 * @param  string $param - The parameter to relay to the callback method.
	 * @throws  \HelpScout\Api\Exception\AuthenticationException $e
	 */
	public function auto_refresh_token( $callback, $param ) {

		$retry = false;

		do {

			try {

				call_user_func( $callback, $param );

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

	/*
	-----------------------------------------------------------------------------------*/
	/*
	 Helper Functions */
	/*-----------------------------------------------------------------------------------*/

	/**
	 * Get default settings.
	 *
	 * @return  array
	 */
	public function get_default_settings() {
		return $this->default_settings;
	}

	/**
	 * Get settings from the DB.
	 *
	 * @return  array
	 */
	public function get_settings() {

		if ( empty( $this->settings ) ) {
			$settings       = App::instance()->get_settings();
			$settings       = isset( $settings[ $this->id ] ) ? $settings[ $this->id ] : array();
			$this->settings = array_merge( $this->get_default_settings(), $settings );
		}

		return $this->settings;

	}

	/**
	 * Get a setting from the DB.
	 *
	 * @return  string
	 */
	public function get_setting( $setting = '' ) {
		$settings = $this->get_settings();
		return isset( $settings[ $setting ] ) ? $settings[ $setting ] : false;
	}

	/**
	 * Update a setting in the DB.
	 *
	 * @param array key => value
	 */
	public function set_settings( $settings = array() ) {

		$result = false;

		if ( ! empty( $settings ) && is_array( $settings ) ) {
			$new_settings = array_intersect_key( $settings, $this->get_default_settings() );
			// $new_settings    = array_map( 'sanitize_text_field', $new_settings );
			App::instance()->set_settings( array( $this->id => $settings ) );
		}

	}

}

