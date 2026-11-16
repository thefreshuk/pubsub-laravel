<?php

namespace Tests\Mocks;

use TheFresh\PubSub\Middleware\AwsSnsMiddleware;

/**
 * Records confirmation URLs instead of sending a real HTTP request.
 */
class RecordingMiddleware extends AwsSnsMiddleware
{
    public static array $confirmed = [];

    protected function confirmSubscription(string $url): void
    {
        static::$confirmed[] = $url;
    }
}
