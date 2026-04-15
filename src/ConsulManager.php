<?php

namespace Maestrodimateo\SimpleConsul;

use Consul\Services\Agent;
use Consul\Services\Catalog;
use Consul\Services\Health;
use Consul\Services\KV;
use Consul\Services\Session;
use Exception;
use JsonException;

/**
 * ConsulManager — Fluent interface for HashiCorp Consul.
 *
 * Wraps the friendsofphp/consul-php-sdk with a developer-friendly API
 * that handles prefixing, JSON encoding/decoding, and sensible defaults.
 */
class ConsulManager
{
    private KV $kv;

    private Agent $agent;

    private Catalog $catalog;

    private Health $health;

    private Session $session;

    private string $prefix;

    public function __construct()
    {
        $this->kv = new KV;
        $this->agent = new Agent;
        $this->catalog = new Catalog;
        $this->health = new Health;
        $this->session = new Session;
        $this->prefix = config('consul.kv_prefix', '');
    }

    // =========================================================================
    // KV Store — Simple key/value operations
    // =========================================================================

    /**
     * Get a value from the KV store.
     *
     * @param  string  $key  Key name (prefix is applied automatically)
     * @param  mixed  $default  Default value if key doesn't exist
     * @return mixed The stored value (auto-decoded if JSON)
     */
    public function get(string $key, mixed $default = null): mixed
    {
        try {
            $response = $this->kv->get($this->prefixed($key), ['raw' => true]);

            return $this->decodeValue($response->getBody()) ?? $default;
        } catch (Exception) {
            return $default;
        }
    }

    /**
     * Store a value in the KV store.
     *
     * @param  string  $key  Key name
     * @param  mixed  $value  Value to store (arrays/objects are JSON-encoded)
     * @return bool True if the write succeeded
     *
     * @throws JsonException
     */
    public function put(string $key, mixed $value): bool
    {
        $encoded = is_array($value) || is_object($value)
            ? json_encode($value)
            : (string) $value;

        return (bool) $this->kv->put($this->prefixed($key), $encoded)->json();
    }

