<?php

namespace Tests;

use Aws\Laravel\AwsServiceProvider;
use Aws\Sns\MessageValidator;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Tests\Mocks\TestSubscriptionServiceProvider;
use TheFresh\PubSub\PubSubServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected const CERT_URL = 'https://sns.test/cert.pem';

    protected function getPackageProviders($app): array
    {
        return [
            AwsServiceProvider::class,
            PubSubServiceProvider::class,
            TestSubscriptionServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('pubsub.topic', 'arn:aws:sns:eu-west-2:000000000000:test');
        $app['config']->set('pubsub.hostPattern', '/^sns\.test$/');
        $app['config']->set('aws', [
            'region' => 'eu-west-2',
            'version' => 'latest',
            'credentials' => ['key' => 'test-key', 'secret' => 'test-secret'],
        ]);
    }

    /**
     * Generates an RSA key pair; returns [private key, public key PEM].
     */
    protected function makeKeyPair(): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

        return [$key, openssl_pkey_get_details($key)['key']];
    }

    /**
     * Builds an SNS "Notification" payload, signed with $privateKey (SignatureVersion 2).
     */
    protected function signedNotification($privateKey, array $content, array $overrides = []): array
    {
        $message = array_merge([
            'Type' => 'Notification',
            'MessageId' => 'a1b2c3',
            'TopicArn' => 'arn:aws:sns:eu-west-2:000000000000:test',
            'Message' => json_encode($content),
            'Timestamp' => '2026-01-01T00:00:00.000Z',
            'SignatureVersion' => '2',
            'SigningCertURL' => self::CERT_URL,
        ], $overrides);

        return $this->sign($privateKey, $message);
    }

    protected function signedSubscriptionConfirmation($privateKey, string $subscribeUrl): array
    {
        return $this->sign($privateKey, [
            'Type' => 'SubscriptionConfirmation',
            'MessageId' => 'd4e5f6',
            'Token' => 'token',
            'TopicArn' => 'arn:aws:sns:eu-west-2:000000000000:test',
            'Message' => 'You have chosen to subscribe',
            'SubscribeURL' => $subscribeUrl,
            'Timestamp' => '2026-01-01T00:00:00.000Z',
            'SignatureVersion' => '2',
            'SigningCertURL' => self::CERT_URL,
        ]);
    }

    private function sign($privateKey, array $message): array
    {
        $validator = new MessageValidator(fn () => '', '/^sns\.test$/');
        $toSign = $validator->getStringToSign(new \Aws\Sns\Message(array_merge($message, ['Signature' => 'x'])));
        openssl_sign($toSign, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return $message + ['Signature' => base64_encode($signature)];
    }

    /**
     * Makes the container validate against $publicKeyPem instead of fetching a certificate.
     */
    protected function trustPublicKey(string $publicKeyPem): void
    {
        $this->app->bind(MessageValidator::class, fn () => new MessageValidator(
            fn () => $publicKeyPem,
            config('pubsub.hostPattern')
        ));
    }

    protected function postRaw(string $uri, string $body)
    {
        return $this->call('POST', $uri, [], [], [], ['CONTENT_TYPE' => 'application/json'], $body);
    }
}
