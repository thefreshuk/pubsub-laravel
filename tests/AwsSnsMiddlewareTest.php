<?php

namespace Tests;

use Aws\Sns\Exception\InvalidSnsMessageException;
use Aws\Sns\MessageValidator;
use Mockery as m;
use Tests\Mocks\RecordingController;
use Tests\Mocks\RecordingMiddleware;
use TheFresh\PubSub\Messages\DynamicMessage;
use TheFresh\PubSub\Middleware\Middleware;

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

    private function assertMalformed(array|string $body): void
    {
        $this->postRaw(self::URI, is_array($body) ? json_encode($body) : $body)->assertStatus(400);
        $this->assertSame([], RecordingController::$received);
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

    public function testEmptyBodyIsRejectedWith400(): void
    {
        // Regression test for https://github.com/thefreshuk/pubsub-laravel/issues/11.
        $this->assertMalformed('');
    }

    public function testNonJsonBodyIsRejectedWith400(): void
    {
        $this->assertMalformed('not json');
    }

    public function testMissingSnsKeysAreRejectedWith400(): void
    {
        $this->assertMalformed(['Type' => 'Notification']);
    }

    public function testNonJsonInnerMessageIsRejectedWith400(): void
    {
        [$private, $public] = $this->makeKeyPair();
        $this->trustPublicKey($public);

        $payload = $this->signedNotification($private, [], ['Message' => 'plain text, not json']);

        $this->assertMalformed($payload);
    }

    public function testSubscriptionConfirmationIsConfirmedAndNotPassedToController(): void
    {
        [$private, $public] = $this->makeKeyPair();
        $this->trustPublicKey($public);
        $this->app->bind(Middleware::class, fn ($app) => new RecordingMiddleware($app->make(MessageValidator::class)));

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
        $this->app->bind(Middleware::class, fn ($app) => new RecordingMiddleware($app->make(MessageValidator::class)));

        $this->assertPostThrows(InvalidSnsMessageException::class, $this->signedSubscriptionConfirmation($private, 'https://sns.test/confirm')); // TODO https://github.com/thefreshuk/pubsub-laravel/issues/10: 403 once fixed
        $this->assertSame([], RecordingMiddleware::$confirmed);
    }
}
