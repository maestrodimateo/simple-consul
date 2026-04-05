<?php

namespace Maestrodimateo\SimpleConsul;

use Illuminate\Support\ServiceProvider;

class SimpleConsulServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/consul.php', 'consul');

        $this->app->singleton(ConsulManager::class, function () {
            // Set the Consul HTTP address as an environment variable
            // so the underlying SDK picks it up automatically
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
    }
}
