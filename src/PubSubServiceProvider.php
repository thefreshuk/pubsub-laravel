<?php declare(strict_types = 1);

namespace TheFresh\PubSub;

use Illuminate\Support\ServiceProvider;
use TheFresh\Clients\AwsSnsClient;

class PubSubServiceProvider extends ServiceProvider
{
    public const CLIENT_INTERFACE = 'TheFresh\PubSub\Clients\ClientInterface';

    public function register(): void
    {
        $this->registerClient();
        $this->registerTopic();
    }

    public function registerClient(): void
    {
        $this->app->bind(static::CLIENT_INTERFACE, function ($app) {
            $provider = config('pubsub.provider');

            if ($provider === 'sns') {
                $client = $app->make('aws')->createClient('sns');
                return new AwsSnsClient($client);
            }
        });
    }

    public function registerTopic(): void
    {
        $this->app->bind(Topic::class, function ($app) {
            $topic = config('pubsub.topic');
            $client = $app->make(static::CLIENT_INTERFACE);
            return new Topic($client, $topic);
        });
    }
}
