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
            putenv("CONSUL_HTTP_ADDR={$address}");

            $token = config('consul.token');
            if ($token) {
                putenv("CONSUL_HTTP_TOKEN={$token}");
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

        // Auto-register with Consul if enabled
        if (config('consul.service.enabled')) {
            $this->autoRegister();
        }
    }

    /**
     * Register this application with Consul on boot, deregister on shutdown.
     */
    private function autoRegister(): void
    {
        try {
            /** @var ConsulManager $consul */
            $consul = $this->app->make(ConsulManager::class);
            $consul->register();

            Log::info('Consul: service registered', [
                'id' => config('consul.service.id'),
                'name' => config('consul.service.name'),
            ]);

            // Deregister on shutdown
            $this->app->terminating(function () use ($consul) {
                try {
                    $consul->deregister();
                    Log::info('Consul: service deregistered', ['id' => config('consul.service.id')]);
                } catch (Exception $e) {
                    Log::warning('Consul: failed to deregister', ['error' => $e->getMessage()]);
                }
            });
        } catch (Exception $e) {
            Log::warning('Consul: failed to register', ['error' => $e->getMessage()]);
        }
    }
}
