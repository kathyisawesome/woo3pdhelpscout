<?php

namespace Woo3pdHelpscout;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Short-circuit special WP calls.
 *
 * @since 1.0.0
 */
if (
	// xmlrpc.php:13
	defined( 'XMLRPC_REQUEST' )
	|| (
		! empty( $_SERVER['REQUEST_URI'] )
		&& preg_match( '/\/wp-(login|signup|trackback)\.php/i', $_SERVER['REQUEST_URI'] ) )
) {
	return;
}

// The autoloader for the plugin's own classes. Third-party packages are namespace-prefixed
// by Strauss into /vendor-prefixed and loaded in the main plugin file.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

/**
 * Initialize the Application.
 *
 * @note We need to pass the full path of the plugin's main file.
 *       To avoid having yet another global variable or constant,
 *       We build it here, assuming that it's one folder up and the name is known.
 */
App::instance()
	->configure( dirname( __DIR__ ) . '/woo3pdhelpscout.php' )
	->setup_hooks();
