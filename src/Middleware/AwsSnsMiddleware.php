<?php

namespace TheFresh\PubSub\Middleware;

use Aws\Sns\Message as SnsMessage;
use Aws\Sns\MessageValidator;
use Closure;
use GuzzleHttp\Client;
use Log;
use TheFresh\PubSub\Messages\DynamicMessage as Message;

class AwsSnsMiddleware extends Middleware
{
    private $validator;

    public function __construct(MessageValidator $validator)
    {
        $this->validator = $validator;
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
     */
    protected function confirmSubscription(string $url): void
    {
        $client = new Client;
        $client->get($url);
        return;
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
        $input = $request->input();

        if (! $this->verifyMessage($input)) {
            Log::error('[PubSub] Message not authentic', $input);
            return response('Message not authentic', 403);
        }

        if ($this->isConfirmationMessage($input['Type'])) {
            $this->confirmSubscription($input['SubscribeURL']);
            return response('Subscription confirmed', 200);
        }

        $request->message = new Message('', json_decode($input['Message'], true));

        $next($request);
    }
}
