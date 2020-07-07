<?php

namespace bandwidthThrottle\tokenBucket\storage\scope;

use bandwidthThrottle\tokenBucket\storage\Storage;

/**
 * Marker interface for the global scope, meaning a {@see Storage} with this scope will be reused between all processes.
 */
interface GlobalScope
{

}
