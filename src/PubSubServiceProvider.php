<?php

declare(strict_types = 1);

namespace TheFresh\PubSub;

use Illuminate\Support\ServiceProvider;
use TheFresh\PubSub\Clients\AwsSnsClient;
use TheFresh\PubSub\Clients\RelaxedSnsMessage;
use TheFresh\PubSub\Console\MessageMakeCommand;
use TheFresh\PubSub\Console\PubSubSubscribeCommand;
use TheFresh\PubSub\Middleware\Middleware;
use TheFresh\PubSub\Middleware\AwsSnsMiddleware;
use Aws\Sns\Message as SnsMessage;
use Aws\Sns\MessageValidator;
use InvalidArgumentException;

class PubSubServiceProvider extends ServiceProvider
{
    public const CONFIG_PATH = __DIR__ . '/../config/pubsub.php';

    /** Emit the local-bypass deprecation once per process, not per request resolution. */
    protected static bool $deprecationEmitted = false;

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
        // An empty value (e.g. `PUBSUB_PROVIDER=`) falls back to the default.
        $provider = config('pubsub.provider') ?: 'sns';
        switch ($provider) {
            case 'sns':
                $this->registerSns();
                break;
            default:
                throw new InvalidArgumentException(
                    "Unsupported PubSub provider [{$provider}]. Supported providers: sns."
                );
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
            $bypassValidation = $this->shouldBypassValidation();

            return new MessageValidator(null, $hostPattern, $bypassValidation);
        });

        $this->app->bind(Middleware::class, function ($app) {
            return new AwsSnsMiddleware(
                $app->make(MessageValidator::class),
                null,
                config('pubsub.hostPattern')
            );
        });
    }

    /**
     * Whether SNS signature validation should be skipped.
     *
     * An explicit `pubsub.bypassValidation` setting always wins. When it is
     * unset (null) we fall back to the legacy behaviour of bypassing when
     * app.env is 'local', which is deprecated and will be removed in the next
     * major version.
     */
    protected function shouldBypassValidation(): bool
    {
        $configured = config('pubsub.bypassValidation');

        if ($configured !== null) {
            $bypass = filter_var($configured, FILTER_VALIDATE_BOOLEAN);

            if ($bypass && config('app.env') === 'production') {
                // Never allow unsigned messages in production, even if the flag leaks in.
                logger()->critical('[PubSub] PUBSUB_BYPASS_VALIDATION is ignored when APP_ENV=production; signatures are enforced.');

                return false;
            }

            return $bypass;
        }

        if (config('app.env') === 'local') {
            static::$deprecationEmitted || trigger_error(
                'thefreshuk/pubsub-laravel: skipping SNS signature validation because APP_ENV=local is deprecated. '
                . 'Set PUBSUB_BYPASS_VALIDATION=true to keep this behaviour, or =false to validate signatures.',
                E_USER_DEPRECATED
            );
            static::$deprecationEmitted = true;

            return true;
        }

        return false;
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
