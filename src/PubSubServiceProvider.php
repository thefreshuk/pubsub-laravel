<?php declare(strict_types = 1);

namespace TheFresh\PubSub;

use Illuminate\Support\ServiceProvider;
use TheFresh\PubSub\Clients\AwsSnsClient;
use TheFresh\PubSub\Console\MakeMessageCommand;

class PubSubServiceProvider extends ServiceProvider
{
    public const CONFIG_PATH = __DIR__ . '/../config/pubsub.php';
    public const CLIENT_INTERFACE = 'TheFresh\PubSub\Clients\ClientInterface';

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeMessageCommand::class
            ]);
        }

        $this->publishes([
            static::CONFIG_PATH => config_path('pubsub.php')
        ]);
    }

    public function register(): void
    {
        $this->provideDefaultConfig();
        $this->registerClient();
        $this->registerTopic();
    }

    public function provideDefaultConfig()
    {
        $this->mergeConfigFrom(static::CONFIG_PATH, 'pubsub');
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
