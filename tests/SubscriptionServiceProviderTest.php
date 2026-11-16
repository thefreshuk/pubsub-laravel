<?php

namespace Tests;

use Tests\Mocks\TestSubscriptionServiceProvider;
use TheFresh\PubSub\Middleware\Middleware;
use TheFresh\PubSub\SubscriptionServiceProvider;

class SubscriptionServiceProviderTest extends TestCase
{
    public function testRegistersAPostRoutePerSubscription(): void
    {
        $routes = $this->app['router']->getRoutes();

        foreach (['subscriptions/test-type', 'subscriptions/other-type'] as $uri) {
            $route = $routes->match($this->app['request']->create("/{$uri}", 'POST'));
            $this->assertSame(['POST'], $route->methods());
            $this->assertSame('Tests\Mocks\RecordingController@onTestType', $route->getActionName());
        }
    }

    public function testRoutesUseThePubSubMiddleware(): void
    {
        $route = $this->app['router']->getRoutes()
            ->match($this->app['request']->create('/subscriptions/test-type', 'POST'));

        $this->assertContains(Middleware::class, $route->gatherMiddleware());
    }

    public function testOnlyPostIsAllowed(): void
    {
        $this->get('/subscriptions/test-type')->assertStatus(405);
    }

    public function testSubscribesReturnsTheMap(): void
    {
        $provider = new TestSubscriptionServiceProvider($this->app);

        $this->assertSame(['test_type1', 'test_type2'], array_keys($provider->subscribes()));
        $this->assertSame([], (new SubscriptionServiceProvider($this->app))->subscribes());
    }

    public function testNoRoutesWhenNothingSubscribed(): void
    {
        $before = count($this->app['router']->getRoutes());
        (new SubscriptionServiceProvider($this->app))->map();

        $this->assertCount($before, $this->app['router']->getRoutes());
    }
}
