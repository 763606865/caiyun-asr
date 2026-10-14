<?php

declare(strict_types=1);

namespace Caiyun\Asr\Tests;

use Caiyun\Asr\Audio;
use Caiyun\Asr\Drivers\TencentDriver;
use Caiyun\Asr\Exceptions\RequestException;
use Caiyun\Asr\Tests\Doubles\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class TencentDriverTest extends TestCase
{
    public function test_recognize_local_audio(): void
    {
        $http = new FakeHttpClient([
            [
                'status' => 200,
                'body' => json_encode([
                    'Response' => [
                        'Result' => '今天天气不错',
                        'RequestId' => 'req-1',
                    ],
                ], JSON_THROW_ON_ERROR),
            ],
        ]);
        $driver = new TencentDriver('tencent', [
            'secret_id' => 'id',
            'secret_key' => 'key',
            'engine' => '16k_zh',
        ], $http);

        $result = $driver->recognize(Audio::contents('hello-wav', ['format' => 'wav']), ['filter_punc' => 1]);

        $this->assertSame('今天天气不错', $result->text());
        $this->assertSame('req-1', $result->requestId());
        $this->assertSame('POST', $http->calls[0]['method']);
        $this->assertSame('https://asr.tencentcloudapi.com', $http->calls[0]['url']);
        $this->assertSame('SentenceRecognition', $http->calls[0]['headers']['X-TC-Action']);
        $this->assertStringStartsWith('TC3-HMAC-SHA256 ', $http->calls[0]['headers']['Authorization']);

        $payload = json_decode($http->calls[0]['body'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('16k_zh', $payload['EngSerViceType']);
        $this->assertSame(1, $payload['SourceType']);
        $this->assertSame('wav', $payload['VoiceFormat']);
        $this->assertSame(base64_encode('hello-wav'), $payload['Data']);
        $this->assertSame(strlen('hello-wav'), $payload['DataLen']);
        $this->assertSame(1, $payload['FilterPunc']);
    }

    public function test_recognize_url(): void
    {
        $http = new FakeHttpClient([
            [
                'status' => 200,
                'body' => '{"Response":{"Result":"链接音频","RequestId":"req-2"}}',
            ],
        ]);
        $driver = new TencentDriver('tencent', [
            'secret_id' => 'id',
            'secret_key' => 'key',
        ], $http);

        $result = $driver->recognize('https://example.com/a.mp3');

        $this->assertSame('链接音频', $result->text());
        $payload = json_decode($http->calls[0]['body'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(0, $payload['SourceType']);
        $this->assertSame('https://example.com/a.mp3', $payload['Url']);
        $this->assertSame('mp3', $payload['VoiceFormat']);
        $this->assertArrayNotHasKey('Data', $payload);
    }

    public function test_api_error(): void
    {
        $http = new FakeHttpClient([
            [
                'status' => 200,
                'body' => '{"Response":{"Error":{"Code":"AuthFailure.SignatureFailure","Message":"签名错误"},"RequestId":"req-e"}}',
            ],
        ]);
        $driver = new TencentDriver('tencent', [
            'secret_id' => 'id',
            'secret_key' => 'key',
        ], $http);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('AuthFailure.SignatureFailure');
        $driver->recognize(Audio::contents('audio', ['format' => 'pcm']));
    }
}
