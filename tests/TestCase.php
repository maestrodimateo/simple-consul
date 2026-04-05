<?php

namespace Maestrodimateo\SimpleConsul\Tests;

use Maestrodimateo\SimpleConsul\Facades\Consul;
use Maestrodimateo\SimpleConsul\SimpleConsulServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [SimpleConsulServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Consul' => Consul::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('consul.address', 'http://127.0.0.1:8500');
        $app['config']->set('consul.kv_prefix', 'test/simple-consul/');
    }
}
