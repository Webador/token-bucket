<?php

namespace JouwWeb\TokenBucket\Storage\Scope;

/**
 * Marker interface for the request scope, meaning a {@see Storage} with this scope will not be reused between
 * processes.
 */
interface RequestScope
{

}
