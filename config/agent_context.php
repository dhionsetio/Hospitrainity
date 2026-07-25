<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Agent context API
    |--------------------------------------------------------------------------
    |
    | This read-only API is disabled unless an operator explicitly enables it.
    | The bearer token is a deployment secret: generate at least 32 random
    | characters, supply it through the environment, and never commit it.
    |
    */
    'enabled' => (bool) env('AGENT_CONTEXT_API_ENABLED', false),
    'token' => env('AGENT_CONTEXT_API_TOKEN'),
    'minimum_token_length' => 32,
    'rate_limit_per_minute' => (int) env('AGENT_CONTEXT_API_RATE_LIMIT', 30),
];
