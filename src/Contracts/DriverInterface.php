<?php

declare(strict_types=1);

namespace Caiyun\Asr\Contracts;

use Caiyun\Asr\Audio;
use Caiyun\Asr\RecognitionResult;

interface DriverInterface
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function recognize(Audio|string $audio, array $options = []): RecognitionResult;
}
