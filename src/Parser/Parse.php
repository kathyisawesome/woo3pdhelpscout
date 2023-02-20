<?php
/**
 * Parse the Email from WooCommerce.com's form.
 *
 * @package woo3pdHelpscout
 */

namespace Woo3pdHelpscout\Parser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Woo3pdHelpscout\App;
use Woo3pdHelpscout\Api\Abstracts\AbstractAPI;
use Woo3pdHelpscout\Exceptions\QuietException;

/**
 * Parsing class.
 */
class Parse extends AbstractAPI {

	/**
	 * Parse email into data.
	 *
	 * @param  string $html The html from the first conversation's thread, which is the email from Woo.
	 * @return  array
	 */
	public function parse_woo_email( $html ) {

		$html = stripslashes( $html );

		if ( ! $html ) {
			throw new \Exception( 'Empty message' );
		}

		$html_loaded = false;

		libxml_use_internal_errors( true );
		$html_document = new \DOMDocument();
		$html_loaded   = $html_document->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
		libxml_clear_errors();

		if ( ! $html_loaded ) {
			throw new \Exception( 'Could not parse message content' );
		}

		$product_name_node         = $html_document->getElementById( 'product-name' );
		$customer_email_node       = $html_document->getElementById( 'customer-email' );
		$customer_name_node        = $html_document->getElementById( 'customer-name' );
		$website_node              = $html_document->getElementById( 'ticket-web-site' );
		$description_node          = $html_document->getElementById( 'ticket-description' );
		$subscription_started_node = $html_document->getElementById( 'support-subscription-started' );
		$subscription_ends_node    = $html_document->getElementById( 'support-subscription-ends' );
		$site_status_node          = $html_document->getElementById( 'site-status-report' );

		$ticket_data = array(
			'product_name'         => $product_name_node ? $product_name_node->textContent : '',
			'product_tag'          => $product_name_node ? $this->sanitize_product_name( $product_name_node->textContent ) : '',
			'customer'             => array(
				'full_name'  => $customer_name_node ? $customer_name_node->textContent : '',
				'first_name' => '',
				'last_name'  => '',
				'email'      => $customer_email_node ? $customer_email_node->textContent : '',
			),
			'subscription_started' => $subscription_started_node ? $subscription_started_node->textContent : '',
			'subscription_ends'    => $subscription_ends_node ? $subscription_ends_node->textContent : '',
			'website'              => $website_node ? esc_url_raw( $website_node->textContent ) : '',
			'description'          => $description_node ? str_replace( array( '<dd id="ticket-description" style="padding-bottom: 1em;">', '</dd>' ), '', $html_document->saveHTML( $description_node ) ) : '',
			'status'               => $site_status_node ? trim( str_replace( '`', '', strip_tags( $html_document->saveHTML( $site_status_node ), '<br>' ) ) ) : '',
			'wc_version'           => '',
			'php_version'          => '',
			'version'              => '',
			'connected'            => '',
			'wpdotcom'             => '',
		);

		// Stash full name as it's own key for custom fields.
		$ticket_data['customer_name'] = $ticket_data['customer']['full_name'];

		if ( empty( $ticket_data['customer']['full_name'] ) ) {
			$ticket_data['customer']['full_name'] = 'Undefined';
		}

		$name = explode( ' ', $ticket_data['customer']['full_name'] );

		$ticket_data['customer']['first_name'] = $name[0];
		if ( 2 === sizeof( $name ) ) {
			$ticket_data['customer']['last_name'] = $name[1];
		}

		// WooCommerce plugin version.
		if ( preg_match( '/WC Version: ([A-Za-z0-9\.\-]+)/i', $ticket_data['status'], $wc_version_matches ) ) {
			$ticket_data['wc_version'] = strip_tags( $wc_version_matches[1] );
		}

		// PHP version.
		if ( preg_match( '/PHP Version: ([A-Za-z0-9\.\-]+)/i', $ticket_data['status'], $php_version_matches ) ) {
			$ticket_data['php_version'] = strip_tags( $php_version_matches[1] );
		}

		// Plugin version.
		if ( preg_match( '/' . $ticket_data['product_name'] . ': (.*?(?=\d))([A-Za-z0-9\.\-]+)/i', $ticket_data['status'], $plugin_version_matches ) ) {
			$ticket_data['version'] = strip_tags( $plugin_version_matches[2] );
		}

		// Connected to WooCommerce.com. Check for unicode character
		if ( preg_match( '/Connected to WooCommerce.com: (.+?)/iu', $ticket_data['status'], $connected_matches ) ) {
			$ticket_data['connected'] = '✔' === $connected_matches[1] ? __( 'Yes', 'woo3pdhelpscout' ) : __( 'No', 'woo3pdhelpscout' );
		}

		// Hosted at WordPress.com
		if ( preg_match( '/WP\.com Site Helper/iu', $ticket_data['status'], $dotcommatches ) ) {
			$ticket_data['wpdotcom'] = ! empty( $dotcommatches ) ? __( 'Yes', 'woo3pdhelpscout' ) : __( 'No', 'woo3pdhelpscout' );
		}

		return $ticket_data;

	}

	/**
	 * Makes all letters lowercase and replaces whitespaces with dashes.
	 *
	 * @param  string  $product_name
	 * @return string  $product_name
	 */
	private function sanitize_product_name( $product_name ) {
		$product_name = strtolower( $product_name );
		$product_name = preg_replace( '/[\s_]/', '-', $product_name );
		$product_name = str_replace( 'woocommerce-', '', $product_name );
		return $product_name;
	}

}
