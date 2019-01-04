<?php

namespace TheFresh\PubSub\Middleware;

use Closure;

abstract class Middleware
{
    abstract public function handle($request, Closure $next);
}
