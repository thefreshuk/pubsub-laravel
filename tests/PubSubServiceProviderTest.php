<?php

namespace Tests;

use Aws\Sns\Exception\InvalidSnsMessageException;
use Aws\Sns\Message as SnsMessage;
use Aws\Sns\MessageValidator;
use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;
use TheFresh\PubSub\Clients\AwsSnsClient;
use TheFresh\PubSub\Clients\ClientInterface;
use TheFresh\PubSub\Console\PubSubSubscribeCommand;
use TheFresh\PubSub\Middleware\AwsSnsMiddleware;
use TheFresh\PubSub\Middleware\Middleware;
use TheFresh\PubSub\PubSubServiceProvider;
use TheFresh\PubSub\Topic;
use TheFresh\PubSub\TopicInterface;

class PubSubServiceProviderTest extends TestCase
{
    public function testMergesDefaultConfig(): void
    {
        $this->assertSame('sns', config('pubsub.provider'));
        $this->assertArrayNotHasKey('allowHttp', config('pubsub')); // removed in https://github.com/thefreshuk/pubsub-laravel/issues/14
        $this->assertArrayHasKey('hostPattern', config('pubsub'));
    }

    public function testBindsClientInterfaceToAwsSnsClient(): void
    {
        $this->assertInstanceOf(AwsSnsClient::class, $this->app->make(PubSubServiceProvider::CLIENT_INTERFACE));
        $this->assertSame(ClientInterface::class, PubSubServiceProvider::CLIENT_INTERFACE);
    }

    public function testBindsMiddlewareToAwsSnsMiddleware(): void
    {
        $this->assertInstanceOf(AwsSnsMiddleware::class, $this->app->make(Middleware::class));
    }

    public function testBindsMessageValidatorAsNewInstanceEachTime(): void
    {
        $this->assertInstanceOf(MessageValidator::class, $this->app->make(MessageValidator::class));
        $this->assertNotSame($this->app->make(MessageValidator::class), $this->app->make(MessageValidator::class));
    }

    public function testBindsTopicUsingConfiguredTopicName(): void
    {
        $topic = $this->app->make(Topic::class);

        $this->assertInstanceOf(Topic::class, $topic);
        $this->assertSame('arn:aws:sns:eu-west-2:000000000000:test', (new \ReflectionProperty($topic, 'name'))->getValue($topic));
    }

    public function testSubscribeCommandReceivesDefaultTopic(): void
    {
        $command = $this->app->make(PubSubSubscribeCommand::class);

        $this->assertInstanceOf(PubSubSubscribeCommand::class, $command);
        $this->assertInstanceOf(TopicInterface::class, $this->app->make(Topic::class));
    }

    public function testRegistersConsoleCommands(): void
    {
        $commands = array_keys(Artisan::all());

        $this->assertContains('make:message', $commands);
        $this->assertContains('pubsub:subscribe', $commands);
    }

    public function testPublishesConfig(): void
    {
        $paths = PubSubServiceProvider::pathsToPublish(PubSubServiceProvider::class);

        $this->assertArrayHasKey(PubSubServiceProvider::CONFIG_PATH, $paths);
    }

    public function testUnsupportedProviderFailsFast(): void
    {
        // Regression test for https://github.com/thefreshuk/pubsub-laravel/issues/16.
        config(['pubsub.provider' => 'other']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported PubSub provider [other]');

        (new PubSubServiceProvider($this->app))->registerClient();
    }

    public function testEmptyProviderFallsBackToSns(): void
    {
        config(['pubsub.provider' => '']);
        unset($this->app[Middleware::class]);

        (new PubSubServiceProvider($this->app))->registerClient();

        $this->assertTrue($this->app->bound(Middleware::class));
    }

    private function unsignedMessage(): SnsMessage
    {
        return new SnsMessage([
            'Type' => 'Notification',
            'MessageId' => 'a1',
            'TopicArn' => 'arn',
            'Message' => '{}',
            'Timestamp' => '2026-01-01T00:00:00.000Z',
            'SignatureVersion' => '2',
            'Signature' => base64_encode('not a real signature'),
            'SigningCertURL' => self::CERT_URL,
        ]);
    }

    public function testValidationIsEnforcedWhenEnvIsNotLocal(): void
    {
        config(['app.env' => 'production']);
        $this->app['env'] = 'production';

        $this->expectException(InvalidSnsMessageException::class);
        $this->app->make(MessageValidator::class)->validate($this->unsignedMessage());
    }

    public function testValidationIsSkippedWhenEnvIsLocal(): void
    {
        // TODO(known risk) https://github.com/thefreshuk/pubsub-laravel/issues/13: signature validation is bypassed entirely whenever
        // app.env === 'local'. Should be an explicit opt-in flag.
        config(['app.env' => 'local']);

        $validator = $this->app->make(MessageValidator::class);
        $message = $this->unsignedMessage();

        $validator->validate($message);
        $this->assertTrue($validator->isValid($message));
    }
}
