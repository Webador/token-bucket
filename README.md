# Token Bucket

This is a fork of [bandwidth-throttle/token-bucket](https://github.com/bandwidth-throttle/token-bucket) originally created to add support for zend-cache (now laminas-cache).

This is a threadsafe implementation of the [Token Bucket algorithm](https://en.wikipedia.org/wiki/Token_bucket) in PHP.
You can use a token bucket to limit an usage rate for a resource. (E.g., a stream bandwidth or an API usage.)

The token bucket is an abstract metaphor and can be applied in throttling both consumption and production of resources.
E.g., you can limit the consumption rate of a third party API, or you can limit the rate at which others can use yours.

# Installation
Use [Composer](https://getcomposer.org/):

```sh
composer require jouwweb/token-bucket
```

## Example
This example will limit the rate of a global resource to 10 requests per second for all requests.

```php
use JouwWeb\TokenBucket\Rate;
use JouwWeb\TokenBucket\TokenBucket;
use JouwWeb\TokenBucket\storage\FileStorage;

$storage = new FileStorage(__DIR__ . "/api.bucket");
$rate = new Rate(10, Rate::SECOND);
$bucket = new TokenBucket(10, $rate, $storage);
$bucket->bootstrap(10);

if (!$bucket->consume(1, $seconds)) {
    http_response_code(429);
    header(sprintf("Retry-After: %d", floor($seconds)));
    exit();
}

echo "API response";
```

Note: In this example `TokenBucket::bootstrap()` is part of the code.
This is not recommended for production, as this is producing unnecessary storage communication.
`TokenBucket::bootstrap()` should be part of the application's bootstrap or deploy process to provide an intial amount of available tokens.

## BlockingConsumer
In the example we either served the request or failed with the HTTP status 429.
This is a very resource efficient way of throttling requests, but sometimes it is desirable to not fail but instead wait untill the request can pass.
You can achieve this by consuming the token bucket with an instance of `BlockingConsumer`:

```php
use JouwWeb\TokenBucket\BlockingConsumer;
/** @var \JouwWeb\TokenBucket\TokenBucket $bucket */

$consumer = new BlockingConsumer($bucket);

// This will block until one token is available.
$consumer->consume(1);

echo "API response";
```

Adding this to the previous example will effectively limit the rate to 10 requests per seconds all the same.
However, the client does not have to handle the 429 error and instead has to sometimes wait a bit longer.
