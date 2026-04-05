<?php

use Maestrodimateo\SimpleConsul\ConsulManager;

if (! function_exists('consul')) {
    /**
     * Get the Consul manager instance.
     *
     * @example consul()->get('config/app/debug')
     * @example consul()->put('config/app/name', 'MyApp')
     */
    function consul(): ConsulManager
    {
        return app(ConsulManager::class);
    }
}
