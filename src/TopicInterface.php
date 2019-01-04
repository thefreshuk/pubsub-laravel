<?php

namespace TheFresh\PubSub;

use TheFresh\PubSub\Messages\MessageInterface;

interface TopicInterface
{
    public function publish(MessageInterface $message): void;

    public function subscribe(string $type, string $endpoint): void;
}
