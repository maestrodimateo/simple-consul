<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Consul HTTP Address
    |--------------------------------------------------------------------------
    | The base URL of your Consul agent (default: http://127.0.0.1:8500).
    */
    'address' => env('CONSUL_HTTP_ADDR', 'http://127.0.0.1:8500'),

    /*
    |--------------------------------------------------------------------------
    | ACL Token
    |--------------------------------------------------------------------------
    | Optional ACL token for authenticated requests.
    */
    'token' => env('CONSUL_HTTP_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Datacenter
    |--------------------------------------------------------------------------
    | Default datacenter for requests. Leave null to use the agent's datacenter.
    */
    'datacenter' => env('CONSUL_DATACENTER'),

    /*
    |--------------------------------------------------------------------------
    | KV Prefix
    |--------------------------------------------------------------------------
    | A prefix automatically prepended to all KV keys.
    | Useful for namespacing per environment (e.g., "production/myapp/").
    */
    'kv_prefix' => env('CONSUL_KV_PREFIX', ''),

];
