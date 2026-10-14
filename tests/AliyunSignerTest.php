<?php

declare(strict_types=1);

namespace Caiyun\Asr\Tests;

use Caiyun\Asr\Support\AliyunSigner;
use PHPUnit\Framework\TestCase;

class AliyunSignerTest extends TestCase
{
    public function test_official_create_token_vector(): void
    {
        $query = (new AliyunSigner)->signedQuery([
            'AccessKeyId' => 'my_access_key_id',
            'Action' => 'CreateToken',
            'Version' => '2019-02-28',
            'Timestamp' => '2019-04-18T08:32:31Z',
            'Format' => 'JSON',
            'RegionId' => 'cn-shanghai',
            'SignatureMethod' => 'HMAC-SHA1',
            'SignatureVersion' => '1.0',
            'SignatureNonce' => 'b924c8c3-6d03-4c5d-ad36-d984d3116788',
        ], 'my_access_key_secret');

        $this->assertSame(
            'Signature=hHq4yNsPitlfDJ2L0nQPdugdEzM%3D&AccessKeyId=my_access_key_id&Action=CreateToken&Format=JSON&RegionId=cn-shanghai&SignatureMethod=HMAC-SHA1&SignatureNonce=b924c8c3-6d03-4c5d-ad36-d984d3116788&SignatureVersion=1.0&Timestamp=2019-04-18T08%3A32%3A31Z&Version=2019-02-28',
            $query,
        );
    }
}
