<?php

namespace JouwWeb\TokenBucket\Storage;

use malkusch\lock\mutex\Mutex;
use malkusch\lock\mutex\NoMutex;
use JouwWeb\TokenBucket\Storage\Scope\SessionScope;

/**
 * Session-based storage which is shared for one user across all of their requests.
 */
final class SessionStorage implements Storage, SessionScope
{
    const SESSION_NAMESPACE = "TokenBucket_";

    /** @var Mutex The mutex. */
    private $mutex;
 
    /** @var string The session key for this bucket. */
    private $key;
    
    /**
     * Sets the bucket's name.
     *
     * @param string $name
     */
    public function __construct($name)
    {
        $this->mutex = new NoMutex();
        $this->key   = self::SESSION_NAMESPACE . $name;
    }

    public function getMutex()
    {
        return $this->mutex;
    }
    
    public function bootstrap($microtime)
    {
        $this->setMicrotime($microtime);
    }

    public function getMicrotime()
    {
        return $_SESSION[$this->key];
    }

    public function isBootstrapped()
    {
        return isset($_SESSION[$this->key]);
    }

    public function remove()
    {
        unset($_SESSION[$this->key]);
    }

    public function setMicrotime($microtime)
    {
        $_SESSION[$this->key] = $microtime;
    }

    public function letMicrotimeUnchanged()
    {
    }
}
