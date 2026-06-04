<?php

return [
    'allow_mock_payments' => env('BILLING_ALLOW_MOCK_PAYMENTS', false),
    'mock_payment_environments' => ['local', 'testing', 'staging'],
];
