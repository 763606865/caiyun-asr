<?php

declare(strict_types=1);

namespace Caiyun\Asr\Contracts;

interface HttpClientInterface
{
    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $options
     * @return array{status: int, body: string, headers: array<string, string>}
     */
    public function request(string $method, string $url, array $headers = [], string $body = '', array $options = []): array;
}
