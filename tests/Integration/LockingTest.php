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
// Sessions
// =============================================================================

it('can create and destroy a session', function () {
    $sessionId = Consul::createSession(ttlSeconds: 30, name: 'test-session');

    expect($sessionId)->toBeString()->not->toBeEmpty();

    Consul::destroySession($sessionId);
})->group('integration');

// =============================================================================
// Distributed Locking
// =============================================================================

it('can acquire and release a lock', function () {
    $sessionId = Consul::createSession(30);

    $acquired = Consul::acquireLock('integration/lock', $sessionId);
    expect($acquired)->toBeTrue();

    $released = Consul::releaseLock('integration/lock', $sessionId);
    expect($released)->toBeTrue();

    Consul::destroySession($sessionId);
    Consul::delete('integration/lock');
})->group('integration');

it('can execute a callback with a lock', function () {
    $result = Consul::withLock('integration/with-lock', function () {
        return 'executed';
    }, ttlSeconds: 30);

    expect($result)->toBe('executed');

    Consul::delete('integration/with-lock');
})->group('integration');

it('returns false when lock cannot be acquired', function () {
    $session1 = Consul::createSession(30);
    Consul::acquireLock('integration/contested', $session1);

    // Another attempt should fail
    $result = Consul::withLock('integration/contested', fn () => 'nope');

    expect($result)->toBeFalse();

    Consul::releaseLock('integration/contested', $session1);
    Consul::destroySession($session1);
    Consul::delete('integration/contested');
})->group('integration');

it('releases the lock even when the callback throws', function () {
    try {
        Consul::withLock('integration/exception', function () {
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
        // expected
    }

    // Lock should be released — new lock should work
    $result = Consul::withLock('integration/exception', fn () => 'ok');
    expect($result)->toBe('ok');

    Consul::delete('integration/exception');
})->group('integration');
