<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mock subscription payments (local / automated tests only)
    |--------------------------------------------------------------------------
    |
    | When false (default), paid plan upgrades must go through the plan-change
    | request workflow with payment proof — never auto-confirmed mock payments.
    |
    */
    'allow_mock_payments' => env('SUBSCRIPTION_ALLOW_MOCK_PAYMENTS', false),

];
