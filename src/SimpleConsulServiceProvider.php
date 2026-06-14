<?php

namespace Maestrodimateo\SimpleConsul;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class SimpleConsulServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/consul.php', 'consul');

        $this->app->singleton(ConsulManager::class, function () {
            $address = config('consul.address', 'http://127.0.0.1:8500');
            putenv("CONSUL_HTTP_ADDR=$address");

            $token = config('consul.token');
            if ($token) {
                putenv("CONSUL_HTTP_TOKEN=$token");
            }

            return new ConsulManager;
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/consul.php' => config_path('consul.php'),
            ], 'consul-config');
        }

        if (config('consul.service.enabled') && ! $this->isAlreadyRegistered()) {
            $this->autoRegister();
        }
    }

    /**
     * Register the service with Consul and persist a marker so subsequent
     * boots in the same container skip the call. The marker lives in tmpfs
     * (/tmp) and is wiped on container restart — fresh boot, fresh register.
     */
    private function autoRegister(): void
    {
        try {
            $this->app->make(ConsulManager::class)->register();
            $this->markAsRegistered();

            Log::info('Consul: service registered', [
                'id' => config('consul.service.id'),
                'name' => config('consul.service.name'),
            ]);
        } catch (Exception $e) {
            Log::warning('Consul: failed to register', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Decide whether the current boot must re-register or skip.
     *
     * Mode "once" (default): the first boot per container writes a marker
     * to tmpfs. Subsequent boots see it and return early — critical for
     * PHP-FPM where every HTTP request bootstraps Laravel afresh.
     *
     * Mode "always": never skip — appropriate for Octane, Swoole, RoadRunner,
     * or long-running daemons that boot Laravel once and live in memory.
     */
    private function isAlreadyRegistered(): bool
    {
        if ($this->registerMode() === 'always') {
            return false;
        }

        $marker = $this->markerPath();

        if (! file_exists($marker)) {
            return false;
        }

        $ttl = (int) config('consul.service.register_ttl_seconds', 3600);

        if ($ttl > 0 && (time() - filemtime($marker)) > $ttl) {
            @unlink($marker);

            return false;
        }

        return true;
    }

    private function markAsRegistered(): void
    {
        if ($this->registerMode() === 'always') {
            return;
        }

        @touch($this->markerPath());
    }

    private function registerMode(): string
    {
        return (string) config('consul.service.register_mode', 'once');
    }

    /**
     * Marker path is scoped by service ID — different services on the
     * same host get distinct markers and never collide.
     */
    private function markerPath(): string
    {
        $id = (string) (config('consul.service.id') ?? 'default');

        return sys_get_temp_dir().'/simple-consul-registered-'.md5($id);
    }
}
