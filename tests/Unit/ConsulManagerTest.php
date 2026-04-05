<?php

use Consul\Services\Agent;
use Consul\Services\Catalog;
use Consul\Services\Health;
use Consul\Services\KV;
use Consul\Services\Session;
use Maestrodimateo\SimpleConsul\ConsulManager;
use Maestrodimateo\SimpleConsul\Facades\Consul;

// =============================================================================
// Service provider & binding
// =============================================================================

it('registers the ConsulManager as a singleton', function () {
    $instance1 = app(ConsulManager::class);
    $instance2 = app(ConsulManager::class);

    expect($instance1)->toBeInstanceOf(ConsulManager::class)
        ->and($instance1)->toBe($instance2);
});

it('resolves via the Facade', function () {
    expect(Consul::getFacadeRoot())->toBeInstanceOf(ConsulManager::class);
});

it('resolves via the helper', function () {
    expect(consul())->toBeInstanceOf(ConsulManager::class);
});

// =============================================================================
// Default values (no Consul needed)
// =============================================================================

it('returns the default when key does not exist', function () {
    expect(Consul::get('unit/nonexistent', 'fallback'))->toBe('fallback');
});

it('returns null when key does not exist and no default', function () {
    expect(Consul::get('unit/nonexistent'))->toBeNull();
});

it('returns false for has on nonexistent key', function () {
    expect(Consul::has('unit/nope'))->toBeFalse();
});

it('returns empty array when listing keys with no matches', function () {
    expect(Consul::keys('unit/empty-prefix/'))->toBeArray()->toBeEmpty();
});

it('returns false for health check on nonexistent service', function () {
    expect(Consul::isHealthy('nonexistent-service-xyz'))->toBeFalse();
});

// =============================================================================
// Raw SDK access
// =============================================================================

it('exposes the underlying SDK services', function () {
    expect(Consul::kv())->toBeInstanceOf(KV::class)
        ->and(Consul::agent())->toBeInstanceOf(Agent::class)
        ->and(Consul::catalog())->toBeInstanceOf(Catalog::class)
        ->and(Consul::health())->toBeInstanceOf(Health::class)
        ->and(Consul::session())->toBeInstanceOf(Session::class);
});
