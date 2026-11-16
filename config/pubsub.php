<?php

return [
    'provider' => env('PUBSUB_PROVIDER', 'sns'),
    'topic' => env('PUBSUB_TOPIC', ''),
    // Skips SNS signature validation. Leave unset (null) for the legacy
    // behaviour: bypass only when app.env is 'local' (deprecated). Set to
    // true/false explicitly via PUBSUB_BYPASS_VALIDATION.
    'bypassValidation' => env('PUBSUB_BYPASS_VALIDATION'),
    'hostPattern' => env('PUBSUB_SNS_HOST_PATTERN', '')
];
