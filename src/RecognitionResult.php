<?php

declare(strict_types=1);

namespace Caiyun\Asr;

/**
 * 一次识别的返回值。text() 是识别文本，raw() 是厂商原始响应。
 */
class RecognitionResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        private readonly string $text,
        private readonly string $driver,
        private readonly array $raw = [],
        private readonly ?string $requestId = null,
    ) {}

    /** 识别出的文本。 */
    public function text(): string
    {
        return $this->text;
    }

    /** 实际完成识别的驱动名，例如 tencent。 */
    public function driver(): string
    {
        return $this->driver;
    }

    /** 厂商请求号。腾讯云为 RequestId，阿里云为 task_id，讯飞为 sid。 */
    public function requestId(): ?string
    {
        return $this->requestId;
    }

    /**
     * 厂商原始响应，便于排查识别结果。
     *
     * @return array<string, mixed>
     */
    public function raw(): array
    {
        return $this->raw;
    }
}