    /**
     * Delete a key from the KV store.
     *
     * @param  string  $key  Key name
     * @return bool True if the delete succeeded
     */
    public function delete(string $key): bool
    {
        try {
            $this->kv->delete($this->prefixed($key));

            return true;
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Check if a key exists in the KV store.
     *
     * @param  string  $key  Key name
     */
    public function has(string $key): bool
    {
        try {
            $this->kv->get($this->prefixed($key));

            return true;
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Get all keys matching a prefix.
     *
     * @param  string  $prefix  Key prefix to search (added after the global prefix)
     * @return array<string> List of matching key names (without global prefix)
     */
    public function keys(string $prefix = ''): array
    {
        try {
            $response = $this->kv->get($this->prefixed($prefix), ['keys' => true]);
            $keys = $response->json();

            $globalPrefix = $this->prefix;

            return array_map(
                fn (string $key) => $globalPrefix ? str_replace($globalPrefix, '', $key) : $key,
                $keys ?? [],
            );
        } catch (Exception) {
            return [];
        }
    }

    // =========================================================================
    // Service Registration (config-driven)
    // =========================================================================

    /**
     * Register this application as a Consul service using config/consul.php.
     * Automatically builds the health check URL from the config.
     * Called automatically on boot if consul.service.enabled is true.
     */
    public function register(): void
    {
        $service = config('consul.service');
        $healthCheck = config('consul.health_check');

        $definition = [
            'ID' => $service['id'],
            'Name' => $service['name'],
            'Address' => $service['host'],
            'Port' => $service['port'],
        ];

        if (! empty($service['tags'])) {
            $definition['Tags'] = $service['tags'];
        }

        if (! empty($service['meta'])) {
            $definition['Meta'] = $service['meta'];
        }

        if ($healthCheck['enabled'] ?? true) {
            $definition['Check'] = $this->buildCheckDefinition($service, $healthCheck);
        }

        $this->agent->registerService($definition);
    }

    /**
     * Deregister this application from Consul using the configured service ID.
     */
    public function deregister(): void
    {
        $this->agent->deregisterService(config('consul.service.id'));
    }

    /**
     * Send a TTL check-in to keep the service alive.
     * Only needed when using health_check.type = "ttl".
     *
     * @param  string|null  $note  Optional status note
     */
    public function passCheck(?string $note = null): void
    {
        $checkId = 'service:'.config('consul.service.id');
        $this->agent->passCheck($checkId, $note);
    }

    /**
     * Build the Consul check definition based on the configured type.
     */
    private function buildCheckDefinition(array $service, array $healthCheck): array
    {
        $type = $healthCheck['type'] ?? 'http';

        $check = [
            'DeregisterCriticalServiceAfter' => $healthCheck['deregister_after'] ?? '10m',
        ];

        match ($type) {
            'tcp' => $check += [
                'TCP' => "{$service['host']}:{$service['port']}",
                'Interval' => $healthCheck['interval'] ?? '15s',
                'Timeout' => $healthCheck['timeout'] ?? '5s',
            ],
            'script' => $check += [
                'Args' => $healthCheck['args'] ?? [],
                'Interval' => $healthCheck['interval'] ?? '15s',
                'Timeout' => $healthCheck['timeout'] ?? '5s',
            ],
            'ttl' => $check += [
                'TTL' => $healthCheck['ttl'] ?? '30s',
            ],
            'grpc' => $check += [
                'GRPC' => $healthCheck['grpc'] ?? "{$service['host']}:{$service['port']}",
                'Interval' => $healthCheck['interval'] ?? '15s',
                'Timeout' => $healthCheck['timeout'] ?? '5s',
            ],
            default => $check += [
                'HTTP' => $this->buildHealthUrl($service, $healthCheck),
                'Interval' => $healthCheck['interval'] ?? '15s',
                'Timeout' => $healthCheck['timeout'] ?? '5s',
            ],
        };

        return $check;
    }

    /**
     * Build the HTTP health check URL from config.
     * Uses the explicit scheme config instead of guessing from Consul's own address.
     */
    private function buildHealthUrl(array $service, array $healthCheck): string
    {
        $scheme = $healthCheck['scheme'] ?? 'http';

        return "$scheme://{$service['host']}:{$service['port']}{$healthCheck['endpoint']}";
    }

    // =========================================================================
    // Service Discovery (manual)
    // =========================================================================

    /**
     * Register a service with the local Consul agent.
     *
     * @param  string  $name  Service name
     * @param  int  $port  Service port
     * @param  array  $tags  Optional tags
     * @param  array  $meta  Optional metadata
     * @param  string|null  $id  Optional service ID (defaults to name)
     * @param  array  $check  Optional health check definition
     */
    public function registerService(
        string $name,
        int $port,
        array $tags = [],
        array $meta = [],
        ?string $id = null,
        array $check = [],
    ): void {
        $definition = [
            'Name' => $name,
            'Port' => $port,
        ];

        if ($id) {
            $definition['ID'] = $id;
        }
        if ($tags) {
            $definition['Tags'] = $tags;
        }
        if ($meta) {
            $definition['Meta'] = $meta;
        }
        if ($check) {
            $definition['Check'] = $check;
        }

        $this->agent->registerService($definition);
    }

    /**
     * Deregister a service from the local Consul agent.
     *
     * @param  string  $serviceId  Service ID to deregister
     */
    public function deregisterService(string $serviceId): void
    {
        $this->agent->deregisterService($serviceId);
    }

    /**
     * Get all services known to the catalog.
     *
     * @return array Services indexed by name
     *
     * @throws JsonException
     */
    public function services(): array
    {
        return $this->catalog->services()->json() ?? [];
    }

    /**
     * Get instances of a specific service.
     *
     * @param  string  $name  Service name
     * @return array List of service instances with their details
     *
     * @throws JsonException
     */
    public function service(string $name): array
    {
        return $this->catalog->service($name)->json() ?? [];
    }

    // =========================================================================
    // Health Checks
    // =========================================================================

    /**
     * Get healthy instances of a service.
     *
     * @param  string  $name  Service name
     * @return array List of healthy service instances
     */
    public function healthyService(string $name): array
    {
        try {
            return $this->health->service($name, ['passing' => true])->json() ?? [];
        } catch (Exception) {
            return [];
        }
    }

    /**
     * Check if a service has at least one healthy instance.
     *
     * @param  string  $name  Service name
     */
    public function isHealthy(string $name): bool
    {
        return count($this->healthyService($name)) > 0;
    }

    // =========================================================================
    // Sessions & Distributed Locking
    // =========================================================================

    /**
     * Create a new Consul session.
     *
     * @param  int  $ttlSeconds  Session TTL in seconds
     * @param  string|null  $name  Optional session name
     * @return string The session ID
     *
     * @throws JsonException
     */
    public function createSession(int $ttlSeconds = 60, ?string $name = null): string
    {
        $body = ['TTL' => $ttlSeconds.'s'];

        if ($name) {
            $body['Name'] = $name;
        }

        return $this->session->create($body)->json()['ID'];
    }

    /**
     * Destroy a Consul session.
     *
     * @param  string  $sessionId  Session ID to destroy
     */
    public function destroySession(string $sessionId): void
    {
        $this->session->destroy($sessionId);
    }

    /**
     * Acquire a distributed lock.
     *
     * @param  string  $lockKey  Key to lock on
     * @param  string  $sessionId  Session ID that owns the lock
     * @param  string  $value  Optional value to store with the lock
     * @return bool True if the lock was acquired
     *
     * @throws JsonException
     */
    public function acquireLock(string $lockKey, string $sessionId, string $value = ''): bool
    {
        return (bool) $this->kv->put(
            $this->prefixed($lockKey),
            $value,
            ['acquire' => $sessionId],
        )->json();
    }

    /**
     * Release a distributed lock.
     *
     * @param  string  $lockKey  Key to unlock
     * @param  string  $sessionId  Session ID that owns the lock
     * @return bool True if the lock was released
     *
     * @throws JsonException
     */
    public function releaseLock(string $lockKey, string $sessionId): bool
    {
        return (bool) $this->kv->put(
            $this->prefixed($lockKey),
            '',
            ['release' => $sessionId],
        )->json();
    }

    /**
     * Execute a callback while holding a distributed lock.
     * The lock is automatically released after the callback completes (or fails).
     *
     * @param  string  $lockKey  Key to lock on
     * @param  callable  $callback  Callback to execute while holding the lock
     * @param  int  $ttlSeconds  Lock/session TTL in seconds
     * @return mixed The callback's return value, or false if the lock couldn't be acquired
     *
     * @throws JsonException
     */
    public function withLock(string $lockKey, callable $callback, int $ttlSeconds = 60): mixed
    {
        $sessionId = $this->createSession($ttlSeconds, 'lock:'.$lockKey);

        if (! $this->acquireLock($lockKey, $sessionId)) {
            $this->destroySession($sessionId);

            return false;
        }

        try {
            return $callback();
        } finally {
            $this->releaseLock($lockKey, $sessionId);
            $this->destroySession($sessionId);
        }
    }

    // =========================================================================
    // Raw access
    // =========================================================================

    /** Get the underlying KV service */
    public function kv(): KV
    {
        return $this->kv;
    }

    /** Get the underlying Agent service */
    public function agent(): Agent
    {
        return $this->agent;
    }

    /** Get the underlying Catalog service */
    public function catalog(): Catalog
    {
        return $this->catalog;
    }

    /** Get the underlying Health service */
    public function health(): Health
    {
        return $this->health;
    }

    /** Get the underlying Session service */
    public function session(): Session
    {
        return $this->session;
    }

    // =========================================================================
    // Internal
    // =========================================================================

    /**
     * Apply the global KV prefix to a key.
     */
    private function prefixed(string $key): string
    {
        return $this->prefix.$key;
    }

    /**
     * Attempt to decode a value as JSON, returning the raw string if it fails.
     */
    private function decodeValue(?string $raw): mixed
    {
        if ($raw === null) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $raw;
    }
}
