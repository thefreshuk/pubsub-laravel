<?php

namespace Tests;

use Aws\Sns\Exception\InvalidSnsMessageException;
use Aws\Sns\MessageValidator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Mockery as m;
use TheFresh\PubSub\Middleware\AwsSnsMiddleware;
use Tests\Mocks\RecordingController;
use Tests\Mocks\RecordingMiddleware;
use TheFresh\PubSub\Messages\DynamicMessage;
use TheFresh\PubSub\Middleware\Middleware as PubSubMiddleware;

/**
 * Pins the current behaviour of AwsSnsMiddleware through the real routes
 * registered by SubscriptionServiceProvider. Known bugs are asserted as they
 * behave today and marked TODO so the fix PRs flip them.
 */
class AwsSnsMiddlewareTest extends TestCase
{
    private const URI = '/subscriptions/test-type';

    protected function setUp(): void
    {
        parent::setUp();

        RecordingController::$received = [];
        RecordingMiddleware::$confirmed = [];
    }

    /**
     * Posts $body and asserts the request blows up with $exception (instead of
     * returning an HTTP error response). Used to pin known bugs.
     */
    private function assertPostThrows(string $exception, array|string $body): void
    {
        $this->withoutExceptionHandling();

        try {
            $this->postRaw(self::URI, is_array($body) ? json_encode($body) : $body);
        } catch (\Throwable $e) {
            $this->assertInstanceOf($exception, $e);
            $this->assertSame([], RecordingController::$received);

            return;
        }

        $this->fail("Expected {$exception} to be thrown");
    }

    public function testValidMessageReachesControllerWithDynamicMessage(): void
    {
        [$private, $public] = $this->makeKeyPair();
        $this->trustPublicKey($public);

        $this->postRaw(self::URI, json_encode($this->signedNotification($private, ['user' => 'abc'])))
            ->assertOk();

        $this->assertCount(1, RecordingController::$received);
        $message = RecordingController::$received[0];
        $this->assertInstanceOf(DynamicMessage::class, $message);
        $this->assertSame('', $message->type());
        $this->assertSame(['user' => 'abc'], $message->content());
    }

    public function testControllerResponseIsDiscarded(): void
    {
        // TODO(known bug) https://github.com/thefreshuk/pubsub-laravel/issues/9: handle() calls $next() without `return`, so the
        // controller's 202 response is dropped and Laravel sends an empty 200.
        [$private, $public] = $this->makeKeyPair();
        $this->trustPublicKey($public);

        $response = $this->postRaw(self::URI, json_encode($this->signedNotification($private, ['a' => 1])));

        $response->assertOk();
        $this->assertSame('', $response->getContent());
        $this->assertCount(1, RecordingController::$received);
    }

    public function testInvalidSignatureIsRejectedAndControllerNotCalled(): void
    {
        // TODO(known bug) https://github.com/thefreshuk/pubsub-laravel/issues/10: validate() throws InvalidSnsMessageException, so the
        // intended 403 / Log::error('[PubSub]...') branch is never reached and
        // the request ends in an unhandled exception (500). Should become 403.
        [$private] = $this->makeKeyPair();
        [, $otherPublic] = $this->makeKeyPair();
        $this->trustPublicKey($otherPublic);

        $this->assertPostThrows(InvalidSnsMessageException::class, $this->signedNotification($private, ['a' => 1]));
    }

    public function testTamperedMessageIsRejected(): void
    {
        [$private, $public] = $this->makeKeyPair();
        $this->trustPublicKey($public);

        $payload = $this->signedNotification($private, ['a' => 1]);
        $payload['Message'] = json_encode(['a' => 'tampered']);

        $this->assertPostThrows(InvalidSnsMessageException::class, $payload); // TODO https://github.com/thefreshuk/pubsub-laravel/issues/10: 403 once fixed
    }

    public function testCertificateOnDisallowedHostIsRejected(): void
    {
        [$private, $public] = $this->makeKeyPair();
        $this->trustPublicKey($public);

        $payload = $this->signedNotification($private, ['a' => 1], ['SigningCertURL' => 'https://evil.example/cert.pem']);

        $this->assertPostThrows(InvalidSnsMessageException::class, $payload); // TODO https://github.com/thefreshuk/pubsub-laravel/issues/10: 403 once fixed
    }

    public function testNotAuthenticBranchReturns403WhenValidatorDoesNotThrow(): void
    {
        // The 403 branch only runs if validate() returns and isValid() is false,
        // which the real validator never does. Documented via a mock.
        $validator = m::mock(MessageValidator::class);
        $validator->shouldReceive('validate')->once();
        $validator->shouldReceive('isValid')->once()->andReturn(false);
        $this->app->instance(MessageValidator::class, $validator);

        [$private] = $this->makeKeyPair();
        $this->postRaw(self::URI, json_encode($this->signedNotification($private, [])))
            ->assertStatus(403);
        $this->assertSame([], RecordingController::$received);
    }

