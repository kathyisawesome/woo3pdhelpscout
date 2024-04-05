<?php
namespace Woo3pdHelpscout;

/**
 * Singleton trait to handle getting instances of classes.
 */

trait Singleton
{
    protected static $instance;
    final public static function instance()
    {
        return isset(static::$instance)
            ? static::$instance
            : static::$instance = new static;
    }
    final public function __construct() {
        $this->init();
    }
    protected function init() {}
    final public function __wakeup() {}
    final public function __clone() {}    
}