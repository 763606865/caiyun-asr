<?php

declare(strict_types=1);

namespace Caiyun\Asr;

final class RecognitionResult
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

    public function text(): string
    {
        return $this->text;
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function requestId(): ?string
    {
        return $this->requestId;
    }

    /**
     * @return array<string, mixed>
     */
    public function raw(): array
    {
        return $this->raw;
    }
}
