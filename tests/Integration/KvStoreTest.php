<?php

/**
 * Integration tests — require a running Consul agent on 127.0.0.1:8500.
 *
 * Run with: ./vendor/bin/pest --group=integration
 * Skip with: ./vendor/bin/pest --exclude-group=integration
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
// KV Store
// =============================================================================

it('can put and get a string value', function () {
    Consul::put('integration/string', 'hello world');

    expect(Consul::get('integration/string'))->toBe('hello world');

    Consul::delete('integration/string');
})->group('integration');

it('can put and get an array value with auto JSON encoding', function () {
    $data = ['host' => 'localhost', 'port' => 5432];

    Consul::put('integration/array', $data);
    $result = Consul::get('integration/array');

    expect($result)->toBeArray()
        ->and($result['host'])->toBe('localhost')
        ->and($result['port'])->toBe(5432);

    Consul::delete('integration/array');
})->group('integration');

it('can check if a key exists', function () {
    Consul::put('integration/exists', 'yes');

    expect(Consul::has('integration/exists'))->toBeTrue()
        ->and(Consul::has('integration/nope'))->toBeFalse();

    Consul::delete('integration/exists');
})->group('integration');

it('can delete a key', function () {
    Consul::put('integration/to-delete', 'bye');

    expect(Consul::delete('integration/to-delete'))->toBeTrue()
        ->and(Consul::has('integration/to-delete'))->toBeFalse();
})->group('integration');

it('can list keys by prefix', function () {
    Consul::put('integration/list/a', '1');
    Consul::put('integration/list/b', '2');
    Consul::put('integration/list/c', '3');

    $keys = Consul::keys('integration/list/');

    expect($keys)->toContain('integration/list/a')
        ->and($keys)->toContain('integration/list/b')
        ->and($keys)->toContain('integration/list/c');

    Consul::delete('integration/list/a');
    Consul::delete('integration/list/b');
    Consul::delete('integration/list/c');
})->group('integration');

it('applies the configured prefix to keys', function () {
    consul()->put('integration/prefixed', 'value');

    // Read via raw SDK — the key should include the global prefix
    $raw = consul()->kv()->get('test/simple-consul/integration/prefixed', ['raw' => true]);

    expect($raw->getBody())->toBe('value');

    consul()->delete('integration/prefixed');
})->group('integration');
