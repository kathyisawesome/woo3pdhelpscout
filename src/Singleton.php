<?php
namespace Woo3pdHelpscout;

/**
 * Singleton trait to handle getting instances of classes.
 * 
 * @since 1.1.0
 * @version 1.1.1
 */

trait Singleton
{
    protected static $instances = array();
    final public static function instance()
    {
        return static::$instances[ static::class ] ??= new static;
    }
    final public function __construct() {
        $this->init();
    }
    protected function init() {}
    /**
	 * Cloning is forbidden.
	 */
	final public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning this object is forbidden.', 'woo3pdHelpscout' ) );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 */
	final public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Unserializing instances of this class is forbidden.', 'woo3pdHelpscout' ) );
	}
}