<?php

declare(strict_types=1);

namespace Caiyun\Asr;

use Illuminate\Support\ServiceProvider;

class AsrServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/asr.php', 'asr');

        $this->app->singleton(Asr::class, function ($app): Asr {
            $config = $app['config']->get('asr', []);

            return new Asr(is_array($config) ? $config : []);
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/asr.php' => $this->app->configPath('asr.php'),
            ], 'asr-config');
        }
    }
}
