<?php

declare(strict_types=1);

return [
    'unauthorized' => 'Authentication required.',
    'forbidden' => 'Insufficient privileges for this action.',
    'rate_limited' => 'Too many requests, slow down.',
    'not_found' => 'Resource not found.',
    'validation_failed' => 'The request payload is invalid.',
    'idempotency_conflict' => 'This Idempotency-Key was already used with a different request.',
    'configuration_not_found' => 'Configuration not found or not accessible to this shop.',
    'order_not_found' => 'Order not found.',
    'webhook_endpoint_not_found' => 'Webhook endpoint not found.',
];
