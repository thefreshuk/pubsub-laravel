<?php

namespace TheFresh\PubSub;

use TheFresh\PubSub\Messages\MessageInterface;

interface TopicInterface
{
    /**
     * Publishes a message to this topic
     *
     * @param MessageInterface $message The message to publish
     */
    public function publish(MessageInterface $message): void;

    /**
     * Subscribes an enpoint to this topic for messages of
     * the given type.
     *
     * @param string $type The type of message
     * @param string $endpoint The endpoint to subscribe
     */
    public function subscribe(string $type, string $endpoint): void;
}
