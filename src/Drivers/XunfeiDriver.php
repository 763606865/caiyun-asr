<?php

declare(strict_types=1);

namespace Caiyun\Asr\Drivers;

use Caiyun\Asr\Audio;
use Caiyun\Asr\Contracts\HttpClientInterface;
use Caiyun\Asr\Contracts\WebSocketClientInterface;
use Caiyun\Asr\Exceptions\RequestException;
use Caiyun\Asr\RecognitionResult;
use Caiyun\Asr\Support\Json;
use Caiyun\Asr\Support\Wav;
use Caiyun\Asr\Support\XunfeiSigner;

final class XunfeiDriver extends AbstractDriver
{
    public function __construct(
        string $name,
        array $config,
        HttpClientInterface $http,
        private readonly WebSocketClientInterface $websocket,
    ) {
        parent::__construct($name, $config, $http);
    }

    protected function assertConfig(): void
    {
        $this->requireConfig('app_id', 'api_key', 'api_secret');
    }

    public function recognize(Audio|string $audio, array $options = []): RecognitionResult
    {
        $audio = $this->audio($audio, $options);
        $binary = $this->binary($audio);
        $encoding = $this->encoding($audio);
        if ($encoding === 'raw' && ($audio->format() === 'wav' || Wav::isWav($binary))) {
            $binary = Wav::pcm($binary);
        }

        $host = (string) ($options['host'] ?? $this->config['host'] ?? 'iat-api.xfyun.cn');
        $path = (string) ($options['path'] ?? $this->config['path'] ?? '/v2/iat');
        $url = (new XunfeiSigner)->url(
            (string) $this->config['api_key'],
            (string) $this->config['api_secret'],
            $host,
            $path,
        );
        $frames = $this->websocket->exchange(
            $url,
            $this->messages($binary, $audio, $options, $encoding),
            $this->timeout(),
            (int) ($options['frame_interval'] ?? $this->config['frame_interval'] ?? 40),
        );

        return $this->result($frames);
    }

    private function binary(Audio $audio): string
    {
        if (! $audio->isRemote()) {
            return $audio->bytes();
        }

        $response = $this->http->request('GET', (string) $audio->remoteUrl(), [], '', $this->httpOptions());
        if ($response['status'] >= 400 || $response['body'] === '') {
            throw new RequestException('下载讯飞音频失败，HTTP '.$response['status']);
        }

        return $response['body'];
    }

    private function encoding(Audio $audio): string
    {
        return match ($audio->format()) {
            'mp3' => 'lame',
            'speex' => $audio->sampleRate() === 8000 ? 'speex' : 'speex-wb',
            default => 'raw',
        };
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<string>
     */
    private function messages(string $binary, Audio $audio, array $options, string $encoding): array
    {
        $frameSize = (int) ($options['frame_size'] ?? $this->config['frame_size'] ?? 1280);
        if ($frameSize < 1) {
            $frameSize = 1280;
        }

        $format = 'audio/L16;rate='.$audio->sampleRate();
        $chunks = str_split($binary, $frameSize);
        if ($chunks === []) {
            $chunks = [''];
        }

        $business = [
            'language' => (string) ($options['language'] ?? $this->config['language'] ?? 'zh_cn'),
            'domain' => (string) ($options['domain'] ?? $this->config['domain'] ?? 'iat'),
            'accent' => (string) ($options['accent'] ?? $this->config['accent'] ?? 'mandarin'),
        ];
        foreach (['eos', 'ptt', 'nunum', 'pcm', 'ltc', 'vinfo', 'nbest', 'wbest'] as $key) {
            if (array_key_exists($key, $options) || array_key_exists($key, $this->config)) {
                $business[$key] = (int) ($options[$key] ?? $this->config[$key]);
            }
        }
        foreach (['dwa', 'rlang'] as $key) {
            $value = $options[$key] ?? $this->config[$key] ?? null;
            if (is_string($value) && $value !== '') {
                $business[$key] = $value;
            }
        }

        $messages = [];
        $last = count($chunks) - 1;
        foreach ($chunks as $index => $chunk) {
            $status = $index === 0 ? 0 : ($index === $last ? 2 : 1);
            $data = [
                'status' => $status,
                'format' => $format,
                'encoding' => $encoding,
                'audio' => base64_encode($chunk),
            ];
            $payload = ['data' => $data];
            if ($index === 0) {
                $payload = [
                    'common' => ['app_id' => (string) $this->config['app_id']],
                    'business' => $business,
                    'data' => $data,
                ];
            }
            $messages[] = Json::encode($payload);
        }

        if (count($chunks) === 1) {
            $messages[] = Json::encode([
                'data' => [
                    'status' => 2,
                    'format' => $format,
                    'encoding' => $encoding,
                    'audio' => '',
                ],
            ]);
        }

        return $messages;
    }

    /**
     * @param  list<string>  $frames
     */
    private function result(array $frames): RecognitionResult
    {
        if ($frames === []) {
            throw new RequestException('讯飞未返回识别结果');
        }

        $pieces = [];
        $requestId = null;
        $raw = [];
        foreach ($frames as $frame) {
            $data = Json::decode($frame);
            $raw[] = $data;
            $code = (int) ($data['code'] ?? 0);
            if ($code !== 0) {
                throw new RequestException('讯飞识别失败: '.$code.' '.(string) ($data['message'] ?? ''));
            }
            if ($requestId === null && isset($data['sid'])) {
                $requestId = (string) $data['sid'];
            }

            $result = $data['data']['result'] ?? null;
            if (! is_array($result)) {
                continue;
            }
            $text = '';
            foreach ($result['ws'] ?? [] as $word) {
                if (is_array($word)) {
                    $text .= (string) ($word['cw'][0]['w'] ?? '');
                }
            }
            $sn = (int) ($result['sn'] ?? (count($pieces) + 1));
            if (($result['pgs'] ?? null) === 'rpl' && isset($result['rg'][0], $result['rg'][1])) {
                for ($i = (int) $result['rg'][0]; $i <= (int) $result['rg'][1]; $i++) {
                    unset($pieces[$i]);
                }
            }
            $pieces[$sn] = $text;
        }

        ksort($pieces);

        return new RecognitionResult(implode('', $pieces), $this->name, ['frames' => $raw], $requestId);
    }
}
