<?php

declare(strict_types=1);

namespace Caiyun\Asr\Exceptions;

use RuntimeException;

/**
 * 语音识别异常的基类。配置错误和厂商请求失败都从这里派生。
 */
class AsrException extends RuntimeException {}
