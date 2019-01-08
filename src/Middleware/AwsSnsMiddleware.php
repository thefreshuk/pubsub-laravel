<?php

namespace TheFresh\PubSub\Middleware;

use Closure;
use Log;
use TheFresh\PubSub\Messages\DynamicMessage as Message;
use Aws\Sns\Message as SnsMessage;
use Aws\Sns\MessageValidator;
use GuzzleHttp\Client;

class AwsSnsMiddleware extends Middleware
{
    private $validator;

    public function __construct(MessageValidator $validator)
    {
        $this->validator = $validator;
    }

    public function verifyMessage(array $input): bool
    {
        // Convert message back to JSON for signature
        // verification. Not sure why Laravel takes
        // it upon itself to convert for me.
        $input = array_merge([], $input, [
            'Message' => json_encode($input['Message'])
        ]);

        $message = new SnsMessage($input);
        $this->validator->validate($message);
        return $this->validator->isValid($message);
    }

    public function confirmSubscription($url): void
    {
        $client = new Client;
        $client->get($url);
        return;
    }

    public function handle($request, Closure $next)
    {
        $input = $request->input();

        if (! $this->verifyMessage($input)) {
            Log::error('[SNSPubSub] Message not authentic', $input);
            abort(403);
            return;
        }

        if ($input['Type'] === 'SubscriptionConfirmation') {
            Log::info('[SNSPubSub] Confirming subscription');
            $this->confirmSubscription($input['SubscribeURL']);
            return response('', 200);
        }

        $message = $input['Message'];
        $request->message = new Message($message['type'], $message['content']);

        $next($request);
    }
}
