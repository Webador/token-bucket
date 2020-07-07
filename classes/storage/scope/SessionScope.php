<?php

namespace bandwidthThrottle\tokenBucket\storage\scope;

/**
 * Marker interface for the session scope, meaning a {@see Storage} with this scope will be reused for all of the
 * session's requests.
 */
interface SessionScope
{

}
