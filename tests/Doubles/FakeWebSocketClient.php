<?php

declare(strict_types=1);

namespace Caiyun\Asr\Tests\Doubles;

use Caiyun\Asr\Contracts\WebSocketClientInterface;

class FakeWebSocketClient implements WebSocketClientInterface
{
    /** @var list<array{url: string, messages: list<string>, timeout: int, intervalMs: int}> */
    public array $calls = [];

    /**
     * @param  list<string>  $frames
     */
    public function __construct(private readonly array $frames) {}

    public function exchange(string $url, array $messages, int $timeout = 60, int $intervalMs = 40): array
    {
        $this->calls[] = [
            'url' => $url,
            'messages' => $messages,
            'timeout' => $timeout,
            'intervalMs' => $intervalMs,
        ];

        return $this->frames;
    }
}
