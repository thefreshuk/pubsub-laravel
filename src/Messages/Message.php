<?php

declare(strict_types=1);

namespace TheFresh\PubSub\Messages;

abstract class Message implements MessageInterface
{
    /**
     * Type
     *
     * Gets the type of message
     *
     * @return string The type
     */
    public function type(): string
    {
        return static::TYPE;
    }

    /**
     * Content
     *
     * Gets the message contents. Sub-classes must provide
     * this method.
     *
     * @return array The message content
     */
    abstract public function content(): array;

    /**
     * ToJSON
     *
     * Serializes this message to a JSON string
     *
     * @return string The JSON string
     */
    public function toJSON(): string
    {
        return json_encode([
            'type' => $this->type(),
            'content' => $this->content()
        ]);
    }

    /**
     * __toString
     *
     * Converts object to string - JSON by default.
     *
     * @return string The message as a string
     */
    public function __toString(): string
    {
        return $this->toJSON();
    }
}
