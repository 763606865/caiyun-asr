<?php

declare(strict_types=1);

namespace Caiyun\Asr\Tests;

use Caiyun\Asr\Http\StreamWebSocketClient;
use PHPUnit\Framework\TestCase;

class StreamWebSocketClientTest extends TestCase
{
    public function test_exchange_with_local_server(): void
    {
        $addressFile = tempnam(sys_get_temp_dir(), 'asr-ws');
        $this->assertNotFalse($addressFile);
        unlink($addressFile);

        $script = tempnam(sys_get_temp_dir(), 'asr-ws-server');
        $this->assertNotFalse($script);
        file_put_contents($script, $this->serverScript());

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open(
            [PHP_BINARY, $script, $addressFile, dirname(__DIR__).'/vendor/autoload.php'],
            $descriptors,
            $pipes,
        );
        $this->assertIsResource($process);

        try {
            $address = $this->waitForAddress($addressFile);
            $client = new StreamWebSocketClient;
            $frames = $client->exchange('ws://'.$address.'/v2/iat', ['{"data":{"status":2}}'], 5, 0);

            $this->assertCount(1, $frames);
            $payload = json_decode($frames[0], true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('本地', $payload['data']['result']['ws'][0]['cw'][0]['w']);
        } finally {
            proc_terminate($process);
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            @unlink($script);
            @unlink($addressFile);
            if (($frames ?? null) === null) {
                $this->fail("WebSocket 服务未完成。stdout: {$stdout} stderr: {$stderr}");
            }
        }
    }

    private function waitForAddress(string $addressFile): string
    {
        for ($i = 0; $i < 50; $i++) {
            if (is_file($addressFile) && filesize($addressFile) > 0) {
                $address = trim((string) file_get_contents($addressFile));
                if ($address !== '') {
                    return $address;
                }
            }
            usleep(20000);
        }

        $this->fail('测试用 WebSocket 服务没有开始监听');
    }

    private function serverScript(): string
    {
        return <<<'PHP'
<?php
require $argv[2];

$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if ($server === false) {
    fwrite(STDERR, $error);
    exit(1);
}
file_put_contents($argv[1], (string) stream_socket_get_name($server, false));
$client = stream_socket_accept($server, 5);
if ($client === false) {
    fwrite(STDERR, "accept failed\n");
    exit(1);
}
$buffer = '';
while (!str_contains($buffer, "\r\n\r\n")) {
    $chunk = fread($client, 8192);
    if ($chunk === false || $chunk === '') {
        fwrite(STDERR, "handshake read failed\n");
        exit(1);
    }
    $buffer .= $chunk;
}
preg_match('/Sec-WebSocket-Key:\s*(.+)\r/i', $buffer, $matches);
$accept = base64_encode(sha1(trim($matches[1]) . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
fwrite($client, "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: {$accept}\r\n\r\n");
$position = strpos($buffer, "\r\n\r\n");
$buffer = substr($buffer, $position + 4);
while (\Caiyun\Asr\Http\WebSocketFrame::tryDecode($buffer) === null) {
    $chunk = fread($client, 8192);
    if ($chunk === false || $chunk === '') {
        fwrite(STDERR, "frame read failed\n");
        exit(1);
    }
    $buffer .= $chunk;
}
$reply = json_encode([
    'code' => 0,
    'data' => [
        'status' => 2,
        'result' => ['ws' => [['cw' => [['w' => '本地']]]]],
    ],
], JSON_UNESCAPED_UNICODE);
fwrite($client, \Caiyun\Asr\Http\WebSocketFrame::encode($reply, 0x1, true, false));
fclose($client);
fclose($server);
PHP;
    }
}
