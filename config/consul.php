<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Consul HTTP Address
    |--------------------------------------------------------------------------
    */
    'address' => env('CONSUL_HTTP_ADDR', 'http://127.0.0.1:8500'),

    /*
    |--------------------------------------------------------------------------
    | ACL Token
    |--------------------------------------------------------------------------
    */
    'token' => env('CONSUL_HTTP_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Datacenter
    |--------------------------------------------------------------------------
    */
    'datacenter' => env('CONSUL_DATACENTER'),

    /*
    |--------------------------------------------------------------------------
    | KV Prefix
    |--------------------------------------------------------------------------
    | Automatically prepended to all KV keys.
    */
    'kv_prefix' => env('CONSUL_KV_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Service Registration
    |--------------------------------------------------------------------------
    | If enabled, the application registers itself as a Consul service on boot
    | and deregisters on shutdown. Set to false to disable auto-registration.
    */
    'service' => [
        'enabled' => env('CONSUL_SERVICE_ENABLED', false),
        'id' => env('CONSUL_SERVICE_ID', env('APP_NAME', 'laravel').'-'.env('APP_ENV', 'local')),
        'name' => env('CONSUL_SERVICE_NAME', env('APP_NAME', 'laravel')),
        'host' => env('CONSUL_SERVICE_HOST', '127.0.0.1'),
        'port' => (int) env('CONSUL_SERVICE_PORT', 8000),
        'tags' => array_filter(explode(',', env('CONSUL_SERVICE_TAGS', ''))),
        'meta' => [
            'env' => env('APP_ENV', 'local'),
            'version' => env('APP_VERSION', '1.0.0'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Check
    |--------------------------------------------------------------------------
    | HTTP health check that Consul will poll to verify the service is alive.
    */
    'health_check' => [
        'enabled' => env('CONSUL_HEALTH_CHECK_ENABLED', true),
        'endpoint' => env('CONSUL_HEALTH_CHECK_ENDPOINT', '/up'),
        'interval' => env('CONSUL_HEALTH_CHECK_INTERVAL', '15s'),
        'timeout' => env('CONSUL_HEALTH_CHECK_TIMEOUT', '5s'),
        'deregister_after' => env('CONSUL_DEREGISTER_AFTER', '10m'),
    ],

];
