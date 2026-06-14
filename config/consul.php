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
    | If enabled, the application registers itself as a Consul service on boot.
    | Consul's health check + DeregisterCriticalServiceAfter handle removal
    | when the app goes down (no need to deregister on terminate).
    |
    | register_mode:
    |   "once"   (default) — Register on the first Laravel boot per container,
    |                        then skip via a tmpfs marker. Required for PHP-FPM
    |                        where each HTTP request bootstraps Laravel anew.
    |                        Container restart wipes /tmp → fresh register.
    |   "always"           — Re-register on every boot. Use with Octane / Swoole
    |                        / RoadRunner / queue workers / long-running daemons
    |                        where Laravel boots once and stays in memory.
    |
    | register_ttl_seconds:
    |   In "once" mode, force a re-register if the marker is older than this
    |   threshold. Acts as a safety net when Consul itself restarted and lost
    |   the service definition while the container kept running. 0 disables.
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
        'register_mode' => env('CONSUL_REGISTER_MODE', 'once'),
        'register_ttl_seconds' => (int) env('CONSUL_REGISTER_TTL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Check
    |--------------------------------------------------------------------------
    | Consul health check configuration.
    |
    | Supported types: "http", "tcp", "script", "ttl", "grpc"
    |
    | Each type uses specific fields:
    |   http   → endpoint (path appended to host:port)
    |   tcp    → uses host:port directly
    |   script → args (array of command + arguments)
    |   ttl    → ttl (e.g., "30s" — your app must call Consul::passCheck() periodically)
    |   grpc   → grpc (e.g., "127.0.0.1:8080/my.service")
    */
    'health_check' => [
        'enabled' => env('CONSUL_HEALTH_CHECK_ENABLED', true),
        'type' => env('CONSUL_HEALTH_CHECK_TYPE', 'http'),
        'scheme' => env('CONSUL_HEALTH_CHECK_SCHEME', 'http'),
        'endpoint' => env('CONSUL_HEALTH_CHECK_ENDPOINT', '/up'),
        'interval' => env('CONSUL_HEALTH_CHECK_INTERVAL', '15s'),
        'timeout' => env('CONSUL_HEALTH_CHECK_TIMEOUT', '5s'),
        'deregister_after' => env('CONSUL_DEREGISTER_AFTER', '10m'),
        'ttl' => env('CONSUL_HEALTH_CHECK_TTL', '30s'),
        'grpc' => env('CONSUL_HEALTH_CHECK_GRPC'),
        'args' => [], // For script checks: ['php', 'artisan', 'health:check']
    ],

];
