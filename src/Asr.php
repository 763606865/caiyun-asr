<?php

declare(strict_types=1);

namespace Caiyun\Asr;

use Caiyun\Asr\Contracts\DriverInterface;
use Caiyun\Asr\Contracts\HttpClientInterface;
use Caiyun\Asr\Contracts\WebSocketClientInterface;
use Caiyun\Asr\Drivers\AliyunDriver;
use Caiyun\Asr\Drivers\TencentDriver;
use Caiyun\Asr\Drivers\XunfeiDriver;
use Caiyun\Asr\Exceptions\ConfigException;
use Caiyun\Asr\Http\CurlClient;
use Caiyun\Asr\Http\StreamWebSocketClient;

final class Asr
{
    public const VERSION = '0.1.0';

    public const TENCENT = 'tencent';

    public const XUNFEI = 'xunfei';

    public const ALIYUN = 'aliyun';

    /** @var array<string, DriverInterface> */
    private array $drivers = [];

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config,
        private readonly ?HttpClientInterface $http = null,
        private readonly ?WebSocketClientInterface $websocket = null,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function recognize(Audio|string $audio, array $options = []): RecognitionResult
    {
        $driver = $options['driver'] ?? null;
        unset($options['driver']);

        return $this->driver(is_string($driver) ? $driver : null)->recognize($audio, $options);
    }

    public function driver(?string $name = null): DriverInterface
    {
        $name ??= $this->defaultDriver();
        if (isset($this->drivers[$name])) {
            return $this->drivers[$name];
        }

        $drivers = $this->config['drivers'] ?? null;
        if (! is_array($drivers) || ! isset($drivers[$name]) || ! is_array($drivers[$name])) {
            throw new ConfigException('未配置语音识别驱动: '.$name);
        }

        return $this->drivers[$name] = $this->make($name, $drivers[$name]);
    }

    private function defaultDriver(): string
    {
        $default = $this->config['default'] ?? null;
        if (is_string($default) && $default !== '') {
            return $default;
        }

        $drivers = $this->config['drivers'] ?? [];
        if (is_array($drivers) && count($drivers) === 1) {
            return (string) array_key_first($drivers);
        }

        throw new ConfigException('请在配置中设置 default 驱动');
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function make(string $name, array $config): DriverInterface
    {
        if (! isset($config['timeout']) && isset($this->config['timeout'])) {
            $config['timeout'] = $this->config['timeout'];
        }

        $http = $this->http ?? new CurlClient;

        return match ($name) {
            self::TENCENT => new TencentDriver($name, $config, $http),
            self::ALIYUN => new AliyunDriver($name, $config, $http),
            self::XUNFEI => new XunfeiDriver(
                $name,
                $config,
                $http,
                $this->websocket ?? new StreamWebSocketClient((bool) ($config['verify_ssl'] ?? true)),
            ),
            default => throw new ConfigException('不支持的语音识别驱动: '.$name.'，可选 tencent、xunfei、aliyun'),
        };
    }
}
