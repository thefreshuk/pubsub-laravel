<?php

declare(strict_types = 1);

namespace TheFresh\PubSub\Console;

use Illuminate\Console\Command;
use TheFresh\PubSub\TopicInterface;
use TheFresh\PubSub\SubscriptionServiceProvider;

class PubSubSubscribeCommand extends Command
{
    private $topic;

    protected $name = 'pubsub:subscribe';

    protected $description = 'Subscribes to the PubSub topics';

    public function __construct(TopicInterface $topic)
    {
        $this->topic = $topic;
    }

    public function handle()
    {
        $providers = $this->laravel->getProviders(
            SubscriptionServiceProvider::class
        );

        foreach ($providers as $provider) {
            $subscribes = $provider->subscribes();
            foreach ($subscribes as $type => [$route, $action]) {
                $this->topic->subscribe($type, url($route));
            }
        }
    }
}
