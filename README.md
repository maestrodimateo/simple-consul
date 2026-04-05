# Simple Consul

[![Latest Version on Packagist](https://img.shields.io/packagist/v/maestrodimateo/simple-consul.svg)](https://packagist.org/packages/maestrodimateo/simple-consul)
[![License](https://img.shields.io/packagist/l/maestrodimateo/simple-consul.svg)](https://packagist.org/packages/maestrodimateo/simple-consul)

A simple, elegant Laravel wrapper for [HashiCorp Consul](https://www.consul.io/).

Built on top of [friendsofphp/consul-php-sdk](https://github.com/FriendsOfPHP/consul-php-sdk), this package provides a clean Facade and helper for KV store, service discovery, health checks, and distributed locking — without dealing with raw HTTP responses.

## Installation

```bash
composer require maestrodimateo/simple-consul
```

Publish the config:

```bash
php artisan vendor:publish --tag=consul-config
```

Set your Consul address in `.env`:

```env
CONSUL_HTTP_ADDR=http://127.0.0.1:8500
CONSUL_HTTP_TOKEN=your-acl-token        # optional
CONSUL_KV_PREFIX=myapp/production/      # optional, auto-prefixed on all KV keys
```

## Quick Start

```php
use Maestrodimateo\SimpleConsul\Facades\Consul;

// Store a value
Consul::put('config/app/name', 'My Application');

// Retrieve it
$name = Consul::get('config/app/name');

// Store complex data (auto JSON-encoded)
Consul::put('config/database', [
    'host' => 'db.example.com',
    'port' => 5432,
]);

// Retrieve it (auto JSON-decoded)
$db = Consul::get('config/database');
echo $db['host']; // "db.example.com"
```

The `consul()` helper is also available:

```php
consul()->put('key', 'value');
consul()->get('key');
```

## KV Store

```php
// Get with default
$debug = Consul::get('config/debug', false);

// Check existence
if (Consul::has('config/api-key')) { ... }

// Delete
Consul::delete('config/old-key');

// List keys
$keys = Consul::keys('config/');
// ["config/app/name", "config/database", ...]
```

## Service Discovery

```php
// Register a service
Consul::registerService(
    name: 'payment-api',
    port: 8080,
    tags: ['v2', 'production'],
    meta: ['version' => '2.1.0'],
);

// List all services
$services = Consul::services();

// Get instances of a service
$instances = Consul::service('payment-api');

// Deregister
Consul::deregisterService('payment-api');
```

## Health Checks

```php
// Get healthy instances only
$healthy = Consul::healthyService('payment-api');

// Quick health check
if (Consul::isHealthy('payment-api')) {
    // Service is up
}
```

## Distributed Locking

```php
// Simple lock with callback
$result = Consul::withLock('jobs/send-emails', function () {
    // This code runs only if the lock is acquired.
    // Lock is auto-released when done (even on exceptions).
    return sendEmails();
}, ttlSeconds: 30);

if ($result === false) {
    // Lock was not acquired (another process holds it)
}
```

Manual lock management:

```php
$sessionId = Consul::createSession(ttlSeconds: 60, name: 'my-lock');

if (Consul::acquireLock('my-resource', $sessionId)) {
    try {
        // critical section
    } finally {
        Consul::releaseLock('my-resource', $sessionId);
        Consul::destroySession($sessionId);
    }
}
```

## Raw SDK Access

For advanced use cases, access the underlying SDK services directly:

```php
$kvService = Consul::kv();       // Consul\Services\KV
$agent     = Consul::agent();    // Consul\Services\Agent
$catalog   = Consul::catalog();  // Consul\Services\Catalog
$health    = Consul::health();   // Consul\Services\Health
$session   = Consul::session();  // Consul\Services\Session
```

## Configuration

```php
// config/consul.php
return [
    'address'    => env('CONSUL_HTTP_ADDR', 'http://127.0.0.1:8500'),
    'token'      => env('CONSUL_HTTP_TOKEN'),
    'datacenter' => env('CONSUL_DATACENTER'),
    'kv_prefix'  => env('CONSUL_KV_PREFIX', ''),
];
```

The `kv_prefix` is automatically prepended to all KV operations. This lets you namespace your keys per environment without changing your code:

```env
# .env.production
CONSUL_KV_PREFIX=production/myapp/

# .env.staging
CONSUL_KV_PREFIX=staging/myapp/
```

```php
// Both environments use the same code:
Consul::get('database/host');
// production: reads "production/myapp/database/host"
// staging: reads "staging/myapp/database/host"
```

## License

MIT

## Credits

- [Noel Mebale](https://github.com/maestrodimateo)
- Built on [friendsofphp/consul-php-sdk](https://github.com/FriendsOfPHP/consul-php-sdk)
