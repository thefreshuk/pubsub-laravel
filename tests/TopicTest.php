<?php

namespace TheFresh\PubSub;

use PHPUnit\Framework\TestCase;

use TheFresh\PubSub\Mocks\MockClient;
use TheFresh\PubSub\Mocks\MockMessage;
use TheFresh\PubSub\Topic;

class TopicTest extends TestCase
{
    public function testPublishesAMessage()
    {
        // Mocks
        $client = new MockClient;
        $message = new MockMessage('test_type', [
            'test_message'
        ]);

        $topic = new Topic($client, 'test_topic');
        $topic->publish($message);

        $this->assertEquals($client->topic, 'test_topic');
        $this->assertEquals($client->message->type, 'test_type');
        $this->assertEquals($client->message->content, ['test_message']);
    }
}
