<?php

declare(strict_types=1);

namespace Caiyun\Asr\Tests;

use Caiyun\Asr\AsrServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Testing\PendingCommand;
use LogicException;
use Orchestra\Testbench\TestCase;

final class ConfigPublishingTest extends TestCase
{
    private string $configDirectory;

    protected function getPackageProviders($app): array
    {
        return [AsrServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $this->configDirectory = sys_get_temp_dir().'/caiyun-asr-config-'.bin2hex(random_bytes(8));
        (new Filesystem)->makeDirectory($this->configDirectory, 0755, true);
        $app->useConfigPath($this->configDirectory);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->beforeApplicationDestroyed(function (): void {
            (new Filesystem)->deleteDirectory($this->configDirectory);
        });
    }

    public function test_artisan_publishes_asr_configuration(): void
    {
        $this->publishConfiguration();
        $path = $this->application()->configPath('asr.php');

        $this->assertFileExists($path);
        $this->assertSame(file_get_contents(dirname(__DIR__).'/config/asr.php'), file_get_contents($path));

        $config = require $path;
        $this->assertSame('tencent', $config['default']);
        $this->assertSame(['tencent', 'xunfei', 'aliyun'], array_keys($config['drivers']));
    }

    public function test_existing_configuration_is_preserved_unless_forced(): void
    {
        $path = $this->application()->configPath('asr.php');
        $existing = "<?php\nreturn ['default' => 'aliyun'];\n";
        file_put_contents($path, $existing);

        $this->publishConfiguration();
        $this->assertSame($existing, file_get_contents($path));

        $this->publishConfiguration(true);
        $this->assertSame(file_get_contents(dirname(__DIR__).'/config/asr.php'), file_get_contents($path));
    }

    public function test_package_config_is_available_before_publishing(): void
    {
        $this->assertSame('tencent', config('asr.default'));
        $this->assertSame('16k_zh', config('asr.drivers.tencent.engine'));
        $this->assertSame('cn-shanghai', config('asr.drivers.aliyun.region'));
    }

    private function application(): Application
    {
        if (! $this->app instanceof Application) {
            throw new LogicException('测试应用尚未初始化');
        }

        return $this->app;
    }

    private function publishConfiguration(bool $force = false): void
    {
        $arguments = ['--provider' => AsrServiceProvider::class, '--tag' => 'asr-config'];
        if ($force) {
            $arguments['--force'] = true;
        }

        $command = $this->artisan('vendor:publish', $arguments);
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->assertExitCode(0);
    }
}
