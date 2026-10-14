<?php

declare(strict_types=1);

namespace Caiyun\Asr\Support;

/**
 * 读取 WAV 容器。讯飞 raw 编码只接受其中的 PCM 数据。
 */
class Wav
{
    public static function isWav(string $contents): bool
    {
        return str_starts_with($contents, 'RIFF') && substr($contents, 8, 4) === 'WAVE';
    }

    /**
     * 取出 data 块。不是 WAV 时原样返回。
     */
    public static function pcm(string $contents): string
    {
        $chunk = self::chunk($contents, 'data');

        return $chunk ?? $contents;
    }

    /**
     * 从 fmt 块读取采样率。无法识别时返回 null。
     */
    public static function sampleRate(string $contents): ?int
    {
        $chunk = self::chunk($contents, 'fmt ');
        if ($chunk === null || strlen($chunk) < 8) {
            return null;
        }

        $rate = unpack('V', substr($chunk, 4, 4));

        return is_array($rate) ? (int) $rate[1] : null;
    }

    private static function chunk(string $contents, string $name): ?string
    {
        if (! self::isWav($contents)) {
            return null;
        }

        $offset = 12;
        $length = strlen($contents);
        while ($offset + 8 <= $length) {
            $id = substr($contents, $offset, 4);
            $unpacked = unpack('V', substr($contents, $offset + 4, 4));
            $size = is_array($unpacked) ? (int) $unpacked[1] : 0;
            $offset += 8;
            if ($offset + $size > $length) {
                return null;
            }
            if ($id === $name) {
                return substr($contents, $offset, $size);
            }
            $offset += $size + ($size % 2);
        }

        return null;
    }
}
