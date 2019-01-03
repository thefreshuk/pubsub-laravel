<?php declare(strict_types = 1);

namespace TheFresh\PubSub\Clients;

use Aws\Sns\SnsClient;
use TheFresh\PubSub\Messages\MessageInterface;

class AwsSnsClient implements ClientInterface
{
    /**
     * @var SnsClient $sns AWS SNS client
     */
    private $sns;

    public function __construct(SnsClient $sns)
    {
        $this->sns = $sns;
    }

    /**
     * Publishes a message to the topic with ARN $topic.
     *
     * @param string $topic The ARN of the topic to publish
     * @param MessageInterface $message The message to publish
     */
    public function publish(string $topic, MessageInterface $message): void
    {
        $this->sns->publish([
            'Message' => $message,
            'TopicArn' => $topic,
        ]);
    }

    public function subscribe(string $topic, string $endpoint)
    {
        // TODO: Implement subscribe method
    }
}
