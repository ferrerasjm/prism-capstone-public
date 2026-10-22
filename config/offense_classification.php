<?php

declare(strict_types=1);

return [
    'enabled' => env('PRISM_ML_CLASSIFIER_ENABLED', false),
    'url' => env('PRISM_ML_CLASSIFIER_URL', 'http://127.0.0.1:8765'),
    'token' => env('PRISM_ML_CLASSIFIER_TOKEN'),
    'connect_timeout_seconds' => (float) env('PRISM_ML_CONNECT_TIMEOUT', 1),
    'timeout_seconds' => (float) env('PRISM_ML_TIMEOUT', 15),
    'top_k' => 3,
];
