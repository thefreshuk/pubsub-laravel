<?php

namespace TheFresh\PubSub\Middleware;

use Closure;

class AwsSnsMiddleware extends Middleware
{
    public function handle($request, Closure $next)
    {

        $next($request);
    }
}
