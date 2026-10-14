<?php

declare(strict_types=1);

namespace Caiyun\Asr\Support;

final class TencentSigner
{
    public function __construct(
        private readonly string $secretId,
        private readonly string $secretKey,
    ) {}

    /**
     * @return array{authorization: string, timestamp: int}
     */
    public function sign(string $payload, string $host, string $service, ?int $timestamp = null): array
    {
        $timestamp ??= time();
        $date = gmdate('Y-m-d', $timestamp);
        $contentType = 'application/json; charset=utf-8';
        $signedHeaders = 'content-type;host';
        $canonicalHeaders = 'content-type:'.$contentType."\n".'host:'.$host."\n";
        $hashedPayload = hash('sha256', $payload);
        $canonicalRequest = implode("\n", [
            'POST',
            '/',
            '',
            $canonicalHeaders,
            $signedHeaders,
            $hashedPayload,
        ]);
        $scope = $date.'/'.$service.'/tc3_request';
        $stringToSign = implode("\n", [
            'TC3-HMAC-SHA256',
            (string) $timestamp,
            $scope,
            hash('sha256', $canonicalRequest),
        ]);

        $secretDate = hash_hmac('sha256', $date, 'TC3'.$this->secretKey, true);
        $secretService = hash_hmac('sha256', $service, $secretDate, true);
        $secretSigning = hash_hmac('sha256', 'tc3_request', $secretService, true);
        $signature = hash_hmac('sha256', $stringToSign, $secretSigning);

        return [
            'authorization' => sprintf(
                'TC3-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
                $this->secretId,
                $scope,
                $signedHeaders,
                $signature,
            ),
            'timestamp' => $timestamp,
        ];
    }
}
