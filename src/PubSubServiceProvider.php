<?php

declare(strict_types = 1);

namespace TheFresh\PubSub;

use TheFresh\PubSub\Support\ServiceProvider;
use TheFresh\PubSub\Clients\AwsSnsClient;
use TheFresh\PubSub\Clients\RelaxedSnsMessage;
use TheFresh\PubSub\Console\MessageMakeCommand;
use TheFresh\PubSub\Console\PubSubSubscribeCommand;
use TheFresh\PubSub\Middleware\Middleware;
use TheFresh\PubSub\Middleware\AwsSnsMiddleware;
use Aws\Sns\Message as SnsMessage;
use Aws\Sns\MessageValidator;

class PubSubServiceProvider extends ServiceProvider
{
    public const CONFIG_PATH = __DIR__ . '/../config/pubsub.php';

    public const CLIENT_INTERFACE = 'TheFresh\PubSub\Clients\ClientInterface';

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MessageMakeCommand::class,
                PubSubSubscribeCommand::class
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

    /**
     * Merges the default config with the user's own pubsub
     * configuration.
     */
    public function provideDefaultConfig(): void
    {
        $this->mergeConfigFrom(static::CONFIG_PATH, 'pubsub');
    }

    /**
     * Registers the PubSub client to use. Right now, only
     * SNS is supported.
     */
    public function registerClient(): void
    {
        $provider = config('pubsub.provider');
        switch ($provider) {
            case 'sns':
                $this->registerSns();
        }
    }

    /**
     * Registers everything needed to publish and subscribe
     * to SNS messages.
     */
    public function registerSns()
    {
        $this->app->bind(static::CLIENT_INTERFACE, function ($app) {
            $client = $app->make('aws')->createClient('sns');
            return new AwsSnsClient($client);
        });

        $this->app->bind(MessageValidator::class, function ($app) {
            $hostPattern = config('pubsub.hostPattern');
            $bypassValidation = config('app.env') === 'local';

            return new MessageValidator(null, $hostPattern, $bypassValidation);
        });

        $this->app->bind(Middleware::class, function ($app) {
            return new AwsSnsMiddleware(
                $app->make(MessageValidator::class)
            );
        });
    }

    /**
     * Registers the default Topic. We use content-based
     * PubSub so we only need one topic right now.
     */
    public function registerTopic(): void
    {
        $this->app->bind(Topic::class, function ($app) {
            $topic = config('pubsub.topic');
            $client = $app->make(static::CLIENT_INTERFACE);
            return new Topic($client, $topic);
        });

        $this->app->when(PubSubSubscribeCommand::class)
            ->needs('TheFresh\PubSub\TopicInterface')
            ->give(Topic::class);
    }
}
