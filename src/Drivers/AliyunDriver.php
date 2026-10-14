<?php

declare(strict_types=1);

namespace Caiyun\Asr\Drivers;

use Caiyun\Asr\Audio;
use Caiyun\Asr\Exceptions\ConfigException;
use Caiyun\Asr\Exceptions\RequestException;
use Caiyun\Asr\RecognitionResult;
use Caiyun\Asr\Support\AliyunSigner;
use Caiyun\Asr\Support\Json;

final class AliyunDriver extends AbstractDriver
{
    private const GATEWAYS = [
        'cn-shanghai' => 'nls-gateway-cn-shanghai.aliyuncs.com',
        'cn-beijing' => 'nls-gateway-cn-beijing.aliyuncs.com',
        'cn-shenzhen' => 'nls-gateway-cn-shenzhen.aliyuncs.com',
    ];

    private const SUCCESS = 20000000;

    private ?string $token = null;

    private int $tokenExpireAt = 0;

    protected function assertConfig(): void
    {
        $this->requireConfig('app_key');
        if ($this->configuredToken() === null) {
            $this->requireConfig('access_key_id', 'access_key_secret');
        }
    }

    public function recognize(Audio|string $audio, array $options = []): RecognitionResult
    {
        $audio = $this->audio($audio, $options);
        $query = [
            'appkey' => (string) $this->config['app_key'],
            'format' => $audio->format(),
            'sample_rate' => (string) $audio->sampleRate(),
            'enable_punctuation_prediction' => $this->boolOption('enable_punctuation_prediction', $options, true),
            'enable_inverse_text_normalization' => $this->boolOption('enable_inverse_text_normalization', $options, true),
        ];
        foreach (['enable_voice_detection', 'disfluency'] as $flag) {
            if (array_key_exists($flag, $options) || array_key_exists($flag, $this->config)) {
                $query[$flag] = $this->boolOption($flag, $options, false);
            }
        }
        foreach (['vocabulary_id', 'customization_id'] as $key) {
            $value = $options[$key] ?? $this->config[$key] ?? null;
            if (is_string($value) && $value !== '') {
                $query[$key] = $value;
            }
        }

        $body = '';
        if ($audio->isRemote()) {
            $query['audio_address'] = (string) $audio->remoteUrl();
        } else {
            $body = $audio->bytes();
        }

        $host = $this->gateway();
        $url = 'https://'.$host.'/stream/v1/asr?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $response = $this->http->request('POST', $url, [
            'X-NLS-Token' => $this->token(),
            'Content-Type' => 'application/octet-stream',
            'Host' => $host,
        ], $body, $this->httpOptions());

        $data = Json::decode($response['body']);
        $status = (int) ($data['status'] ?? 0);
        if ($response['status'] >= 400 || $status !== self::SUCCESS) {
            $message = (string) ($data['message'] ?? ('HTTP '.$response['status']));
            throw new RequestException('阿里云识别失败: '.$status.' '.$message);
        }

        return new RecognitionResult(
            (string) ($data['result'] ?? ''),
            $this->name,
            $data,
            isset($data['task_id']) ? (string) $data['task_id'] : null,
        );
    }

    private function token(): string
    {
        $configured = $this->configuredToken();
        if ($configured !== null) {
            return $configured;
        }
        if ($this->token !== null && $this->tokenExpireAt > time() + 60) {
            return $this->token;
        }

        $params = [
            'AccessKeyId' => (string) $this->config['access_key_id'],
            'Action' => 'CreateToken',
            'Version' => '2019-02-28',
            'Format' => 'JSON',
            'RegionId' => 'cn-shanghai',
            'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'SignatureMethod' => 'HMAC-SHA1',
            'SignatureVersion' => '1.0',
            'SignatureNonce' => $this->nonce(),
        ];
        $query = (new AliyunSigner)->signedQuery($params, (string) $this->config['access_key_secret']);
        $response = $this->http->request(
            'GET',
            'https://nls-meta.cn-shanghai.aliyuncs.com/?'.$query,
            [],
            '',
            $this->httpOptions(),
        );
        $data = Json::decode($response['body']);
        $tokenData = $data['Token'] ?? null;
        $token = is_array($tokenData) ? ($tokenData['Id'] ?? null) : null;
        if (! is_string($token) || $token === '') {
            $message = (string) ($data['Message'] ?? $data['ErrMsg'] ?? '未返回 Token');
            throw new RequestException('阿里云获取 Token 失败: '.$message);
        }

        $this->token = $token;
        $this->tokenExpireAt = (int) ($tokenData['ExpireTime'] ?? 0);

        return $token;
    }

    private function configuredToken(): ?string
    {
        $token = $this->config['token'] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }

    private function gateway(): string
    {
        $gateway = $this->config['gateway'] ?? null;
        if (is_string($gateway) && $gateway !== '') {
            return $gateway;
        }

        $region = (string) ($this->config['region'] ?? 'cn-shanghai');
        if (! isset(self::GATEWAYS[$region])) {
            throw new ConfigException('阿里云驱动不支持的地域: '.$region);
        }

        return self::GATEWAYS[$region];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function boolOption(string $key, array $options, bool $default): string
    {
        $value = $options[$key] ?? $this->config[$key] ?? $default;

        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
    }

    private function nonce(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
