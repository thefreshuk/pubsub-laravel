<?php declare(strict_types = 1);

namespace TheFresh\PubSub\Clients;

use Aws\Sns\SnsClient;
use Aws\Sns\Exception\SnsException;
use TheFresh\PubSub\Messages\MessageInterface;
use TheFresh\PubSub\Clients\Exceptions\ClientException;

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
     * @throws ClientException
     */
    public function publish(string $topic, MessageInterface $message): void
    {
        try {
            $this->sns->publish([
                'Message' => $message,
                'TopicArn' => $topic,
                'MessageAttributes' => [
                    'type' => [
                        'DataType' => 'String',
                        'StringValue' => $message->type()
                    ]
                ]
            ]);
        } catch (SnsException $e) {
            throw new ClientException($e->getMessage());
        }
    }

    /**
     * Subscribes $endpoint to a $topic to messages with the given $type
     *
     * @param string $topic The topic to subscribe to
     * @param string $type The type to filter
     * @param string $endpoint The endpoint that receives messages
     * @throws ClientException
     */
    public function subscribe(string $topic, string $type, string $endpoint): void
    {
        try {
            $this->sns->subscribe([
                'Protocol' => $this->getEndpointProtocol($endpoint),
                'TopicArn' => $topic,
                'Endpoint' => $endpoint,
                'Attributes' => [
                    'FilterPolicy' => json_encode([
                        'type' => [$type]
                    ])
                ]
            ]);
        } catch (SnsException $e) {
            throw new ClientException($e->getMessage());
        }
    }

    protected function getEndpointProtocol(string $endpoint): string
    {
        if (starts_with($endpoint, 'https')) {
            return 'https';
        }

        return 'http';
    }
}
