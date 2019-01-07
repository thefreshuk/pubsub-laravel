<?php

declare(strict_types=1);

namespace TheFresh\PubSub\Clients;

use TheFresh\PubSub\Messages\MessageInterface;

interface ClientInterface
{
    public function publish(string $topic, MessageInterface $message): void ;

    public function subscribe(string $topic, string $type, string $endpoint): void;
}
