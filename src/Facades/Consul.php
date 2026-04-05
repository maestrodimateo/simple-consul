<?php

namespace Maestrodimateo\SimpleConsul\Facades;

use Illuminate\Support\Facades\Facade;
use Maestrodimateo\SimpleConsul\ConsulManager;

/**
 * @method static mixed get(string $key, mixed $default = null)
 * @method static bool put(string $key, mixed $value)
 * @method static bool delete(string $key)
 * @method static bool has(string $key)
 * @method static array keys(string $prefix = '')
 * @method static void register()
 * @method static void deregister()
 * @method static void registerService(string $name, int $port, array $tags = [], array $meta = [], ?string $id = null, array $check = [])
 * @method static void deregisterService(string $serviceId)
 * @method static array services()
 * @method static array service(string $name)
 * @method static array healthyService(string $name)
 * @method static bool isHealthy(string $name)
 * @method static string createSession(int $ttlSeconds = 60, ?string $name = null)
 * @method static void destroySession(string $sessionId)
 * @method static bool acquireLock(string $lockKey, string $sessionId, string $value = '')
 * @method static bool releaseLock(string $lockKey, string $sessionId)
 * @method static mixed withLock(string $lockKey, callable $callback, int $ttlSeconds = 60)
 * @method static \Consul\Services\KV kv()
 * @method static \Consul\Services\Agent agent()
 * @method static \Consul\Services\Catalog catalog()
 * @method static \Consul\Services\Health health()
 * @method static \Consul\Services\Session session()
 *
 * @see ConsulManager
 */
class Consul extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ConsulManager::class;
    }
}
