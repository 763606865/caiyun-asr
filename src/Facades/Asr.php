<?php

declare(strict_types=1);

namespace Caiyun\Asr\Facades;

use Caiyun\Asr\Audio;
use Caiyun\Asr\Contracts\DriverInterface;
use Caiyun\Asr\RecognitionResult;
use Illuminate\Support\Facades\Facade;

/**
 * 语音识别门面。实际调用绑定在容器里的 {@see \Caiyun\Asr\Asr}。
 *
 * @method static RecognitionResult recognize(Audio|string $audio, array<string, mixed> $options = [])
 * @method static DriverInterface driver(?string $name = null)
 *
 * @see \Caiyun\Asr\Asr
 */
class Asr extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'asr';
    }
}
