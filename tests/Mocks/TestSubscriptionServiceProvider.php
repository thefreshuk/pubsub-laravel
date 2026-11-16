<?php

namespace Tests\Mocks;

use TheFresh\PubSub\SubscriptionServiceProvider;

class TestSubscriptionServiceProvider extends SubscriptionServiceProvider
{
    protected $namespace = 'Tests\Mocks';

    protected $subscribe = [
        'test_type1' => ['/subscriptions/test-type', 'RecordingController@onTestType'],
        'test_type2' => ['/subscriptions/other-type', 'RecordingController@onTestType'],
    ];
}
