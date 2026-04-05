<?php

/**
 * Integration tests — require a running Consul agent on 127.0.0.1:8500.
 *
 * Run with: ./vendor/bin/pest --group=integration
 */

use Maestrodimateo\SimpleConsul\Facades\Consul;

beforeEach(function () {
    try {
        Consul::put('_health_check', 'ok');
        Consul::delete('_health_check');
    } catch (Exception) {
        $this->markTestSkipped('Consul agent not available on 127.0.0.1:8500');
    }
});

// =============================================================================
// Service Registration
// =============================================================================

it('can register and deregister a service', function () {
    Consul::registerService(
        name: 'test-svc',
        port: 9999,
        tags: ['test'],
        id: 'test-svc-1',
    );

    $services = Consul::services();
    expect($services)->toHaveKey('test-svc');

    Consul::deregisterService('test-svc-1');
})->group('integration');

it('can get instances of a service', function () {
    Consul::registerService(name: 'test-instance', port: 8888, id: 'test-instance-1');

    $instances = Consul::service('test-instance');
    expect($instances)->toBeArray()->not->toBeEmpty();

    Consul::deregisterService('test-instance-1');
})->group('integration');

// =============================================================================
// Health Checks
// =============================================================================

it('can check if consul itself is healthy', function () {
    expect(Consul::isHealthy('consul'))->toBeTrue();
})->group('integration');
