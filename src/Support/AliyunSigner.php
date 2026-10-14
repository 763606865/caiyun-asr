<?php

declare(strict_types=1);

namespace Caiyun\Asr\Support;

/**
 * 阿里云 RPC 签名，用于换取智能语音交互的 Token。
 */
class AliyunSigner
{
    /**
     * 生成带 Signature 的查询字符串。参数按阿里云 RPC 规则排序并编码。
     *
     * @param  array<string, string>  $params
     */
    public function signedQuery(array $params, string $accessKeySecret, string $method = 'GET'): string
    {
        ksort($params);
        $pairs = [];
        foreach ($params as $key => $value) {
            $pairs[] = self::percentEncode((string) $key).'='.self::percentEncode($value);
        }
        $canonical = implode('&', $pairs);
        $stringToSign = strtoupper($method).'&'.self::percentEncode('/').'&'.self::percentEncode($canonical);
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $accessKeySecret.'&', true));

        return 'Signature='.self::percentEncode($signature).'&'.$canonical;
    }

    /**
     * 按阿里云规则编码：空格为 %20，星号为 %2A，波浪号保持原样。
     */
    public static function percentEncode(string $value): string
    {
        return str_replace(['+', '*', '%7E'], ['%20', '%2A', '~'], urlencode($value));
    }
}
