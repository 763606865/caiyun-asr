<?php

declare(strict_types=1);

namespace Caiyun\Asr\Drivers;

use Caiyun\Asr\Audio;
use Caiyun\Asr\Contracts\DriverInterface;
use Caiyun\Asr\Contracts\HttpClientInterface;
use Caiyun\Asr\Exceptions\ConfigException;

/**
 * 驱动公共逻辑：构造时校验配置，并把字符串解析成本地文件或公网地址。
 */
abstract class AbstractDriver implements DriverInterface
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected readonly string $name,
        protected readonly array $config,
        protected readonly HttpClientInterface $http,
    ) {
        $this->assertConfig();
    }

    /**
     * 检查当前驱动的必填配置，缺失时抛出 {@see ConfigException}。
     */
    abstract protected function assertConfig(): void;

    /**
     * 字符串以 http(s) 开头时视为公网地址，否则视为本地文件。
     *
     * @param  array<string, mixed>  $options
     */
    protected function audio(Audio|string $audio, array $options): Audio
    {
        if (is_string($audio)) {
            $audio = preg_match('#^https?://#i', $audio) === 1 ? Audio::url($audio) : Audio::file($audio);
        }

        return $audio->with($options);
    }

    /**
     * 单次请求超时，单位秒。未配置时为 60。
     */
    protected function timeout(): int
    {
        return (int) ($this->config['timeout'] ?? 60);
    }

    /**
     * 传给 HTTP 客户端的超时和证书校验开关。
     *
     * @return array{timeout: int, verify_ssl: bool}
     */
    protected function httpOptions(): array
    {
        return [
            'timeout' => $this->timeout(),
            'verify_ssl' => (bool) ($this->config['verify_ssl'] ?? true),
        ];
    }

    /**
     * 要求配置项存在且不是空字符串。值为 null 也视为缺失。
     */
    protected function requireConfig(string ...$keys): void
    {
        foreach ($keys as $key) {
            if (! isset($this->config[$key]) || $this->config[$key] === '') {
                throw new ConfigException(sprintf('%s 驱动缺少配置项 %s', $this->name, $key));
            }
        }
    }
}
