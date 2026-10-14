<?php

declare(strict_types=1);

namespace Caiyun\Asr\Support;

final class XunfeiSigner
{
    public function url(
        string $apiKey,
        string $apiSecret,
        string $host,
        string $path = '/v2/iat',
        ?string $date = null,
    ): string {
        $date ??= gmdate('D, d M Y H:i:s').' GMT';
        $requestLine = 'GET '.$path.' HTTP/1.1';
        $authorization = $this->authorization($apiKey, $apiSecret, $host, $date, $requestLine);
        $query = http_build_query([
            'authorization' => $authorization,
            'date' => $date,
            'host' => $host,
        ], '', '&', PHP_QUERY_RFC3986);

        return 'wss://'.$host.$path.'?'.$query;
    }

    public function authorization(
        string $apiKey,
        string $apiSecret,
        string $host,
        string $date,
        string $requestLine,
    ): string {
        $origin = 'host: '.$host."\n".'date: '.$date."\n".$requestLine;
        $signature = base64_encode(hash_hmac('sha256', $origin, $apiSecret, true));
        $authorization = sprintf(
            'api_key="%s", algorithm="hmac-sha256", headers="host date request-line", signature="%s"',
            $apiKey,
            $signature,
        );

        return base64_encode($authorization);
    }
}
