# caiyun/asr

彩云语音识别。用同一套调用方式接腾讯云、科大讯飞、阿里云的一句话识别，三家都处理 60 秒以内的短音频。

| 驱动 | 服务 | 协议 |
| --- | --- | --- |
| `tencent` | [腾讯云语音识别 · 一句话识别](https://cloud.tencent.com/document/product/1093/35646) | HTTPS + TC3 签名 |
| `xunfei` | [讯飞语音听写（流式版）](https://www.xfyun.cn/doc/asr/voicedictation/API.html) | WSS |
| `aliyun` | [阿里云智能语音交互 · 一句话识别](https://help.aliyun.com/zh/isi/developer-reference/restful-api-2) | HTTPS，自动换 Token |

## 安装

需要 PHP 8.3+、Laravel 11+，以及 `curl`、`json`、`openssl` 扩展。

```bash
composer require caiyun/asr
php artisan vendor:publish --provider="Caiyun\Asr\AsrServiceProvider" --tag=asr-config
```

Laravel 会自动注册服务提供者和 `Asr` 门面。配置会发布到业务项目的 `config/asr.php`。未发布时使用包内的默认配置；项目里已经有这个文件时，发布命令会保留原文件，需要覆盖再加 `--force`。

## 配置

```dotenv
ASR_DRIVER=tencent
ASR_TIMEOUT=60

ASR_TENCENT_SECRET_ID=
ASR_TENCENT_SECRET_KEY=
ASR_TENCENT_ENGINE=16k_zh

ASR_XUNFEI_APP_ID=
ASR_XUNFEI_API_KEY=
ASR_XUNFEI_API_SECRET=

ASR_ALIYUN_ACCESS_KEY_ID=
ASR_ALIYUN_ACCESS_KEY_SECRET=
ASR_ALIYUN_APP_KEY=
ASR_ALIYUN_REGION=cn-shanghai
```

`ASR_DRIVER` 取 `tencent`、`xunfei` 或 `aliyun`。8k 音频把腾讯云引擎改成 `8k_zh`。阿里云地域还可以是 `cn-beijing`、`cn-shenzhen`；如果已经有 NLS Token，配置里写 `ASR_ALIYUN_TOKEN`，不必再填 AccessKey。改完配置后如果开了配置缓存，执行 `php artisan config:cache`。

## 使用

```php
use Caiyun\Asr\Facades\Asr;

$result = Asr::recognize('/path/to/audio.wav');

echo $result->text();
$result->driver();
$result->requestId();
$result->raw();
```

本地路径直接传字符串。公网地址用 `Audio::url()`，上传得到的二进制用 `Audio::contents()`。字符串以 `http://` 或 `https://` 开头时也会按公网地址处理。

```php
use Caiyun\Asr\Audio;
use Caiyun\Asr\Facades\Asr;

Asr::driver('aliyun')->recognize(Audio::url('https://example.com/a.wav'));

Asr::recognize(Audio::contents($binary, [
    'format' => 'pcm',
    'sample_rate' => 16000,
]), [
    'driver' => 'xunfei',
]);
```

`driver()` 切换已配置的服务商。当次调用也可以在第二个参数里写 `'driver' => 'aliyun'`，这次调用结束后默认驱动不变。

wav 没有传 `sample_rate` 时，会从文件头读取采样率。

## 音频要求

- 腾讯云支持本地内容或公网 URL，格式包括 wav、pcm、mp3、m4a、aac、amr 等，文件不超过 3MB。
- 阿里云上传原始音频字节。采样率用 8000 或 16000，单声道 16bit。公网地址通过 `audio_address` 提交。
- 讯飞接收 pcm。wav 会去掉文件头再上传，mp3 按 `lame` 编码上传。默认每 40ms 发送 1280 字节，识别耗时接近音频时长。本地调试可以把 `frame_interval` 设为 `0`。

## 可选参数

调用时的 `$options` 会覆盖 `config/asr.php` 里的同名项。

腾讯云：`engine`、`region`、`filter_dirty`、`filter_modal`、`filter_punc`、`convert_num_mode`、`word_info`。

讯飞：`language`（默认 `zh_cn`）、`domain`（默认 `iat`）、`accent`（默认 `mandarin`）、`ptt`、`dwa`、`nunum`、`host`、`frame_size`、`frame_interval`。

阿里云：`sample_rate`、`enable_punctuation_prediction`（默认开）、`enable_inverse_text_normalization`（默认开）、`enable_voice_detection`、`disfluency`、`vocabulary_id`、`customization_id`。

通用：`format`、`sample_rate`、`driver`。

## 不使用 Laravel

把同一份配置数组交给 `Caiyun\Asr\Asr`。只配置一个驱动时可以不写 `default`。

```php
$asr = new \Caiyun\Asr\Asr([
    'default' => 'tencent',
    'timeout' => 60,
    'drivers' => [
        'tencent' => [
            'secret_id' => 'your-secret-id',
            'secret_key' => 'your-secret-key',
            'engine' => '16k_zh',
        ],
    ],
]);

$asr->recognize('/path/to/audio.wav');
```

## 测试

```bash
composer test
composer check
```
