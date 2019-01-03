<?php

namespace TheFresh\PubSub\Mocks;

use TheFresh\PubSub\Messages\MessageInterface;

class MockMessage implements MessageInterface
{
    public $type;
    public $content;

    public function __construct(string $type, array $content)
    {
        $this->type = $type;
        $this->content = $content;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function content(): array
    {
        return $this->content;
    }

    public function toJSON(): string
    {
        return json_encode([
            'type' => $this->type(),
            'content' => $this->content(),
        ]);
    }

    public function __toString(): string
    {
        return $this->toJSON();
    }
}
