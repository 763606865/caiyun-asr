<?php

declare(strict_types=1);

namespace Caiyun\Asr\Exceptions;

/**
 * 驱动未配置、缺少必填项，或地域等配置无法识别。
 */
class ConfigException extends AsrException {}
