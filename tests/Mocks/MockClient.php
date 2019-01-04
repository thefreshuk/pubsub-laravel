<?php

namespace Tests\Mocks;

use TheFresh\PubSub\Clients\ClientInterface;
use TheFresh\PubSub\Messages\MessageInterface;

class MockClient implements ClientInterface
{
    /**
     * @var string $topic The topic to publish or subscribe to
     */
    public $topic;

    /**
     * @var MessageInterface $message The message to publish to
     */
    public $message;

    /**
     * @var string $endpoint The endpoint to subscribe to
     */
    public $endpoint;

    public function publish(string $topic, MessageInterface $message)
    {
        $this->topic = $topic;
        $this->message = $message;
    }

    public function subscribe(string $topic, string $endpoint)
    {
        $this->topic = $topic;
        $this->endpoint = $endpoint;
    }
}
