<?php

declare(strict_types=1);

namespace Caiyun\Asr\Tests;

use Caiyun\Asr\Support\TencentSigner;
use PHPUnit\Framework\TestCase;

class TencentSignerTest extends TestCase
{
    public function test_tc3_signature_vector(): void
    {
        $signed = (new TencentSigner(
            'test-tencent-id',
            'test-tencent-key',
        ))->sign('{"EngSerViceType":"16k_zh"}', 'asr.tencentcloudapi.com', 'asr', 1551113065);

        $this->assertSame(1551113065, $signed['timestamp']);
        $this->assertSame(
            'TC3-HMAC-SHA256 Credential=test-tencent-id/2019-02-25/asr/tc3_request, SignedHeaders=content-type;host, Signature=0addaaa80d7fd4fc73ebd21e61fc73ea8a8b9066f31a1afab31f3b6c9bccb5bf',
            $signed['authorization'],
        );
    }
}
