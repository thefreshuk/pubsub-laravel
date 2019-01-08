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

    /**
     * Subscribes $endpoint to a $topic to messages with the given $type
     *
     * @param string $topic The topic to subscribe to
     * @param string $type The type to filter
     * @param string $endpoint The endpoint that receives messages
     */
    public function subscribe(string $topic, string $type, string $endpoint): void
    {
        $this->sns->subscribe([
            'Protocol' => $this->getEndpointProtocol($endpoint),
            'TopicArn' => $topic,
            'Endpoint' => $endpoint,
            'Attributes' => [
                'FilterPolicy' => json_encode([
                    'type' => [
                        'Type' => 'String',
                        'Value' => $type
                    ]
                ])
            ]
        ]);
    }

    protected function getEndpointProtocol(string $endpoint): string
    {
        if (starts_with($endpoint, 'https')) {
            return 'https';
        }

        return 'http';
    }
}
