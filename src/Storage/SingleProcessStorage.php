<?php

namespace JouwWeb\TokenBucket\Storage;

use malkusch\lock\mutex\NoMutex;
use JouwWeb\TokenBucket\Storage\Scope\RequestScope;

/**
 * In-memory token storage which is only used for one single process.
 */
final class SingleProcessStorage implements Storage, RequestScope
{
    /** @var NoMutex */
    private $mutex;
    
    /** @var float The microtime. */
    private $microtime;

    public function __construct()
    {
        $this->mutex = new NoMutex();
    }
    
    public function isBootstrapped()
    {
        return ! is_null($this->microtime);
    }
    
    public function bootstrap($microtime)
    {
        $this->setMicrotime($microtime);
    }
    
    public function remove()
    {
        $this->microtime = null;
    }

    public function setMicrotime($microtime)
    {
        $this->microtime = $microtime;
    }
    
    public function getMicrotime()
    {
        return $this->microtime;
    }

    public function getMutex()
    {
        return $this->mutex;
    }

    public function letMicrotimeUnchanged()
    {
    }
}
