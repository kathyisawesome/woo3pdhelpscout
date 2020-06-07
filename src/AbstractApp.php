<?php
/**
 * Abstract application class.
 *
 * Copyright (c) 2019, TIV.NET INC. All Rights Reserved.
 */

namespace Woo3pdHelpscout;

/**
 * Class AbstractApp
 */
abstract class AbstractApp {

	/**
	* @constant string donate url
	*/
	const DONATE_URL = '';

	/**
	* @constant string the options page.
	*/
	const OPTION = '';

	/**
	 * `__FILE__` from the {@see configure}.
	 *
	 * @var string
	 */
	public $plugin_file = '';

	/**
	 * Basename from `__FILE__`.
	 *
	 * @var string
	 */
	public $plugin_basename = '';

	/**
	 * Plugin directory URL. Initialized by the {@see configure}.
	 *
	 * @var string
	 */
	protected $plugin_dir_url = '';

	/**
	 * Plugin directory URL - filtered.
	 * Need to use this instead of the class variable because 3rd party extensions (Polylang, etc.) might filter it.
	 *
	 * @return string
	 */
	public function plugin_dir_url() {
		return \plugin_dir_url( $this->plugin_file );
	}

	/**
	 * Plugin directory path. Initialized by the {@see configure}.
	 *
	 * @var string
	 */
	protected $plugin_dir_path;

	/**
	 * Plugin directory path.
	 *
	 * @return string
	 */
	public function plugin_dir_path() {
		return $this->plugin_dir_path;
	}

	/**
	 * Used in the `load_textdomain` call.
	 *
	 * @var string
	 */
	protected $textdomain = '';

	/**
	 * Whether to use minimized or full versions of JS.
	 *
	 * @var string
	 */
	protected $ext_js = '.min.js';

	/**
	 * Getter for $script_suffix.
	 *
	 * @return string
	 */
	public function getExtJS() {
		return $this->ext_js;
	}

	/**
	 * Setup important WP variables.
	 *
	 * @param string $plugin_file The full path to the plugin's main file.
	 *
	 * @return $this (fluent)
	 */
	public function configure( $plugin_file ) {

		$this->plugin_file     = $plugin_file;
		$this->plugin_basename = \plugin_basename( $this->plugin_file );
		$this->plugin_dir_url  = \plugin_dir_url( $this->plugin_file );
		$this->plugin_dir_path = \plugin_dir_path( $this->plugin_file );

		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			$this->ext_js = '.js';
		}

		return $this;
	}

	/**
	 * Load PO/MO.
	 * $this->textdomain must be set by the child class' constructor.
	 */
	public function load_translations() {
		if ( $this->textdomain ) {
			\load_plugin_textdomain(
				$this->textdomain,
				false,
				dirname( $this->plugin_basename ) . '/languages'
			);
		}
	}

	/**
	 * Access to the lazy-loaded instance.
	 *
	 * @return AbstractApp
	 */
	public static function instance() {
		static $o;

		return $o ? $o : $o = new static();
	}


	/*
	-----------------------------------------------------------------------------------*/
	/*
	 Helper Functions */
	/*-----------------------------------------------------------------------------------*/

	/**
	 * Get settings from the DB.
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
			$this->settings = array_merge( $this->get_default_settings(), get_option( static::OPTION, array() ) );
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
			$this->settings = array_merge( $this->get_settings(), $new_settings );
			$result         = update_option( static::OPTION, $this->settings );
		}

		return $result;

	}

	/**
	 * Log.
	 *
	 * @param  $message
	 * @param  $type
	 */
	public function log( $message, $type = 'error' ) {

		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->log( $type, $message, 'Woo3pdHelpscout' );
		} else {
			error_log( $type . ' : ' . $message );
		}
	}
}
