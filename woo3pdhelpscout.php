<?php
/**
 * Plugin Name: Woo3pd Helpscout Integration
 * Plugin URI: http://www.kathyisawesome.com/
 * Description: Parse WooCommerce.com support emails into HelpScout conversations
 * Version: 1.2.0
 * Author: Kathy Darling
 * Author URI: http://www.kathyisawesome.com
 * License: GPL3
 * Text Domain: woo3pdhelpscout
 *
 * Requires at least: 6.6
 * Requires PHP: 8.0
 *
 * Update URI: https://www.backcourt.io
 * 
 * Copyright 2024  Kathy Darling
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// PHP version check.
if ( ! function_exists( 'phpversion' ) || version_compare( phpversion(), '8.0', '<' ) ) {
	require_once 'src/Admin/Notices.php';
	add_action( 'admin_notices', array( '\Woo3pdHelpscout\Admin\Notices', 'php_failure_notice' ) );
	return false;
}

/**
 * Add Git Updater Lite
 */
if ( file_exists( __DIR__ . '/vendor-prefixed/autoload.php' ) ) {
	require_once __DIR__ . '/vendor-prefixed/autoload.php';
	( new Woo3pdHelpscout\Vendor\Fragen\Git_Updater\Lite( __FILE__ ) )->run();
}

// Continue with the loader.
/* @noinspection dirnameCallOnFileConstantInspection */
require_once __DIR__ . '/src/loader.php';
