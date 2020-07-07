<?php

namespace JouwWeb\TokenBucket\Storage;

use malkusch\lock\mutex\Mutex;

/**
 * The backing storage container for tokens.
 */
interface Storage
{
    /**
     * @return Mutex
     */
    public function getMutex();
    
    /**
     * Returns if the storage was already bootstrapped.
     *
     * @return bool
     * @throws StorageException Checking the state of the storage failed.
     */
    public function isBootstrapped();
    
    /**
     * Bootstraps the storage.
     *
     * @param double $microtime
     * @throws StorageException Bootstrapping failed.
     */
    public function bootstrap($microtime);
    
    /**
     * Removes the storage. After storage removal you should not use the object anymore. Only {@see isBootstrapped()}
     * and {@see bootstrap()} are safe to use after calling this method.
     *
     * @throws StorageException Cleaning failed.
     */
    public function remove();
    
    /**
     * @param double $microtime
     * @throws StorageException Writing to the storage failed.
     */
    public function setMicrotime($microtime);

    /**
     * Indicates that there won't be any change within this transaction.
     */
    public function letMicrotimeUnchanged();

    /**
     * Returns the stored timestamp.
     *
     * @return float
     * @throws StorageException Reading from the storage failed.
     */
    public function getMicrotime();
}
