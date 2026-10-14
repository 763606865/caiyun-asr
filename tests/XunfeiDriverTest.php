<?php

declare(strict_types=1);

namespace Caiyun\Asr\Tests;

use Caiyun\Asr\Audio;
use Caiyun\Asr\Drivers\XunfeiDriver;
use Caiyun\Asr\Exceptions\RequestException;
use Caiyun\Asr\Tests\Doubles\FakeHttpClient;
use Caiyun\Asr\Tests\Doubles\FakeWebSocketClient;
use PHPUnit\Framework\TestCase;

class XunfeiDriverTest extends TestCase
{
    public function test_strips_wav_and_parses_dynamic_correction(): void
    {
        $pcm = 'pcm-bytes';
        $wav = AudioTest::wav($pcm, 16000);
        $ws = new FakeWebSocketClient([
            json_encode([
                'code' => 0,
                'message' => 'success',
                'sid' => 'sid-1',
                'data' => [
                    'status' => 1,
                    'result' => [
                        'sn' => 1,
                        'ws' => [['cw' => [['w' => '测']]]],
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
            json_encode([
                'code' => 0,
                'message' => 'success',
                'data' => [
                    'status' => 2,
                    'result' => [
                        'sn' => 2,
                        'pgs' => 'rpl',
                        'rg' => [1, 1],
                        'ws' => [
                            ['cw' => [['w' => '测试']]],
                            ['cw' => [['w' => '一下']]],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
        ]);
        $driver = new XunfeiDriver('xunfei', [
            'app_id' => 'app',
            'api_key' => 'key',
            'api_secret' => 'secret',
        ], new FakeHttpClient([]), $ws);

        $result = $driver->recognize(Audio::contents($wav), ['frame_interval' => 0]);

        $this->assertSame('测试一下', $result->text());
        $this->assertSame('sid-1', $result->requestId());
        $this->assertCount(1, $ws->calls);
        $this->assertSame(0, $ws->calls[0]['intervalMs']);
        $this->assertStringStartsWith('wss://iat-api.xfyun.cn/v2/iat?', $ws->calls[0]['url']);

        $first = json_decode($ws->calls[0]['messages'][0], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('app', $first['common']['app_id']);
        $this->assertSame('zh_cn', $first['business']['language']);
        $this->assertSame('iat', $first['business']['domain']);
        $this->assertSame('mandarin', $first['business']['accent']);
        $this->assertSame(0, $first['data']['status']);
        $this->assertSame('raw', $first['data']['encoding']);
        $this->assertSame('audio/L16;rate=16000', $first['data']['format']);
        $this->assertSame($pcm, base64_decode($first['data']['audio'], true));

        $end = json_decode($ws->calls[0]['messages'][1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(2, $end['data']['status']);
        $this->assertSame('', $end['data']['audio']);
    }

    public function test_api_error(): void
    {
        $ws = new FakeWebSocketClient([
            '{"code":10105,"message":"invalid appid"}',
        ]);
        $driver = new XunfeiDriver('xunfei', [
            'app_id' => 'app',
            'api_key' => 'key',
            'api_secret' => 'secret',
        ], new FakeHttpClient([]), $ws);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('10105');
        $driver->recognize(Audio::contents('abc', ['format' => 'pcm']), ['frame_interval' => 0]);
    }
}
