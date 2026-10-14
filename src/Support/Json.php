<?php

declare(strict_types=1);

namespace Caiyun\Asr\Support;

use Caiyun\Asr\Exceptions\RequestException;
use JsonException;

final class Json
{
    public static function encode(mixed $value): string
    {
        try {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RequestException('请求参数无法编码为 JSON', 0, $e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function decode(string $json): array
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RequestException('响应不是合法 JSON', 0, $e);
        }

        if (! is_array($data)) {
            throw new RequestException('响应 JSON 不是对象');
        }

        /** @var array<string, mixed> $data */
        return $data;
    }
}
