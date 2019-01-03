<?php

namespace TheFresh\PubSub\Messages;

interface MessageInterface
{
    /**
     * Type
     *
     * Returns the type of message.
     *
     * @return string
     */
    public function type(): string;

    /**
     * Content
     *
     * The content of the message. Can contain anything
     * as long as it's an array.
     *
     * @return array
     */
    public function content(): array;

    /**
     * ToJSON
     *
     * Transforms this message into a JSON string.
     */
    public function toJSON(): string;

    /**
     * __toString
     *
     * Transforms this message into a string.
     *
     * @return string
     */
    public function __toString(): string;
}
