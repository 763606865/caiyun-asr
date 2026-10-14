<?php

declare(strict_types=1);

namespace Caiyun\Asr\Http;

use Caiyun\Asr\Asr;
use Caiyun\Asr\Contracts\HttpClientInterface;
use Caiyun\Asr\Exceptions\RequestException;

final class CurlClient implements HttpClientInterface
{
    public function request(string $method, string $url, array $headers = [], string $body = '', array $options = []): array
    {
        $method = strtoupper($method);
        if ($method === '') {
            throw new RequestException('HTTP 方法不能为空');
        }

        $handle = curl_init($url);
        if ($handle === false) {
            throw new RequestException('无法初始化 HTTP 请求');
        }

        $headerLines = [];
        $hasUserAgent = false;
        foreach ($headers as $name => $value) {
            if (strcasecmp($name, 'User-Agent') === 0) {
                $hasUserAgent = true;
            }
            $headerLines[] = $name.': '.$value;
        }
        if (! $hasUserAgent) {
            $headerLines[] = 'User-Agent: caiyun-asr/'.Asr::VERSION;
        }

        $verify = (bool) ($options['verify_ssl'] ?? true);
        $curlOptions = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => (int) ($options['timeout'] ?? 60),
            CURLOPT_SSL_VERIFYPEER => $verify,
            CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
        ];
        if ($body !== '' || in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $curlOptions[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($handle, $curlOptions);
        $raw = curl_exec($handle);
        if (! is_string($raw)) {
            $error = curl_error($handle);
            curl_close($handle);
            throw new RequestException('HTTP 请求失败: '.$error);
        }

        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE);
        curl_close($handle);

        return [
            'status' => $status,
            'headers' => self::headers(substr($raw, 0, $headerSize)),
            'body' => substr($raw, $headerSize),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function headers(string $raw): array
    {
        $headers = [];
        foreach (preg_split("/\r\n|\n|\r/", $raw) ?: [] as $line) {
            $position = strpos($line, ':');
            if ($position === false) {
                continue;
            }
            $headers[strtolower(trim(substr($line, 0, $position)))] = trim(substr($line, $position + 1));
        }

        return $headers;
    }
}
