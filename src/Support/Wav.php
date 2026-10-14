<?php

declare(strict_types=1);

namespace Caiyun\Asr\Support;

final class Wav
{
    public static function isWav(string $contents): bool
    {
        return str_starts_with($contents, 'RIFF') && substr($contents, 8, 4) === 'WAVE';
    }

    public static function pcm(string $contents): string
    {
        $chunk = self::chunk($contents, 'data');

        return $chunk ?? $contents;
    }

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
