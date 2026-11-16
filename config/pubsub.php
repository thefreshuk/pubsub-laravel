<?php

return [
    'provider' => env('PUBSUB_PROVIDER', 'sns'),
    'topic' => env('PUBSUB_TOPIC', ''),
    'hostPattern' => env('PUBSUB_SNS_HOST_PATTERN', '')
];
