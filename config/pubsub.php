<?php

return [
    'provider' => env('PUBSUB_PROVIDER', 'sns'),
    'topic' => env('PUBSUB_TOPIC', ''),
    'allowHttp' => env('PUBSUB_ALLOW_HTTP', false),
    'hostPattern' => env('PUBSUB_SNS_HOST_PATTERN', '')
];
