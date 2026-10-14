<?php

declare(strict_types=1);

namespace Caiyun\Asr\Contracts;

use Caiyun\Asr\Audio;
use Caiyun\Asr\Drivers\AbstractDriver;
use Caiyun\Asr\RecognitionResult;

/**
 * 识别驱动。新增服务商时实现本接口，或继承 {@see AbstractDriver}。
 */
interface DriverInterface
{
    /**
     * 识别一段 60 秒以内的短音频。
     *
     * @param  array<string, mixed>  $options  覆盖该驱动配置中的同名项，例如 format、sample_rate
     */
    public function recognize(Audio|string $audio, array $options = []): RecognitionResult;
}
