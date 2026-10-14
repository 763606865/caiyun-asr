<?php

declare(strict_types=1);

namespace Caiyun\Asr\Contracts;

/**
 * HTTP 传输。生产环境使用 cURL 实现，测试可以换成不访问网络的替身。
 */
interface HttpClientInterface
{
    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $options
     * @return array{status: int, body: string, headers: array<string, string>}
     */
    public function request(string $method, string $url, array $headers = [], string $body = '', array $options = []): array;
}
