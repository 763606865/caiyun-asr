<?php

declare(strict_types=1);

namespace Caiyun\Asr\Tests;

use Caiyun\Asr\Audio;
use Caiyun\Asr\Drivers\AliyunDriver;
use Caiyun\Asr\Exceptions\ConfigException;
use Caiyun\Asr\Exceptions\RequestException;
use Caiyun\Asr\Tests\Doubles\FakeHttpClient;
use PHPUnit\Framework\TestCase;

class AliyunDriverTest extends TestCase
{
    public function test_recognize_with_given_token(): void
    {
        $http = new FakeHttpClient([
            [
                'status' => 200,
                'body' => '{"task_id":"task-1","result":"阿里云识别","status":20000000,"message":"SUCCESS"}',
            ],
        ]);
        $driver = new AliyunDriver('aliyun', [
            'app_key' => 'app',
            'token' => 'nls-token',
            'region' => 'cn-beijing',
        ], $http);

        $result = $driver->recognize(Audio::contents('wav-bytes', ['format' => 'wav', 'sample_rate' => 16000]));

        $this->assertSame('阿里云识别', $result->text());
        $this->assertSame('task-1', $result->requestId());
        $this->assertCount(1, $http->calls);
        $this->assertStringStartsWith('https://nls-gateway-cn-beijing.aliyuncs.com/stream/v1/asr?', $http->calls[0]['url']);
        $this->assertStringContainsString('appkey=app', $http->calls[0]['url']);
        $this->assertStringContainsString('format=wav', $http->calls[0]['url']);
        $this->assertStringContainsString('sample_rate=16000', $http->calls[0]['url']);
        $this->assertStringContainsString('enable_punctuation_prediction=true', $http->calls[0]['url']);
        $this->assertSame('nls-token', $http->calls[0]['headers']['X-NLS-Token']);
        $this->assertSame('wav-bytes', $http->calls[0]['body']);
    }

    public function test_creates_and_caches_token(): void
    {
        $http = new FakeHttpClient([
            [
                'status' => 200,
                'body' => json_encode([
                    'Token' => ['Id' => 'fresh-token', 'ExpireTime' => time() + 3600],
                ], JSON_THROW_ON_ERROR),
            ],
            [
                'status' => 200,
                'body' => '{"task_id":"t1","result":"第一句","status":20000000,"message":"SUCCESS"}',
            ],
            [
                'status' => 200,
                'body' => '{"task_id":"t2","result":"第二句","status":20000000,"message":"SUCCESS"}',
            ],
        ]);
        $driver = new AliyunDriver('aliyun', [
            'app_key' => 'app',
            'access_key_id' => 'ak',
            'access_key_secret' => 'sk',
        ], $http);

        $this->assertSame('第一句', $driver->recognize(Audio::contents('a', ['format' => 'pcm']))->text());
        $this->assertSame('第二句', $driver->recognize(Audio::contents('b', ['format' => 'pcm']))->text());

        $this->assertCount(3, $http->calls);
        $this->assertSame('GET', $http->calls[0]['method']);
        $this->assertStringContainsString('Action=CreateToken', $http->calls[0]['url']);
        $this->assertSame('fresh-token', $http->calls[1]['headers']['X-NLS-Token']);
        $this->assertSame('fresh-token', $http->calls[2]['headers']['X-NLS-Token']);
    }

    public function test_remote_audio_uses_address(): void
    {
        $http = new FakeHttpClient([
            [
                'status' => 200,
                'body' => '{"task_id":"t","result":"网址","status":20000000,"message":"SUCCESS"}',
            ],
        ]);
        $driver = new AliyunDriver('aliyun', [
            'app_key' => 'app',
            'token' => 'nls-token',
        ], $http);

        $driver->recognize('https://example.com/a.wav');

        $this->assertStringContainsString('audio_address='.rawurlencode('https://example.com/a.wav'), $http->calls[0]['url']);
        $this->assertSame('', $http->calls[0]['body']);
    }

    public function test_business_error(): void
    {
        $http = new FakeHttpClient([
            [
                'status' => 400,
                'body' => '{"status":40000001,"message":"Gateway:ACCESS_DENIED"}',
            ],
        ]);
        $driver = new AliyunDriver('aliyun', [
            'app_key' => 'app',
            'token' => 'nls-token',
        ], $http);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('ACCESS_DENIED');
        $driver->recognize(Audio::contents('a', ['format' => 'pcm']));
    }

    public function test_rejects_unknown_region(): void
    {
        $driver = new AliyunDriver('aliyun', [
            'app_key' => 'app',
            'token' => 'nls-token',
            'region' => 'cn-hangzhou',
        ], new FakeHttpClient([]));

        $this->expectException(ConfigException::class);
        $driver->recognize(Audio::contents('a', ['format' => 'pcm']));
    }
}
