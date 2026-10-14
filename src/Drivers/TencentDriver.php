<?php

declare(strict_types=1);

namespace Caiyun\Asr\Drivers;

use Caiyun\Asr\Audio;
use Caiyun\Asr\Exceptions\RequestException;
use Caiyun\Asr\RecognitionResult;
use Caiyun\Asr\Support\Json;
use Caiyun\Asr\Support\TencentSigner;

/**
 * 腾讯云一句话识别。使用 TC3-HMAC-SHA256 签名，支持本地音频和公网 URL。
 */
class TencentDriver extends AbstractDriver
{
    private const HOST = 'asr.tencentcloudapi.com';

    protected function assertConfig(): void
    {
        $this->requireConfig('secret_id', 'secret_key');
    }

    public function recognize(Audio|string $audio, array $options = []): RecognitionResult
    {
        $audio = $this->audio($audio, $options);
        $payload = $this->payload($audio, $options);
        $body = Json::encode($payload);
        $signed = (new TencentSigner((string) $this->config['secret_id'], (string) $this->config['secret_key']))
            ->sign($body, self::HOST, 'asr');

        $headers = [
            'Authorization' => $signed['authorization'],
            'Content-Type' => 'application/json; charset=utf-8',
            'Host' => self::HOST,
            'X-TC-Action' => 'SentenceRecognition',
            'X-TC-Timestamp' => (string) $signed['timestamp'],
            'X-TC-Version' => '2019-06-14',
        ];
        $region = (string) ($options['region'] ?? $this->config['region'] ?? '');
        if ($region !== '') {
            $headers['X-TC-Region'] = $region;
        }

        $response = $this->http->request('POST', 'https://'.self::HOST, $headers, $body, $this->httpOptions());
        $data = Json::decode($response['body']);
        $result = $data['Response'] ?? null;
        if (! is_array($result)) {
            throw new RequestException('腾讯云响应缺少 Response');
        }
        if (isset($result['Error']) && is_array($result['Error'])) {
            $code = (string) ($result['Error']['Code'] ?? 'Unknown');
            $message = (string) ($result['Error']['Message'] ?? '请求失败');
            throw new RequestException('腾讯云识别失败: '.$code.' '.$message);
        }
        if (! array_key_exists('Result', $result)) {
            throw new RequestException('腾讯云响应缺少识别结果');
        }

        return new RecognitionResult(
            (string) $result['Result'],
            $this->name,
            $data,
            isset($result['RequestId']) ? (string) $result['RequestId'] : null,
        );
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function payload(Audio $audio, array $options): array
    {
        $payload = [
            'ProjectId' => 0,
            // 腾讯云字段名官方即为 EngSerViceType（V 大写）。
            'EngSerViceType' => (string) ($options['engine'] ?? $this->config['engine'] ?? '16k_zh'),
            'SourceType' => $audio->isRemote() ? 0 : 1,
            'VoiceFormat' => $audio->format(),
        ];

        if ($audio->isRemote()) {
            $payload['Url'] = (string) $audio->remoteUrl();
        } else {
            $contents = $audio->bytes();
            $payload['Data'] = base64_encode($contents);
            $payload['DataLen'] = strlen($contents);
        }

        $map = [
            'filter_dirty' => 'FilterDirty',
            'filter_modal' => 'FilterModal',
            'filter_punc' => 'FilterPunc',
            'convert_num_mode' => 'ConvertNumMode',
            'word_info' => 'WordInfo',
        ];
        foreach ($map as $from => $to) {
            if (array_key_exists($from, $options)) {
                $payload[$to] = (int) $options[$from];
            } elseif (array_key_exists($from, $this->config)) {
                $payload[$to] = (int) $this->config[$from];
            }
        }

        return $payload;
    }
}
