<?php

declare(strict_types=1);

namespace Caiyun\Asr\Tests\Doubles;

use Caiyun\Asr\Contracts\HttpClientInterface;
use RuntimeException;

final class FakeHttpClient implements HttpClientInterface
{
    /** @var list<array{method: string, url: string, headers: array<string, string>, body: string}> */
    public array $calls = [];

    /**
     * @param  list<array{status: int, body: string, headers?: array<string, string>}>  $responses
     */
    public function __construct(private array $responses) {}

    public function request(string $method, string $url, array $headers = [], string $body = '', array $options = []): array
    {
        $this->calls[] = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
        ];

        $response = array_shift($this->responses);
        if ($response === null) {
            throw new RuntimeException('没有更多伪造响应');
        }

        return [
            'status' => $response['status'],
            'body' => $response['body'],
            'headers' => $response['headers'] ?? [],
        ];
    }
}
