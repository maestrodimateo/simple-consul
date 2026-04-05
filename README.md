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

## Quick Start

Add to your `.env`:

```env
CONSUL_HTTP_ADDR=http://127.0.0.1:8500
CONSUL_HTTP_TOKEN=your-acl-token        # optional
CONSUL_KV_PREFIX=myapp/production/      # optional
```

```php
use Maestrodimateo\SimpleConsul\Facades\Consul;

// Store & retrieve
Consul::put('config/app/name', 'My Application');
$name = Consul::get('config/app/name');

// Auto JSON encode/decode for arrays
Consul::put('config/database', ['host' => 'db.example.com', 'port' => 5432]);
$db = Consul::get('config/database');
echo $db['host']; // "db.example.com"
```

The `consul()` helper is also available:

```php
consul()->put('key', 'value');
consul()->get('key');
```

---

## Auto Service Registration

The package can automatically register your application with Consul on boot. Just set the env variables — **zero PHP code needed**:

```env
CONSUL_SERVICE_ENABLED=true
CONSUL_SERVICE_NAME=payment-api
CONSUL_SERVICE_HOST=10.0.0.5
CONSUL_SERVICE_PORT=8080
CONSUL_SERVICE_TAGS=v2,production
```

The service registers on every boot (idempotent) and Consul's health check handles cleanup when the app goes down — no deregister needed.

### Manual register/deregister

```php
Consul::register();    // Register using config values
Consul::deregister();  // Remove from Consul
```

---

## Health Check Types

Configure the check type via `CONSUL_HEALTH_CHECK_TYPE`. Default is `http`.

### HTTP (default)

Consul polls an endpoint on your app:

```env
CONSUL_HEALTH_CHECK_TYPE=http
CONSUL_HEALTH_CHECK_ENDPOINT=/up
CONSUL_HEALTH_CHECK_INTERVAL=15s
CONSUL_HEALTH_CHECK_TIMEOUT=5s
```

### TCP

Consul checks that the port accepts connections:

```env
CONSUL_HEALTH_CHECK_TYPE=tcp
```

### gRPC

For gRPC services:

```env
CONSUL_HEALTH_CHECK_TYPE=grpc
CONSUL_HEALTH_CHECK_GRPC=127.0.0.1:8080/my.service
```

### TTL

Your app sends periodic heartbeats. Consul marks the service as critical if no heartbeat is received within the TTL:

```env
CONSUL_HEALTH_CHECK_TYPE=ttl
CONSUL_HEALTH_CHECK_TTL=30s
```

Call `passCheck()` periodically (e.g., in the scheduler):

```php
// app/Console/Kernel.php
$schedule->call(fn () => Consul::passCheck())->everyFifteenSeconds();
```

### Script

Consul runs a command to check health:

```php
// config/consul.php
'health_check' => [
    'type' => 'script',
    'args' => ['php', 'artisan', 'health:check'],
    'interval' => '15s',
],
```

---

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

---

## Service Discovery

```php
// Register a service manually (with health check)
Consul::registerService(
    name: 'payment-api',
    port: 8080,
    tags: ['v2', 'production'],
    meta: ['version' => '2.1.0'],
    check: [
        'HTTP' => 'http://10.0.0.5:8080/up',
        'Interval' => '10s',
    ],
);

// List all services
$services = Consul::services();

// Get instances of a service
$instances = Consul::service('payment-api');

// Deregister
Consul::deregisterService('payment-api');
```

---

## Health Checks

```php
// Get healthy instances only
$healthy = Consul::healthyService('payment-api');

// Quick boolean check
if (Consul::isHealthy('payment-api')) {
    // At least one instance is passing
}
```

---

## Distributed Locking

### With callback (recommended)

```php
$result = Consul::withLock('jobs/send-emails', function () {
    // Runs only if the lock is acquired.
    // Lock is auto-released when done (even on exceptions).
    return sendEmails();
}, ttlSeconds: 30);

if ($result === false) {
    // Another process holds the lock
}
```

### Manual lock management

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

---

## Raw SDK Access

For advanced use cases, access the underlying SDK services directly:

```php
Consul::kv();       // Consul\Services\KV
Consul::agent();    // Consul\Services\Agent
Consul::catalog();  // Consul\Services\Catalog
Consul::health();   // Consul\Services\Health
Consul::session();  // Consul\Services\Session
```

---

## Configuration

```php
// config/consul.php
return [
    'address'    => env('CONSUL_HTTP_ADDR', 'http://127.0.0.1:8500'),
    'token'      => env('CONSUL_HTTP_TOKEN'),
    'datacenter' => env('CONSUL_DATACENTER'),
    'kv_prefix'  => env('CONSUL_KV_PREFIX', ''),

    'service' => [
        'enabled' => env('CONSUL_SERVICE_ENABLED', false),
        'id'      => env('CONSUL_SERVICE_ID', 'laravel-local'),
        'name'    => env('CONSUL_SERVICE_NAME', 'laravel'),
        'host'    => env('CONSUL_SERVICE_HOST', '127.0.0.1'),
        'port'    => (int) env('CONSUL_SERVICE_PORT', 8000),
        'tags'    => ['v1'],
        'meta'    => ['env' => 'production'],
    ],

    'health_check' => [
        'enabled'          => true,
        'type'             => 'http',          // http, tcp, script, ttl, grpc
        'endpoint'         => '/up',           // for http
        'interval'         => '15s',
        'timeout'          => '5s',
        'deregister_after' => '10m',
        'ttl'              => '30s',           // for ttl
        'grpc'             => null,            // for grpc
        'args'             => [],              // for script
    ],
];
```

### KV Prefix

The `kv_prefix` is prepended to all KV operations automatically:

```env
# .env.production
CONSUL_KV_PREFIX=production/myapp/

# .env.staging
CONSUL_KV_PREFIX=staging/myapp/
```

```php
// Same code, different namespaces:
Consul::get('database/host');
// production → reads "production/myapp/database/host"
// staging    → reads "staging/myapp/database/host"
```

---

## Testing

```bash
# Unit tests (no Consul needed)
./vendor/bin/pest tests/Unit

# Integration tests (requires Consul on 127.0.0.1:8500)
./vendor/bin/pest --group=integration
```

---

## License

MIT

## Credits

- [Noel Mebale](https://github.com/maestrodimateo)
- Built on [friendsofphp/consul-php-sdk](https://github.com/FriendsOfPHP/consul-php-sdk)
