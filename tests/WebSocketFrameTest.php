<?php

declare(strict_types=1);

namespace Caiyun\Asr\Tests;

use Caiyun\Asr\Http\WebSocketFrame;
use PHPUnit\Framework\TestCase;

class WebSocketFrameTest extends TestCase
{
    public function test_round_trip_masked_text(): void
    {
        $encoded = WebSocketFrame::encode('你好', 0x1, true, true, "\x01\x02\x03\x04");
        $frame = WebSocketFrame::tryDecode($encoded);

        $this->assertNotNull($frame);
        $this->assertTrue($frame['fin']);
        $this->assertSame(0x1, $frame['opcode']);
        $this->assertSame('你好', $frame['payload']);
        $this->assertSame(strlen($encoded), $frame['consumed']);
    }

    public function test_incomplete_buffer(): void
    {
        $encoded = WebSocketFrame::encode('hello', 0x1, true, false);
        $this->assertNull(WebSocketFrame::tryDecode(substr($encoded, 0, 1)));

        $frame = WebSocketFrame::tryDecode($encoded);
        $this->assertNotNull($frame);
        $this->assertSame('hello', $frame['payload']);
    }

    public function test_extended_length(): void
    {
        $payload = str_repeat('a', 200);
        $encoded = WebSocketFrame::encode($payload, 0x1, true, false);
        $frame = WebSocketFrame::tryDecode($encoded);

        $this->assertNotNull($frame);
        $this->assertSame($payload, $frame['payload']);
    }
}
