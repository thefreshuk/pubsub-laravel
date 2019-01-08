<?php

declare(strict_types = 1);

namespace TheFresh\PubSub;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class SubscriptionServiceProvider extends ServiceProvider
{
    /**
     * @var array $subscribe Mapping of types to routes and controllers
     */
    protected $subscribe = [];

    /**
     * Maps the types of message to routes and controllers.
     */
    public function map()
    {
        $router = $this->app->make('router');

        $router->namespace($this->namespace)
            ->middleware(Middleware\Middleware::class)
            ->group(function () use ($router) {
                foreach ($this->subscribe as $type => [$route, $action]) {
                    $router->post($route, $action);
                }
            });
    }

    /**
     * Provides the subscription map.
     *
     * @return array The map of subscribes
     */
    public function subscribes(): array
    {
        return $this->subscribe;
    }
}
