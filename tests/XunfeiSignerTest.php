<?php

declare(strict_types=1);

namespace Caiyun\Asr\Tests;

use Caiyun\Asr\Support\XunfeiSigner;
use PHPUnit\Framework\TestCase;

final class XunfeiSignerTest extends TestCase
{
    public function test_hmac_authorization(): void
    {
        $authorization = (new XunfeiSigner)->authorization(
            'test-key',
            'test-secret',
            'iat-api.xfyun.cn',
            'Wed, 10 Jul 2019 07:35:43 GMT',
            'GET /v2/iat HTTP/1.1',
        );

        $this->assertSame(
            'YXBpX2tleT0idGVzdC1rZXkiLCBhbGdvcml0aG09ImhtYWMtc2hhMjU2IiwgaGVhZGVycz0iaG9zdCBkYXRlIHJlcXVlc3QtbGluZSIsIHNpZ25hdHVyZT0icHNZazg4V2dvYnY5NkhFTnY2NzVZRWRsRktIdEppVS9FVzExSEN6N0ZJdz0i',
            $authorization,
        );
    }

    public function test_url_contains_signed_query(): void
    {
        $url = (new XunfeiSigner)->url(
            'test-key',
            'test-secret',
            'iat-api.xfyun.cn',
            '/v2/iat',
            'Wed, 10 Jul 2019 07:35:43 GMT',
        );

        $this->assertStringStartsWith('wss://iat-api.xfyun.cn/v2/iat?', $url);
        $this->assertStringContainsString('host=iat-api.xfyun.cn', $url);
        $this->assertStringContainsString(rawurlencode('Wed, 10 Jul 2019 07:35:43 GMT'), $url);
    }
}