    public function testEmptyBodyCausesTypeError(): void
    {
        // TODO(known bug) https://github.com/thefreshuk/pubsub-laravel/issues/11: json_decode('') is null and verifyMessage(array) throws TypeError. Should be 400.
        $this->assertPostThrows(\TypeError::class, '');
    }

    public function testNonJsonBodyCausesTypeError(): void
    {
        // TODO(known bug) https://github.com/thefreshuk/pubsub-laravel/issues/11: see testEmptyBodyCausesTypeError.
        $this->assertPostThrows(\TypeError::class, 'not json');
    }

    public function testMissingSnsKeysCauseInvalidArgumentException(): void
    {
        // TODO(known bug) https://github.com/thefreshuk/pubsub-laravel/issues/11: new SnsMessage() throws InvalidArgumentException. Should be 400.
        $this->assertPostThrows(\InvalidArgumentException::class, ['Type' => 'Notification']);
    }

    public function testNonJsonInnerMessageCausesTypeError(): void
    {
        // TODO(known bug) https://github.com/thefreshuk/pubsub-laravel/issues/11: json_decode($input['Message']) is null for a non-JSON payload and DynamicMessage requires an array.
        [$private, $public] = $this->makeKeyPair();
        $this->trustPublicKey($public);

        $payload = $this->signedNotification($private, [], ['Message' => 'plain text, not json']);

        $this->assertPostThrows(\TypeError::class, $payload);
    }

    public function testSubscriptionConfirmationIsConfirmedAndNotPassedToController(): void
    {
        [$private, $public] = $this->makeKeyPair();
        $this->trustPublicKey($public);
        $this->app->bind(PubSubMiddleware::class, fn ($app) => new RecordingMiddleware($app->make(MessageValidator::class), null, config('pubsub.hostPattern')));

        $url = 'https://sns.test/confirm?token=abc';
        $response = $this->postRaw(self::URI, json_encode($this->signedSubscriptionConfirmation($private, $url)));

        $response->assertOk();
        $this->assertSame('Subscription confirmed', $response->getContent());
        $this->assertSame([$url], RecordingMiddleware::$confirmed);
        $this->assertSame([], RecordingController::$received);
    }

    public function testSubscriptionConfirmationWithInvalidSignatureIsNotConfirmed(): void
    {
        [$private] = $this->makeKeyPair();
        [, $otherPublic] = $this->makeKeyPair();
        $this->trustPublicKey($otherPublic);
        $this->app->bind(PubSubMiddleware::class, fn ($app) => new RecordingMiddleware($app->make(MessageValidator::class), null, config('pubsub.hostPattern')));

        $this->assertPostThrows(InvalidSnsMessageException::class, $this->signedSubscriptionConfirmation($private, 'https://sns.test/confirm')); // TODO https://github.com/thefreshuk/pubsub-laravel/issues/10: 403 once fixed
        $this->assertSame([], RecordingMiddleware::$confirmed);
    }

    private function bindMiddlewareWithHttp(array $responses, array &$history): void
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($history));

        $this->app->bind(PubSubMiddleware::class, fn ($app) => new AwsSnsMiddleware(
            $app->make(MessageValidator::class),
            new Client(['handler' => $stack]),
            config('pubsub.hostPattern')
        ));
    }

    public function testConfirmationSendsGetToSubscribeUrl(): void
    {
        [$private, $public] = $this->makeKeyPair();
        $this->trustPublicKey($public);
        $history = [];
        $this->bindMiddlewareWithHttp([new Response(200)], $history);

        $url = 'https://sns.test/confirm?token=abc';
        $this->postRaw(self::URI, json_encode($this->signedSubscriptionConfirmation($private, $url)))
            ->assertOk();

        $this->assertCount(1, $history);
        $this->assertSame('GET', $history[0]['request']->getMethod());
        $this->assertSame($url, (string) $history[0]['request']->getUri());
    }

    public function testUntrustedSubscribeUrlIsNotFetched(): void
    {
        [$private, $public] = $this->makeKeyPair();
        $this->trustPublicKey($public);
        $history = [];
        $this->bindMiddlewareWithHttp([new Response(200)], $history);

        foreach (['https://evil.example/confirm', 'http://sns.test/confirm', 'https://sns.test.evil.example/confirm', 'file:///etc/passwd'] as $url) {
            $this->postRaw(self::URI, json_encode($this->signedSubscriptionConfirmation($private, $url)))
                ->assertStatus(400);
        }

        $this->assertSame([], $history);
    }

    public function testFailedConfirmationReturns502(): void
    {
        // Regression test for https://github.com/thefreshuk/pubsub-laravel/issues/12: HTTP errors were previously uncaught.
        [$private, $public] = $this->makeKeyPair();
        $this->trustPublicKey($public);
        $history = [];
        $this->bindMiddlewareWithHttp([new Response(500), new ConnectException('timeout', new Request('GET', 'https://sns.test'))], $history);

        $payload = json_encode($this->signedSubscriptionConfirmation($private, 'https://sns.test/confirm'));

        $this->postRaw(self::URI, $payload)->assertStatus(502);
        $this->postRaw(self::URI, $payload)->assertStatus(502);
    }
}
