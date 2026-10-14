<?php

declare(strict_types=1);

namespace Caiyun\Asr\Support;

/**
 * 讯飞 WebSocket 握手签名。date 使用 GMT，与服务器相差超过 5 分钟会被拒绝。
 */
class XunfeiSigner
{
    /**
     * 拼出带 authorization、date、host 的 wss 地址。
     */
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

    /**
     * 计算 authorization 参数。返回值是签名原文的 Base64，还不是完整 URL。
     */
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
