<?php

namespace TheFresh\PubSub\Middleware;

use Aws\Sns\Message as SnsMessage;
use Aws\Sns\MessageValidator;
use Closure;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Log;
use TheFresh\PubSub\Messages\DynamicMessage as Message;

class AwsSnsMiddleware extends Middleware
{
    private $validator;

    private const DEFAULT_HOST_PATTERN = '/^sns\.[a-zA-Z0-9\-]{3,}\.amazonaws\.com(\.cn)?$/';

    private $http;

    private $hostPattern;

    public function __construct(MessageValidator $validator, ?ClientInterface $http = null, ?string $hostPattern = null)
    {
        $this->validator = $validator;
        $this->http = $http;
        $this->hostPattern = $hostPattern ?: self::DEFAULT_HOST_PATTERN;
    }

    /**
     * Only https URLs on an SNS host (the same pattern used for the signing
     * certificate) may be fetched to confirm a subscription.
     */
    protected function isTrustedSubscribeUrl(string $url): bool
    {
        $parsed = parse_url($url);

        return ($parsed['scheme'] ?? '') === 'https'
            && isset($parsed['host'])
            && preg_match($this->hostPattern, $parsed['host']) === 1;
    }

    /**
     * Uses the official SNS message validator to verify
     * an SNS message.
     *
     * @param array $input The SNS message
     * @return bool True if the message passes verification
     */
    protected function verifyMessage(array $input): bool
    {
        $message = new SnsMessage($input);
        $this->validator->validate($message);
        return $this->validator->isValid($message);
    }

    /**
     * Confirms a subscription given a URL with a GET
     * request.
     *
     * @param string $url The subscription confirmation URL
     * @return bool True if SNS accepted the confirmation
     */
    protected function confirmSubscription(string $url): bool
    {
        $client = $this->http ?? new Client([
            'timeout' => 5,
            'connect_timeout' => 5,
            'allow_redirects' => false,
        ]);

        try {
            $client->request('GET', $url);
        } catch (GuzzleException $e) {
            Log::error('[PubSub] Subscription confirmation failed: ' . $e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * Tests if $type is from an SNS subscription
     * confirmation message.
     *
     * @param string $type The message's type
     * @return bool True if it is a confirmation message
     */
    protected function isConfirmationMessage(string $type): bool
    {
        return $type === 'SubscriptionConfirmation';
    }

    /**
     * Handles an SNS message, storing the the PubSub
     * message in the $message property on the Request
     * object.
     *
     * To accept an SNS message, we first have to verify the
     * message is authenticate. We do this by verifying the
     * digital signature and the certificate provided with
     * the message.
     *
     * We then need to check to see if SNS is confirming the
     * subscription. SNS sends confirmation messages out for
     * every new subscription. Along with these messages,
     * SNS provides a URL (SubscribeURL). We can send a GET
     * request to this URL to confirm the subscription. This
     * message does not contain application-specific inform-
     * ation so we can stop processing once confirmed.
     *
     * Finally, if all checks pass, we provide the message
     * to the rest of the application on the Request object.
     *
     * @param Illuminate\Http\Request $request
     * @param Closure $next
     * @return Illuminate\Http\Response
     */
    public function handle($request, Closure $next)
    {
        $input = json_decode($request->getContent(), true);

        if (! $this->verifyMessage($input)) {
            Log::error('[PubSub] Message not authentic', $input);
            return response('Message not authentic', 403);
        }

        if ($this->isConfirmationMessage($input['Type'])) {
            if (! $this->isTrustedSubscribeUrl($input['SubscribeURL'])) {
                Log::error('[PubSub] Refusing to confirm subscription: untrusted SubscribeURL host');
                return response('Untrusted SubscribeURL', 400);
            }

            if (! $this->confirmSubscription($input['SubscribeURL'])) {
                // 502 so SNS retries the confirmation.
                return response('Subscription confirmation failed', 502);
            }

            return response('Subscription confirmed', 200);
        }

        $request->message = new Message('', json_decode($input['Message'], true));

        $next($request);
    }
}
