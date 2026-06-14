<?php

use Maestrodimateo\SimpleConsul\ConsulManager;
use Maestrodimateo\SimpleConsul\SimpleConsulServiceProvider;

/**
 * Tests for the register_mode gate ("once" vs "always") added in v1.3.
 * No real Consul agent involved — we mock ConsulManager::register() to count calls.
 */
beforeEach(function () {
    // Service ID baked into the marker filename; isolate each test.
    $this->serviceId = 'unit-test-'.uniqid('', true);

    config([
        'consul.service.enabled' => true,
        'consul.service.id' => $this->serviceId,
        'consul.service.name' => 'unit-test',
        'consul.service.register_mode' => 'once',
        'consul.service.register_ttl_seconds' => 3600,
    ]);

    $this->markerPath = sys_get_temp_dir().'/simple-consul-registered-'.md5($this->serviceId);

    // Replace the manager binding with a spy that counts register() calls.
    $this->registerCount = 0;
    $spy = Mockery::mock(ConsulManager::class);
    $spy->shouldReceive('register')->andReturnUsing(function () {
        $this->registerCount++;
    });
    $this->app->instance(ConsulManager::class, $spy);
});

afterEach(function () {
    @unlink($this->markerPath);
});

/**
 * Re-runs the provider's boot logic. Mirrors what Laravel does on each fresh boot
 * — we just call the public boot() again for the test.
 */
function rebootProvider(): void
{
    (new SimpleConsulServiceProvider(app()))->boot();
}

// =============================================================================
// Mode "once" (default)
// =============================================================================

it('registers exactly once across multiple boots in "once" mode', function () {
    rebootProvider();
    rebootProvider();
    rebootProvider();

    expect($this->registerCount)->toBe(1)
        ->and(file_exists($this->markerPath))->toBeTrue();
});

it('re-registers when the marker file is missing', function () {
    rebootProvider();
    expect($this->registerCount)->toBe(1);

    @unlink($this->markerPath);

    rebootProvider();
    expect($this->registerCount)->toBe(2);
});

it('re-registers when the marker is older than the configured TTL', function () {
    config(['consul.service.register_ttl_seconds' => 1]);

    rebootProvider();
    expect($this->registerCount)->toBe(1);

    // Backdate the marker past the TTL.
    touch($this->markerPath, time() - 10);

    rebootProvider();
    expect($this->registerCount)->toBe(2);
});

it('never expires the marker when TTL is set to 0', function () {
    config(['consul.service.register_ttl_seconds' => 0]);

    rebootProvider();
    expect($this->registerCount)->toBe(1);

    // Backdate to a value that would expire any positive TTL.
    touch($this->markerPath, time() - 999_999);

    rebootProvider();
    expect($this->registerCount)->toBe(1);
});

it('isolates markers per service ID', function () {
    rebootProvider();
    $firstMarker = $this->markerPath;

    // Simulate a different service in the same process — a new ID, a new marker.
    $newId = 'unit-test-other-'.uniqid('', true);
    config(['consul.service.id' => $newId]);

    rebootProvider();
    expect($this->registerCount)->toBe(2)
        ->and(file_exists($firstMarker))->toBeTrue()
        ->and(file_exists(sys_get_temp_dir().'/simple-consul-registered-'.md5($newId)))->toBeTrue();

    @unlink(sys_get_temp_dir().'/simple-consul-registered-'.md5($newId));
});

// =============================================================================
// Mode "always"
// =============================================================================

it('registers on every boot in "always" mode', function () {
    config(['consul.service.register_mode' => 'always']);

    rebootProvider();
    rebootProvider();
    rebootProvider();

    expect($this->registerCount)->toBe(3);
});

it('does not write a marker in "always" mode', function () {
    config(['consul.service.register_mode' => 'always']);

    rebootProvider();

    expect(file_exists($this->markerPath))->toBeFalse();
});

// =============================================================================
// Disabled service
// =============================================================================

it('does not register when the service is disabled', function () {
    config(['consul.service.enabled' => false]);

    rebootProvider();

    expect($this->registerCount)->toBe(0)
        ->and(file_exists($this->markerPath))->toBeFalse();
});
