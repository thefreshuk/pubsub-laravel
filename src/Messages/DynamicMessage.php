<?php

declare(strict_types=1);

namespace TheFresh\PubSub\Messages;

class DynamicMessage extends Message
{
    private $type;
    private $content;

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
}
