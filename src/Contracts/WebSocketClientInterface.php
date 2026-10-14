<?php

declare(strict_types=1);

namespace Caiyun\Asr\Contracts;

/**
 * WebSocket 传输，目前供讯飞流式听写上传音频帧。
 */
interface WebSocketClientInterface
{
    /**
     * 建立连接、按顺序发送文本帧，并返回服务端文本帧。
     *
     * @param  list<string>  $messages
     * @return list<string>
     */
    public function exchange(string $url, array $messages, int $timeout = 60, int $intervalMs = 40): array;
}
