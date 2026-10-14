<?php

declare(strict_types=1);

namespace Caiyun\Asr;

use Caiyun\Asr\Exceptions\AsrException;
use Caiyun\Asr\Support\Wav;

final class Audio
{
    private function __construct(
        private readonly ?string $contents,
        private readonly ?string $url,
        private readonly string $format,
        private readonly ?int $rate,
    ) {}

    /**
     * @param  array<string, mixed>  $options  format、sample_rate
     */
    public static function file(string $path, array $options = []): self
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new AsrException('音频文件不存在或不可读: '.$path);
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new AsrException('读取音频失败: '.$path);
        }

        return self::contents($contents, $options + ['format' => self::extension($path)]);
    }

    /**
     * @param  array<string, mixed>  $options  format、sample_rate
     */
    public static function contents(string $binary, array $options = []): self
    {
        if ($binary === '') {
            throw new AsrException('音频内容为空');
        }

        return new self(
            $binary,
            null,
            self::normalizeFormat(self::optionString($options, 'format') ?? 'wav'),
            self::optionRate($options),
        );
    }

    /**
     * @param  array<string, mixed>  $options  format、sample_rate
     */
    public static function url(string $url, array $options = []): self
    {
        if (preg_match('#^https?://#i', $url) !== 1) {
            throw new AsrException('音频地址必须是 http 或 https: '.$url);
        }

        $format = self::optionString($options, 'format') ?? self::extension($url) ?? 'wav';

        return new self(null, $url, self::normalizeFormat($format), self::optionRate($options));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function with(array $options): self
    {
        $format = self::optionString($options, 'format') ?? $this->format;
        $rate = array_key_exists('sample_rate', $options) ? self::optionRate($options) : $this->rate;

        return new self($this->contents, $this->url, self::normalizeFormat($format), $rate);
    }

    public function isRemote(): bool
    {
        return $this->url !== null;
    }

    public function remoteUrl(): ?string
    {
        return $this->url;
    }

    public function bytes(): string
    {
        if ($this->contents === null) {
            throw new AsrException('当前音频没有本地内容');
        }

        return $this->contents;
    }

    public function format(): string
    {
        return $this->format;
    }

    public function sampleRate(): int
    {
        if ($this->rate !== null) {
            return $this->rate;
        }

        if ($this->contents !== null && $this->format === 'wav') {
            return Wav::sampleRate($this->contents) ?? 16000;
        }

        return 16000;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private static function optionString(array $options, string $key): ?string
    {
        $value = $options[$key] ?? null;
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private static function optionRate(array $options): ?int
    {
        if (! array_key_exists('sample_rate', $options) || $options['sample_rate'] === null || $options['sample_rate'] === '') {
            return null;
        }

        return (int) $options['sample_rate'];
    }

    private static function extension(string $path): ?string
    {
        $target = parse_url($path, PHP_URL_PATH);
        $extension = strtolower(pathinfo(is_string($target) ? $target : $path, PATHINFO_EXTENSION));

        return $extension === '' ? null : $extension;
    }

    private static function normalizeFormat(string $format): string
    {
        $format = strtolower($format);

        return $format === 'wave' ? 'wav' : $format;
    }
}
