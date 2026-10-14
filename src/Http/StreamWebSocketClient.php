<?php

declare(strict_types=1);

namespace Caiyun\Asr\Http;

use Caiyun\Asr\Contracts\WebSocketClientInterface;
use Caiyun\Asr\Exceptions\RequestException;

/**
 * 基于 PHP 流的 WebSocket 客户端，支持 ws 和 wss。讯飞握手会校验 Sec-WebSocket-Accept。
 */
class StreamWebSocketClient implements WebSocketClientInterface
{
    private const ACCEPT_GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    public function __construct(private readonly bool $verifySsl = true) {}

    public function exchange(string $url, array $messages, int $timeout = 60, int $intervalMs = 40): array
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['host'], $parts['path'])) {
            throw new RequestException('讯飞 WebSocket 地址无效');
        }

        $scheme = $parts['scheme'] ?? 'wss';
        $host = $parts['host'];
        $port = (int) ($parts['port'] ?? ($scheme === 'ws' ? 80 : 443));
        $target = $parts['path'].(isset($parts['query']) ? '?'.$parts['query'] : '');
        $transport = ($scheme === 'ws' ? 'tcp' : 'ssl').'://'.$host.':'.$port;
        $deadline = microtime(true) + max(1, $timeout);

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => $this->verifySsl,
                'verify_peer_name' => $this->verifySsl,
                'SNI_enabled' => true,
                'peer_name' => $host,
            ],
        ]);

        $errno = 0;
        $error = '';
        $socket = stream_socket_client($transport, $errno, $error, min(10, $timeout), STREAM_CLIENT_CONNECT, $context);
        if ($socket === false) {
            throw new RequestException('讯飞连接失败: '.$error);
        }

        stream_set_blocking($socket, true);
        try {
            $key = base64_encode(random_bytes(16));
            $this->writeAll($socket, $this->handshake($host, $port, $scheme, $target, $key));
            $buffer = '';
            $headers = $this->readHeaders($socket, $buffer, $deadline);
            $this->assertHandshake($headers, $key);

            $total = count($messages);
            foreach ($messages as $index => $message) {
                $this->writeAll($socket, WebSocketFrame::encode($message));
                if ($index < $total - 1 && $intervalMs > 0) {
                    usleep($intervalMs * 1000);
                }
            }

            return $this->collect($socket, $buffer, $deadline, true);
        } finally {
            fclose($socket);
        }
    }

    private function handshake(string $host, int $port, string $scheme, string $target, string $key): string
    {
        $hostHeader = $host;
        $defaultPort = $scheme === 'ws' ? 80 : 443;
        if ($port !== $defaultPort) {
            $hostHeader .= ':'.$port;
        }

        return "GET {$target} HTTP/1.1\r\n"
            ."Host: {$hostHeader}\r\n"
            ."Upgrade: websocket\r\n"
            ."Connection: Upgrade\r\n"
            ."Sec-WebSocket-Key: {$key}\r\n"
            ."Sec-WebSocket-Version: 13\r\n"
            ."\r\n";
    }

    private function assertHandshake(string $headers, string $key): void
    {
        if (preg_match('#^HTTP/1\.[01] 101\b#', $headers) !== 1) {
            $line = strtok($headers, "\r\n") ?: '未知响应';
            throw new RequestException('讯飞握手失败: '.$line);
        }

        if (preg_match('/^Sec-WebSocket-Accept:\s*(.+)$/mi', $headers, $matches) !== 1) {
            return;
        }

        $expected = base64_encode(sha1($key.self::ACCEPT_GUID, true));
        if (trim($matches[1]) !== $expected) {
            throw new RequestException('讯飞握手校验失败');
        }
    }

    /**
     * @param  resource  $socket
     * @return list<string>
     */
    private function collect($socket, string &$buffer, float $deadline, bool $untilFinal): array
    {
        $messages = [];
        $fragment = '';
        while (microtime(true) < $deadline) {
            $frame = $this->readFrame($socket, $buffer, $deadline);
            if ($frame === null) {
                break;
            }

            if ($frame['opcode'] === 0x8) {
                break;
            }
            if ($frame['opcode'] === 0x9) {
                $this->writeAll($socket, WebSocketFrame::encode($frame['payload'], 0xA));

                continue;
            }
            if ($frame['opcode'] === 0x0) {
                $fragment .= $frame['payload'];
                if (! $frame['fin']) {
                    continue;
                }
                $payload = $fragment;
                $fragment = '';
            } elseif (! $frame['fin']) {
                $fragment = $frame['payload'];

                continue;
            } else {
                $payload = $frame['payload'];
            }

            if (($frame['opcode'] !== 0x1 && $frame['opcode'] !== 0x0) || $payload === '') {
                continue;
            }

            $messages[] = $payload;
            if ($untilFinal && $this->isFinal($payload)) {
                break;
            }
        }

        return $messages;
    }

    private function isFinal(string $payload): bool
    {
        $data = json_decode($payload, true);

        return is_array($data) && (($data['data']['status'] ?? null) === 2 || ($data['data']['status'] ?? null) === '2');
    }

    /**
     * @param  resource  $socket
     * @return array{fin: bool, opcode: int, payload: string}|null
     */
    private function readFrame($socket, string &$buffer, float $deadline): ?array
    {
        while (true) {
            $frame = WebSocketFrame::tryDecode($buffer);
            if ($frame !== null) {
                $buffer = substr($buffer, $frame['consumed']);

                return [
                    'fin' => $frame['fin'],
                    'opcode' => $frame['opcode'],
                    'payload' => $frame['payload'],
                ];
            }
            if (microtime(true) >= $deadline) {
                return null;
            }

            $chunk = $this->readChunk($socket, $deadline);
            if ($chunk === null) {
                return null;
            }
            if ($chunk === '') {
                usleep(10000);

                continue;
            }
            $buffer .= $chunk;
        }
    }

    /**
     * @param  resource  $socket
     */
    private function readHeaders($socket, string &$buffer, float $deadline): string
    {
        while (! str_contains($buffer, "\r\n\r\n")) {
            if (microtime(true) >= $deadline || strlen($buffer) > 16384) {
                throw new RequestException('讯飞握手超时');
            }
            $chunk = $this->readChunk($socket, $deadline);
            if ($chunk === null) {
                throw new RequestException('讯飞握手失败，连接已关闭');
            }
            $buffer .= $chunk;
        }

        $position = strpos($buffer, "\r\n\r\n");
        $headers = substr($buffer, 0, (int) $position);
        $buffer = substr($buffer, (int) $position + 4);

        return $headers;
    }

    /**
     * @param  resource  $socket
     */
    private function readChunk($socket, float $deadline): ?string
    {
        $slice = min(1.0, max(0.05, $deadline - microtime(true)));
        $seconds = (int) $slice;
        stream_set_timeout($socket, $seconds, (int) (($slice - $seconds) * 1000000));
        $chunk = fread($socket, 8192);
        if ($chunk === false) {
            return null;
        }
        if ($chunk === '') {
            $meta = stream_get_meta_data($socket);

            return ! empty($meta['eof']) ? null : '';
        }

        return $chunk;
    }

    /**
     * @param  resource  $socket
     */
    private function writeAll($socket, string $data): void
    {
        $length = strlen($data);
        $written = 0;
        while ($written < $length) {
            $count = fwrite($socket, substr($data, $written));
            if ($count === false || $count === 0) {
                throw new RequestException('讯飞连接写入失败');
            }
            $written += $count;
        }
    }
}
