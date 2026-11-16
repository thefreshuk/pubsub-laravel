<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use TheFresh\PubSub\Messages\DynamicMessage;
use TheFresh\PubSub\Messages\MessageInterface;

class DynamicMessageTest extends TestCase
{
    public function testExposesTypeAndContent(): void
    {
        $message = new DynamicMessage('ias.user.created', ['guid' => 'abc']);

        $this->assertInstanceOf(MessageInterface::class, $message);
        $this->assertSame('ias.user.created', $message->type());
        $this->assertSame(['guid' => 'abc'], $message->content());
    }

    public function testEmptyTypeIsAllowed(): void
    {
        // The middleware builds received messages with an empty type.
        $this->assertSame('', (new DynamicMessage('', []))->type());
    }

    public function testRequiresArrayContent(): void
    {
        $this->expectException(\TypeError::class);
        new DynamicMessage('t', null);
    }

    public function testToJsonContainsContentAndOrigin(): void
    {
        putenv('SERVICE_UUID=svc-1');
        $decoded = json_decode((new DynamicMessage('t', ['a' => 1]))->toJSON(), true);
        putenv('SERVICE_UUID');

        $this->assertSame(['a' => 1, 'Origin' => 'svc-1'], $decoded);
    }

    public function testOriginIsEmptyStringWhenServiceUuidUnset(): void
    {
        // Regression test for https://github.com/thefreshuk/pubsub-laravel/issues/15: getenv() returns false when unset.
        putenv('SERVICE_UUID');
        $decoded = json_decode((new DynamicMessage('t', []))->toJSON(), true);

        $this->assertSame('', $decoded['Origin']);
    }

    public function testToStringIsJson(): void
    {
        putenv('SERVICE_UUID=svc-1');
        $message = new DynamicMessage('t', ['a' => 1]);

        $this->assertSame($message->toJSON(), (string) $message);
        putenv('SERVICE_UUID');
    }
}
