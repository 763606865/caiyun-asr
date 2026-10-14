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

/**
 * 语音识别入口。按配置选择腾讯云、讯飞或阿里云，识别 60 秒以内的短音频。
 */
class Asr
{
    /** 包版本，同时作为 HTTP User-Agent。 */
    public const VERSION = '0.1.0';

    /** 腾讯云驱动名，对应配置键 drivers.tencent。 */
    public const TENCENT = 'tencent';

    /** 讯飞驱动名，对应配置键 drivers.xunfei。 */
    public const XUNFEI = 'xunfei';

    /** 阿里云驱动名，对应配置键 drivers.aliyun。 */
    public const ALIYUN = 'aliyun';

    /** @var array<string, DriverInterface> */
    private array $drivers = [];

    /**
     * @param  array<string, mixed>  $config  default、timeout 和 drivers
     * @param  HttpClientInterface|null  $http  不传则使用 cURL。测试可注入替身
     * @param  WebSocketClientInterface|null  $websocket  讯飞使用。不传则使用流式客户端
     */
    public function __construct(
        private readonly array $config,
        private readonly ?HttpClientInterface $http = null,
        private readonly ?WebSocketClientInterface $websocket = null,
    ) {}

    /**
     * 识别一段音频。
     *
     * 字符串以 http:// 或 https:// 开头时按公网地址处理，否则按本地文件路径处理。
     * `$options['driver']` 可临时切换驱动，其余键会覆盖该驱动配置里的同名项。
     *
     * @param  array<string, mixed>  $options
     */
    public function recognize(Audio|string $audio, array $options = []): RecognitionResult
    {
        $driver = $options['driver'] ?? null;
        unset($options['driver']);

        return $this->driver(is_string($driver) ? $driver : null)->recognize($audio, $options);
    }

    /**
     * 取得一个驱动实例。不传名称时使用 default；只配置了一个驱动时可以不写 default。
     */
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
