<?php

namespace Tests;

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

    public function testValidMessageReachesControllerWithDynamicMessage(): void
    {
        [$private, $public] = $this->makeKeyPair();
        $this->trustPublicKey($public);

        $this->postRaw(self::URI, json_encode($this->signedNotification($private, ['user' => 'abc'])))
            ->assertSuccessful();

        $this->assertCount(1, RecordingController::$received);
        $message = RecordingController::$received[0];
        $this->assertInstanceOf(DynamicMessage::class, $message);
        $this->assertSame('', $message->type());
        $this->assertSame(['user' => 'abc'], $message->content());
    }

    public function testControllerResponseIsReturned(): void
    {
        // Regression test for https://github.com/thefreshuk/pubsub-laravel/issues/9: the controller's response must be returned.
        [$private, $public] = $this->makeKeyPair();
        $this->trustPublicKey($public);

        $response = $this->postRaw(self::URI, json_encode($this->signedNotification($private, ['a' => 1])));

        $response->assertStatus(202);
        $this->assertSame('controller response', $response->getContent());
        $this->assertCount(1, RecordingController::$received);
    }

    public function testInvalidSignatureIsRejectedAndControllerNotCalled(): void
    {
        // Regression test for https://github.com/thefreshuk/pubsub-laravel/issues/10: an invalid signature must be a 403, not an exception.
        [$private] = $this->makeKeyPair();
        [, $otherPublic] = $this->makeKeyPair();
        $this->trustPublicKey($otherPublic);

        $this->postRaw(self::URI, json_encode($this->signedNotification($private, ['a' => 1])))
            ->assertStatus(403);
        $this->assertSame([], RecordingController::$received);
    }

    public function testTamperedMessageIsRejected(): void
    {
        [$private, $public] = $this->makeKeyPair();
        $this->trustPublicKey($public);

        $payload = $this->signedNotification($private, ['a' => 1]);
        $payload['Message'] = json_encode(['a' => 'tampered']);

        $this->postRaw(self::URI, json_encode($payload))->assertStatus(403);
        $this->assertSame([], RecordingController::$received);
    }

    public function testCertificateOnDisallowedHostIsRejected(): void
    {
        [$private, $public] = $this->makeKeyPair();
        $this->trustPublicKey($public);

        $payload = $this->signedNotification($private, ['a' => 1], ['SigningCertURL' => 'https://evil.example/cert.pem']);

        $this->postRaw(self::URI, json_encode($payload))->assertStatus(403);
        $this->assertSame([], RecordingController::$received);
    }

    public function testNotAuthenticMessageReturns403(): void
    {
        $validator = m::mock(MessageValidator::class);
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

        $this->postRaw(self::URI, json_encode($this->signedSubscriptionConfirmation($private, 'https://sns.test/confirm')))
            ->assertStatus(403);
        $this->assertSame([], RecordingMiddleware::$confirmed);
    }
}
