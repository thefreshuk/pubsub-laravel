<?php

declare(strict_types=1);

namespace TheFresh\PubSub;

use TheFresh\PubSub\Clients\ClientInterface;
use TheFresh\PubSub\Messages\MessageInterface;

class Topic
{
    /**
     * @var ClientInterface $client The PubSub interface
     */
    private $client;

    /**
     * @var string $name The name of the topic
     */
    private $name;

    public function __construct(ClientInterface $client, string $name)
    {
        $this->client = $client;
        $this->name = $name;
    }

    /**
     * Publishes a message to this topic
     *
     * @param MessageInterface $message The message to publish
     */
    public function publish(MessageInterface $message): void
    {
        $this->client->publish($this->name, $message);
    }
}