<?php
/**
 * Scheduled Actions.
 * Gmail pub/sub needs to renew "watch" every day.
 *
 * @package woo3pdHelpscout/actions
 */
namespace Woo3pdHelpscout\Actions;

use Woo3pdHelpscout\App;
use Woo3pdHelpscout\AbstractApp;
use Woo3pdHelpscout\Api\Gmail;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Scheduled extends AbstractApp {

	/**
	 * constructor.
	 */
	public function setup_hooks() {
		add_action( 'woo3pdhelpscout_gmail_api_watch', array( $this, 'watch' ) );
	}

	/*
	-----------------------------------------------------------------------------------*/
	/*
	 API */
	/*-----------------------------------------------------------------------------------*/


	/**
	 * API request - Begin watching inbox.
	 *
	 * @version 1.0
	 */
	public function watch() {
		Gmail::watch();
	}

} // End class.
