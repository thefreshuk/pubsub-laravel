<?php

namespace Tests;

use Mockery as m;
use PHPUnit\Framework\TestCase;
use Aws\Sns\SnsClient;
use TheFresh\PubSub\Clients\AwsSnsClient;

class SnsClientTest extends TestCase
{
    public function testSubscribesToTopic()
    {
        $sns = m::mock(SnsClient::class);
        $client = new AwsSnsClient($sns);

        $sns->shouldReceive('subscribe');
        $client->subscribe('test_topic', 'test_type', 'https://endpoint');
    }
}
