<?php
/**
 * Handles responses from Gmail API.
 *
 * @package woo3pd_helpscout/api
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

}
