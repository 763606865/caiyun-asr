<?php

declare(strict_types=1);

namespace Caiyun\Asr\Drivers;

use Caiyun\Asr\Audio;
use Caiyun\Asr\Contracts\DriverInterface;
use Caiyun\Asr\Contracts\HttpClientInterface;
use Caiyun\Asr\Exceptions\ConfigException;

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

    abstract protected function assertConfig(): void;

    /**
     * @param  array<string, mixed>  $options
     */
    protected function audio(Audio|string $audio, array $options): Audio
    {
        if (is_string($audio)) {
            $audio = preg_match('#^https?://#i', $audio) === 1 ? Audio::url($audio) : Audio::file($audio);
        }

        return $audio->with($options);
    }

    protected function timeout(): int
    {
        return (int) ($this->config['timeout'] ?? 60);
    }

    /**
     * @return array<string, mixed>
     */
    protected function httpOptions(): array
    {
        return [
            'timeout' => $this->timeout(),
            'verify_ssl' => (bool) ($this->config['verify_ssl'] ?? true),
        ];
    }

    protected function requireConfig(string ...$keys): void
    {
        foreach ($keys as $key) {
            if (! isset($this->config[$key]) || $this->config[$key] === '') {
                throw new ConfigException(sprintf('%s 驱动缺少配置项 %s', $this->name, $key));
            }
        }
    }
}
