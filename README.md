# caiyun/asr

彩云语音识别，用同一套调用方式接腾讯云、科大讯飞、阿里云的一句话识别。

三家都处理 60 秒以内的短音频：

| 驱动 | 服务 | 协议 |
| --- | --- | --- |
| `tencent` | [腾讯云语音识别 · 一句话识别](https://cloud.tencent.com/document/product/1093/35646) | HTTPS + TC3 签名 |
| `xunfei` | [讯飞语音听写（流式版）](https://www.xfyun.cn/doc/asr/voicedictation/API.html) | WSS |
| `aliyun` | [阿里云智能语音交互 · 一句话识别](https://help.aliyun.com/zh/isi/developer-reference/restful-api-2) | HTTPS，自动换 Token |

## 安装

```bash
composer require caiyun/asr
```

需要 PHP 8.3+、Laravel 11+，以及 `curl`、`json`、`openssl` 扩展。

## 配置

安装后把配置发布到业务项目的 `config/asr.php`：

```bash
php artisan vendor:publish --provider="Caiyun\Asr\AsrServiceProvider" --tag=asr-config
```

未发布时也会加载包内的默认配置。发布命令默认保留已经存在的 `config/asr.php`；需要覆盖时加上 `--force`。

凭证放在环境变量里，例如：

```dotenv
ASR_DRIVER=tencent
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

Laravel 里可以直接解析：

```php
$asr = app(\Caiyun\Asr\Asr::class);
```

不使用 Laravel 时，把同样的数组传给构造函数。只配置一个驱动时可以不写 `default`。阿里云如果手里已经有 NLS Token，写 `token` 即可，不必再配 AccessKey。

## 调用

```php
use Caiyun\Asr\Audio;

$result = $asr->recognize('/path/to/audio.wav');
echo $result->text();

$result = $asr->driver('aliyun')->recognize(Audio::url('https://example.com/a.wav'));

$result = $asr->recognize(Audio::contents($binary, [
    'format' => 'pcm',
    'sample_rate' => 16000,
]), [
    'driver' => 'xunfei',
]);
```

`recognize()` 的返回值包含识别文本、驱动名、厂商请求号和原始响应：

```php
$result->text();
$result->driver();
$result->requestId();
$result->raw();
```

字符串参数以 `http://` 或 `https://` 开头时按音频地址处理，否则按本地文件路径处理。

## 音频

- 腾讯云支持本地内容或公网 URL，格式包括 wav、pcm、mp3、m4a、aac、amr 等，文件不超过 3MB。
- 阿里云上传原始音频字节。采样率用 8000 或 16000，单声道 16bit。公网地址通过 `audio_address` 提交。
- 讯飞流式接口接收 pcm。wav 会去掉文件头再上传；mp3 按 `lame` 编码上传。默认每 40ms 发送 1280 字节，和官方建议一致，所以识别耗时接近音频时长。本地调试可以把 `frame_interval` 设为 `0`。

wav 未指定 `sample_rate` 时，会从头里读取采样率。

## 可选参数

调用时的 `$options` 会覆盖驱动配置里的同名项。

腾讯云：`engine`、`region`、`filter_dirty`、`filter_modal`、`filter_punc`、`convert_num_mode`、`word_info`。

讯飞：`language`（默认 `zh_cn`）、`domain`（默认 `iat`）、`accent`（默认 `mandarin`）、`ptt`、`dwa`、`nunum`、`host`、`frame_size`、`frame_interval`。

阿里云：`sample_rate`、`enable_punctuation_prediction`（默认开）、`enable_inverse_text_normalization`（默认开）、`enable_voice_detection`、`disfluency`、`vocabulary_id`、`customization_id`。

通用：`format`、`sample_rate`、`driver`。

## 测试

```bash
composer test
```
