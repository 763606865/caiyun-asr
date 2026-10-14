<?php

declare(strict_types=1);

namespace Caiyun\Asr\Tests;

use Caiyun\Asr\Asr;
use Caiyun\Asr\AsrServiceProvider;
use Caiyun\Asr\Audio;
use Caiyun\Asr\Exceptions\ConfigException;
use Caiyun\Asr\Facades\Asr as AsrFacade;
use Illuminate\Foundation\Application;
use LogicException;
use Orchestra\Testbench\TestCase;

class FacadeTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [AsrServiceProvider::class];
    }

    public function test_facade_resolves_the_asr_binding(): void
    {
        $this->assertInstanceOf(Asr::class, AsrFacade::getFacadeRoot());
        $this->assertSame($this->application()->make(Asr::class), $this->application()->make('asr'));
    }

    public function test_facade_recognize_uses_the_package_config(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('secret_id');

        AsrFacade::recognize(Audio::contents('pcm-bytes', ['format' => 'pcm']));
    }

    private function application(): Application
    {
        if (! $this->app instanceof Application) {
            throw new LogicException('测试应用尚未初始化');
        }

        return $this->app;
    }
}
