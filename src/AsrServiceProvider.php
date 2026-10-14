<?php

declare(strict_types=1);

namespace Caiyun\Asr;

use Illuminate\Support\ServiceProvider;

/**
 * 合并 config/asr.php，把 Asr 注册为单例和 asr 别名，并在控制台发布配置文件。
 */
class AsrServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/asr.php', 'asr');

        $this->app->singleton(Asr::class, function ($app): Asr {
            $config = $app['config']->get('asr', []);

            return new Asr(is_array($config) ? $config : []);
        });
        $this->app->alias(Asr::class, 'asr');
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
