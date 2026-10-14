<?php

declare(strict_types=1);

namespace Caiyun\Asr\Tests;

use Caiyun\Asr\Asr;
use Caiyun\Asr\Audio;
use Caiyun\Asr\Exceptions\ConfigException;
use Caiyun\Asr\Tests\Doubles\FakeHttpClient;
use Caiyun\Asr\Tests\Doubles\FakeWebSocketClient;
use PHPUnit\Framework\TestCase;

final class AsrTest extends TestCase
{
    public function test_uses_default_driver_and_can_switch(): void
    {
        $http = new FakeHttpClient([
            [
                'status' => 200,
                'body' => '{"Response":{"Result":"腾讯","RequestId":"t"}}',
            ],
            [
                'status' => 200,
                'body' => '{"task_id":"a","result":"阿里","status":20000000,"message":"SUCCESS"}',
            ],
        ]);
        $asr = new Asr([
            'default' => 'tencent',
            'drivers' => [
                'tencent' => ['secret_id' => 'id', 'secret_key' => 'key'],
                'aliyun' => ['app_key' => 'app', 'token' => 'token'],
            ],
        ], $http);

        $audio = Audio::contents('audio', ['format' => 'pcm']);
        $this->assertSame('腾讯', $asr->recognize($audio)->text());
        $this->assertSame('阿里', $asr->recognize($audio, ['driver' => Asr::ALIYUN])->text());
    }

    public function test_single_driver_does_not_need_default(): void
    {
        $http = new FakeHttpClient([
            [
                'status' => 200,
                'body' => '{"Response":{"Result":"只有腾讯","RequestId":"t"}}',
            ],
        ]);
        $asr = new Asr([
            'drivers' => [
                'tencent' => ['secret_id' => 'id', 'secret_key' => 'key'],
            ],
        ], $http);

        $this->assertSame('只有腾讯', $asr->recognize(Audio::contents('audio', ['format' => 'pcm']))->text());
    }

    public function test_xunfei_driver_is_wired(): void
    {
        $ws = new FakeWebSocketClient([
            '{"code":0,"sid":"s","data":{"status":2,"result":{"sn":1,"ws":[{"cw":[{"w":"讯飞"}]}]}}}',
        ]);
        $asr = new Asr([
            'default' => 'xunfei',
            'drivers' => [
                'xunfei' => [
                    'app_id' => 'app',
                    'api_key' => 'key',
                    'api_secret' => 'secret',
                ],
            ],
        ], new FakeHttpClient([]), $ws);

        $result = $asr->recognize(Audio::contents('abc', ['format' => 'pcm']), ['frame_interval' => 0]);

        $this->assertSame('讯飞', $result->text());
        $this->assertSame('xunfei', $result->driver());
    }

    public function test_missing_default(): void
    {
        $asr = new Asr([
            'drivers' => [
                'tencent' => ['secret_id' => 'id', 'secret_key' => 'key'],
                'aliyun' => ['app_key' => 'app', 'token' => 'token'],
            ],
        ], new FakeHttpClient([]));

        $this->expectException(ConfigException::class);
        $asr->recognize(Audio::contents('audio', ['format' => 'pcm']));
    }

    public function test_unknown_driver(): void
    {
        $asr = new Asr([
            'default' => 'baidu',
            'drivers' => [
                'baidu' => ['secret' => 'x'],
            ],
        ], new FakeHttpClient([]));

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('baidu');
        $asr->driver();
    }

    public function test_missing_credential(): void
    {
        $asr = new Asr([
            'default' => 'tencent',
            'drivers' => [
                'tencent' => ['secret_id' => 'id'],
            ],
        ], new FakeHttpClient([]));

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('secret_key');
        $asr->recognize(Audio::contents('audio', ['format' => 'pcm']));
    }
}
